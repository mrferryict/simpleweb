<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Controllers\Concerns\AutosaveResponder;
use App\Controllers\Concerns\OccConflictResponder;
use App\Dtos\PageWriteDto;
use App\Dtos\CreateScheduledActionDto;
use App\Enums\PageStatus;
use App\Enums\RevisionResourceType;
use App\Services\PageService;
use App\Services\Revision\RevisionService;
use App\Services\ScheduledContentService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Config\Site;

/**
 * Control Panel Page foundation CRUD + publishing (Phase 2–4 / Tasks 2.5–4.3).
 * Revision history + OCC 409 (Phase 4 / Task 4.9C / ADR-019).
 *
 * Authorization via route filters (page.create / page.edit / page.publish / page.unpublish / page.trash / page.restore).
 * Content Schema fields are resolved via PageService → ThemeService (ADR-002).
 */
class PageController extends BaseController
{
    use AutosaveResponder;
    use OccConflictResponder;

    /**
     * GET /admin/pages
     */
    public function index(): string
    {
        $status  = strtoupper((string) $this->request->getGet('status'));
        $isTrash = $status === PageStatus::Trash->value;
        $actor   = $this->actor();

        return view('admin/pages/index', [
            'rows'               => $isTrash ? $this->pageService()->listTrashed() : $this->pageService()->listActive(),
            'isTrash'            => $isTrash,
            'success'            => session()->getFlashdata('success'),
            'error'              => session()->getFlashdata('error'),
            'canTrash'           => $actor?->can('page.trash') ?? false,
            'canRestore'         => $actor?->can('page.restore') ?? false,
            'canPermanentDelete' => $actor?->can('content.permanent_delete') ?? false,
        ]);
    }

    /**
     * GET /admin/pages/new
     */
    public function create(): string
    {
        $item = $this->emptyFormData();

        return view('admin/pages/form', $this->formViewData(
            mode: 'create',
            item: $item,
            errors: [],
            formAction: site_url('admin/pages'),
            parentExcludeId: null,
        ));
    }

    /**
     * POST /admin/pages
     */
    public function store(): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->pageService()->create($dto, $this->actor());
        if ($errors !== []) {
            return view('admin/pages/form', $this->formViewData(
                mode: 'create',
                item: $this->formDataFromDto($dto),
                errors: $errors,
                formAction: site_url('admin/pages'),
                parentExcludeId: null,
            ));
        }

