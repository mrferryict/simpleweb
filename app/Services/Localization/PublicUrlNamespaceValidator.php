<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Models\PageTranslationModel;
use App\Models\PostTranslationModel;
use App\Models\UrlRedirectModel;
use App\Services\SettingService;

/**
 * Global public URL namespace validation (REQ-SEO-007 / ADR-024 §9).
 */
final class PublicUrlNamespaceValidator
{
    /** @var list<string> */
    public const RESERVED_PATHS = [
        'cp',
        'admin',
        'logout',
        'download',
        'uploads',
        'sitemap.xml',
        'robots.txt',
        'en',
        'id',
        'news',
    ];

    public function __construct(
        private readonly PageTranslationModel $pageTranslationModel,
        private readonly PostTranslationModel $postTranslationModel,
        private readonly UrlRedirectModel $urlRedirectModel,
        private readonly PublicUrlBuilder $publicUrlBuilder,
        private readonly SettingService $settingService,
    ) {
    }

    /**
     * Validate a Page translation slug against the global namespace.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function validatePageSlug(
        string $slug,
        string $locale,
        ?int $exceptPageId = null,
    ): array {
        $normalizedSlug = $this->publicUrlBuilder->normalizeSlugSegment($slug);
        if ($normalizedSlug === '') {
            return ['slug' => 'The Slug field is required.'];
        }

        $path = $this->publicUrlBuilder->normalizePath(
            $this->publicUrlBuilder->pagePath($normalizedSlug, $locale),
        );

        return $this->validatePath($path, 'page', $exceptPageId, $locale, $normalizedSlug);
    }

    /**
     * Validate a Post translation slug against the global namespace.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function validatePostSlug(
        string $slug,
        string $locale,
        ?int $exceptPostId = null,
    ): array {
        $normalizedSlug = $this->publicUrlBuilder->normalizeSlugSegment($slug);
        if ($normalizedSlug === '') {
            return ['slug' => 'The Slug field is required.'];
        }

        $path = $this->publicUrlBuilder->normalizePath(
            $this->publicUrlBuilder->postPath($normalizedSlug, $locale),
        );

        return $this->validatePath($path, 'post', $exceptPostId, $locale, $normalizedSlug);
    }

    /**
     * @return array<string, string>
     */
    private function validatePath(
        string $path,
        string $resourceType,
        ?int $exceptResourceId,
        string $locale,
        string $normalizedSlug,
    ): array {
        $segment = ltrim($path, '/');
        $parts   = $segment === '' ? [] : explode('/', $segment);
        $slugSegment = $normalizedSlug;

        if ($resourceType === 'page') {
            $primary   = $this->settingService->primaryLocale();
            $secondary = $this->settingService->secondaryLocale();
            if ($secondary !== null && str_starts_with($path, '/' . $secondary . '/')) {
                $slugSegment = $parts[1] ?? $normalizedSlug;
            } else {
                $slugSegment = $parts[0] ?? $normalizedSlug;
            }

            if (in_array($slugSegment, self::RESERVED_PATHS, true)) {
                return ['slug' => 'This slug conflicts with a reserved system route.'];
            }
        }

        if ($this->urlRedirectModel->isActiveSourceReserved($path)) {
            return ['slug' => 'This slug conflicts with an active redirect source path.'];
        }

        $primary   = $this->settingService->primaryLocale();
        $secondary = $this->settingService->secondaryLocale();

        if ($secondary !== null && ($path === '/' . $secondary || str_starts_with($path, '/' . $secondary . '/'))) {
            // Path shape is valid for secondary; check cross-resource below.
        }

        if ($resourceType === 'page') {
            $pageConflict = $this->pageTranslationModel->findBySlugAndLocale(
                $normalizedSlug,
                $locale,
                $exceptResourceId,
            );
            if ($pageConflict !== null) {
                return ['slug' => 'This slug is already used by another Page in this locale.'];
            }

            $postConflict = $this->postTranslationModel->findBySlugAndLocale($normalizedSlug, $locale);
            if ($postConflict !== null) {
                $postPath = $this->publicUrlBuilder->normalizePath(
                    $this->publicUrlBuilder->postPath($normalizedSlug, $locale),
                );
                if ($postPath === $path) {
                    return ['slug' => 'This slug conflicts with an existing Post public URL.'];
                }
            }
        } else {
            $postConflict = $this->postTranslationModel->findBySlugAndLocale(
                $normalizedSlug,
                $locale,
                $exceptResourceId,
            );
            if ($postConflict !== null) {
                return ['slug' => 'This slug is already used by another Post in this locale.'];
            }

            $pageConflict = $this->pageTranslationModel->findBySlugAndLocale($normalizedSlug, $locale);
            if ($pageConflict !== null) {
                $pagePath = $this->publicUrlBuilder->normalizePath(
                    $this->publicUrlBuilder->pagePath($normalizedSlug, $locale),
                );
                if ($pagePath === $path) {
                    return ['slug' => 'This slug conflicts with an existing Page public URL.'];
                }
            }
        }

        if ($path === '/' . $primary || $path === '/' . ($secondary ?? '')) {
            return ['slug' => 'This slug conflicts with a locale prefix route.'];
        }

        return [];
    }
}
