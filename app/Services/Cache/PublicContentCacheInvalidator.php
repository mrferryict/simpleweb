<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Dtos\PublicPageCacheEntry;
use App\Dtos\PublicPageViewDto;
use App\Dtos\PublicPostCacheEntry;
use App\Dtos\PublicPostViewDto;
use App\Dtos\PublicSeoViewDto;
use CodeIgniter\Cache\CacheInterface;

/**
 * Public Page/Post File Cache population + Phase 4 invalidation (ADR-009 / ADR-025).
 *
 * Population keys: content.page|post.{themeId}.{locale}.{slug}
 * Reverse-index: page.public.{id} / post.public.{id} → list of population keys
 */
final class PublicContentCacheInvalidator
{
    public const TTL_SECONDS = 3600;

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public static function pageKey(int $pageId): string
    {
        return 'page.public.' . $pageId;
    }

    public static function postKey(int $postId): string
    {
        return 'post.public.' . $postId;
    }

    /**
     * Active Theme presentation metadata key (ADR-009; dot form for FileHandler).
     */
    public static function themeActiveKey(): string
    {
        return 'theme.active';
    }

    public static function pagePopulationKey(string $themeId, string $locale, string $slug): string
    {
        return 'content.page.'
            . self::keyPart($themeId) . '.'
            . self::keyPart($locale) . '.'
            . self::keyPart($slug);
    }

    public static function postPopulationKey(string $themeId, string $locale, string $slug): string
    {
        return 'content.post.'
            . self::keyPart($themeId) . '.'
            . self::keyPart($locale) . '.'
            . self::keyPart($slug);
    }

    public function getPagePackage(string $themeId, string $locale, string $slug): ?PublicPageCacheEntry
    {
        try {
            $raw = $this->cache->get(self::pagePopulationKey($themeId, $locale, $slug));
        } catch (\Throwable) {
            return null;
        }

        return $this->hydratePagePackage($raw);
    }

    public function getPostPackage(string $themeId, string $locale, string $slug): ?PublicPostCacheEntry
    {
        try {
            $raw = $this->cache->get(self::postPopulationKey($themeId, $locale, $slug));
        } catch (\Throwable) {
            return null;
        }

        return $this->hydratePostPackage($raw);
    }

    /**
     * Best-effort population write + reverse-index update (ADR-025).
     */
    public function savePagePackage(
        int $pageId,
        string $themeId,
        string $locale,
        string $slug,
        PublicPageCacheEntry $entry,
    ): void {
        if ($pageId < 1) {
            return;
        }

        $populationKey = self::pagePopulationKey($themeId, $locale, $slug);

        try {
            $this->cache->save($populationKey, $this->serializePagePackage($entry), self::TTL_SECONDS);
            $this->rememberPopulationKey(self::pageKey($pageId), $populationKey);
        } catch (\Throwable) {
            // Best-effort: public response must not fail when DB resolution succeeded.
        }
    }

    /**
     * Best-effort population write + reverse-index update (ADR-025).
     */
    public function savePostPackage(
        int $postId,
        string $themeId,
        string $locale,
        string $slug,
        PublicPostCacheEntry $entry,
    ): void {
        if ($postId < 1) {
            return;
        }

        $populationKey = self::postPopulationKey($themeId, $locale, $slug);

        try {
            $this->cache->save($populationKey, $this->serializePostPackage($entry), self::TTL_SECONDS);
            $this->rememberPopulationKey(self::postKey($postId), $populationKey);
        } catch (\Throwable) {
            // Best-effort.
        }
    }

    public function invalidatePage(int $pageId): void
    {
        if ($pageId < 1) {
            return;
        }

        $indexKey = self::pageKey($pageId);
        $this->deleteIndexedPopulationKeys($indexKey);
        $this->cache->delete($indexKey);
    }

    public function invalidatePost(int $postId): void
    {
        if ($postId < 1) {
            return;
        }

        $indexKey = self::postKey($postId);
        $this->deleteIndexedPopulationKeys($indexKey);
        $this->cache->delete($indexKey);
    }

    /**
     * Post-commit public presentation invalidation on Theme activation (ADR-009 / ADR-022).
     */
    public function invalidateThemePresentation(): void
    {
        $this->invalidatePublicContentPresentation();
    }

    /**
     * Wipe public content population + reverse indexes (Theme activation / locale Settings).
     */
    public function invalidatePublicContentPresentation(): void
    {
        try {
            $this->cache->delete(self::themeActiveKey());

            if (! method_exists($this->cache, 'deleteMatching')) {
                return;
            }

            foreach (['content.', 'nav.', 'page.public.', 'post.public.'] as $prefix) {
                $this->cache->deleteMatching($prefix . '*');
            }
        } catch (\Throwable) {
            // Best-effort invalidation must not break the mutator.
        }
    }

