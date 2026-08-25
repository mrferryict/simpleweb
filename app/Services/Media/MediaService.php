<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Dtos\MediaMetadataUpdateDto;
use App\Dtos\MediaUploadDto;
use App\Entities\MediaAsset;
use App\Enums\MediaStatus;
use App\Enums\MediaType;
use App\Models\MediaAssetModel;
use App\Services\Theme\ThemeService;
use CodeIgniter\Shield\Entities\User;
use RuntimeException;

/**
 * Media Library foundation (Phase 4 / Task 4.5 / ADR-007 / ADR-018).
 */
class MediaService
{
    private const IMAGE_MAX_BYTES    = 5 * 1024 * 1024;
    private const DOCUMENT_MAX_BYTES = 15 * 1024 * 1024;

    /** @var array<string, string> extension => mime */
    private const IMAGE_ALLOWLIST = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
        'gif'  => 'image/gif',
    ];

    /** @var array<string, list<string>> extension => accepted MIMEs */
    private const DOCUMENT_ALLOWLIST = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    ];

    /** @var list<string> */
    private const REJECTED_EXTENSIONS = [
        'svg', 'svgz', 'php', 'phtml', 'phar', 'exe', 'sh', 'bash', 'js', 'html', 'htm', 'cgi', 'pl',
    ];

    public function __construct(
        private readonly MediaAssetModel $model,
        private readonly MediaDependencyChecker $dependencyChecker,
        private readonly ThemeService $themeService,
    ) {
    }

    /**
     * ContentSchemaValidator media resolver (ADR-004 / ADR-018).
     */
    public function isValidReference(int $mediaId, string $expectedKind): bool
    {
        $asset = $this->findById($mediaId);
        if ($asset === null) {
            return false;
        }

        if ($asset->status !== MediaStatus::Active->value) {
            return false;
        }

        $kind = strtoupper(trim($expectedKind));

        return $asset->type === $kind;
    }

    public function findById(int $id): ?MediaAsset
    {
        if ($id < 1) {
            return null;
        }

        /** @var MediaAsset|null $asset */
        $asset = $this->model->find($id);

        return $asset instanceof MediaAsset ? $asset : null;
    }

    /**
     * @return list<MediaAsset>
     */
    public function listByStatus(string $status): array
    {
        $status = strtoupper(trim($status));
        if ($status !== MediaStatus::Active->value && $status !== MediaStatus::Trash->value) {
            return [];
        }

        /** @var list<MediaAsset> $rows */
        $rows = $this->model
            ->where('status', $status)
            ->orderBy('id', 'DESC')
            ->findAll();

        return $rows;
    }

    /**
     * ACTIVE assets of one type for the Control Panel Media Picker (Task 4.6).
     *
     * @return list<MediaAsset>
     */
    public function listActiveForPicker(string $type): array
    {
        $type = strtoupper(trim($type));
        if ($type !== MediaType::Image->value && $type !== MediaType::Document->value) {
            return [];
        }

        /** @var list<MediaAsset> $rows */
        $rows = $this->model
            ->where('status', MediaStatus::Active->value)
            ->where('type', $type)
            ->orderBy('id', 'DESC')
            ->findAll(100);

        return $rows;
    }

    /**
     * Display metadata for a picker field (may include TRASH for existing payloads).
     *
     * @return array{
     *     id: int,
     *     label: string,
     *     mime: string,
     *     size: int,
     *     type: string,
     *     status: string,
     *     previewUrl: string|null,
     *     downloadUrl: string|null
     * }|null
     */
    public function pickerDisplay(?int $mediaId): ?array
    {
        if ($mediaId === null || $mediaId < 1) {
            return null;
        }

        $asset = $this->findById($mediaId);
        if ($asset === null) {
            return null;
        }

        $label = trim((string) ($asset->title ?? ''));
        if ($label === '') {
            $label = (string) $asset->original_filename;
        }

        $previewUrl  = null;
        $downloadUrl = null;
        if ($asset->status === MediaStatus::Active->value) {
            if ($asset->type === MediaType::Image->value) {
                $previewUrl = $this->publicImageUrl((int) $asset->id);
            }
            if ($asset->type === MediaType::Document->value
                && is_string($asset->download_token)
                && $asset->download_token !== ''
            ) {
                $downloadUrl = '/download/document/' . $asset->download_token;
            }
        }

        return [
            'id'          => (int) $asset->id,
            'label'       => $label,
            'mime'        => (string) $asset->mime_type,
            'size'        => (int) $asset->file_size,
            'type'        => (string) $asset->type,
            'status'      => (string) $asset->status,
            'previewUrl'  => $previewUrl,
            'downloadUrl' => $downloadUrl,
        ];
    }

    /**
     * @return array{errors: array<string, string>, asset: MediaAsset|null}
     */
    #[\NoDiscard]
    public function upload(MediaUploadDto $dto, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('media.upload')) {
            return ['errors' => ['_forbidden' => 'You are not allowed to upload Media.'], 'asset' => null];
        }

        if (! is_file($dto->tmpPath) || ! is_readable($dto->tmpPath)) {
            return ['errors' => ['file' => 'The uploaded file could not be read.'], 'asset' => null];
        }

        $original = $this->sanitizeOriginalFilename($dto->originalFilename);
        $ext      = $this->extensionOf($original);

        if ($ext === '' || in_array($ext, self::REJECTED_EXTENSIONS, true)) {
            return ['errors' => ['file' => 'This file type is not allowed.'], 'asset' => null];
        }

        if (isset(self::IMAGE_ALLOWLIST[$ext])) {
            return $this->uploadImage($dto, $original, $ext, $actor);
        }

        if (isset(self::DOCUMENT_ALLOWLIST[$ext])) {
            return $this->uploadDocument($dto, $original, $ext, $actor);
        }

        return ['errors' => ['file' => 'This file type is not allowed.'], 'asset' => null];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function updateMetadata(int $id, MediaMetadataUpdateDto $dto, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('media.edit')) {
            return ['_forbidden' => 'You are not allowed to edit Media.'];
        }

        $asset = $this->findById($id);
        if ($asset === null || $asset->status === MediaStatus::Trash->value) {
            return ['_not_found' => 'Media not found.'];
        }

        $title = $dto->title !== null ? trim($dto->title) : null;
        $desc  = $dto->description !== null ? trim($dto->description) : null;
        $alt   = $dto->alt !== null ? trim($dto->alt) : null;

        if ($title !== null && mb_strlen($title) > 200) {
            return ['title' => 'Title may not exceed 200 characters.'];
        }
        if ($alt !== null && mb_strlen($alt) > 255) {
            return ['alt' => 'Alt text may not exceed 255 characters.'];
        }

        $this->model->update($id, [
            'title'       => $title === '' ? null : $title,
            'description' => $desc === '' ? null : $desc,
            'alt'         => $alt === '' ? null : $alt,
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function trash(int $id, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('media.delete')) {
            return ['_forbidden' => 'You are not allowed to trash Media.'];
        }

        $asset = $this->findById($id);
        if ($asset === null || $asset->status === MediaStatus::Trash->value) {
            return ['_not_found' => 'Media not found.'];
        }

        $this->model->update($id, [
            'status'     => MediaStatus::Trash->value,
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function restore(int $id, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('media.restore')) {
            return ['_forbidden' => 'You are not allowed to restore Media.'];
        }

        $asset = $this->findById($id);
        if ($asset === null || $asset->status !== MediaStatus::Trash->value) {
            return ['_not_found' => 'Media not found in Trash.'];
        }

        $this->model->update($id, [
            'status'     => MediaStatus::Active->value,
            'deleted_at' => null,
        ]);

        return [];
    }

    /**
     * Permanent delete — Admin only (AUTHZ-004 / DOC-06).
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function permanentlyDelete(int $id, ?User $actor = null): array
    {
        if ($actor !== null && ! $actor->can('content.permanent_delete')) {
            return ['_forbidden' => 'Only Admin may permanently delete Media.'];
        }

        $asset = $this->findById($id);
        if ($asset === null) {
            return ['_not_found' => 'Media not found.'];
        }

        $deps = $this->dependencyChecker->findDependencies($id);
        if ($deps !== []) {
            return [
                '_dependency' => 'This Media is still referenced and cannot be permanently deleted: '
                    . implode('; ', array_slice($deps, 0, 5)),
            ];
        }

        $path = $this->absolutePathFor($asset);
        $this->model->delete($id, true);

        if (is_file($path)) {
            @unlink($path);
        }

        return [];
    }

    /**
     * Public image URL for ACTIVE IMAGE only (ADR-018).
     */
    public function publicImageUrl(int $mediaId): ?string
    {
        $asset = $this->findById($mediaId);
        if ($asset === null
            || $asset->type !== MediaType::Image->value
            || $asset->status !== MediaStatus::Active->value
        ) {
            return null;
        }

        $key = (string) $asset->storage_key;
        if ($key === '' || str_contains($key, '/') || str_contains($key, '\\') || str_contains($key, '..')) {
            return null;
        }

        return '/uploads/images/' . $key;
    }

    /**
     * Controlled public download URL for ACTIVE DOCUMENT only (ADR-007 / ADR-018).
     */
    public function publicDocumentUrl(int $mediaId): ?string
    {
        $asset = $this->findById($mediaId);
        if ($asset === null
            || $asset->type !== MediaType::Document->value
            || $asset->status !== MediaStatus::Active->value
        ) {
            return null;
        }

        $token = is_string($asset->download_token) ? strtolower(trim($asset->download_token)) : '';
        if ($token === '' || ! preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        return '/download/document/' . $token;
    }

    /**
     * Resolve one content-field media_id for public Theme presentation (ACTIVE + type only).
     *
     * Does not mutate content payloads. Never returns filesystem paths or storage_key.
     *
     * @return array{media_id: int, url: string, label: string, type: string}|null
     */
    public function resolvePublicReference(int $mediaId, string $expectedKind): ?array
    {
        $kind = strtoupper(trim($expectedKind));
        if ($kind !== MediaType::Image->value && $kind !== MediaType::Document->value) {
            return null;
        }

        if (! $this->isValidReference($mediaId, $kind)) {
            return null;
        }

        $url = $kind === MediaType::Image->value
            ? $this->publicImageUrl($mediaId)
            : $this->publicDocumentUrl($mediaId);

        if ($url === null) {
            return null;
        }

        $asset = $this->findById($mediaId);
        if ($asset === null) {
            return null;
        }

        $label = trim((string) ($asset->title ?? ''));
        if ($label === '') {
            $label = (string) $asset->original_filename;
        }

        return [
            'media_id' => $mediaId,
            'url'      => $url,
            'label'    => $label,
            'type'     => $kind,
        ];
    }

    /**
     * Build a parallel presentation map for IMAGE/DOCUMENT schema fields (Task 4.7).
     *
     * Leaves the persisted content payload untouched. Unresolved references are null.
     * REPEATABLE children are walked only when nested IMAGE/DOCUMENT fields exist.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function resolveContentMediaForSchema(array $payload, array $schema): array
    {
        $out = [];

        foreach ($schema as $fieldKey => $fieldDef) {
            if (! is_string($fieldKey) || ! is_array($fieldDef)) {
                continue;
            }

            $type = strtoupper(trim((string) ($fieldDef['type'] ?? '')));

            if ($type === MediaType::Image->value || $type === MediaType::Document->value) {
                $mediaId = $this->extractMediaId($payload[$fieldKey] ?? null);
                $out[$fieldKey] = $mediaId !== null
                    ? $this->resolvePublicReference($mediaId, $type)
                    : null;
                continue;
            }

            if ($type !== 'REPEATABLE') {
                continue;
            }

            $childFields = $fieldDef['fields'] ?? null;
            if (! is_array($childFields) || ! $this->schemaHasMediaFields($childFields)) {
                continue;
            }

            $items = $payload[$fieldKey] ?? null;
            $resolvedItems = [];
            if (is_array($items)) {
                foreach ($items as $index => $item) {
                    $resolvedItems[$index] = is_array($item)
                        ? $this->resolveContentMediaForSchema($item, $childFields)
                        : [];
                }
            }
            $out[$fieldKey] = $resolvedItems;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $schema
     */
    private function schemaHasMediaFields(array $schema): bool
    {
        foreach ($schema as $fieldDef) {
            if (! is_array($fieldDef)) {
                continue;
            }
            $type = strtoupper(trim((string) ($fieldDef['type'] ?? '')));
            if ($type === MediaType::Image->value || $type === MediaType::Document->value) {
                return true;
            }
            if ($type === 'REPEATABLE') {
                $child = $fieldDef['fields'] ?? null;
                if (is_array($child) && $this->schemaHasMediaFields($child)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function extractMediaId(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        if (is_array($value) && array_key_exists('media_id', $value)) {
            return $this->extractMediaId($value['media_id']);
        }

        return null;
    }

    /**
     * Resolve document download for public streaming (ADR-007).
     *
     * @return array{path: string, filename: string, mime: string}|null
     */
    public function resolveDocumentDownload(string $token): ?array
    {
        $token = strtolower(trim($token));
        if ($token === '' || ! preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }

        $asset = $this->model->findByDownloadToken($token);
        if ($asset === null
            || $asset->type !== MediaType::Document->value
            || $asset->status !== MediaStatus::Active->value
        ) {
            return null;
        }

        $path = $this->absolutePathFor($asset);
        if (! is_file($path)) {
            return null;
        }

        return [
            'path'     => $path,
            'filename' => (string) $asset->original_filename,
            'mime'     => (string) $asset->mime_type,
        ];
    }

    public function absolutePathFor(MediaAsset $asset): string
    {
        $key = (string) $asset->storage_key;
        if ($key === '' || str_contains($key, '/') || str_contains($key, '\\') || str_contains($key, '..')) {
            throw new RuntimeException('Invalid media storage key.');
        }

        if ($asset->type === MediaType::Image->value) {
            return rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads'
                . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $key;
        }

        return rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $key;
    }

    /**
     * @return array{errors: array<string, string>, asset: MediaAsset|null}
     */
    private function uploadImage(MediaUploadDto $dto, string $original, string $ext, ?User $actor): array
    {
        $profile = $this->themeService->resolveImageProfile(null);
        $maxBytes = min(self::IMAGE_MAX_BYTES, $profile['maximum_file_size']);

        if ($dto->sizeBytes > $maxBytes || $dto->sizeBytes < 1) {
            return ['errors' => ['file' => 'Image must be between 1 byte and 5 MiB.'], 'asset' => null];
        }

        if (! in_array($ext, $profile['allowed_formats'], true)
            || ! array_key_exists($ext, self::IMAGE_ALLOWLIST)
        ) {
            return ['errors' => ['file' => 'This image format is not allowed for the active Image Profile.'], 'asset' => null];
        }

        $detectedMime = $this->detectMime($dto->tmpPath);
        $expectedMime = self::IMAGE_ALLOWLIST[$ext];
        if ($detectedMime !== $expectedMime) {
            return ['errors' => ['file' => 'Image MIME type does not match the file extension.'], 'asset' => null];
        }

        $info = @getimagesize($dto->tmpPath);
        if ($info === false || ! isset($info[0], $info[1], $info[2])) {
            return ['errors' => ['file' => 'The file is not a valid image.'], 'asset' => null];
        }

        $width  = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1) {
            return ['errors' => ['file' => 'The image dimensions are invalid.'], 'asset' => null];
        }

        if ($profile['minimum_width'] !== null && $width < $profile['minimum_width']) {
            return ['errors' => ['file' => 'The image width is below the minimum required by the Image Profile.'], 'asset' => null];
        }
        if ($profile['minimum_height'] !== null && $height < $profile['minimum_height']) {
            return ['errors' => ['file' => 'The image height is below the minimum required by the Image Profile.'], 'asset' => null];
        }

        $this->ensureDirectories();

        $tmpMaster = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8));

        try {
            $processed = $this->processImageMaster(
                $dto->tmpPath,
                $tmpMaster,
                $width,
                $height,
                $profile['maximum_width'],
                $profile['maximum_height'],
            );
        } catch (RuntimeException $e) {
            $this->cleanupFile($tmpMaster);

            return ['errors' => ['file' => $e->getMessage()], 'asset' => null];
        }

        $storageKey = bin2hex(random_bytes(16)) . '.' . $processed['extension'];
        $dest       = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . $storageKey;

        if (! @rename($processed['path'], $dest) && ! @copy($processed['path'], $dest)) {
            $this->cleanupFile($processed['path']);

            return ['errors' => ['file' => 'Failed to store the processed image.'], 'asset' => null];
        }
        $this->cleanupFile($processed['path']);

        $insertId = $this->model->insert([
            'type'              => MediaType::Image->value,
            'title'             => $this->nullableTrim($dto->title, 200),
            'description'       => $this->nullableTrim($dto->description, null),
            'alt'               => $this->nullableTrim($dto->alt, 255),
            'original_filename' => $original,
            'storage_key'       => $storageKey,
            'mime_type'         => $processed['mime'],
            'extension'         => $processed['extension'],
            'file_size'         => (int) filesize($dest),
            'width'             => $processed['width'],
            'height'            => $processed['height'],
            'download_token'    => null,
            'status'            => MediaStatus::Active->value,
            'uploaded_by'       => $actor?->id !== null ? (int) $actor->id : null,
            'deleted_at'        => null,
        ], true);

        if (! is_numeric($insertId)) {
            @unlink($dest);

            return ['errors' => ['file' => 'Failed to persist the Media record.'], 'asset' => null];
        }

        $asset = $this->findById((int) $insertId);

        return ['errors' => [], 'asset' => $asset];
    }

    /**
     * @return array{errors: array<string, string>, asset: MediaAsset|null}
     */
    private function uploadDocument(MediaUploadDto $dto, string $original, string $ext, ?User $actor): array
    {
        if ($dto->sizeBytes > self::DOCUMENT_MAX_BYTES || $dto->sizeBytes < 1) {
            return ['errors' => ['file' => 'Document must be between 1 byte and 15 MiB.'], 'asset' => null];
        }

        $detectedMime = $this->detectMime($dto->tmpPath);
        $allowedMimes = self::DOCUMENT_ALLOWLIST[$ext];
        if (! in_array($detectedMime, $allowedMimes, true)) {
            // Some environments report OOXML as application/zip — accept only when signature matches.
            if (! $this->documentSignatureMatches($dto->tmpPath, $ext, $detectedMime)) {
                return ['errors' => ['file' => 'Document MIME type does not match the file extension.'], 'asset' => null];
            }
            $detectedMime = $allowedMimes[0];
        } elseif (! $this->documentSignatureMatches($dto->tmpPath, $ext, $detectedMime)) {
            return ['errors' => ['file' => 'The document file signature is invalid.'], 'asset' => null];
        }

        $this->ensureDirectories();

        $token      = bin2hex(random_bytes(16));
        $storageKey = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest       = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads'
            . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $storageKey;

        if (! @copy($dto->tmpPath, $dest)) {
            return ['errors' => ['file' => 'Failed to store the document.'], 'asset' => null];
        }

        $insertId = $this->model->insert([
            'type'              => MediaType::Document->value,
            'title'             => $this->nullableTrim($dto->title, 200),
            'description'       => $this->nullableTrim($dto->description, null),
            'alt'               => null,
            'original_filename' => $original,
            'storage_key'       => $storageKey,
            'mime_type'         => $detectedMime,
            'extension'         => $ext,
            'file_size'         => (int) filesize($dest),
            'width'             => null,
            'height'            => null,
            'download_token'    => $token,
            'status'            => MediaStatus::Active->value,
            'uploaded_by'       => $actor?->id !== null ? (int) $actor->id : null,
            'deleted_at'        => null,
        ], true);

        if (! is_numeric($insertId)) {
            @unlink($dest);

            return ['errors' => ['file' => 'Failed to persist the Media record.'], 'asset' => null];
        }

        return ['errors' => [], 'asset' => $this->findById((int) $insertId)];
    }

    /**
     * @return array{path: string, mime: string, extension: string, width: int, height: int}
     */
    private function processImageMaster(
        string $source,
        string $tmpBase,
        int $width,
        int $height,
        int $maxWidth,
        int $maxHeight,
    ): array {
        $targetW = $width;
        $targetH = $height;
        if ($width > $maxWidth || $height > $maxHeight) {
            $scale   = min($maxWidth / $width, $maxHeight / $height);
            $targetW = max(1, (int) floor($width * $scale));
            $targetH = max(1, (int) floor($height * $scale));
        }

        $useWebp = function_exists('imagewebp');
        $ext     = $useWebp ? 'webp' : 'jpg';
        $mime    = $useWebp ? 'image/webp' : 'image/jpeg';
        $outPath = $tmpBase . '.' . $ext;

        $image = \Config\Services::image('gd', null, false);
        $image->withFile($source);
        if ($targetW !== $width || $targetH !== $height) {
            $image->resize($targetW, $targetH, true, 'width');
        }
        $image->convert($useWebp ? IMAGETYPE_WEBP : IMAGETYPE_JPEG);
        $image->save($outPath, 85);

        if (! is_file($outPath)) {
            throw new RuntimeException('Image processing failed.');
        }

        $outInfo = @getimagesize($outPath);
        $outW    = is_array($outInfo) ? (int) $outInfo[0] : $targetW;
        $outH    = is_array($outInfo) ? (int) $outInfo[1] : $targetH;

        return [
            'path'      => $outPath,
            'mime'      => $mime,
            'extension' => $ext,
            'width'     => $outW,
            'height'    => $outH,
        ];
    }

    private function documentSignatureMatches(string $path, string $ext, string $mime): bool
    {
        $fh = fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        $header = fread($fh, 8);
        fclose($fh);
        if ($header === false || $header === '') {
            return false;
        }

        return match ($ext) {
            'pdf' => str_starts_with($header, '%PDF'),
            'doc', 'xls', 'ppt' => str_starts_with($header, "\xD0\xCF\x11\xE0"),
            'docx', 'xlsx', 'pptx' => str_starts_with($header, "PK"),
            default => false,
        };
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);

        return is_string($mime) ? strtolower($mime) : '';
    }

    private function extensionOf(string $filename): string
    {
        $pos = strrpos($filename, '.');
        if ($pos === false) {
            return '';
        }

        return strtolower(substr($filename, $pos + 1));
    }

    private function sanitizeOriginalFilename(string $name): string
    {
        $name = basename(str_replace(["\0", '\\'], '', $name));
        $name = trim($name);
        if ($name === '') {
            return 'upload.bin';
        }

        return mb_substr($name, 0, 255);
    }

    private function nullableTrim(?string $value, ?int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if ($max !== null) {
            $value = mb_substr($value, 0, $max);
        }

        return $value;
    }

    private function ensureDirectories(): void
    {
        $dirs = [
            FCPATH . 'uploads/images',
            WRITEPATH . 'uploads/documents',
            WRITEPATH . 'uploads/tmp',
        ];
        foreach ($dirs as $dir) {
            if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
                throw new RuntimeException('Unable to create media storage directory.');
            }
        }

        foreach ([FCPATH . 'uploads', FCPATH . 'uploads/images'] as $pub) {
            $htaccess = rtrim($pub, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
            if (! is_file($htaccess) && str_contains($pub, 'images')) {
                // Images must remain publicly readable — no deny htaccess on images.
                continue;
            }
        }

        $deny = WRITEPATH . 'uploads/documents/.htaccess';
        if (! is_file($deny)) {
            @file_put_contents($deny, "Require all denied\nDeny from all\n");
        }
    }

    private function cleanupFile(string $path): void
    {
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
    }
}
