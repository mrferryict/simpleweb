<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\PageWriteDto;
use App\Dtos\PublicPageCacheEntry;
use App\Dtos\PublicPageViewDto;
use App\Entities\Page;
use App\Entities\PageTranslation;
use App\Enums\AuditEvent;
use App\Enums\PageStatus;
use App\Enums\RevisionResourceType;
use App\Enums\ScheduledActionResultCode;
use App\Models\PageModel;
use App\Models\PageTranslationModel;
use App\Services\Audit\AuditService;
use App\Services\Cache\PublicContentCacheInvalidator;
use App\Services\Concerns\OptimisticLockTrait;
use App\Services\Content\ContentPermanentDeleteDependencyChecker;
use App\Services\Content\ContentSchemaValidator;
use App\Services\Media\MediaService;
use App\Services\Localization\PublicUrlBuilder;
use App\Services\Localization\PublicUrlNamespaceValidator;
use App\Services\Localization\SeoService;
use App\Services\Localization\UrlRedirectService;
use App\Services\Revision\RevisionService;
use App\Services\Security\RichTextSanitizer;
use App\Services\Theme\ThemeService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\ValidationInterface;
use RuntimeException;

/**
 * Page foundation application boundary (Phase 2–4 / Tasks 2.5–4.4).
 *
 * content_payload: Raw → RichTextSanitizer → ContentSchemaValidator → Persist (ADR-014 / ADR-004).
 * Content Schema is resolved from the active Theme Manifest (ADR-002).
 * Publishing: DRAFT|UNPUBLISHED → PUBLISHED; PUBLISHED → UNPUBLISHED (DOC-04 / Task 4.3).
 * Public rendering: ADR-017 `/{slug}` + `/en/{slug}` (PUBLISHED only).
 * Public media: contentMedia parallel map via MediaService (ADR-018 / Task 4.7).
 * Editorial revisions, immutable audit events, and optimistic locking follow ADR-019.
 * Interactive scheduling UI is ADR-021; this Service stays interactive-only.
 */
class PageService
{
    use OptimisticLockTrait;

    private const TITLE_MAX = 200;
    private const SLUG_MAX  = 200;

    /** @var list<string> Documented V1 locales (docs/07). */
    private const ALLOWED_LOCALES = ['id', 'en'];

    /** @var list<string> Page statuses eligible for Theme Preview (ADR-023). */
    private const PREVIEWABLE_STATUSES = [
        PageStatus::Draft->value,
        PageStatus::Published->value,
        PageStatus::Unpublished->value,
        PageStatus::Archived->value,
    ];

    private const DEFAULT_TEMPLATE = 'custom-page';

    public function __construct(
        private readonly PageModel $pageModel,
        private readonly PageTranslationModel $translationModel,
        private readonly ValidationInterface $validation,
        private readonly BaseConnection $db,
        private readonly ContentSchemaValidator $contentSchemaValidator,
        private readonly ThemeService $themeService,
        private readonly RichTextSanitizer $richTextSanitizer,
        private readonly MediaService $mediaService,
        private readonly RevisionService $revisionService,
        private readonly AuditService $auditService,
        private readonly ContentPermanentDeleteDependencyChecker $permanentDeleteDependencyChecker,
        private readonly PublicContentCacheInvalidator $publicContentCache,
        private readonly SettingService $settingService,
        private readonly PublicUrlNamespaceValidator $publicUrlNamespaceValidator,
        private readonly UrlRedirectService $urlRedirectService,
        private readonly SeoService $seoService,
        private readonly PublicUrlBuilder $publicUrlBuilder,
    ) {
    }

    /**
     * Non-trashed pages with primary translation summary, newest first.
     *
     * @return list<array{page: Page, translation: PageTranslation|null}>
     */
    public function listActive(): array
    {
        /** @var list<Page> $pages */
        $pages = $this->pageModel
            ->where('status !=', PageStatus::Trash->value)
            ->orderBy('id', 'DESC')
            ->findAll();

        $rows = [];
        foreach ($pages as $page) {
            $rows[] = [
                'page'        => $page,
                'translation' => $this->primaryTranslation($page->id),
            ];
        }

        return $rows;
    }

    /**
     * Trashed pages with primary translation summary, newest first.
     *
     * @return list<array{page: Page, translation: PageTranslation|null}>
     */
    public function listTrashed(): array
    {
        /** @var list<Page> $pages */
        $pages = $this->pageModel
            ->where('status', PageStatus::Trash->value)
            ->orderBy('id', 'DESC')
            ->findAll();

        $rows = [];
        foreach ($pages as $page) {
            $rows[] = [
                'page'        => $page,
                'translation' => $this->primaryTranslation($page->id),
            ];
        }

        return $rows;
    }

    public function findById(int $id): ?Page
    {
        if ($id < 1) {
            return null;
        }

        /** @var Page|null $page */
        $page = $this->pageModel->find($id);

        return $page instanceof Page ? $page : null;
    }

    /**
     * Public Page resolution (ADR-017 / ADR-003 Strategy B / ADR-025 cache).
     *
     * Only PUBLISHED Pages resolve. Non-PUBLISHED never fall back.
     * Secondary missing translation → Primary content with isFallback=true.
     * Hierarchy (parent_id) does not affect the public path (single slug segment).
     */
    #[\NoDiscard]
    public function findPublishedForPublic(string $slug, string $locale): ?PublicPageViewDto
    {
        return $this->findPublishedPackageForPublic($slug, $locale)?->view;
    }

