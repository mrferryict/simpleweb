<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Services\SettingService;

/**
 * Builds normalized public URL paths (ADR-003 / ADR-016 / ADR-017).
 */
final class PublicUrlBuilder
{
    public const POST_COLLECTION_PREFIX = 'news';

    public function __construct(
        private readonly SettingService $settingService,
    ) {
    }

    public function pagePath(string $slug, string $locale): string
    {
        $normalizedSlug = $this->normalizeSlugSegment($slug);
        $primary        = $this->settingService->primaryLocale();

        if ($locale === $primary) {
            return '/' . $normalizedSlug;
        }

        $secondary = $this->settingService->secondaryLocale();
        if ($secondary === null) {
            return '/' . $normalizedSlug;
        }

        return '/' . $secondary . '/' . $normalizedSlug;
    }

    public function postPath(string $slug, string $locale): string
    {
        $normalizedSlug = $this->normalizeSlugSegment($slug);
        $primary        = $this->settingService->primaryLocale();

        if ($locale === $primary) {
            return '/' . self::POST_COLLECTION_PREFIX . '/' . $normalizedSlug;
        }

        $secondary = $this->settingService->secondaryLocale();
        if ($secondary === null) {
            return '/' . self::POST_COLLECTION_PREFIX . '/' . $normalizedSlug;
        }

        return '/' . $secondary . '/' . self::POST_COLLECTION_PREFIX . '/' . $normalizedSlug;
    }

    public function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        if (! str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    public function normalizeSlugSegment(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = str_replace([' ', '_'], '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
