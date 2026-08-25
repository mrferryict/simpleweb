<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\PostWriteDto;
use App\Dtos\PublicPostCacheEntry;
use App\Dtos\PublicPostViewDto;
use App\Entities\Post;
use App\Entities\PostTranslation;
use App\Enums\AuditEvent;
use App\Enums\PostStatus;
use App\Enums\RevisionResourceType;
use App\Enums\ScheduledActionResultCode;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Services\Audit\AuditService;
use App\Services\Cache\PublicContentCacheInvalidator;
use App\Services\Concerns\OptimisticLockTrait;
use App\Services\Content\ContentPermanentDeleteDependencyChecker;
use App\Services\Content\ContentSchemaValidator;
use App\Services\Localization\PublicUrlBuilder;
use App\Services\Localization\PublicUrlNamespaceValidator;
use App\Services\Localization\SeoService;
use App\Services\Localization\UrlRedirectService;
use App\Services\Revision\RevisionService;
use App\Services\Security\RichTextSanitizer;
use App\Services\Theme\ThemeService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Validation\ValidationInterface;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

/**
 * Post foundation application boundary (Phase 3–4 / Tasks 3.7–4.2).
 *
 * content_payload: Raw → RichTextSanitizer → ContentSchemaValidator → Persist (ADR-014 / ADR-004).
 * Schema resolves ACTIVE Theme → templates.custom-post (ADR-015). No stored template_key.
 * Public rendering: PUBLISHED-only lookup + Strategy B fallback (ADR-016).
 * Publishing: DRAFT|UNPUBLISHED → PUBLISHED; PUBLISHED → UNPUBLISHED (DOC-04 / Task 4.1).
 * Contributor review: DRAFT → PENDING_REVIEW → PUBLISHED|DRAFT (DOC-04 / REQ-POST-004 / Task 4.2).
 * Revisions, immutable audit, and optimistic concurrency control follow ADR-019.
 * Interactive scheduling UI is ADR-021; this Service stays interactive-only.
 */
class PostService
{
    use OptimisticLockTrait;

    private const TITLE_MAX  = 200;
    private const SLUG_MAX   = 200;
    private const AUTHOR_MAX = 200;

    /** ADR-015 fixed Post template key — never taken from request. */
    private const POST_TEMPLATE = 'custom-post';

    /** @var list<string> */
    private const ALLOWED_LOCALES = ['id', 'en'];

    public function __construct(
        private readonly PostModel $postModel,
        private readonly PostTranslationModel $translationModel,
        private readonly CategoryService $categoryService,
        private readonly TagService $tagService,
        private readonly ValidationInterface $validation,
        private readonly BaseConnection $db,
        private readonly ContentSchemaValidator $contentSchemaValidator,
        private readonly RichTextSanitizer $richTextSanitizer,
        private readonly ThemeService $themeService,
        private readonly RevisionService $revisionService,
        private readonly AuditService $auditService,
        private readonly ContentPermanentDeleteDependencyChecker $permanentDeleteDependencyChecker,
        private readonly PublicContentCacheInvalidator $publicContentCache,
        private readonly SettingService $settingService,
        private readonly PublicUrlNamespaceValidator $publicUrlNamespaceValidator,
        private readonly UrlRedirectService $urlRedirectService,
        private readonly PublicUrlBuilder $publicUrlBuilder,
        private readonly SeoService $seoService,
    ) {
    }

    /**
     * @return list<array{
     *     post: Post,
     *     translation: PostTranslation|null,
     *     category_ids: list<int>,
     *     tag_ids: list<int>
     * }>
     */
    public function listActive(?User $actor = null): array
    {
        $builder = $this->postModel
            ->where('status !=', PostStatus::Trash->value)
            ->orderBy('id', 'DESC');

        if ($actor !== null && ! $this->canEditAny($actor) && $this->canEditOwn($actor)) {
            $builder->where('created_by', (int) $actor->id);
        }

        /** @var list<Post> $posts */
        $posts = $builder->findAll();

        $rows = [];
        foreach ($posts as $post) {
            $rows[] = [
                'post'          => $post,
                'translation'   => $this->primaryTranslation($post->id),
                'category_ids'  => $this->categoryIdsForPost($post->id),
                'tag_ids'       => $this->tagIdsForPost($post->id),
            ];
        }

        return $rows;
    }

    /**
     * Trashed posts with primary translation summary, newest first.
     *
     * @return list<array{
     *     post: Post,
     *     translation: PostTranslation|null,
     *     category_ids: list<int>,
     *     tag_ids: list<int>
     * }>
     */
    public function listTrashed(?User $actor = null): array
    {
        $builder = $this->postModel
            ->where('status', PostStatus::Trash->value)
            ->orderBy('id', 'DESC');

        if ($actor !== null && ! $this->canEditAny($actor) && $this->canEditOwn($actor)) {
            $builder->where('created_by', (int) $actor->id);
        }

        /** @var list<Post> $posts */
        $posts = $builder->findAll();

        $rows = [];
        foreach ($posts as $post) {
            $rows[] = [
                'post'          => $post,
                'translation'   => $this->primaryTranslation($post->id),
                'category_ids'  => $this->categoryIdsForPost($post->id),
                'tag_ids'       => $this->tagIdsForPost($post->id),
            ];
        }

        return $rows;
    }

    public function findById(int $id): ?Post
    {
        if ($id < 1) {
            return null;
        }

        /** @var Post|null $post */
        $post = $this->postModel->find($id);

        return $post instanceof Post ? $post : null;
    }