    /**
     * Public Page package with resolved SEO (ADR-025).
     */
    #[\NoDiscard]
    public function findPublishedPackageForPublic(string $slug, string $locale): ?PublicPageCacheEntry
    {
        $normalizedSlug  = $this->normalizeSlug($slug);
        $requestedLocale = strtolower(trim($locale));

        if ($normalizedSlug === '' || ! in_array($requestedLocale, self::ALLOWED_LOCALES, true)) {
            return null;
        }

        // Reserved single-segment paths must never resolve as Pages (ADR-017 §9).
        if (in_array($normalizedSlug, PublicUrlNamespaceValidator::RESERVED_PATHS, true)) {
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
        $cached  = $this->publicContentCache->getPagePackage($themeId, $requestedLocale, $normalizedSlug);
        if ($cached !== null) {
            return $cached;
        }

        $view = $this->resolvePublishedPageView($normalizedSlug, $requestedLocale, $primary, $secondary);
        if ($view === null) {
            return null;
        }

        $package = new PublicPageCacheEntry(
            view: $view,
            seo: $this->seoService->forPageView($view),
        );
        $this->publicContentCache->savePagePackage(
            pageId: $view->pageId,
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
    private function resolvePublishedPageView(
        string $normalizedSlug,
        string $requestedLocale,
        string $primary,
        ?string $secondary,
    ): ?PublicPageViewDto {
        $requested = $this->translationModel->findBySlugAndLocale($normalizedSlug, $requestedLocale);
        if ($requested !== null) {
            $page = $this->findPublishedPage((int) $requested->page_id);
            if ($page === null) {
                return null;
            }

            return $this->toPublicViewDto(
                page: $page,
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

            $page = $this->findPublishedPage((int) $primaryTranslation->page_id);
            if ($page === null) {
                return null;
            }

            return $this->toPublicViewDto(
                page: $page,
                translation: $primaryTranslation,
                requestedLocale: $requestedLocale,
                isFallback: true,
            );
        }

        return null;
    }

    /**
     * Theme Preview Page resolution (ADR-023).
     *
     * Admin read path: DRAFT|PUBLISHED|UNPUBLISHED|ARCHIVED; TRASH excluded.
     * Exact locale translation required — no secondary-locale fallback.
     * Content schema and media resolution use the candidate Theme, not ACTIVE.
     */
    #[\NoDiscard]
    public function findForThemePreview(int $pageId, string $locale, string $themeId): ?PublicPageViewDto
    {
        $requestedLocale = strtolower(trim($locale));
        if (! in_array($requestedLocale, self::ALLOWED_LOCALES, true)) {
            return null;
        }

        $page = $this->findById($pageId);
        if ($page === null) {
            return null;
        }

        if ($page->deleted_at !== null || $page->status === PageStatus::Trash->value) {
            return null;
        }

        if (! in_array($page->status, self::PREVIEWABLE_STATUSES, true)) {
            return null;
        }

        $translation = $this->findTranslation($pageId, $requestedLocale);
        if ($translation === null) {
            return null;
        }

        return $this->toThemePreviewViewDto(
            page: $page,
            translation: $translation,
            requestedLocale: $requestedLocale,
            themeId: strtolower(trim($themeId)),
        );
    }

    /**
     * Non-trashed Pages suitable as Preview targets on the Themes Admin surface.
     *
     * @return list<array{id: int, title: string}>
     */
    public function listPreviewPageOptions(): array
    {
        $options = [];
        foreach ($this->listActive() as $row) {
            $translation = $row['translation'];
            if ($translation === null) {
                continue;
            }

            $options[] = [
                'id'    => (int) $row['page']->id,
                'title' => (string) $translation->title,
            ];
        }

        return $options;
    }

    private function findPublishedPage(int $pageId): ?Page
    {
        $page = $this->findById($pageId);
        if ($page === null || $page->status !== PageStatus::Published->value) {
            return null;
        }

        if ($page->deleted_at !== null) {
            return null;
        }

        return $page;
    }

    private function toPublicViewDto(
        Page $page,
        PageTranslation $translation,
        string $requestedLocale,
        bool $isFallback,
    ): PublicPageViewDto {
        $templateKey = trim((string) $page->template_key);
        if ($templateKey === '') {
            $templateKey = self::DEFAULT_TEMPLATE;
        }

        $contentPayload = $this->decodeContentPayload((string) $translation->content_payload);
        $schema         = $this->contentSchemaForTemplate($templateKey);
        $contentMedia   = $this->mediaService->resolveContentMediaForSchema($contentPayload, $schema);

        return new PublicPageViewDto(
            pageId: (int) $page->id,
            title: (string) $translation->title,
            locale: (string) $translation->locale,
            slug: (string) $translation->slug,
            contentPayload: $contentPayload,
            requestedLocale: $requestedLocale,
            isFallback: $isFallback,
            templateKey: $templateKey,
            contentMedia: $contentMedia,
            metaTitle: $translation->meta_title !== null ? (string) $translation->meta_title : null,
            metaDescription: $translation->meta_description !== null ? (string) $translation->meta_description : null,
            canonicalUrl: $translation->canonical_url !== null ? (string) $translation->canonical_url : null,
            ogImageId: $translation->og_image_id !== null ? (int) $translation->og_image_id : null,
        );
    }

    private function toThemePreviewViewDto(
        Page $page,
        PageTranslation $translation,
        string $requestedLocale,
        string $themeId,
    ): PublicPageViewDto {
        $templateKey = trim((string) $page->template_key);
        if ($templateKey === '') {
            $templateKey = self::DEFAULT_TEMPLATE;
        }

        $contentPayload = $this->decodeContentPayload((string) $translation->content_payload);

        try {
            $schema = $this->themeService->contentSchemaForThemeTemplate($themeId, $templateKey);
        } catch (RuntimeException) {
            $schema = [];
        }

        $contentMedia = $this->mediaService->resolveContentMediaForSchema($contentPayload, $schema);

        return new PublicPageViewDto(
            pageId: (int) $page->id,
            title: (string) $translation->title,
            locale: (string) $translation->locale,
            slug: (string) $translation->slug,
            contentPayload: $contentPayload,
            requestedLocale: $requestedLocale,
            isFallback: false,
            templateKey: $templateKey,
            contentMedia: $contentMedia,
            metaTitle: $translation->meta_title !== null ? (string) $translation->meta_title : null,
            metaDescription: $translation->meta_description !== null ? (string) $translation->meta_description : null,
            canonicalUrl: $translation->canonical_url !== null ? (string) $translation->canonical_url : null,
            ogImageId: $translation->og_image_id !== null ? (int) $translation->og_image_id : null,
        );
    }

    /**
     * Menu PAGE target: page must exist and not be in Trash.
     */
    public function existsForMenuTarget(int $id): bool
    {
        $page = $this->findById($id);
        if ($page === null) {
            return false;
        }

        return $page->status !== PageStatus::Trash->value;
    }

    public function findTranslation(int $pageId, string $locale): ?PageTranslation
    {
        return $this->translationModel->findByPageAndLocale($pageId, strtolower(trim($locale)));
    }

    /**
     * Content Schema field map for a template on the active Theme Manifest.
     *
     * Used by Control Panel form rendering (Phase 3 / Task 3.3).
     *
     * @return array<string, array<string, mixed>>
     */
    #[\NoDiscard]
    public function contentSchemaForTemplate(string $templateKey): array
    {
        $key = trim($templateKey);
        if ($key === '') {
            $key = self::DEFAULT_TEMPLATE;
        }

        try {
            return $this->themeService->contentSchemaForTemplate($key);
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * Decode a stored content_payload JSON string into an associative array.
     *
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

    /**
     * @return array{page: Page, translation: PageTranslation}|null
     */
    public function findEditable(int $id): ?array
    {
        $page = $this->findById($id);
        if ($page === null || $page->status === PageStatus::Trash->value) {
            return null;
        }

        $translation = $this->primaryTranslation($id);
        if ($translation === null) {
            return null;
        }

        return [
            'page'        => $page,
            'translation' => $translation,
        ];
    }

    /**
     * @return array<string, string> Empty on success; `_id` set to new page id on success is NOT used —
     *                              returns [] on success. Use createAndReturnId.
     */
    #[\NoDiscard]
    public function create(PageWriteDto $dto, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('page.create')) {
            return ['_forbidden' => 'You are not allowed to create Pages.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, null);
        if ($errors !== []) {
            return $errors;
        }

        $schemaResult = $this->resolveSchema($normalized['template_key']);
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

        $this->db->transStart();

        $pageId = $this->pageModel->insert([
            'parent_id'    => $normalized['parent_id'],
            'status'       => PageStatus::Draft->value,
            'template_key' => $normalized['template_key'],
            'deleted_at'   => null,
        ], true);

        if (! is_int($pageId) && ! is_numeric($pageId)) {
            $this->db->transRollback();

            return ['_persist' => 'Unable to create Page.'];
        }

        $pageId = (int) $pageId;

        $this->translationModel->insert([
            'page_id'          => $pageId,
            'locale'           => $normalized['locale'],
            'title'            => $normalized['title'],
            'slug'             => $normalized['slug'],
            'content_payload'  => $payloadJson,
            'meta_title'       => $normalized['meta_title'],
            'meta_description' => $normalized['meta_description'],
            'canonical_url'    => $normalized['canonical_url'],
            'og_image_id'      => $normalized['og_image_id'],
        ]);

        $this->revisionService->lockParentRow('pages', $pageId);
        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $pageId,
            AuditEvent::PageCreated,
            $this->actorId($actor),
        );

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to create Page.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function update(
        int $id,
        PageWriteDto $dto,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('page.edit')) {
            return ['_forbidden' => 'You are not allowed to edit Pages.'];
        }

        $existing = $this->findEditable($id);
        if ($existing === null) {
            return ['_not_found' => 'Page not found.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, $id);
        if ($errors !== []) {
            return $errors;
        }

        $schemaResult = $this->resolveSchema($normalized['template_key']);
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

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->pageModel->update($id, [
            'parent_id'    => $normalized['parent_id'],
            'template_key' => $normalized['template_key'],
        ]);

        $translation = $existing['translation'];
        $oldSlug     = (string) $translation->slug;
        $oldLocale   = (string) $translation->locale;
        $wasPublished = $existing['page']->status === PageStatus::Published->value;

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
            $oldPath = $this->publicUrlBuilder->pagePath($oldSlug, $oldLocale);
            $newPath = $this->publicUrlBuilder->pagePath($normalized['slug'], $normalized['locale']);
            if ($oldPath !== $newPath) {
                $this->urlRedirectService->recordPublishedSlugChange(
                    oldSourcePath: $oldPath,
                    newTargetPath: $newPath,
                    resourceType: 'page',
                    resourceId: $id,
                    locale: $normalized['locale'],
                );
            }
        }

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PageUpdated,
            $this->actorId($actor),
        );

        $this->db->transComplete();

        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to update Page.'];
        }

        if ($existing['page']->status === PageStatus::Published->value) {
            $this->publicContentCache->invalidatePage($id);
        }

        return [];
    }

    /**
     * Publish a Page (DOC-04 §20; DOC-03 page.publish).
     *
     * Allowed: DRAFT → PUBLISHED, UNPUBLISHED → PUBLISHED, ARCHIVED → PUBLISHED (ADR-020).
     * No ownership rule on Pages in V1 sources — permission-only.
     * Does not invent published_at/by columns or public Page routes.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function publish(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('page.publish')) {
            return ['_forbidden' => 'You are not allowed to publish Pages.'];
        }

        $page = $this->findById($id);
        if ($page === null || $page->status === PageStatus::Trash->value || $page->deleted_at !== null) {
            return ['_not_found' => 'Page not found.'];
        }

        $current = PageStatus::tryFromString((string) $page->status);
        if (
            $current !== PageStatus::Draft
            && $current !== PageStatus::Unpublished
            && $current !== PageStatus::Archived
        ) {
            return ['_status' => 'This Page cannot be published from its current state.'];
        }

        $errors = $this->validateForPublish($page);
        if ($errors !== []) {
            return $errors;
        }

        $this->db->transStart();

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->pageModel->update($id, [
            'status' => PageStatus::Published->value,
        ]);

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PagePublished,
            $this->actorId($actor),
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to publish Page.'];
        }

        $this->publicContentCache->invalidatePage($id);

        return [];
    }

    /**
     * Unpublish a Page (DOC-04 §15; DOC-03 page.unpublish).
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
    ): array {
        if ($actor !== null && ! $actor->can('page.unpublish')) {
            return ['_forbidden' => 'You are not allowed to unpublish Pages.'];
        }

        $page = $this->findById($id);
        if ($page === null || $page->status === PageStatus::Trash->value || $page->deleted_at !== null) {
            return ['_not_found' => 'Page not found.'];
        }

        if ($page->status !== PageStatus::Published->value) {
            return ['_status' => 'This Page cannot be unpublished from its current state.'];
        }

        $this->db->transStart();

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->pageModel->update($id, [
            'status' => PageStatus::Unpublished->value,
        ]);

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PageUnpublished,
            $this->actorId($actor),
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to unpublish Page.'];
        }

        $this->publicContentCache->invalidatePage($id);

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
        $page = $this->findById($id);
        if ($page === null) {
            return ['_result_code' => ScheduledActionResultCode::TargetMissing->value];
        }

        if ($page->status === PageStatus::Trash->value || $page->deleted_at !== null) {
            return ['_result_code' => ScheduledActionResultCode::TargetTrash->value];
        }

        $current = PageStatus::tryFromString((string) $page->status);
        if ($current === PageStatus::Published) {
            return ['_result_code' => ScheduledActionResultCode::TargetAlreadyPublished->value];
        }
        if ($current === PageStatus::Archived) {
            return ['_result_code' => ScheduledActionResultCode::TargetArchived->value];
        }
        if ($current !== PageStatus::Draft && $current !== PageStatus::Unpublished) {
            $code = (string) $page->status === 'PENDING_REVIEW'
                ? ScheduledActionResultCode::TargetPendingReview->value
                : ScheduledActionResultCode::InvalidSourceState->value;

            return ['_result_code' => $code];
        }

        $errors = $this->validateForPublish($page);
        if ($errors !== []) {
            $errors['_result_code'] = ScheduledActionResultCode::ValidationFailed->value;

            return $errors;
        }

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $code = isset($occ['errors']['_conflict'])
                ? ScheduledActionResultCode::LockVersionConflict->value
                : ScheduledActionResultCode::TargetMissing->value;

            return ['_result_code' => $code];
        }

        $this->pageModel->update($id, [
            'status' => PageStatus::Published->value,
        ]);

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PagePublished,
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
        $page = $this->findById($id);
        if ($page === null) {
            return ['_result_code' => ScheduledActionResultCode::TargetMissing->value];
        }

        if ($page->status === PageStatus::Trash->value || $page->deleted_at !== null) {
            return ['_result_code' => ScheduledActionResultCode::TargetTrash->value];
        }

        if ($page->status === PageStatus::Archived->value) {
            return ['_result_code' => ScheduledActionResultCode::TargetArchived->value];
        }

        if ($page->status === PageStatus::Unpublished->value) {
            return ['_result_code' => ScheduledActionResultCode::TargetAlreadyUnpublished->value];
        }

        if ($page->status !== PageStatus::Published->value) {
            return ['_result_code' => ScheduledActionResultCode::InvalidSourceState->value];
        }

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $code = isset($occ['errors']['_conflict'])
                ? ScheduledActionResultCode::LockVersionConflict->value
                : ScheduledActionResultCode::TargetMissing->value;

            return ['_result_code' => $code];
        }

        $this->pageModel->update($id, [
            'status' => PageStatus::Unpublished->value,
        ]);

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PageUnpublished,
            null,
        );

        return [];
    }

    /**
     * Archive a Page (DOC-04 §16; DOC-03 page.archive; ADR-020).
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
        if ($actor !== null && ! $actor->can('page.archive')) {
            return ['_forbidden' => 'You are not allowed to archive Pages.'];
        }

        $page = $this->findById($id);
        if ($page === null || $page->status === PageStatus::Trash->value || $page->deleted_at !== null) {
            return ['_not_found' => 'Page not found.'];
        }

        if ($page->status !== PageStatus::Published->value) {
            return ['_status' => 'This Page cannot be archived from its current state.'];
        }

        $this->db->transStart();

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->pageModel->update($id, [
            'status' => PageStatus::Archived->value,
        ]);

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PageArchived,
            $this->actorId($actor),
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to archive Page.'];
        }

        $this->publicContentCache->invalidatePage($id);

        return [];
    }

    /**
     * Soft-delete: status=TRASH + deleted_at (REQ-PAGE-012).
     * Rejects parents that still have non-trashed children.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function trash(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('page.trash')) {
            return ['_forbidden' => 'You are not allowed to trash Pages.'];
        }

        $page = $this->findById($id);
        if ($page === null || $page->status === PageStatus::Trash->value) {
            return ['_not_found' => 'Page not found.'];
        }

        if ($this->pageModel->countChildren($id) > 0) {
            return [
                'parent_id' => 'Cannot trash a Page that has child Pages. Trash or reassign children first.',
            ];
        }

        $this->db->transStart();

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->pageModel->update($id, [
            'status'     => PageStatus::Trash->value,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PageTrashed,
            $this->actorId($actor),
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to trash Page.'];
        }

        $this->publicContentCache->invalidatePage($id);

        return [];
    }

    /**
     * Restore a trashed Page as a draft.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function restoreFromTrash(
        int $id,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('page.restore')) {
            return ['_forbidden' => 'You are not allowed to restore Pages.'];
        }

        $page = $this->findById($id);
        if ($page === null) {
            return ['_not_found' => 'Page not found.'];
        }
        if ($page->status !== PageStatus::Trash->value) {
            return ['_status' => 'Only a trashed Page can be restored from Trash.'];
        }

        $this->db->transStart();

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->pageModel->update($id, [
            'status'     => PageStatus::Draft->value,
            'deleted_at' => null,
        ]);

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::PageRestored,
            $this->actorId($actor),
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to restore Page.'];
        }

        return [];
    }

    /**
     * Permanently delete a trashed Page (DOC-04 §19 / ADR-019 / Task 4.10A).
     *
     * Removes owned live rows (page + translations). Retains revisions and audit_logs.
     * Blocks when child Pages or Menu PAGE targets still reference the Page.
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
            return ['_forbidden' => 'Only Admin may permanently delete Pages.'];
        }

        $page = $this->findById($id);
        if ($page === null) {
            return ['_not_found' => 'Page not found.'];
        }
        if ($page->status !== PageStatus::Trash->value) {
            return ['_status' => 'Only a trashed Page can be permanently deleted.'];
        }

        $deps = $this->permanentDeleteDependencyChecker->findPageDependencies($id);
        if ($deps !== []) {
            return [
                '_dependency' => 'This Page still has dependencies and cannot be permanently deleted: '
                    . implode('; ', array_slice($deps, 0, 5)),
            ];
        }

        $revisionCountBefore = $this->db->table('revisions')
            ->where('resource_type', RevisionResourceType::Page->value)
            ->where('resource_id', $id)
            ->countAllResults();
        $auditCountBefore = $this->db->table('audit_logs')
            ->where('resource_type', RevisionResourceType::Page->value)
            ->where('resource_id', $id)
            ->countAllResults();

        $this->db->transStart();

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        // Re-check status under lock.
        $locked = $this->revisionService->lockParentRow('pages', $id);
        if ($locked === null || (string) ($locked['status'] ?? '') !== PageStatus::Trash->value) {
            $this->db->transRollback();

            return ['_status' => 'Only a trashed Page can be permanently deleted.'];
        }

        $depsLocked = $this->permanentDeleteDependencyChecker->findPageDependencies($id);
        if ($depsLocked !== []) {
            $this->db->transRollback();

            return [
                '_dependency' => 'This Page still has dependencies and cannot be permanently deleted: '
                    . implode('; ', array_slice($depsLocked, 0, 5)),
            ];
        }

        // Owned live data (SQLite tests lack MySQL ON DELETE CASCADE).
        $this->translationModel->where('page_id', $id)->delete();
        $this->pageModel->delete($id);

        (void) $this->auditService->append(
            AuditEvent::PagePermanentlyDeleted,
            $this->actorId($actor),
            RevisionResourceType::Page->value,
            $id,
            null,
            null,
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to permanently delete Page.'];
        }

        // ADR-019: revisions and prior audit rows must still be present.
        $revisionCountAfter = $this->db->table('revisions')
            ->where('resource_type', RevisionResourceType::Page->value)
            ->where('resource_id', $id)
            ->countAllResults();
        $auditCountAfter = $this->db->table('audit_logs')
            ->where('resource_type', RevisionResourceType::Page->value)
            ->where('resource_id', $id)
            ->countAllResults();

        if ($revisionCountAfter < $revisionCountBefore || $auditCountAfter < $auditCountBefore + 1) {
            throw new RuntimeException('Page permanent delete violated revision/audit retention.');
        }

        $this->publicContentCache->invalidatePage($id);

        return [];
    }

    /**
     * Restore an editorial revision without changing the Page status.
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
        if ($actor !== null && ! $actor->can('page.restore')) {
            return ['_forbidden' => 'You are not allowed to restore Page revisions.'];
        }

        if ($this->findById($id) === null) {
            return ['_not_found' => 'Page not found.'];
        }

        $revision = $this->revisionService->findById($revisionId);
        if (
            $revision === null
            || $revision->resource_type !== RevisionResourceType::Page->value
            || (int) $revision->resource_id !== $id
            || (int) $revision->is_autosave !== 0
        ) {
            return ['_revision' => 'Page revision not found.'];
        }

        $snapshot = $revision->decodedSnapshot();
        if ($snapshot === null) {
            return ['_revision' => 'The Page revision snapshot is invalid.'];
        }

        $prepared = $this->prepareRevisionSnapshot($id, $snapshot);
        if ($prepared['errors'] !== []) {
            return $prepared['errors'];
        }

        $this->db->transStart();

        $occ = $this->beginOccMutation('pages', $id, $expectedLockVersion);
        if (! $occ['ok']) {
            $this->db->transRollback();

            return $occ['errors'];
        }

        $this->pageModel->update($id, [
            'template_key' => $prepared['template_key'],
            'parent_id'    => $prepared['parent_id'],
        ]);

        $this->translationModel->where('page_id', $id)->delete();
        foreach ($prepared['translations'] as $translation) {
            $this->translationModel->insert([
                'page_id'         => $id,
                'locale'          => $translation['locale'],
                'title'           => $translation['title'],
                'slug'            => $translation['slug'],
                'content_payload' => $translation['content_payload'],
            ]);
        }

        $this->revisionService->recordEditorialFromLive(
            RevisionResourceType::Page,
            $id,
            AuditEvent::RevisionRestored,
            $this->actorId($actor),
            ['source_revision_id' => $revisionId],
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to restore Page revision.'];
        }

        if ($this->findById($id)?->status === PageStatus::Published->value) {
            $this->publicContentCache->invalidatePage($id);
        }

        return [];
    }

    /**
     * Record an isolated autosave snapshot without mutating the live Page.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function autosave(
        int $id,
        PageWriteDto $dto,
        ?User $actor = null,
        ?int $expectedLockVersion = null,
    ): array {
        if ($actor !== null && ! $actor->can('page.edit')) {
            return ['_forbidden' => 'You are not allowed to autosave Pages.'];
        }

        $existing = $this->findEditable($id);
        if ($existing === null) {
            return ['_not_found' => 'Page not found.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, $id);
        if ($errors !== []) {
            return $errors;
        }

        $schemaResult = $this->resolveSchema($normalized['template_key']);
        if ($schemaResult['errors'] !== []) {
            return $schemaResult['errors'];
        }

        $schema        = $schemaResult['schema'];
        $payload       = $this->richTextSanitizer->sanitizePayload($normalized['content_payload'], $schema);
        $contentResult = $this->contentSchemaValidator->validate($payload, $schema);
        if (! $contentResult->ok) {
            return $contentResult->errors;
        }

        $existingPayload = $this->decodeContentPayload((string) $existing['translation']->content_payload);
        $mergedPayload   = $this->contentSchemaValidator->mergePreservingLegacy(
            $existingPayload,
            $contentResult->normalized,
            $schema,
        );

        $this->db->transStart();

        $locked = $this->revisionService->lockParentRow('pages', $id);
        if ($locked === null) {
            $this->db->transRollback();

            return ['_not_found' => 'Page not found.'];
        }

        $currentLockVersion = (int) $locked['lock_version'];
        if ($expectedLockVersion !== null && $expectedLockVersion !== $currentLockVersion) {
            $this->db->transRollback();

            return [
                '_conflict'    => 'The content was modified by another session.',
                'lock_version' => (string) $currentLockVersion,
            ];
        }
        if ((string) $locked['status'] === PageStatus::Trash->value) {
            $this->db->transRollback();

            return ['_not_found' => 'Page not found.'];
        }

        $snapshot                 = $this->revisionService->buildPageSnapshot($id);
        $snapshot['template_key'] = $normalized['template_key'];
        $snapshot['parent_id']    = $normalized['parent_id'];
        $translations             = $snapshot['translations'];
        if (! is_array($translations)) {
            $translations = [];
        }
        $translations[$normalized['locale']] = [
            'title'           => $normalized['title'],
            'slug'            => $normalized['slug'],
            'content_payload' => $mergedPayload,
        ];
        $snapshot['translations'] = $translations;

        $this->revisionService->recordAutosave(
            RevisionResourceType::Page,
            $id,
            $snapshot,
            $this->actorId($actor),
        );

        $this->db->transComplete();
        if (! $this->db->transStatus()) {
            return ['_persist' => 'Unable to autosave Page.'];
        }

        return [];
    }

    /**
     * Top-level (non-trashed) pages for parent selection.
     *
     * @return list<Page>
     */
    public function listValidParents(?int $excludeId = null): array
    {
        $builder = $this->pageModel
            ->where('parent_id', null)
            ->where('status !=', PageStatus::Trash->value)
            ->orderBy('id', 'ASC');

        /** @var list<Page> $pages */
        $pages = $builder->findAll();

        if ($excludeId === null) {
            return $pages;
        }

        return array_values(array_filter(
            $pages,
            static fn (Page $page): bool => $page->id !== $excludeId,
        ));
    }

    private function primaryTranslation(int $pageId): ?PageTranslation
    {
        /** @var list<PageTranslation> $rows */
        $rows = $this->translationModel
            ->where('page_id', $pageId)
            ->orderBy('id', 'ASC')
            ->findAll();

        return $rows[0] ?? null;
    }

    /**
     * Validate and normalize a schema-version-1 Page revision snapshot.
     *
     * @param array<string, mixed> $snapshot
     *
     * @return array{
     *     template_key: string,
     *     parent_id: int|null,
     *     translations: list<array{
     *         locale: string,
     *         title: string,
     *         slug: string,
     *         content_payload: string
     *     }>,
     *     errors: array<string, string>
     * }
     */
    private function prepareRevisionSnapshot(int $pageId, array $snapshot): array
    {
        $invalid = [
            'template_key' => '',
            'parent_id'    => null,
            'translations' => [],
            'errors'       => ['_revision' => 'The Page revision snapshot is invalid.'],
        ];

        if (
            ($snapshot['schema_version'] ?? null) !== 1
            || ($snapshot['resource_type'] ?? null) !== RevisionResourceType::Page->value
            || (int) ($snapshot['resource_id'] ?? 0) !== $pageId
            || ! is_string($snapshot['template_key'] ?? null)
            || ! is_array($snapshot['translations'] ?? null)
            || $snapshot['translations'] === []
        ) {
            return $invalid;
        }

        $templateKey = trim($snapshot['template_key']);
        $parentValue = $snapshot['parent_id'] ?? null;
        if ($parentValue !== null && (! is_numeric($parentValue) || (int) $parentValue < 1)) {
            return $invalid;
        }
        $parentId = $parentValue !== null ? (int) $parentValue : null;

        $schemaResult = $this->resolveSchema($templateKey);
        if ($schemaResult['errors'] !== []) {
            return [
                'template_key' => $templateKey,
                'parent_id'    => $parentId,
                'translations' => [],
                'errors'       => $schemaResult['errors'],
            ];
        }

        $preparedTranslations = [];
        foreach ($snapshot['translations'] as $localeKey => $translation) {
            if (! is_string($localeKey) || ! is_array($translation)) {
                return $invalid;
            }

            $locale = strtolower(trim($localeKey));
            if (
                ! in_array($locale, self::ALLOWED_LOCALES, true)
                || ! is_string($translation['title'] ?? null)
                || ! is_string($translation['slug'] ?? null)
                || ! is_array($translation['content_payload'] ?? null)
            ) {
                return $invalid;
            }

            $data = [
                'title'           => trim($translation['title']),
                'slug'            => $this->normalizeSlug($translation['slug']),
                'locale'          => $locale,
                'template_key'    => $templateKey,
                'parent_id'       => $parentId,
                'content_payload' => $translation['content_payload'],
            ];
            $errors = $this->validate($data, $pageId);
            if ($errors !== []) {
                return [
                    'template_key' => $templateKey,
                    'parent_id'    => $parentId,
                    'translations' => [],
                    'errors'       => $errors,
                ];
            }

            $sanitized = $this->richTextSanitizer->sanitizePayload(
                $data['content_payload'],
                $schemaResult['schema'],
            );
            $contentResult = $this->contentSchemaValidator->validate($sanitized, $schemaResult['schema']);
            if (! $contentResult->ok) {
                return [
                    'template_key' => $templateKey,
                    'parent_id'    => $parentId,
                    'translations' => [],
                    'errors'       => $contentResult->errors,
                ];
            }

            $preparedTranslations[] = [
                'locale'          => $locale,
                'title'           => $data['title'],
                'slug'            => $data['slug'],
                'content_payload' => $this->encodePayload($contentResult->normalized),
            ];
        }

        return [
            'template_key' => $templateKey,
            'parent_id'    => $parentId,
            'translations' => $preparedTranslations,
            'errors'       => [],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     slug: string,
     *     locale: string,
     *     template_key: string,
     *     parent_id: int|null,
     *     content_payload: array<string, mixed>,
     *     meta_title: ?string,
     *     meta_description: ?string,
     *     canonical_url: ?string,
     *     og_image_id: ?int
     * }
     */
    private function normalize(PageWriteDto $dto): array
    {
        $locale = strtolower(trim($dto->locale));
        $slug   = $this->normalizeSlug($dto->slug);

        $template = trim($dto->templateKey);
        if ($template === '') {
            $template = self::DEFAULT_TEMPLATE;
        }

        $metaTitle = trim((string) ($dto->metaTitle ?? ''));
        $metaDescription = trim((string) ($dto->metaDescription ?? ''));
        $canonicalUrl = trim((string) ($dto->canonicalUrl ?? ''));

        return [
            'title'            => trim($dto->title),
            'slug'             => $slug,
            'locale'           => $locale,
            'template_key'     => $template,
            'parent_id'        => $dto->parentId !== null && $dto->parentId > 0 ? $dto->parentId : null,
            'content_payload'  => $dto->contentPayload,
            'meta_title'       => $metaTitle !== '' ? $metaTitle : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'canonical_url'    => $canonicalUrl !== '' ? $canonicalUrl : null,
            'og_image_id'      => $dto->ogImageId !== null && $dto->ogImageId > 0 ? $dto->ogImageId : null,
        ];
    }

    /**
     * Resolve Content Schema for a template key from the active Theme Manifest.
     *
     * @return array{schema: array<string, array<string, mixed>>, errors: array<string, string>}
     */
    private function resolveSchema(string $templateKey): array
    {
        try {
            return [
                'schema' => $this->themeService->contentSchemaForTemplate($templateKey),
                'errors' => [],
            ];
        } catch (RuntimeException) {
            return [
                'schema' => [],
                'errors' => ['template_key' => 'The template is not available on the active theme.'],
            ];
        }
    }

    /**
     * Encode content_payload as a JSON object (empty payload remains `{}`).
     *
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

    /**
     * @param array{
     *     title: string,
     *     slug: string,
     *     locale: string,
     *     template_key: string,
     *     parent_id: int|null
     * } $data
     *
     * @return array<string, string>
     */
    private function validate(array $data, ?int $currentPageId): array
    {
        $rules = [
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
            'template_key' => [
                'label' => 'Template',
                'rules' => 'required|in_list[' . self::DEFAULT_TEMPLATE . ']',
            ],
        ];

        $this->validation->reset();
        $this->validation->setRules($rules);

        if (! $this->validation->run([
            'title'        => $data['title'],
            'slug'         => $data['slug'],
            'locale'       => $data['locale'],
            'template_key' => $data['template_key'],
        ])) {
            /** @var array<string, string> $errors */
            $errors = $this->validation->getErrors();

            return $errors;
        }

        if ($data['slug'] === '' || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $data['slug'])) {
            return ['slug' => 'The slug format is invalid.'];
        }

        if (in_array($data['slug'], PublicUrlNamespaceValidator::RESERVED_PATHS, true)) {
            return ['slug' => 'The slug conflicts with a reserved system route.'];
        }

        $namespaceErrors = $this->publicUrlNamespaceValidator->validatePageSlug(
            $data['slug'],
            $data['locale'],
            $currentPageId,
        );
        if ($namespaceErrors !== []) {
            return $namespaceErrors;
        }

        return $this->validateHierarchy($data['parent_id'], $currentPageId);
    }

    /**
     * Publish-time validation (DOC-04 §20 / §22).
     *
     * Schema `required` flags apply as declared — do not invent body/SEO requirements.
     *
     * @return array<string, string>
     */
    private function validateForPublish(Page $page): array
    {
        $templateKey = trim((string) $page->template_key);
        if ($templateKey === '') {
            $templateKey = self::DEFAULT_TEMPLATE;
        }

        $schemaResult = $this->resolveSchema($templateKey);
        if ($schemaResult['errors'] !== []) {
            return $schemaResult['errors'];
        }

        $primary = $this->translationModel->findByPageAndLocale(
            (int) $page->id,
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

        $parentId = $page->parent_id !== null ? (int) $page->parent_id : null;
        $hierarchyErrors = $this->validateHierarchy($parentId, (int) $page->id);
        if ($hierarchyErrors !== []) {
            return $hierarchyErrors;
        }

        $payload = $this->decodeContentPayload((string) $primary->content_payload);
        $contentResult = $this->contentSchemaValidator->validate($payload, $schemaResult['schema']);
        if (! $contentResult->ok) {
            return $contentResult->errors;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function validateHierarchy(?int $parentId, ?int $currentPageId): array
    {
        if ($currentPageId !== null && $this->pageModel->countChildren($currentPageId) > 0) {
            if ($parentId !== null) {
                return [
                    'parent_id' => 'A Page that has children cannot become a child (maximum two levels).',
                ];
            }
        }

        if ($parentId === null) {
            return [];
        }

        if ($currentPageId !== null && $parentId === $currentPageId) {
            return ['parent_id' => 'A Page cannot be its own parent.'];
        }

        $parent = $this->findById($parentId);
        if ($parent === null || $parent->status === PageStatus::Trash->value) {
            return ['parent_id' => 'The selected parent Page does not exist.'];
        }

        if ($parent->parent_id !== null) {
            return [
                'parent_id' => 'The selected parent is already a child. Maximum hierarchy is two levels.',
            ];
        }

        return [];
    }
}
