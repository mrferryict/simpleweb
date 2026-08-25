<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Concerns\AutosaveResponder;
use App\Controllers\Concerns\OccConflictResponder;
use App\Dtos\PostWriteDto;
use App\Dtos\CreateScheduledActionDto;
use App\Enums\PostStatus;
use App\Enums\RevisionResourceType;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Services\Revision\RevisionService;
use App\Services\ScheduledContentService;
use App\Services\TagService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Config\Site;

/**
 * Control Panel Post foundation CRUD + publishing + review (Phase 3–4 / Tasks 3.7–4.2).
 * Revision history + OCC 409 (Phase 4 / Task 4.9C / ADR-019).
 *
 * Authorization: route filters + PostService ownership / publish / review permissions (DOC-03).
 * Content schema: ACTIVE Theme → custom-post (ADR-015); no template selector.
 */
class PostController extends BaseController
{
    use AutosaveResponder;
    use OccConflictResponder;

    public function index(): string
    {
        $status  = strtoupper((string) $this->request->getGet('status'));
        $isTrash = $status === PostStatus::Trash->value;
        $actor   = $this->actor();

        return view('admin/posts/index', [
            'rows'               => $isTrash
                ? $this->postService()->listTrashed($actor)
                : $this->postService()->listActive($actor),
            'isTrash'            => $isTrash,
            'success'            => session()->getFlashdata('success'),
            'error'              => session()->getFlashdata('error'),
            'canTrash'           => $actor?->can('post.trash') ?? false,
            'canRestore'         => $actor?->can('post.restore') ?? false,
            'canPermanentDelete' => $actor?->can('content.permanent_delete') ?? false,
        ]);
    }

    public function create(): string
    {
        return view('admin/posts/form', $this->formViewData(
            mode: 'create',
            item: $this->emptyFormData(),
            errors: [],
            formAction: site_url('admin/posts'),
        ));
    }

    public function store(): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->postService()->create($dto, $this->actor());
        if ($errors !== []) {
            return view('admin/posts/form', $this->formViewData(
                mode: 'create',
                item: $this->formDataFromDto($dto),
                errors: $errors,
                formAction: site_url('admin/posts'),
            ));
        }