    /**
     * @return array{
     *     post: Post,
     *     translation: PostTranslation,
     *     category_ids: list<int>,
     *     tag_ids: list<int>
     * }|null
     */
    public function findEditable(int $id, ?User $actor = null): ?array
    {
        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value) {
            return null;
        }

        if ($actor !== null && ! $this->actorMayWrite($actor, $post)) {
            return null;
        }

        $translation = $this->primaryTranslation($id);
        if ($translation === null) {
            return null;
        }

        return [
            'post'         => $post,
            'translation'  => $translation,
            'category_ids' => $this->categoryIdsForPost($id),
            'tag_ids'      => $this->tagIdsForPost($id),
        ];
    }

    /**
     * Content Schema for Posts: ACTIVE Theme → custom-post (ADR-015).
     *
     * @return array<string, array<string, mixed>>
     */
    public function contentSchema(): array
    {
        try {
            return $this->themeService->contentSchemaForTemplate(self::POST_TEMPLATE);
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * Fixed Post template key (ADR-015). Not user-selectable.
     */
    public function postTemplateKey(): string
    {
        return self::POST_TEMPLATE;
    }

    /**
     * Public Post resolution for ADR-016 routes (PUBLISHED only).
     *
     * Locale is route-driven (`id` or `en`). Secondary requests may fall back to
     * Primary translation content when the Secondary translation row is missing.
     * Non-PUBLISHED Posts never resolve (including during fallback).
     * Read-through File Cache population: ADR-025.
     */
    #[\NoDiscard]
    public function findPublishedForPublic(string $slug, string $locale): ?PublicPostViewDto
    {
        return $this->findPublishedPackageForPublic($slug, $locale)?->view;
    }

    /**
     * Public Post package with resolved SEO (ADR-025).
     */
    #[\NoDiscard]
    public function findPublishedPackageForPublic(string $slug, string $locale): ?PublicPostCacheEntry
    {
        $normalizedSlug  = $this->normalizeSlug($slug);
        $requestedLocale = strtolower(trim($locale));

        if ($normalizedSlug === '' || ! in_array($requestedLocale, self::ALLOWED_LOCALES, true)) {
            return null;
        }

        $primary   = $this->settingService->primaryLocale();
        $secondary = $this->settingService->secondaryLocale();

        if ($requestedLocale === $secondary && $secondary === null) {
            return null;
        }

        if (! in_array($requestedLocale, [$primary, $secondary], true)) {
            return null;
        }

        $themeId = $this->themeService->activeThemeId();
        $cached  = $this->publicContentCache->getPostPackage($themeId, $requestedLocale, $normalizedSlug);
        if ($cached !== null) {
            return $cached;
        }

        $view = $this->resolvePublishedPostView($normalizedSlug, $requestedLocale, $primary, $secondary);
        if ($view === null) {
            return null;
        }

        $package = new PublicPostCacheEntry(
            view: $view,
            seo: $this->seoService->forPostView($view),
        );
        $this->publicContentCache->savePostPackage(
            postId: $view->postId,
            themeId: $themeId,
            locale: $requestedLocale,
            slug: $normalizedSlug,
            entry: $package,
        );

        return $package;
    }

    /**
     * @param non-empty-string $normalizedSlug
     */
    private function resolvePublishedPostView(
        string $normalizedSlug,
        string $requestedLocale,
        string $primary,
        ?string $secondary,
    ): ?PublicPostViewDto {
        $requested = $this->translationModel->findBySlugAndLocale($normalizedSlug, $requestedLocale);
        if ($requested !== null) {
            $post = $this->findPublishedPost((int) $requested->post_id);
            if ($post === null) {
                // Translation exists on a non-public Post — do not fall back via another Post's slug.
                return null;
            }

            return $this->toPublicViewDto(
                post: $post,
                translation: $requested,
                requestedLocale: $requestedLocale,
                isFallback: false,
            );
        }

        if ($requestedLocale === $secondary && $secondary !== null) {
            $primaryTranslation = $this->translationModel->findBySlugAndLocale($normalizedSlug, $primary);
            if ($primaryTranslation === null) {
                return null;
            }

            $post = $this->findPublishedPost((int) $primaryTranslation->post_id);
            if ($post === null) {
                return null;
            }

            return $this->toPublicViewDto(
                post: $post,
                translation: $primaryTranslation,
                requestedLocale: $requestedLocale,
                isFallback: true,
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function decodeContentPayload(string $json): array
    {
        if ($json === '' || $json === '{}') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function findPublishedPost(int $postId): ?Post
    {
        $post = $this->findById($postId);
        if ($post === null || $post->status !== PostStatus::Published->value) {
            return null;
        }

        if ($post->deleted_at !== null) {
            return null;
        }

        return $post;
    }

    private function toPublicViewDto(
        Post $post,
        PostTranslation $translation,
        string $requestedLocale,
        bool $isFallback,
    ): PublicPostViewDto {
        $payload = $this->decodeContentPayload((string) $translation->content_payload);
        $body    = '';
        if (isset($payload['body']) && (is_string($payload['body']) || is_numeric($payload['body']))) {
            $body = is_string($payload['body']) ? $payload['body'] : (string) $payload['body'];
        }

        return new PublicPostViewDto(
            postId: (int) $post->id,
            title: (string) $translation->title,
            manualAuthor: (string) $post->manual_author,
            locale: (string) $translation->locale,
            slug: (string) $translation->slug,
            body: $body,
            requestedLocale: $requestedLocale,
            isFallback: $isFallback,
            templateKey: self::POST_TEMPLATE,
            metaTitle: $translation->meta_title !== null ? (string) $translation->meta_title : null,
            metaDescription: $translation->meta_description !== null ? (string) $translation->meta_description : null,
            canonicalUrl: $translation->canonical_url !== null ? (string) $translation->canonical_url : null,
            ogImageId: $translation->og_image_id !== null ? (int) $translation->og_image_id : null,
        );
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function create(PostWriteDto $dto, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('post.create')) {
            return ['_forbidden' => 'You are not allowed to create Posts.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, null);
        if ($errors !== []) {
            return $errors;
        }

        $schemaResult = $this->resolvePostSchema();
        if ($schemaResult['errors'] !== []) {
            return $schemaResult['errors'];
        }
        $schema  = $schemaResult['schema'];
        $payload = $this->richTextSanitizer->sanitizePayload($normalized['content_payload'], $schema);
        $contentResult = $this->contentSchemaValidator->validate($payload, $schema);
        if (! $contentResult->ok) {
            return $contentResult->errors;
        }

        $payloadJson = $this->encodePayload($contentResult->normalized);
        $createdBy   = $normalized['created_by'];
        if ($createdBy === null && $actor !== null) {
            $createdBy = (int) $actor->id;
        }

        $this->db->transStart();

        $postId = $this->postModel->insert([
            'status'            => PostStatus::Draft->value,
            'manual_author'     => $normalized['manual_author'],
            'featured_image_id' => $normalized['featured_image_id'],
            'created_by'        => $createdBy,
            'deleted_at'        => null,
        ], true);

        if (! is_int($postId) && ! is_numeric($postId)) {
            $this->db->transRollback();

            return ['_persist' => 'Unable to create Post.'];
        }

        $postId = (int) $postId;

        $this->translationModel->insert([
            'post_id'          => $postId,
            'locale'           => $normalized['locale'],
            'title'            => $normalized['title'],
            'slug'             => $normalized['slug'],
            'content_payload'  => $payloadJson,
            'meta_title'       => $normalized['meta_title'],
            'meta_description' => $normalized['meta_description'],
            'canonical_url'    => $normalized['canonical_url'],
            'og_image_id'      => $normalized['og_image_id'],
        ]);

        $this->syncCategories($postId, $normalized['category_ids']);
        $this->syncTags($postId, $normalized['tag_ids']);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $postId,
            AuditEvent::PostCreated,
            $this->actorId($actor),
        );

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to create Post.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function update(
        int $id,
        PostWriteDto $dto,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array
    {
        $existing = $this->findEditable($id, $actor);
        if ($existing === null) {
            if ($actor !== null && $this->findById($id) !== null) {
                return ['_forbidden' => 'You are not allowed to edit this Post.'];
            }

            return ['_not_found' => 'Post not found.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, $id);
        if ($errors !== []) {
            return $errors;
        }

        $schemaResult = $this->resolvePostSchema();
        if ($schemaResult['errors'] !== []) {
            return $schemaResult['errors'];
        }
        $schema  = $schemaResult['schema'];
        $payload = $this->richTextSanitizer->sanitizePayload($normalized['content_payload'], $schema);
        $contentResult = $this->contentSchemaValidator->validate($payload, $schema);
        if (! $contentResult->ok) {
            return $contentResult->errors;
        }

        $existingPayload = json_decode($existing['translation']->content_payload, true);
        if (! is_array($existingPayload)) {
            $existingPayload = [];
        }

        /** @var array<string, mixed> $existingPayload */
        $merged = $this->contentSchemaValidator->mergePreservingLegacy(
            $existingPayload,
            $contentResult->normalized,
            $schema,
        );
        $payloadJson = $this->encodePayload($merged);

        $this->db->transStart();

        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'manual_author'     => $normalized['manual_author'],
            'featured_image_id' => $normalized['featured_image_id'],
        ]);

        $translation = $existing['translation'];
        $oldSlug      = (string) $translation->slug;
        $oldLocale    = (string) $translation->locale;
        $wasPublished = $existing['post']->status === PostStatus::Published->value;

        $this->translationModel->update($translation->id, [
            'locale'           => $normalized['locale'],
            'title'            => $normalized['title'],
            'slug'             => $normalized['slug'],
            'content_payload'  => $payloadJson,
            'meta_title'       => $normalized['meta_title'],
            'meta_description' => $normalized['meta_description'],
            'canonical_url'    => $normalized['canonical_url'],
            'og_image_id'      => $normalized['og_image_id'],
        ]);

        if ($wasPublished) {
            $oldPath = $this->publicUrlBuilder->postPath($oldSlug, $oldLocale);
            $newPath = $this->publicUrlBuilder->postPath($normalized['slug'], $normalized['locale']);
            if ($oldPath !== $newPath) {
                $this->urlRedirectService->recordPublishedSlugChange(
                    oldSourcePath: $oldPath,
                    newTargetPath: $newPath,
                    resourceType: 'post',
                    resourceId: $id,
                    locale: $normalized['locale'],
                );
            }
        }

        $this->syncCategories($id, $normalized['category_ids']);
        $this->syncTags($id, $normalized['tag_ids']);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostUpdated,
            $this->actorId($actor),
        );

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to update Post.'];
        }

        if ($existing['post']->status === PostStatus::Published->value) {
            $this->publicContentCache->invalidatePost($id);
        }

        return [];
    }

    /**
     * Publish a Post (DOC-04 §4 / §21; DOC-03 post.publish).
     *
     * Allowed: DRAFT → PUBLISHED, UNPUBLISHED → PUBLISHED, ARCHIVED → PUBLISHED (ADR-020).
     * Status is Post-wide (not per-translation). Does not invent published_at/by columns.
     * Schema `required` flags apply as declared (ADR-015: body remains optional until Schema says otherwise).
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function publish(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array
    {
        if ($actor !== null && ! $actor->can('post.publish')) {
            return ['_forbidden' => 'You are not allowed to publish Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_not_found' => 'Post not found.'];
        }

        $current = PostStatus::tryFromString((string) $post->status);
        if (
            $current !== PostStatus::Draft
            && $current !== PostStatus::Unpublished
            && $current !== PostStatus::Archived
        ) {
            return ['_status' => 'This Post cannot be published from its current state.'];
        }

        $errors = $this->validateForPublish($post);
        if ($errors !== []) {
            return $errors;
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::Published->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostPublished,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to publish Post.'];
        }

        $this->publicContentCache->invalidatePost($id);

        return [];
    }

    /**
     * Unpublish a Post (DOC-04 §15; DOC-03 post.unpublish).
     *
     * Allowed: PUBLISHED → UNPUBLISHED. Does not modify content_payload.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function unpublish(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array
    {
        if ($actor !== null && ! $actor->can('post.unpublish')) {
            return ['_forbidden' => 'You are not allowed to unpublish Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_not_found' => 'Post not found.'];
        }

        if ($post->status !== PostStatus::Published->value) {
            return ['_status' => 'This Post cannot be unpublished from its current state.'];
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::Unpublished->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostUnpublished,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to unpublish Post.'];
        }

        $this->publicContentCache->invalidatePost($id);

        return [];
    }

    /**
     * Scheduler-safe PUBLISH (ADR-021). Caller owns the DB transaction.
     * Does not accept ARCHIVED. Interactive publish() is unchanged.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function applyScheduledPublish(int $id, ?int $expectedLockVersion = null): array
    {
        $post = $this->findById($id);
        if ($post === null) {
            return ['_result_code' => ScheduledActionResultCode::TargetMissing->value];
        }

        if ($post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_result_code' => ScheduledActionResultCode::TargetTrash->value];
        }

        $current = PostStatus::tryFromString((string) $post->status);
        if ($current === PostStatus::Published) {
            return ['_result_code' => ScheduledActionResultCode::TargetAlreadyPublished->value];
        }
        if ($current === PostStatus::Archived) {
            return ['_result_code' => ScheduledActionResultCode::TargetArchived->value];
        }
        if ($current === PostStatus::PendingReview) {
            return ['_result_code' => ScheduledActionResultCode::TargetPendingReview->value];
        }
        if ($current !== PostStatus::Draft && $current !== PostStatus::Unpublished) {
            return ['_result_code' => ScheduledActionResultCode::InvalidSourceState->value];
        }

        $errors = $this->validateForPublish($post);
        if ($errors !== []) {
            $errors['_result_code'] = ScheduledActionResultCode::ValidationFailed->value;

            return $errors;
        }

        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $code = isset($occ['errors']['_conflict'])
                ? ScheduledActionResultCode::LockVersionConflict->value
                : ScheduledActionResultCode::TargetMissing->value;

            return ['_result_code' => $code];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::Published->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostPublished,
            null,
        );

        return [];
    }

    /**
     * Scheduler-safe UNPUBLISH (ADR-021). Caller owns the DB transaction.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function applyScheduledUnpublish(int $id, ?int $expectedLockVersion = null): array
    {
        $post = $this->findById($id);
        if ($post === null) {
            return ['_result_code' => ScheduledActionResultCode::TargetMissing->value];
        }

        if ($post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_result_code' => ScheduledActionResultCode::TargetTrash->value];
        }

        if ($post->status === PostStatus::Archived->value) {
            return ['_result_code' => ScheduledActionResultCode::TargetArchived->value];
        }

        if ($post->status === PostStatus::Unpublished->value) {
            return ['_result_code' => ScheduledActionResultCode::TargetAlreadyUnpublished->value];
        }

        if ($post->status !== PostStatus::Published->value) {
            return ['_result_code' => ScheduledActionResultCode::InvalidSourceState->value];
        }

        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $code = isset($occ['errors']['_conflict'])
                ? ScheduledActionResultCode::LockVersionConflict->value
                : ScheduledActionResultCode::TargetMissing->value;

            return ['_result_code' => $code];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::Unpublished->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostUnpublished,
            null,
        );

        return [];
    }

    /**
     * Whether the actor may write this Post (ownership / edit_any). Used by scheduling.
     */
    public function actorMayWritePost(User $actor, int $postId): bool
    {
        $post = $this->findById($postId);
        if ($post === null) {
            return false;
        }

        return $this->actorMayWrite($actor, $post);
    }

    /**
     * Archive a Post (DOC-04 §16; DOC-03 post.archive; ADR-020).
     *
     * Allowed: PUBLISHED → ARCHIVED. Status-only (does not set deleted_at).
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function archive(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('post.archive')) {
            return ['_forbidden' => 'You are not allowed to archive Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_not_found' => 'Post not found.'];
        }

        if ($post->status !== PostStatus::Published->value) {
            return ['_status' => 'This Post cannot be archived from its current state.'];
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::Archived->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostArchived,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to archive Post.'];
        }

        $this->publicContentCache->invalidatePost($id);

        return [];
    }

    /**
     * Contributor submit-for-review (DOC-04 §4–6; REQ-POST-004; DOC-03 post.submit_review).
     *
     * Allowed: DRAFT → PENDING_REVIEW.
     * Ownership: actor must be allowed to write the Post (AUTHZ-001 / AUTHZ-002).
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function submitForReview(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array
    {
        if ($actor !== null && ! $actor->can('post.submit_review')) {
            return ['_forbidden' => 'You are not allowed to submit Posts for review.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_not_found' => 'Post not found.'];
        }

        if ($actor !== null && ! $this->actorMayWrite($actor, $post)) {
            return ['_forbidden' => 'You are not allowed to submit this Post for review.'];
        }

        if ($post->status !== PostStatus::Draft->value) {
            return ['_status' => 'This Post cannot be submitted for review from its current state.'];
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::PendingReview->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostSubmittedForReview,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to submit Post for review.'];
        }

        return [];
    }

    /**
     * Editor review-and-publish (DOC-04 §6; REQ-POST-004; DOC-03 post.review).
     *
     * Allowed: PENDING_REVIEW → PUBLISHED.
     * Reuses Task 4.1 publish validation; does not invent reviewer columns.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function reviewAndPublish(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array
    {
        if ($actor !== null && ! $actor->can('post.review')) {
            return ['_forbidden' => 'You are not allowed to review Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_not_found' => 'Post not found.'];
        }

        if ($post->status !== PostStatus::PendingReview->value) {
            return ['_status' => 'This Post cannot be published from review in its current state.'];
        }

        $errors = $this->validateForPublish($post);
        if ($errors !== []) {
            return $errors;
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::Published->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostReviewedPublished,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to publish reviewed Post.'];
        }

        $this->publicContentCache->invalidatePost($id);

        return [];
    }

    /**
     * Editor return-for-revision (DOC-04 §6; REQ-POST-004; DOC-03 post.review).
     *
     * Allowed: PENDING_REVIEW → DRAFT. Does not modify content_payload.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function returnForRevision(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array
    {
        if ($actor !== null && ! $actor->can('post.review')) {
            return ['_forbidden' => 'You are not allowed to review Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value || $post->deleted_at !== null) {
            return ['_not_found' => 'Post not found.'];
        }

        if ($post->status !== PostStatus::PendingReview->value) {
            return ['_status' => 'This Post cannot be returned for revision from its current state.'];
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status' => PostStatus::Draft->value,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostReturnedForRevision,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to return Post for revision.'];
        }

        return [];
    }

    /**
     * Soft-delete: status=TRASH + deleted_at (REQ-POST-013).
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function trash(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array
    {
        if ($actor !== null && ! $actor->can('post.trash')) {
            return ['_forbidden' => 'You are not allowed to trash Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value) {
            return ['_not_found' => 'Post not found.'];
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status'     => PostStatus::Trash->value,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostTrashed,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to trash Post.'];
        }

        $this->publicContentCache->invalidatePost($id);

        return [];
    }

    /**
     * Restore a trashed Post as a draft.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function restoreFromTrash(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('post.restore')) {
            return ['_forbidden' => 'You are not allowed to restore Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status !== PostStatus::Trash->value) {
            return ['_not_found' => 'Trashed Post not found.'];
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'status'     => PostStatus::Draft->value,
            'deleted_at' => null,
        ]);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::PostRestored,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to restore Post.'];
        }

        return [];
    }

    /**
     * Permanently delete a trashed Post (DOC-04 §19 / ADR-019 / Task 4.10A).
     *
     * Removes owned live rows (post, translations, category/tag pivots).
     * Retains revisions and audit_logs. Menu has no POST target type in V1.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function permanentlyDelete(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('content.permanent_delete')) {
            return ['_forbidden' => 'Only Admin may permanently delete Posts.'];
        }

        $post = $this->findById($id);
        if ($post === null) {
            return ['_not_found' => 'Post not found.'];
        }
        if ($post->status !== PostStatus::Trash->value) {
            return ['_status' => 'Only a trashed Post can be permanently deleted.'];
        }

        $deps = $this->permanentDeleteDependencyChecker->findPostDependencies($id);
        if ($deps !== []) {
            return [
                '_dependency' => 'This Post still has dependencies and cannot be permanently deleted: '
                    . implode('; ', array_slice($deps, 0, 5)),
            ];
        }

        $revisionCountBefore = $this->db->table('revisions')
            ->where('resource_type', RevisionResourceType::Post->value)
            ->where('resource_id', $id)
            ->countAllResults();
        $auditCountBefore = $this->db->table('audit_logs')
            ->where('resource_type', RevisionResourceType::Post->value)
            ->where('resource_id', $id)
            ->countAllResults();

        $this->db->transStart();

        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $locked = $this->revisionService->lockParentRow('posts', $id);
        if ($locked === null || (string) ($locked['status'] ?? '') !== PostStatus::Trash->value) {
            $this->db->transRollback();

            return ['_status' => 'Only a trashed Post can be permanently deleted.'];
        }

        $depsLocked = $this->permanentDeleteDependencyChecker->findPostDependencies($id);
        if ($depsLocked !== []) {
            $this->db->transRollback();

            return [
                '_dependency' => 'This Post still has dependencies and cannot be permanently deleted: '
                    . implode('; ', array_slice($depsLocked, 0, 5)),
            ];
        }

        // Owned pivots + translations (SQLite tests lack MySQL ON DELETE CASCADE).
        $this->db->table('post_categories')->where('post_id', $id)->delete();
        $this->db->table('post_tags')->where('post_id', $id)->delete();
        $this->translationModel->where('post_id', $id)->delete();
        $this->postModel->delete($id);

        (void) $this->auditService->append(
            AuditEvent::PostPermanentlyDeleted,
            $this->actorId($actor),
            RevisionResourceType::Post->value,
            $id,
            null,
            null,
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to permanently delete Post.'];
        }

        $revisionCountAfter = $this->db->table('revisions')
            ->where('resource_type', RevisionResourceType::Post->value)
            ->where('resource_id', $id)
            ->countAllResults();
        $auditCountAfter = $this->db->table('audit_logs')
            ->where('resource_type', RevisionResourceType::Post->value)
            ->where('resource_id', $id)
            ->countAllResults();

        if ($revisionCountAfter < $revisionCountBefore || $auditCountAfter < $auditCountBefore + 1) {
            throw new RuntimeException('Post permanent delete violated revision/audit retention.');
        }

        $this->publicContentCache->invalidatePost($id);

        return [];
    }

    /**
     * Restore an editorial snapshot without changing the Post status.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function restoreRevision(
        int $id,
        int $revisionId,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('post.restore')) {
            return ['_forbidden' => 'You are not allowed to restore Post revisions.'];
        }

        $post = $this->findById($id);
        if ($post === null || $post->status === PostStatus::Trash->value) {
            return ['_not_found' => 'Post not found.'];
        }

        $revision = $this->revisionService->findById($revisionId);
        if (
            $revision === null
            || $revision->resource_type !== RevisionResourceType::Post->value
            || (int) $revision->resource_id !== $id
        ) {
            return ['_revision' => 'Post revision not found.'];
        }

        $snapshot = $revision->decodedSnapshot();
        if (
            $snapshot === null
            || ($snapshot['schema_version'] ?? null) !== 1
            || ($snapshot['resource_type'] ?? null) !== RevisionResourceType::Post->value
            || ! isset($snapshot['translations'])
            || ! is_array($snapshot['translations'])
        ) {
            return ['_revision' => 'The revision snapshot is invalid.'];
        }

        $manualAuthor = isset($snapshot['manual_author']) && is_string($snapshot['manual_author'])
            ? trim($snapshot['manual_author'])
            : '';
        $featuredImageId = isset($snapshot['featured_image_id']) && is_numeric($snapshot['featured_image_id'])
            && (int) $snapshot['featured_image_id'] > 0
                ? (int) $snapshot['featured_image_id']
                : null;
        $categoryIds = $this->normalizeIdList($snapshot['category_ids'] ?? []);
        $tagIds      = $this->normalizeIdList($snapshot['tag_ids'] ?? []);

        $schemaResult = $this->resolvePostSchema();
        if ($schemaResult['errors'] !== []) {
            return $schemaResult['errors'];
        }
        $schema = $schemaResult['schema'];

        /** @var array<string, array{title: string, slug: string, content_payload: string}> $translations */
        $translations = [];
        foreach ($snapshot['translations'] as $locale => $translation) {
            if (! is_string($locale) || ! is_array($translation)) {
                return ['_revision' => 'The revision contains an invalid translation.'];
            }

            $contentPayload = $translation['content_payload'] ?? null;
            if (
                ! isset($translation['title'], $translation['slug'])
                || ! is_string($translation['title'])
                || ! is_string($translation['slug'])
                || ! is_array($contentPayload)
            ) {
                return ['_revision' => 'The revision contains an invalid translation.'];
            }

            $normalized = [
                'title'             => trim($translation['title']),
                'slug'              => $this->normalizeSlug($translation['slug']),
                'locale'            => strtolower(trim($locale)),
                'manual_author'     => $manualAuthor,
                'category_ids'      => $categoryIds,
                'tag_ids'           => $tagIds,
                'content_payload'   => $contentPayload,
                'featured_image_id' => $featuredImageId,
                'created_by'        => null,
            ];
            $errors = $this->validate($normalized, $id);
            if ($errors !== []) {
                return $errors;
            }

            $sanitized = $this->richTextSanitizer->sanitizePayload($contentPayload, $schema);
            $contentResult = $this->contentSchemaValidator->validate($sanitized, $schema);
            if (! $contentResult->ok) {
                return $contentResult->errors;
            }

            $translations[$normalized['locale']] = [
                'title'           => $normalized['title'],
                'slug'            => $normalized['slug'],
                'content_payload' => $this->encodePayload($contentResult->normalized),
            ];
        }

        if ($translations === []) {
            return ['_revision' => 'The revision contains no translations.'];
        }

        $this->db->transStart();
        $occ = $this->beginOccMutation('posts', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->postModel->update($id, [
            'manual_author'     => $manualAuthor,
            'featured_image_id' => $featuredImageId,
        ]);
        $this->syncCategories($id, $categoryIds);
        $this->syncTags($id, $tagIds);

        foreach ($translations as $locale => $translation) {
            $existingTranslation = $this->translationModel->findByPostAndLocale($id, $locale);
            $values = [
                'locale'          => $locale,
                'title'           => $translation['title'],
                'slug'            => $translation['slug'],
                'content_payload' => $translation['content_payload'],
            ];
            if ($existingTranslation === null) {
                $this->translationModel->insert(['post_id' => $id, ...$values]);
            } else {
                $this->translationModel->update((int) $existingTranslation->id, $values);
            }
        }

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Post,
            $id,
            AuditEvent::RevisionRestored,
            $this->actorId($actor),
            ['source_revision_id' => $revisionId],
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to restore Post revision.'];
        }

        if ($this->findById($id)?->status === PostStatus::Published->value) {
            $this->publicContentCache->invalidatePost($id);
        }

        return [];
    }

    /**
     * Store a recoverable draft snapshot without changing live Post data.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function autosave(
        int $id,
        PostWriteDto $dto,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        $existing = $this->findEditable($id, $actor);
        if ($existing === null) {
            if ($actor !== null && $this->findById($id) !== null) {
                return ['_forbidden' => 'You are not allowed to edit this Post.'];
            }

            return ['_not_found' => 'Post not found.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, $id);
        if ($errors !== []) {
            return $errors;
        }

        $schemaResult = $this->resolvePostSchema();
        if ($schemaResult['errors'] !== []) {
            return $schemaResult['errors'];
        }
        $schema  = $schemaResult['schema'];
        $payload = $this->richTextSanitizer->sanitizePayload($normalized['content_payload'], $schema);
        $contentResult = $this->contentSchemaValidator->validate($payload, $schema);
        if (! $contentResult->ok) {
            return $contentResult->errors;
        }

        $snapshot = [
            'schema_version'    => 1,
            'resource_type'     => RevisionResourceType::Post->value,
            'resource_id'       => $id,
            'captured_at'       => date(DATE_ATOM),
            'status'            => (string) $existing['post']->status,
            'manual_author'     => $normalized['manual_author'],
            'featured_image_id' => $normalized['featured_image_id'],
            'category_ids'      => $normalized['category_ids'],
            'tag_ids'           => $normalized['tag_ids'],
            'translations'      => [
                $normalized['locale'] => [
                    'title'           => $normalized['title'],
                    'slug'            => $normalized['slug'],
                    'content_payload' => $contentResult->normalized,
                ],
            ],
        ];

        $this->db->transStart();
        $locked = $this->revisionService->lockParentRow('posts', $id);
        if ($locked === null) {
            $this->db->transRollback();

            return ['_not_found' => 'Post not found.'];
        }

        $currentLockVersion = (int) $locked['lock_version'];
        if ($expectedLockVersion !== null && $expectedLockVersion !== $currentLockVersion) {
            $this->db->transRollback();

            return [
                '_conflict'    => 'The content was modified by another session.',
                'lock_version' => (string) $currentLockVersion,
            ];
        }

        $this->revisionService->recordAutosave(
            RevisionResourceType::Post,
            $id,
            $snapshot,
            $this->actorId($actor),
        );
        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to autosave Post.'];
        }

        return [];
    }

    /**
     * @return list<int>
     */
    public function categoryIdsForPost(int $postId): array
    {
        $rows = $this->db->table('post_categories')
            ->select('category_id')
            ->where('post_id', $postId)
            ->get()
            ->getResultArray();

        return array_values(array_map(
            static fn (array $row): int => (int) $row['category_id'],
            $rows,
        ));
    }

    /**
     * @return list<int>
     */
    public function tagIdsForPost(int $postId): array
    {
        $rows = $this->db->table('post_tags')
            ->select('tag_id')
            ->where('post_id', $postId)
            ->get()
            ->getResultArray();

        return array_values(array_map(
            static fn (array $row): int => (int) $row['tag_id'],
            $rows,
        ));
    }

    private function primaryTranslation(int $postId): ?PostTranslation
    {
        /** @var list<PostTranslation> $rows */
        $rows = $this->translationModel
            ->where('post_id', $postId)
            ->orderBy('id', 'ASC')
            ->findAll();

        return $rows[0] ?? null;
    }

    /**
     * @return array{
     *     title: string,
     *     slug: string,
     *     locale: string,
     *     manual_author: string,
     *     category_ids: list<int>,
     *     tag_ids: list<int>,
     *     content_payload: array<string, mixed>,
     *     featured_image_id: int|null,
     *     created_by: int|null
     * }
     */
    private function normalize(PostWriteDto $dto): array
    {
        $categoryIds = [];
        foreach ($dto->categoryIds as $id) {
            if (is_int($id) && $id > 0) {
                $categoryIds[$id] = $id;
            }
        }

        $tagIds = [];
        foreach ($dto->tagIds as $id) {
            if (is_int($id) && $id > 0) {
                $tagIds[$id] = $id;
            }
        }

        $featured = $dto->featuredImageId;
        if ($featured !== null && $featured < 1) {
            $featured = null;
        }

        $metaTitle = trim((string) ($dto->metaTitle ?? ''));
        $metaDescription = trim((string) ($dto->metaDescription ?? ''));
        $canonicalUrl = trim((string) ($dto->canonicalUrl ?? ''));
        $ogImageId = $dto->ogImageId;
        if ($ogImageId !== null && $ogImageId < 1) {
            $ogImageId = null;
        }

        return [
            'title'             => trim($dto->title),
            'slug'              => $this->normalizeSlug($dto->slug),
            'locale'            => strtolower(trim($dto->locale)),
            'manual_author'     => trim($dto->manualAuthor),
            'category_ids'      => array_values($categoryIds),
            'tag_ids'           => array_values($tagIds),
            'content_payload'   => $dto->contentPayload,
            'featured_image_id' => $featured,
            'created_by'        => $dto->createdBy !== null && $dto->createdBy > 0 ? $dto->createdBy : null,
            'meta_title'        => $metaTitle !== '' ? $metaTitle : null,
            'meta_description'  => $metaDescription !== '' ? $metaDescription : null,
            'canonical_url'     => $canonicalUrl !== '' ? $canonicalUrl : null,
            'og_image_id'       => $ogImageId,
        ];
    }

    /**
     * @return list<int>
     */
    private function normalizeIdList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $ids = [];
        foreach ($values as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $ids[(int) $value] = (int) $value;
            }
        }

        return array_values($ids);
    }

    /**
     * @param array{
     *     title: string,
     *     slug: string,
     *     locale: string,
     *     manual_author: string,
     *     category_ids: list<int>,
     *     tag_ids: list<int>,
     *     content_payload: array<string, mixed>,
     *     featured_image_id: int|null,
     *     created_by: int|null
     * } $data
     *
     * @return array<string, string>
     */
    private function validate(array $data, ?int $exceptPostId): array
    {
        $this->validation->reset();
        $this->validation->setRules([
            'title' => [
                'label' => 'Title',
                'rules' => 'required|max_length[' . self::TITLE_MAX . ']',
            ],
            'slug' => [
                'label' => 'Slug',
                'rules' => 'required|max_length[' . self::SLUG_MAX . ']',
            ],
            'locale' => [
                'label' => 'Locale',
                'rules' => 'required|in_list[' . implode(',', self::ALLOWED_LOCALES) . ']',
            ],
            'manual_author' => [
                'label' => 'Author',
                'rules' => 'required|max_length[' . self::AUTHOR_MAX . ']',
            ],
        ]);

        if (! $this->validation->run([
            'title'         => $data['title'],
            'slug'          => $data['slug'],
            'locale'        => $data['locale'],
            'manual_author' => $data['manual_author'],
        ])) {
            /** @var array<string, string> $errors */
            $errors = $this->validation->getErrors();

            return $errors;
        }

        if ($data['slug'] === '') {
            return ['slug' => 'The Slug field is invalid or reserved.'];
        }

        $namespaceErrors = $this->publicUrlNamespaceValidator->validatePostSlug(
            $data['slug'],
            $data['locale'],
            $exceptPostId,
        );
        if ($namespaceErrors !== []) {
            return $namespaceErrors;
        }

        foreach ($data['category_ids'] as $categoryId) {
            $category = $this->categoryService->findById($categoryId);
            if ($category === null || ! $category->is_active) {
                return ['categories' => 'One or more selected Categories are invalid or inactive.'];
            }
        }

        foreach ($data['tag_ids'] as $tagId) {
            if ($this->tagService->findById($tagId) === null) {
                return ['tags' => 'One or more selected Tags are invalid.'];
            }
        }

        return [];
    }

    /**
     * @param list<int> $categoryIds
     */
    private function syncCategories(int $postId, array $categoryIds): void
    {
        $this->db->table('post_categories')->where('post_id', $postId)->delete();
        foreach ($categoryIds as $categoryId) {
            $this->db->table('post_categories')->insert([
                'post_id'     => $postId,
                'category_id' => $categoryId,
            ]);
        }
    }

    /**
     * @param list<int> $tagIds
     */
    private function syncTags(int $postId, array $tagIds): void
    {
        $this->db->table('post_tags')->where('post_id', $postId)->delete();
        foreach ($tagIds as $tagId) {
            $this->db->table('post_tags')->insert([
                'post_id' => $postId,
                'tag_id'  => $tagId,
            ]);
        }
    }

    /**
     * Publish-time validation against persisted Post + primary translation (DOC-04 §21–22).
     *
     * Does not invent required body/excerpt/SEO/categories — only schema-declared required
     * fields and documented editorial/localization minima.
     *
     * @return array<string, string>
     */
    private function validateForPublish(Post $post): array
    {
        $manualAuthor = trim((string) $post->manual_author);
        if ($manualAuthor === '') {
            return ['manual_author' => 'Author is required before publishing.'];
        }

        $primary = $this->translationModel->findByPostAndLocale(
            (int) $post->id,
            $this->settingService->primaryLocale(),
        );
        if ($primary === null) {
            return ['_locale' => 'A primary-language translation is required before publishing.'];
        }

        $title = trim((string) $primary->title);
        $slug  = trim((string) $primary->slug);
        if ($title === '') {
            return ['title' => 'Title is required before publishing.'];
        }
        if ($slug === '') {
            return ['slug' => 'Slug is required before publishing.'];
        }

        $schemaResult = $this->resolvePostSchema();
        if ($schemaResult['errors'] !== []) {
            return $schemaResult['errors'];
        }

        $schema  = $schemaResult['schema'];
        $payload = $this->decodeContentPayload((string) $primary->content_payload);
        // Already persisted through RichTextSanitizer; re-validate schema only (DOC-04 §22).
        $contentResult = $this->contentSchemaValidator->validate($payload, $schema);
        if (! $contentResult->ok) {
            return $contentResult->errors;
        }

        return [];
    }

    /**
     * @return array{schema: array<string, array<string, mixed>>, errors: array<string, string>}
     */
    private function resolvePostSchema(): array
    {
        try {
            return [
                'schema' => $this->themeService->contentSchemaForTemplate(self::POST_TEMPLATE),
                'errors' => [],
            ];
        } catch (RuntimeException) {
            return [
                'schema' => [],
                'errors' => ['_schema' => 'The Post content schema is not available on the active theme.'],
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function encodePayload(array $payload): string
    {
        if ($payload === []) {
            return '{}';
        }

        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = str_replace([' ', '_'], '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }

    private function actorMayWrite(User $actor, Post $post): bool
    {
        if ($this->canEditAny($actor)) {
            return true;
        }

        if ($this->canEditOwn($actor) && $post->created_by !== null && (int) $post->created_by === (int) $actor->id) {
            return true;
        }

        return false;
    }

    private function canEditAny(User $actor): bool
    {
        return $actor->can('post.edit_any');
    }

    private function canEditOwn(User $actor): bool
    {
        return $actor->can('post.edit_own');
    }
}
