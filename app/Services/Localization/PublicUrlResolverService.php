<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\UrlRedirectModel;
use App\Services\PageService;
use App\Services\PostService;

/**
 * Central public URL resolution: redirect → Page/Post (DOC-08 §51 / ADR-024).
 */
final class PublicUrlResolverService
{
    public function __construct(
        private readonly UrlRedirectService $urlRedirectService,
        private readonly UrlRedirectModel $urlRedirectModel,
        private readonly PageService $pageService,
        private readonly PostService $postService,
        private readonly PublicUrlBuilder $publicUrlBuilder,
    ) {
    }

    /**
     * Resolve an active redirect for a normalized request path.
     */
    public function resolveRedirect(string $requestPath): ?string
    {
        $path = $this->publicUrlBuilder->normalizePath($requestPath);

        return $this->urlRedirectService->findActiveTarget($path);
    }

    /**
     * Check whether a path is reserved by an active redirect source.
     */
    public function isRedirectSourceReserved(string $path): bool
    {
        $normalized = $this->publicUrlBuilder->normalizePath($path);

        return $this->urlRedirectModel->isActiveSourceReserved($normalized);
    }
}