        return redirect()->to('/admin/pages')->with('success', 'Page created.');
    }

    /**
     * GET /admin/pages/{id}/edit
     */
    public function edit(int $id): ResponseInterface|RedirectResponse|string
    {
        $editable = $this->pageService()->findEditable($id);
        if ($editable === null) {
            return redirect()->to('/admin/pages')->with('error', 'Page not found.');
        }

        $page        = $editable['page'];
        $translation = $editable['translation'];

        return view('admin/pages/form', $this->formViewData(
            mode: 'edit',
            item: [
                'id'              => $page->id,
                'title'           => $translation->title,
                'slug'            => $translation->slug,
                'locale'          => $translation->locale,
                'template_key'    => $page->template_key,
                'parent_id'       => $page->parent_id,
                'status'          => $page->status,
                'lock_version'    => (int) $page->lock_version,
                'content_payload' => $this->pageService()->decodeContentPayload($translation->content_payload),
                'meta_title'       => $translation->meta_title ?? '',
                'meta_description' => $translation->meta_description ?? '',
                'canonical_url'    => $translation->canonical_url ?? '',
                'og_image_id'      => $translation->og_image_id ?? '',
            ],
            errors: [],
            formAction: site_url('admin/pages/' . $page->id),
            parentExcludeId: $page->id,
            success: session()->getFlashdata('success'),
            flashError: session()->getFlashdata('error'),
        ));
    }

    /**
     * POST /admin/pages/{id}
     */
    public function update(int $id): ResponseInterface|RedirectResponse|string
    {
        $dto    = $this->dtoFromRequest();
        $errors = $this->pageService()->update(
            $id,
            $dto,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );
        if (isset($errors['_not_found'])) {
            return redirect()->to('/admin/pages')->with('error', $errors['_not_found']);
        }
        if (isset($errors['_forbidden'])) {
            return redirect()->to('/admin/pages')->with('error', $errors['_forbidden']);
        }

        if ($this->isOccConflict($errors)) {
            return $this->respondOccConflict(
                $errors,
                'admin/pages/form',
                $this->formViewData(
                    mode: 'edit',
                    item: array_merge($this->formDataFromDto($dto), [
                        'id'           => $id,
                        'lock_version' => (int) ($errors['lock_version'] ?? 0),
                    ]),
                    errors: [],
                    formAction: site_url('admin/pages/' . $id),
                    parentExcludeId: $id,
                ),
            );
        }

        if ($errors !== []) {
            return view('admin/pages/form', $this->formViewData(
                mode: 'edit',
                item: array_merge($this->formDataFromDto($dto), ['id' => $id]),
                errors: $errors,
                formAction: site_url('admin/pages/' . $id),
                parentExcludeId: $id,
            ));
        }

        return redirect()->to('/admin/pages')->with('success', 'Page updated.');
    }

    public function publish(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->pageService()->publish(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Page published.');
    }

    public function unpublish(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->pageService()->unpublish(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Page unpublished.');
    }

    public function archive(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->pageService()->archive(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondLifecycleResult($id, $errors, 'Page archived.');
    }

    /**
     * GET /admin/pages/{id}/revisions
     */
    public function revisions(int $id): ResponseInterface|RedirectResponse|string
    {
        $editable = $this->pageService()->findEditable($id);
        if ($editable === null) {
            return redirect()->to('/admin/pages')->with('error', 'Page not found.');
        }

        $page        = $editable['page'];
        $translation = $editable['translation'];
        $actor       = $this->actor();

        return view('admin/pages/revisions', [
            'pageId'      => (int) $page->id,
            'pageTitle'   => (string) $translation->title,
            'revisions'   => $this->revisionService()->listEditorialHistory(
                RevisionResourceType::Page,
                (int) $page->id,
            ),
            'canRestore'  => $actor?->can('page.restore') ?? false,
            'lockVersion' => (int) $page->lock_version,
            'success'     => session()->getFlashdata('success'),
            'flashError'  => session()->getFlashdata('error'),
        ]);
    }

    /**
     * POST /admin/pages/{id}/revisions/{revision}/restore
     */
    public function restoreRevision(int $id, int $revisionId): ResponseInterface|RedirectResponse
    {
        $errors = $this->pageService()->restoreRevision(
            $id,
            $revisionId,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        if (isset($errors['_not_found']) || isset($errors['_forbidden'])) {
            return redirect()->to('/admin/pages')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
        }

        if ($this->isOccConflict($errors)) {
            $message = (string) ($errors['_conflict'] ?? 'Conflict');
            $version = (string) ($errors['lock_version'] ?? '');

            return $this->response
                ->setStatusCode(409)
                ->setBody($message . ($version !== '' ? ' (current version: ' . $version . ')' : ''));
        }

        if ($errors !== []) {
            return redirect()->to('/admin/pages/' . $id . '/revisions')
                ->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/pages/' . $id . '/edit')
            ->with('success', 'Page revision restored.');
    }

    /**
     * POST /admin/pages/{id}/autosave — HTMX draft snapshot (ADR-019 / Task 4.9D).
     * Does not mutate live Page rows or bump lock_version.
     */
    public function autosave(int $id): ResponseInterface
    {
        $page = $this->pageService()->findById($id);
        if ($page === null) {
            return $this->respondAutosaveResult(
                ['_not_found' => 'Page not found.'],
                RevisionResourceType::Page,
                $id,
                0,
            );
        }

        $lockVersion = (int) $page->lock_version;
        $errors      = $this->pageService()->autosave(
            $id,
            $this->dtoFromRequest(),
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        // Re-read lock_version after service call (must be unchanged on success).
        $fresh = $this->pageService()->findById($id);
        if ($fresh !== null) {
            $lockVersion = (int) $fresh->lock_version;
        }

        return $this->respondAutosaveResult(
            $errors,
            RevisionResourceType::Page,
            $id,
            $lockVersion,
        );
    }

    /**
     * POST /admin/pages/{id}/delete — soft trash (REQ-PAGE-012).
     */
    public function delete(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->pageService()->trash(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondTrashLifecycle(
            $errors,
            'Page moved to Trash.',
            '/admin/pages',
        );
    }

    /**
     * POST /admin/pages/{id}/restore — TRASH → DRAFT.
     */
    public function restore(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->pageService()->restoreFromTrash(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        if ($errors === []) {
            return redirect()->to('/admin/pages/' . $id . '/edit')->with('success', 'Page restored.');
        }

        return $this->respondTrashLifecycle(
            $errors,
            'Page restored.',
            '/admin/pages?status=TRASH',
        );
    }

    /**
     * POST /admin/pages/{id}/permanent-delete — TRASH only.
     */
    public function permanentDelete(int $id): ResponseInterface|RedirectResponse
    {
        $errors = $this->pageService()->permanentlyDelete(
            $id,
            $this->actor(),
            $this->expectedLockVersionFromRequest(),
        );

        return $this->respondTrashLifecycle(
            $errors,
            'Page permanently deleted.',
            '/admin/pages?status=TRASH',
        );
    }

    /**
     * POST /admin/pages/{id}/schedules
     */
    public function storeSchedule(int $id): RedirectResponse
    {
        $actor = $this->actor();
        if ($actor === null) {
            return redirect()->route('login');
        }

        $dto = new CreateScheduledActionDto(
            targetType: 'page',
            targetId: $id,
            actionType: (string) $this->request->getPost('action_type'),
            executeAtLocal: (string) $this->request->getPost('execute_at'),
        );

        $errors = $this->scheduledContentService()->create($dto, $actor);
        if ($errors !== []) {
            return redirect()->to('/admin/pages/' . $id . '/edit')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/pages/' . $id . '/edit')->with('success', 'Schedule created.');
    }

    /**
     * POST /admin/pages/{id}/schedules/{scheduleId}/cancel
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
            'page',
            $id,
        );
        if ($errors !== []) {
            return redirect()->to('/admin/pages/' . $id . '/edit')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/pages/' . $id . '/edit')->with('success', 'Schedule cancelled.');
    }

    private function pageService(): PageService
    {
        return service('pageService');
    }

    private function revisionService(): RevisionService
    {
        return service('revisionService');
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
            return redirect()->to('/admin/pages')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
        }

        if ($this->isOccConflict($errors)) {
            $message = (string) ($errors['_conflict'] ?? 'Conflict');
            $version = (string) ($errors['lock_version'] ?? '');

            return $this->response
                ->setStatusCode(409)
                ->setBody($message . ($version !== '' ? ' (current version: ' . $version . ')' : ''));
        }

        if ($errors !== []) {
            return redirect()->to('/admin/pages/' . $id . '/edit')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/pages/' . $id . '/edit')->with('success', $successMessage);
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
            return redirect()->to('/admin/pages')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
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
     * @param array{
     *     id?: int,
     *     title: string,
     *     slug: string,
     *     locale: string,
     *     template_key: string,
     *     parent_id: int|null,
     *     status?: string,
     *     lock_version?: int,
     *     content_payload: array<string, mixed>
     * } $item
     * @param array<string, string> $errors
     *
     * @return array<string, mixed>
     */
    private function formViewData(
        string $mode,
        array $item,
        array $errors,
        string $formAction,
        ?int $parentExcludeId,
        mixed $success = null,
        mixed $flashError = null,
    ): array {
        $templateKey = (string) ($item['template_key'] ?? 'custom-page');
        $schema      = $this->pageService()->contentSchemaForTemplate($templateKey);
        $payload     = $item['content_payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $status = (string) ($item['status'] ?? '');
        $actor  = $this->actor();
        $pageId = isset($item['id']) ? (int) $item['id'] : 0;
        $scheduler = $this->scheduledContentService();

        return [
            'mode'           => $mode,
            'item'           => $item,
            'parents'        => $this->pageService()->listValidParents($parentExcludeId),
            'locales'        => ['id', 'en'],
            'errors'         => $errors,
            'formAction'     => $formAction,
            'contentSchema'  => $schema,
            'contentPayload' => $this->applySchemaDefaults($schema, $payload),
            'success'        => is_string($success) ? $success : null,
            'flashError'     => is_string($flashError) ? $flashError : null,
            'canPublish'     => ($actor?->can('page.publish') ?? false)
                && in_array($status, ['DRAFT', 'UNPUBLISHED', 'ARCHIVED'], true),
            'canUnpublish'   => ($actor?->can('page.unpublish') ?? false)
                && $status === 'PUBLISHED',
            'canArchive'     => ($actor?->can('page.archive') ?? false)
                && $status === 'PUBLISHED',
            'canViewRevisions' => $mode === 'edit'
                && ($actor?->can('page.edit') ?? false)
                && ! empty($item['id']),
            'canSchedulePublish'   => $mode === 'edit' && ($actor?->can('page.publish') ?? false),
            'canScheduleUnpublish' => $mode === 'edit' && ($actor?->can('page.unpublish') ?? false),
            'scheduledActions'     => $mode === 'edit' && $pageId > 0
                ? $scheduler->listForTarget('page', $pageId)
                : [],
            'siteTimezone'         => $scheduler->siteTimezone(),
            'scheduleCreateUrl'    => $pageId > 0 ? site_url('admin/pages/' . $pageId . '/schedules') : '',
            'scheduleCancelBase'   => $pageId > 0 ? site_url('admin/pages/' . $pageId . '/schedules') : '',
        ];
    }

    /**
     * Apply documented field defaults for missing create values.
     *
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

    private function dtoFromRequest(): PageWriteDto
    {
        $parentRaw = $this->request->getPost('parent_id');
        $parentId  = null;
        if ($parentRaw !== null && $parentRaw !== '' && is_numeric($parentRaw)) {
            $parentId = (int) $parentRaw;
            if ($parentId < 1) {
                $parentId = null;
            }
        }

        $templateKey = (string) ($this->request->getPost('template_key') ?? 'custom-page');
        $schema      = $this->pageService()->contentSchemaForTemplate($templateKey);

        return new PageWriteDto(
            title: (string) ($this->request->getPost('title') ?? ''),
            slug: (string) ($this->request->getPost('slug') ?? ''),
            locale: (string) ($this->request->getPost('locale') ?? ''),
            templateKey: $templateKey,
            parentId: $parentId,
            contentPayload: $this->contentPayloadFromRequest($schema),
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
     * Map POST `content[...]` into a content_payload array shaped for the schema.
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
                'REPEATABLE' => $this->normalizeRepeatableInput($value, $definition),
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
     * @return int|string|null Positive media id, raw invalid value for validator rejection, or null when empty.
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

        // Non-empty invalid input is retained so ContentSchemaValidator can reject it.
        if (is_string($value) || is_int($value) || is_float($value)) {
            return is_string($value) ? $value : (string) $value;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return list<array<string, mixed>>|null
     */
    private function normalizeRepeatableInput(mixed $value, array $definition): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $childFields = isset($definition['fields']) && is_array($definition['fields'])
            ? $definition['fields']
            : [];

        $items = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }

            $item    = [];
            $hasData = false;
            foreach ($childFields as $childKey => $childDef) {
                if (! is_string($childKey) || ! is_array($childDef)) {
                    continue;
                }
                if (! array_key_exists($childKey, $row)) {
                    continue;
                }

                $childType = isset($childDef['type']) && is_string($childDef['type'])
                    ? $childDef['type']
                    : '';
                $rawChild = $row[$childKey];

                if ($childType === 'IMAGE' || $childType === 'DOCUMENT') {
                    $normalizedChild = $this->normalizeMediaIdInput($rawChild);
                } else {
                    $normalizedChild = $this->normalizeScalarInput($rawChild);
                }

                if ($normalizedChild === null) {
                    continue;
                }

                $item[$childKey] = $normalizedChild;
                $hasData         = true;
            }

            if ($hasData) {
                $items[] = $item;
            }
        }

        return $items === [] ? null : $items;
    }

    /**
     * @return array{
     *     title: string,
     *     slug: string,
     *     locale: string,
     *     template_key: string,
     *     parent_id: int|null,
     *     content_payload: array<string, mixed>
     * }
     */
    private function emptyFormData(): array
    {
        /** @var Site $site */
        $site = config('Site');

        return [
            'title'           => '',
            'slug'            => '',
            'locale'          => $site->defaultLocale !== '' ? $site->defaultLocale : 'id',
            'template_key'    => 'custom-page',
            'parent_id'       => null,
            'content_payload' => [],
        ];
    }

    /**
     * @return array{
     *     title: string,
     *     slug: string,
     *     locale: string,
     *     template_key: string,
     *     parent_id: int|null,
     *     content_payload: array<string, mixed>
     * }
     */
    private function formDataFromDto(PageWriteDto $dto): array
    {
        return [
            'title'            => $dto->title,
            'slug'             => $dto->slug,
            'locale'           => $dto->locale,
            'template_key'     => $dto->templateKey,
            'parent_id'        => $dto->parentId,
            'content_payload'  => $dto->contentPayload,
            'meta_title'       => $dto->metaTitle ?? '',
            'meta_description' => $dto->metaDescription ?? '',
            'canonical_url'    => $dto->canonicalUrl ?? '',
            'og_image_id'      => $dto->ogImageId ?? '',
        ];
    }
}