    private function rememberPopulationKey(string $indexKey, string $populationKey): void
    {
        $keys = [];
        try {
            $existing = $this->cache->get($indexKey);
            if (is_array($existing)) {
                foreach ($existing as $item) {
                    if (is_string($item) && $item !== '') {
                        $keys[$item] = $item;
                    }
                }
            }
        } catch (\Throwable) {
            $keys = [];
        }

        $keys[$populationKey] = $populationKey;

        try {
            $this->cache->save($indexKey, array_values($keys), self::TTL_SECONDS);
        } catch (\Throwable) {
            // Best-effort reverse-index update.
        }
    }

    private function deleteIndexedPopulationKeys(string $indexKey): void
    {
        try {
            $existing = $this->cache->get($indexKey);
        } catch (\Throwable) {
            return;
        }

        if (! is_array($existing)) {
            return;
        }

        foreach ($existing as $item) {
            if (! is_string($item) || $item === '') {
                continue;
            }

            try {
                $this->cache->delete($item);
            } catch (\Throwable) {
                // Continue clearing remaining keys.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePagePackage(PublicPageCacheEntry $entry): array
    {
        return [
            'type' => 'page',
            'view' => [
                'pageId'           => $entry->view->pageId,
                'title'            => $entry->view->title,
                'locale'           => $entry->view->locale,
                'slug'             => $entry->view->slug,
                'contentPayload'   => $entry->view->contentPayload,
                'requestedLocale'  => $entry->view->requestedLocale,
                'isFallback'       => $entry->view->isFallback,
                'templateKey'      => $entry->view->templateKey,
                'contentMedia'     => $entry->view->contentMedia,
                'metaTitle'        => $entry->view->metaTitle,
                'metaDescription'  => $entry->view->metaDescription,
                'canonicalUrl'     => $entry->view->canonicalUrl,
                'ogImageId'        => $entry->view->ogImageId,
            ],
            'seo' => $this->serializeSeo($entry->seo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePostPackage(PublicPostCacheEntry $entry): array
    {
        return [
            'type' => 'post',
            'view' => [
                'postId'           => $entry->view->postId,
                'title'            => $entry->view->title,
                'manualAuthor'     => $entry->view->manualAuthor,
                'locale'           => $entry->view->locale,
                'slug'             => $entry->view->slug,
                'body'             => $entry->view->body,
                'requestedLocale'  => $entry->view->requestedLocale,
                'isFallback'       => $entry->view->isFallback,
                'templateKey'      => $entry->view->templateKey,
                'metaTitle'        => $entry->view->metaTitle,
                'metaDescription'  => $entry->view->metaDescription,
                'canonicalUrl'     => $entry->view->canonicalUrl,
                'ogImageId'        => $entry->view->ogImageId,
            ],
            'seo' => $this->serializeSeo($entry->seo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSeo(PublicSeoViewDto $seo): array
    {
        return [
            'documentTitle'       => $seo->documentTitle,
            'metaDescription'     => $seo->metaDescription,
            'canonicalUrl'        => $seo->canonicalUrl,
            'hreflangAlternates'  => $seo->hreflangAlternates,
            'xDefaultUrl'         => $seo->xDefaultUrl,
            'ogImageUrl'          => $seo->ogImageUrl,
        ];
    }

    private function hydratePagePackage(mixed $raw): ?PublicPageCacheEntry
    {
        if (! is_array($raw) || ($raw['type'] ?? null) !== 'page') {
            return null;
        }

        $viewRaw = $raw['view'] ?? null;
        $seoRaw  = $raw['seo'] ?? null;
        if (! is_array($viewRaw) || ! is_array($seoRaw)) {
            return null;
        }

        if (! isset($viewRaw['pageId'], $viewRaw['title'], $viewRaw['locale'], $viewRaw['slug'], $viewRaw['requestedLocale'], $viewRaw['templateKey'])) {
            return null;
        }

        $seo = $this->hydrateSeo($seoRaw);
        if ($seo === null) {
            return null;
        }

        try {
            $view = new PublicPageViewDto(
                pageId: (int) $viewRaw['pageId'],
                title: (string) $viewRaw['title'],
                locale: (string) $viewRaw['locale'],
                slug: (string) $viewRaw['slug'],
                contentPayload: is_array($viewRaw['contentPayload'] ?? null) ? $viewRaw['contentPayload'] : [],
                requestedLocale: (string) $viewRaw['requestedLocale'],
                isFallback: (bool) ($viewRaw['isFallback'] ?? false),
                templateKey: (string) $viewRaw['templateKey'],
                contentMedia: is_array($viewRaw['contentMedia'] ?? null) ? $viewRaw['contentMedia'] : [],
                metaTitle: isset($viewRaw['metaTitle']) && is_string($viewRaw['metaTitle']) ? $viewRaw['metaTitle'] : null,
                metaDescription: isset($viewRaw['metaDescription']) && is_string($viewRaw['metaDescription']) ? $viewRaw['metaDescription'] : null,
                canonicalUrl: isset($viewRaw['canonicalUrl']) && is_string($viewRaw['canonicalUrl']) ? $viewRaw['canonicalUrl'] : null,
                ogImageId: isset($viewRaw['ogImageId']) && is_numeric($viewRaw['ogImageId']) ? (int) $viewRaw['ogImageId'] : null,
            );
        } catch (\Throwable) {
            return null;
        }

        return new PublicPageCacheEntry($view, $seo);
    }

    private function hydratePostPackage(mixed $raw): ?PublicPostCacheEntry
    {
        if (! is_array($raw) || ($raw['type'] ?? null) !== 'post') {
            return null;
        }

        $viewRaw = $raw['view'] ?? null;
        $seoRaw  = $raw['seo'] ?? null;
        if (! is_array($viewRaw) || ! is_array($seoRaw)) {
            return null;
        }

        if (! isset($viewRaw['postId'], $viewRaw['title'], $viewRaw['manualAuthor'], $viewRaw['locale'], $viewRaw['slug'], $viewRaw['body'], $viewRaw['requestedLocale'])) {
            return null;
        }

        $seo = $this->hydrateSeo($seoRaw);
        if ($seo === null) {
            return null;
        }

        try {
            $view = new PublicPostViewDto(
                postId: (int) $viewRaw['postId'],
                title: (string) $viewRaw['title'],
                manualAuthor: (string) $viewRaw['manualAuthor'],
                locale: (string) $viewRaw['locale'],
                slug: (string) $viewRaw['slug'],
                body: (string) $viewRaw['body'],
                requestedLocale: (string) $viewRaw['requestedLocale'],
                isFallback: (bool) ($viewRaw['isFallback'] ?? false),
                templateKey: (string) ($viewRaw['templateKey'] ?? 'custom-post'),
                metaTitle: isset($viewRaw['metaTitle']) && is_string($viewRaw['metaTitle']) ? $viewRaw['metaTitle'] : null,
                metaDescription: isset($viewRaw['metaDescription']) && is_string($viewRaw['metaDescription']) ? $viewRaw['metaDescription'] : null,
                canonicalUrl: isset($viewRaw['canonicalUrl']) && is_string($viewRaw['canonicalUrl']) ? $viewRaw['canonicalUrl'] : null,
                ogImageId: isset($viewRaw['ogImageId']) && is_numeric($viewRaw['ogImageId']) ? (int) $viewRaw['ogImageId'] : null,
            );
        } catch (\Throwable) {
            return null;
        }

        return new PublicPostCacheEntry($view, $seo);
    }

    private function hydrateSeo(array $seoRaw): ?PublicSeoViewDto
    {
        if (! isset($seoRaw['documentTitle'], $seoRaw['metaDescription'], $seoRaw['canonicalUrl'], $seoRaw['xDefaultUrl'])) {
            return null;
        }

        $hreflang = $seoRaw['hreflangAlternates'] ?? [];
        if (! is_array($hreflang)) {
            return null;
        }

        /** @var list<array{hreflang: string, href: string}> $normalized */
        $normalized = [];
        foreach ($hreflang as $row) {
            if (! is_array($row) || ! isset($row['hreflang'], $row['href'])) {
                continue;
            }
            $normalized[] = [
                'hreflang' => (string) $row['hreflang'],
                'href'     => (string) $row['href'],
            ];
        }

        try {
            return new PublicSeoViewDto(
                documentTitle: (string) $seoRaw['documentTitle'],
                metaDescription: (string) $seoRaw['metaDescription'],
                canonicalUrl: (string) $seoRaw['canonicalUrl'],
                hreflangAlternates: $normalized,
                xDefaultUrl: (string) $seoRaw['xDefaultUrl'],
                ogImageUrl: isset($seoRaw['ogImageUrl']) && is_string($seoRaw['ogImageUrl']) ? $seoRaw['ogImageUrl'] : null,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private static function keyPart(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $normalized) ?? '';

        return trim($normalized, '-') !== '' ? trim($normalized, '-') : 'invalid';
    }
}
