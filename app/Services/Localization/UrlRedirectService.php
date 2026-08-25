<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\UrlRedirectModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Historical redirect persistence with chain flattening (ADR-003 / ADR-024 §9).
 */
final class UrlRedirectService
{
    public function __construct(
        private readonly UrlRedirectModel $urlRedirectModel,
        private readonly PublicUrlBuilder $publicUrlBuilder,
        private readonly BaseConnection $db,
    ) {
    }

    public function findActiveTarget(string $sourcePath): ?string
    {
        $normalized = $this->publicUrlBuilder->normalizePath($sourcePath);
        $redirect   = $this->urlRedirectModel->findActiveBySourcePath($normalized);
        if ($redirect === null) {
            return null;
        }

        return $this->publicUrlBuilder->normalizePath((string) $redirect->target_path);
    }

    /**
     * Record a published slug change atomically within the caller's transaction.
     */
    public function recordPublishedSlugChange(
        string $oldSourcePath,
        string $newTargetPath,
        string $resourceType,
        int $resourceId,
        string $locale,
    ): void {
        $sourcePath = $this->publicUrlBuilder->normalizePath($oldSourcePath);
        $targetPath = $this->flattenTarget(
            $this->publicUrlBuilder->normalizePath($newTargetPath),
        );

        if ($sourcePath === $targetPath) {
            return;
        }

        foreach ($this->urlRedirectModel->findActiveByTargetPath($sourcePath) as $existing) {
            $this->urlRedirectModel->update($existing->id, [
                'target_path' => $targetPath,
            ]);
        }

        $current = $this->urlRedirectModel->findActiveBySourcePath($sourcePath);
        if ($current !== null) {
            $this->urlRedirectModel->update($current->id, [
                'target_path'  => $targetPath,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'locale'        => $locale,
                'http_code'     => 301,
                'active'        => 1,
            ]);

            return;
        }

        $this->urlRedirectModel->insert([
            'source_path'   => $sourcePath,
            'target_path'   => $targetPath,
            'http_code'     => 301,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId,
            'locale'        => $locale,
            'active'        => 1,
        ]);
    }

    private function flattenTarget(string $targetPath): string
    {
        $seen = [];
        $current = $targetPath;

        while (true) {
            if (isset($seen[$current])) {
                break;
            }
            $seen[$current] = true;

            $redirect = $this->urlRedirectModel->findActiveBySourcePath($current);
            if ($redirect === null) {
                break;
            }

            $current = $this->publicUrlBuilder->normalizePath((string) $redirect->target_path);
        }

        return $current;
    }
}