        return redirect()->to('/admin/posts')->with('success', 'Post created.');
    }

    public function edit(int $id): ResponseInterface|RedirectResponse|string
    {
        $editable = $this->postService()->findEditable($id, $this->actor());
        if ($editable === null) {
            return redirect()->to('/admin/posts')->with('error', 'Post not found.');
        }

        $post        = $editable['post'];
        $translation = $editable['translation'];

        return view('admin/posts/form', $this->formViewData(
            mode: 'edit',
            item: [
                'id'                => $post->id,
                'title'             => $translation->title,
                'slug'              => $translation->slug,
                'locale'            => $translation->locale,
                'manual_author'     => $post->manual_author,
                'status'            => $post->status,
                'lock_version'      => (int) $post->lock_version,
                'category_ids'      => $editable['category_ids'],
                'tag_ids'           => $editable['tag_ids'],
                'featured_image_id' => $post->featured_image_id,
                'content_payload'   => $this->postService()->decodeContentPayload($translation->content_payload),
                'meta_title'        => $translation->meta_title ?? '',
                'meta_description'  => $translation->meta_description ?? '',
                'canonical_url'     => $translation->canonical_url ?? '',
                'og_image_id'       => $translation->og_image_id ?? '',
            ],
            errors: [],
            formAction: site_url('admin/posts/' . $post->id),
            success: session()->getFlashdata('success'),
            flashError: session()->getFlashdata('error'),
        ));
    }

    public function update(int $id): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->postService()->update(
            $id,
            $dto,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );
        if (isset($errors['_not_found']) || isset($errors['_forbidden'])) {
            return redirect()->to('/admin/posts')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
        }

        if ($this->isOccConflict($errors)) {
            return $this->respondOccConflict(
                $errors,
                'admin/posts/form',
                $this->formViewData(
                    mode: 'edit',
                    item: array_merge($this->formDataFromDto($dto), [
                        'id'           => $id,
                        'lock_version' => (int) ($errors['lock_version'] ?? 0),
                    ]),
                    errors: [],
                    formAction: site_url('admin/posts/' . $id),
                ),
            );
        }

        if ($errors !== []) {
            return view('admin/posts/form', $this->formViewData(
                mode: 'edit',
                item: array_merge($this->formDataFromDto($dto), ['id' => $id]),
                errors: $errors,
                formAction: site_url('admin/posts/' . $id),
            ));
        }

        return redirect()->to('/admin/posts')->with('success', 'Post updated.');
    }

    public function publish(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->publish(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Post published.');
    }

    public function unpublish(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->unpublish(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Post unpublished.');
    }

    public function archive(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->archive(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Post archived.');
    }

    public function submitForReview(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->submitForReview(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Post submitted for review.');
    }

    public function reviewAndPublish(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->reviewAndPublish(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Post published after review.');
    }

    public function returnForRevision(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->returnForRevision(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Post returned for revision.');
    }

    /**
     * GET /admin/posts/{id}/revisions — Editor/Admin only (route: post.edit_any).
     */
    public function revisions(int $id): ResponseInterface|RedirectResponse|string
    {
        $editable = $this->postService()->findEditable($id, $this->actor());
        if ($editable === null) {
            return redirect()->to('/admin/posts')->with('error', 'Post not found.');
        }

        $post        = $editable['post'];
        $translation = $editable['translation'];
        $actor       = $this->actor();

        return view('admin/posts/revisions', [
            'postId'      => (int) $post->id,
            'postTitle'   => (string) $translation->title,
            'revisions'   => $this->revisionService()->listEditorialHistory(
                RevisionResourceType::Post,
                (int) $post->id,
            ),
            'canRestore'  => $actor?->can('post.restore') ?? false,
            'lockVersion' => (int) $post->lock_version,
            'success'     => session()->getFlashdata('success'),
            'flashError'  => session()->getFlashdata('error'),
        ]);
    }

    /**
     * POST /admin/posts/{id}/revisions/{revision}/restore
     */
    public function restoreRevision(int $id, int $revisionId): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->restoreRevision(
            $id,
            $revisionId,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        if (isset($errors['_not_found']) || isset($errors['_forbidden'])) {
            return redirect()->to('/admin/posts')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
        }

        if ($this->isOccConflict($errors)) {
            $message = (string) ($errors['_conflict'] ?? 'Conflict');
            $version = (string) ($errors['lock_version'] ?? '');

            return $this->response
                ->setStatusCode(409)
                ->setBody($message . ($version !== '' ? ' (current version: ' . $version . ')' : ''));
        }

        if ($errors !== []) {
            return redirect()->to('/admin/posts/' . $id . '/revisions')
                ->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/posts/' . $id . '/edit')
            ->with('success', 'Post revision restored.');
    }

    /**
     * POST /admin/posts/{id}/autosave — HTMX draft snapshot (ADR-019 / Task 4.9D).
     * Does not mutate live Post rows or bump lock_version.
     */
    public function autosave(int $id): ResponseInterface
    {
        $post = $this->postService()->findById($id);
        if ($post === null) {
            return $this->respondAutosaveResult(
                ['_not_found' => 'Post not found.'],
                RevisionResourceType::Post,
                $id,
                0,
            );
        }

        $lockVersion = (int) $post->lock_version;
        $errors      = $this->postService()->autosave(
            $id,
            $this->dtoFromRequest(),
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        $fresh = $this->postService()->findById($id);
        if ($fresh !== null) {
            $lockVersion = (int) $fresh->lock_version;
        }

        return $this->respondAutosaveResult(
            $errors,
            RevisionResourceType::Post,
            $id,
            $lockVersion,
        );
    }

    public function delete(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->trash(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondTrashLifecycle(
            $errors,
            'Post moved to Trash.',
            '/admin/posts',
        );
    }

    /**
     * POST /admin/posts/{id}/restore — TRASH → DRAFT.
     */
    public function restore(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->restoreFromTrash(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        if ($errors === []) {
            return redirect()->to('/admin/posts/' . $id . '/edit')->with('success', 'Post restored.');
        }

        return $this->respondTrashLifecycle(
            $errors,
            'Post restored.',
            '/admin/posts?status=TRASH',
        );
    }

    /**
     * POST /admin/posts/{id}/permanent-delete — TRASH only.
     */
    public function permanentDelete(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->postService()->permanentlyDelete(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondTrashLifecycle(
            $errors,
            'Post permanently deleted.',
            '/admin/posts?status=TRASH',
        );
    }

    /**
     * POST /admin/posts/{id}/schedules
     */
    public function storeSchedule(int $id): RedirectResponse
    {
        $actor = $this->actor();
        if ($actor === null) {
            return redirect()->route('login');
        }

        $dto = new CreateScheduledActionDto(
            targetType: 'post',
            targetId: $id,
            actionType: (string) $this->request->getPost('action_type'),
            executeAtLocal: (string) $this->request->getPost('execute_at'),
        );

        $errors = $this->scheduledContentService()->create($dto, $actor);
        if ($errors !== []) {
            return redirect()->to('/admin/posts/' . $id . '/edit')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/posts/' . $id . '/edit')->with('success', 'Schedule created.');
    }

    /**
     * POST /admin/posts/{id}/schedules/{scheduleId}/cancel
     */
    public function cancelSchedule(int $id, int $scheduleId): RedirectResponse
    {
        $actor = $this->actor();
        if ($actor === null) {
            return redirect()->route('login');
        }

        $errors = $this->scheduledContentService()->cancel(
            $scheduleId,
            $actor,
            'post',
            $id,
        );
        if ($errors !== []) {
            return redirect()->to('/admin/posts/' . $id . '/edit')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/posts/' . $id . '/edit')->with('success', 'Schedule cancelled.');
    }

    private function postService(): PostService
    {
        return service('postService');
    }

    private function revisionService(): RevisionService
    {
        return service('revisionService');
    }

    private function categoryService(): CategoryService
    {
        return service('categoryService');
    }

    private function tagService(): TagService
    {
        return service('tagService');
    }

    private function scheduledContentService(): ScheduledContentService
    {
        return service('scheduledContentService');
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param array<string, string> $errors
     */
    private function respondLifecycleResult(int $id, array $errors, string $successMessage): ResponseInterface|RedirectResponse
    {
        if (isset($errors['_not_found']) || isset($errors['_forbidden'])) {
            return redirect()->to('/admin/posts')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
        }

        if ($this->isOccConflict($errors)) {
            $message = (string) ($errors['_conflict'] ?? 'Conflict');
            $version = (string) ($errors['lock_version'] ?? '');

            return $this->response
                ->setStatusCode(409)
                ->setBody($message . ($version !== '' ? ' (current version: ' . $version . ')' : ''));
        }

        if ($errors !== []) {
            return redirect()->to('/admin/posts/' . $id . '/edit')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/posts/' . $id . '/edit')->with('success', $successMessage);
    }

    /**
     * Map trash / restore / permanent-delete Service results.
     *
     * @param array<string, string> $errors
     */
    private function respondTrashLifecycle(
        array $errors,
        string $successMessage,
        string $errorPath,
    ): ResponseInterface|RedirectResponse {
        if (isset($errors['_not_found']) || isset($errors['_forbidden'])) {
            return redirect()->to('/admin/posts')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
        }

        if ($this->isOccConflict($errors)) {
            $message = (string) ($errors['_conflict'] ?? 'Conflict');
            $version = (string) ($errors['lock_version'] ?? '');

            return $this->response
                ->setStatusCode(409)
                ->setBody($message . ($version !== '' ? ' (current version: ' . $version . ')' : ''));
        }

        if ($errors !== []) {
            $message = (string) ($errors['_dependency'] ?? $errors['_status'] ?? implode(' ', $errors));

            return redirect()->to($errorPath)->with('error', $message);
        }

        return redirect()->to($errorPath)->with('success', $successMessage);
    }

    /**
     * @param array<string, mixed>  $item
     * @param array<string, string> $errors
     *
     * @return array<string, mixed>
     */
    private function formViewData(
        string $mode,
        array $item,
        array $errors,
        string $formAction,
        mixed $success = null,
        mixed $flashError = null,
    ): array {
        $schema  = $this->postService()->contentSchema();
        $payload = $item['content_payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $status = (string) ($item['status'] ?? '');
        $actor  = $this->actor();
        $postId = isset($item['id']) ? (int) $item['id'] : 0;
        $scheduler = $this->scheduledContentService();

        return [
            'mode'           => $mode,
            'item'           => $item,
            'locales'        => ['id', 'en'],
            'categories'     => $this->categoryService()->listActive(),
            'tags'           => $this->tagService()->listAll(),
            'errors'         => $errors,
            'formAction'     => $formAction,
            'contentSchema'  => $schema,
            'contentPayload' => $this->applySchemaDefaults($schema, $payload),
            'success'        => is_string($success) ? $success : null,
            'flashError'     => is_string($flashError) ? $flashError : null,
            'canPublish'           => ($actor?->can('post.publish') ?? false)
                && in_array($status, ['DRAFT', 'UNPUBLISHED', 'ARCHIVED'], true),
            'canUnpublish'         => ($actor?->can('post.unpublish') ?? false)
                && $status === 'PUBLISHED',
            'canArchive'           => ($actor?->can('post.archive') ?? false)
                && $status === 'PUBLISHED',
            'canSubmitForReview'   => ($actor?->can('post.submit_review') ?? false)
                && $status === 'DRAFT',
            'canReviewPublish'     => ($actor?->can('post.review') ?? false)
                && $status === 'PENDING_REVIEW',
            'canReturnForRevision' => ($actor?->can('post.review') ?? false)
                && $status === 'PENDING_REVIEW',
            'canViewRevisions'     => $mode === 'edit'
                && ($actor?->can('post.edit_any') ?? false)
                && ! empty($item['id']),
            'canSchedulePublish'   => $mode === 'edit' && ($actor?->can('post.publish') ?? false),
            'canScheduleUnpublish' => $mode === 'edit' && ($actor?->can('post.unpublish') ?? false),
            'scheduledActions'     => $mode === 'edit' && $postId > 0
                ? $scheduler->listForTarget('post', $postId)
                : [],
            'siteTimezone'         => $scheduler->siteTimezone(),
            'scheduleCreateUrl'    => $postId > 0 ? site_url('admin/posts/' . $postId . '/schedules') : '',
            'scheduleCancelBase'   => $postId > 0 ? site_url('admin/posts/' . $postId . '/schedules') : '',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $schema
     * @param array<string, mixed>                $payload
     *
     * @return array<string, mixed>
     */
    private function applySchemaDefaults(array $schema, array $payload): array
    {
        foreach ($schema as $key => $definition) {
            if (! is_string($key) || array_key_exists($key, $payload)) {
                continue;
            }
            if (is_array($definition) && array_key_exists('default', $definition)) {
                $payload[$key] = $definition['default'];
            }
        }

        return $payload;
    }

    private function dtoFromRequest(): PostWriteDto
    {
        $categoryRaw = $this->request->getPost('category_ids');
        $categoryIds = [];
        if (is_array($categoryRaw)) {
            foreach ($categoryRaw as $value) {
                if (is_numeric($value) && (int) $value > 0) {
                    $categoryIds[] = (int) $value;
                }
            }
        }

        $tagRaw = $this->request->getPost('tag_ids');
        $tagIds = [];
        if (is_array($tagRaw)) {
            foreach ($tagRaw as $value) {
                if (is_numeric($value) && (int) $value > 0) {
                    $tagIds[] = (int) $value;
                }
            }
        }

        $featuredRaw = $this->request->getPost('featured_image_id');
        $featuredId  = null;
        if ($featuredRaw !== null && $featuredRaw !== '' && is_numeric($featuredRaw) && (int) $featuredRaw > 0) {
            $featuredId = (int) $featuredRaw;
        }

        $actor  = $this->actor();
        $schema = $this->postService()->contentSchema();

        return new PostWriteDto(
            title: (string) ($this->request->getPost('title') ?? ''),
            slug: (string) ($this->request->getPost('slug') ?? ''),
            locale: (string) ($this->request->getPost('locale') ?? ''),
            manualAuthor: (string) ($this->request->getPost('manual_author') ?? ''),
            categoryIds: $categoryIds,
            tagIds: $tagIds,
            contentPayload: $this->contentPayloadFromRequest($schema),
            featuredImageId: $featuredId,
            createdBy: $actor !== null ? (int) $actor->id : null,
            metaTitle: $this->optionalStringPost('meta_title'),
            metaDescription: $this->optionalStringPost('meta_description'),
            canonicalUrl: $this->optionalStringPost('canonical_url'),
            ogImageId: $this->optionalIntPost('og_image_id'),
        );
    }

    private function optionalStringPost(string $key): ?string
    {
        $value = trim((string) ($this->request->getPost($key) ?? ''));

        return $value !== '' ? $value : null;
    }

    private function optionalIntPost(string $key): ?int
    {
        $raw = $this->request->getPost($key);
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $int = (int) $raw;

        return $int > 0 ? $int : null;
    }

    /**
     * Map POST `content[...]` into content_payload shaped for custom-post schema.
     *
     * @param array<string, array<string, mixed>> $schema
     *
     * @return array<string, mixed>
     */
    private function contentPayloadFromRequest(array $schema): array
    {
        $raw = $this->request->getPost('content');
        if (! is_array($raw)) {
            return [];
        }

        $payload = [];

        foreach ($schema as $fieldKey => $definition) {
            if (! is_string($fieldKey) || ! is_array($definition)) {
                continue;
            }
            if (! array_key_exists($fieldKey, $raw)) {
                continue;
            }

            $type = isset($definition['type']) && is_string($definition['type'])
                ? $definition['type']
                : '';

            $value = $raw[$fieldKey];

            $normalized = match ($type) {
                'IMAGE', 'DOCUMENT' => $this->normalizeMediaIdInput($value),
                'REPEATABLE' => null,
                default => $this->normalizeScalarInput($value),
            };

            if ($normalized === null) {
                continue;
            }

            $payload[$fieldKey] = $normalized;
        }

        return $payload;
    }

    private function normalizeScalarInput(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $string = is_string($value) ? $value : (string) $value;
        if (trim($string) === '') {
            return null;
        }

        return $string;
    }

    /**
     * @return int|string|null
     */
    private function normalizeMediaIdInput(mixed $value): int|string|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return is_string($value) ? $value : (string) $value;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFormData(): array
    {
        /** @var Site $site */
        $site = config('Site');

        return [
            'title'             => '',
            'slug'              => '',
            'locale'            => $site->defaultLocale !== '' ? $site->defaultLocale : 'id',
            'manual_author'     => '',
            'category_ids'      => [],
            'tag_ids'           => [],
            'featured_image_id' => null,
            'content_payload'   => [],
            'meta_title'        => '',
            'meta_description'  => '',
            'canonical_url'     => '',
            'og_image_id'       => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formDataFromDto(PostWriteDto $dto): array
    {
        return [
            'title'             => $dto->title,
            'slug'              => $dto->slug,
            'locale'            => $dto->locale,
            'manual_author'     => $dto->manualAuthor,
            'category_ids'      => $dto->categoryIds,
            'tag_ids'           => $dto->tagIds,
            'featured_image_id' => $dto->featuredImageId,
            'content_payload'   => $dto->contentPayload,
            'meta_title'        => $dto->metaTitle ?? '',
            'meta_description'  => $dto->metaDescription ?? '',
            'canonical_url'     => $dto->canonicalUrl ?? '',
            'og_image_id'       => $dto->ogImageId ?? '',
        ];
    }
}
