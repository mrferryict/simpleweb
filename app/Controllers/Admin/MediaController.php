<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Dtos\MediaMetadataUpdateDto;
use App\Dtos\MediaUploadDto;
use App\Enums\MediaStatus;
use App\Services\Media\MediaService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;

/**
 * Control Panel Media Library (Phase 4 / Task 4.5 / ADR-018).
 */
class MediaController extends BaseController
{
    public function index(): string
    {
        $status = strtoupper((string) $this->request->getGet('status'));
        if ($status !== MediaStatus::Trash->value) {
            $status = MediaStatus::Active->value;
        }

        $rows = $this->mediaService()->listByStatus($status);
        $list = [];
        foreach ($rows as $asset) {
            $list[] = [
                'asset'    => $asset,
                'imageUrl' => $this->mediaService()->publicImageUrl((int) $asset->id),
            ];
        }

        return view('admin/media/index', [
            'status'  => $status,
            'rows'    => $list,
            'success' => session()->getFlashdata('success'),
            'error'   => session()->getFlashdata('error'),
            'canPermanentDelete' => $this->actor()?->can('content.permanent_delete') ?? false,
        ]);
    }

    public function uploadForm(): string
    {
        return view('admin/media/upload', [
            'errors' => [],
            'item'   => [
                'title'       => '',
                'alt'         => '',
                'description' => '',
            ],
        ]);
    }

    public function store(): ResponseInterface|RedirectResponse|string
    {
        $file = $this->request->getFile('file');
        if (! $file instanceof UploadedFile || ! $file->isValid() || $file->hasMoved()) {
            return view('admin/media/upload', [
                'errors' => ['file' => 'A valid file upload is required.'],
                'item'   => $this->metaFromRequest(),
            ]);
        }

        $dto = new MediaUploadDto(
            tmpPath: $file->getTempName(),
            originalFilename: $file->getClientName(),
            clientMime: (string) $file->getClientMimeType(),
            sizeBytes: $file->getSize(),
            title: $this->request->getPost('title'),
            alt: $this->request->getPost('alt'),
            description: $this->request->getPost('description'),
        );

        $result = $this->mediaService()->upload($dto, $this->actor());
        if ($result['errors'] !== []) {
            return view('admin/media/upload', [
                'errors' => $result['errors'],
                'item'   => $this->metaFromRequest(),
            ]);
        }

        return redirect()->to('/admin/media')->with('success', 'Media uploaded.');
    }

    /**
     * GET fragment: ACTIVE Media list for the content-field picker (Task 4.6).
     */
    public function picker(): string
    {
        $type = strtoupper(trim((string) $this->request->getGet('type')));
        if ($type !== 'IMAGE' && $type !== 'DOCUMENT') {
            return view('admin/media/_partials/picker_list', [
                'assets'       => [],
                'mediaType'    => 'UNKNOWN',
                'mediaService' => $this->mediaService(),
            ]);
        }

        return view('admin/media/_partials/picker_list', [
            'assets'       => $this->mediaService()->listActiveForPicker($type),
            'mediaType'    => $type,
            'mediaService' => $this->mediaService(),
        ]);
    }

    public function edit(int $id): ResponseInterface|RedirectResponse|string
    {
        $asset = $this->mediaService()->findById($id);
        if ($asset === null || $asset->status === MediaStatus::Trash->value) {
            return redirect()->to('/admin/media')->with('error', 'Media not found.');
        }

        return view('admin/media/edit', [
            'asset'    => $asset,
            'imageUrl' => $this->mediaService()->publicImageUrl($id),
            'errors'   => [],
        ]);
    }

    public function update(int $id): ResponseInterface|RedirectResponse|string
    {
        $dto = new MediaMetadataUpdateDto(
            title: $this->request->getPost('title'),
            description: $this->request->getPost('description'),
            alt: $this->request->getPost('alt'),
        );

        $errors = $this->mediaService()->updateMetadata($id, $dto, $this->actor());
        if (isset($errors['_not_found']) || isset($errors['_forbidden'])) {
            return redirect()->to('/admin/media')->with('error', $errors['_not_found'] ?? $errors['_forbidden']);
        }

        if ($errors !== []) {
            $asset = $this->mediaService()->findById($id);
            if ($asset === null) {
                return redirect()->to('/admin/media')->with('error', 'Media not found.');
            }

            return view('admin/media/edit', [
                'asset'    => $asset,
                'imageUrl' => $this->mediaService()->publicImageUrl($id),
                'errors'   => $errors,
            ]);
        }

        return redirect()->to('/admin/media/' . $id . '/edit')->with('success', 'Media updated.');
    }

    public function trash(int $id): RedirectResponse
    {
        $errors = $this->mediaService()->trash($id, $this->actor());
        if ($errors !== []) {
            return redirect()->to('/admin/media')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/media')->with('success', 'Media moved to Trash.');
    }

    public function restore(int $id): RedirectResponse
    {
        $errors = $this->mediaService()->restore($id, $this->actor());
        if ($errors !== []) {
            return redirect()->to('/admin/media?status=TRASH')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/media')->with('success', 'Media restored.');
    }

    public function delete(int $id): RedirectResponse
    {
        $errors = $this->mediaService()->permanentlyDelete($id, $this->actor());
        if ($errors !== []) {
            return redirect()->to('/admin/media?status=TRASH')->with('error', implode(' ', $errors));
        }

        return redirect()->to('/admin/media?status=TRASH')->with('success', 'Media permanently deleted.');
    }

    private function mediaService(): MediaService
    {
        return service('mediaService');
    }

    private function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @return array{title: string, alt: string, description: string}
     */
    private function metaFromRequest(): array
    {
        return [
            'title'       => (string) $this->request->getPost('title'),
            'alt'         => (string) $this->request->getPost('alt'),
            'description' => (string) $this->request->getPost('description'),
        ];
    }
}
