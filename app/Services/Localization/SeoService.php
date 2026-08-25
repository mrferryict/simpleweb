<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Dtos\PublicPageViewDto;
use App\Dtos\PublicPostViewDto;
use App\Dtos\PublicSeoViewDto;
use App\Entities\PageTranslation;
use App\Entities\PostTranslation;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Models\PageModel;
use App\Models\PageTranslationModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Services\Media\MediaService;
use App\Services\SettingService;
use Config\App as AppConfig;
use Uri\Rfc3986\Uri;

/**
 * SEO resolution for public Theme presentation (DOC-08 §52 / ADR-024 §11).
 */
final class SeoService
{
    private const META_TITLE_MAX       = 255;
    private const META_DESCRIPTION_MAX = 500;

    public function __construct(
        private readonly SettingService $settingService,
        private readonly PageTranslationModel $pageTranslationModel,
        private readonly PostTranslationModel $postTranslationModel,
        private readonly PageModel $pageModel,
        private readonly PostModel $postModel,
        private readonly PublicUrlBuilder $publicUrlBuilder,
        private readonly MediaService $mediaService,
    ) {
    }

    public function forPageView(PublicPageViewDto $view): PublicSeoViewDto
    {
        $translation = new PageTranslation([
            'title'            => $view->title,
            'slug'             => $view->slug,
            'locale'           => $view->locale,
            'meta_title'       => $view->metaTitle,
            'meta_description' => $view->metaDescription,
            'canonical_url'    => $view->canonicalUrl,
            'og_image_id'      => $view->ogImageId,
        ]);

        return $this->forPage(
            pageId: $view->pageId,
            activeTranslation: $translation,
            requestedLocale: $view->requestedLocale,
            isFallback: $view->isFallback,
        );
    }

    public function forPostView(PublicPostViewDto $view): PublicSeoViewDto
    {
        $translation = new PostTranslation([
            'title'            => $view->title,
            'slug'             => $view->slug,
            'locale'           => $view->locale,
            'meta_title'       => $view->metaTitle,
            'meta_description' => $view->metaDescription,
            'canonical_url'    => $view->canonicalUrl,
            'og_image_id'      => $view->ogImageId,
        ]);

        return $this->forPost(
            postId: $view->postId,
            activeTranslation: $translation,
            requestedLocale: $view->requestedLocale,
            isFallback: $view->isFallback,
        );
    }

    public function forPage(
        int $pageId,
        PageTranslation $activeTranslation,
        string $requestedLocale,
        bool $isFallback,
    ): PublicSeoViewDto {
        return $this->build(
            resourceType: 'page',
            resourceId: $pageId,
            activeTranslation: $activeTranslation,
            requestedLocale: $requestedLocale,
            isFallback: $isFallback,
        );
    }

    public function forPost(
        int $postId,
        PostTranslation $activeTranslation,
        string $requestedLocale,
        bool $isFallback,
    ): PublicSeoViewDto {
        return $this->build(
            resourceType: 'post',
            resourceId: $postId,
            activeTranslation: $activeTranslation,
            requestedLocale: $requestedLocale,
            isFallback: $isFallback,
        );
    }

    /**
     * @param PageTranslation|PostTranslation $activeTranslation
     */
    private function build(
        string $resourceType,
        int $resourceId,
        PageTranslation|PostTranslation $activeTranslation,
        string $requestedLocale,
        bool $isFallback,
    ): PublicSeoViewDto {
        $primary   = $this->settingService->primaryLocale();
        $secondary = $this->settingService->secondaryLocale();

        $translations = $resourceType === 'page'
            ? $this->publishedTranslationsForPage($resourceId)
            : $this->publishedTranslationsForPost($resourceId);

        $primaryTranslation = $translations[$primary] ?? null;

        $selfPath = $resourceType === 'page'
            ? $this->publicUrlBuilder->pagePath((string) $activeTranslation->slug, $requestedLocale)
            : $this->publicUrlBuilder->postPath((string) $activeTranslation->slug, $requestedLocale);

        $overrideCanonical = trim((string) ($activeTranslation->canonical_url ?? ''));
        if ($isFallback && $primaryTranslation !== null) {
            $canonicalPath = $resourceType === 'page'
                ? $this->publicUrlBuilder->pagePath((string) $primaryTranslation->slug, $primary)
                : $this->publicUrlBuilder->postPath((string) $primaryTranslation->slug, $primary);
            $canonicalUrl = $this->absoluteUrl($canonicalPath);
        } elseif ($overrideCanonical !== '') {
            $canonicalUrl = $this->absoluteUrl($overrideCanonical);
        } else {
            $canonicalUrl = $this->absoluteUrl($selfPath);
        }

        $localeForDefaults = $isFallback ? $primary : $requestedLocale;
        $metaTitle         = $this->resolveMetaTitle($activeTranslation, $localeForDefaults);
        $metaDescription   = $this->resolveMetaDescription($activeTranslation, $localeForDefaults);

        $hreflang = [];
        foreach ($translations as $locale => $translation) {
            if ($isFallback && $secondary !== null && $locale === $secondary) {
                continue;
            }

            $path = $resourceType === 'page'
                ? $this->publicUrlBuilder->pagePath((string) $translation->slug, $locale)
                : $this->publicUrlBuilder->postPath((string) $translation->slug, $locale);

            $hreflang[] = [
                'hreflang' => $locale,
                'href'     => $this->absoluteUrl($path),
            ];
        }

        $xDefault = $primaryTranslation !== null
            ? $this->absoluteUrl(
                $resourceType === 'page'
                    ? $this->publicUrlBuilder->pagePath((string) $primaryTranslation->slug, $primary)
                    : $this->publicUrlBuilder->postPath((string) $primaryTranslation->slug, $primary),
            )
            : $this->absoluteUrl($selfPath);

        $ogImageUrl = $this->resolveOgImageUrl($activeTranslation, $localeForDefaults);

        return new PublicSeoViewDto(
            documentTitle: $metaTitle,
            metaDescription: $metaDescription,
            canonicalUrl: $canonicalUrl,
            hreflangAlternates: $hreflang,
            xDefaultUrl: $xDefault,
            ogImageUrl: $ogImageUrl,
        );
    }

    /**
     * @return array<string, PageTranslation>
     */
    private function publishedTranslationsForPage(int $pageId): array
    {
        $page = $this->pageModel->find($pageId);
        if ($page === null || (string) $page->status !== PageStatus::Published->value) {
            return [];
        }

        $rows = $this->pageTranslationModel->where('page_id', $pageId)->findAll();
        $map  = [];
        foreach ($rows as $row) {
            if ($row instanceof PageTranslation) {
                $map[(string) $row->locale] = $row;
            }
        }

        return $map;
    }

    /**
     * @return array<string, PostTranslation>
     */
    private function publishedTranslationsForPost(int $postId): array
    {
        $post = $this->postModel->find($postId);
        if ($post === null || (string) $post->status !== PostStatus::Published->value) {
            return [];
        }

        $rows = $this->postTranslationModel->where('post_id', $postId)->findAll();
        $map  = [];
        foreach ($rows as $row) {
            if ($row instanceof PostTranslation) {
                $map[(string) $row->locale] = $row;
            }
        }

        return $map;
    }

    /**
     * @param PageTranslation|PostTranslation $translation
     */
    private function resolveMetaTitle(PageTranslation|PostTranslation $translation, string $locale): string
    {
        $specific = trim((string) ($translation->meta_title ?? ''));
        if ($specific !== '') {
            return $this->truncate($specific, self::META_TITLE_MAX);
        }

        $localized = trim($this->settingService->seoMetaTitleForLocale($locale));
        if ($localized !== '') {
            return $this->truncate($localized, self::META_TITLE_MAX);
        }

        $global = trim($this->settingService->getSiteSettings()->siteName);
        if ($global !== '') {
            return $this->truncate($global, self::META_TITLE_MAX);
        }

        return $this->truncate(trim((string) $translation->title), self::META_TITLE_MAX);
    }

    /**
     * @param PageTranslation|PostTranslation $translation
     */
    private function resolveMetaDescription(PageTranslation|PostTranslation $translation, string $locale): string
    {
        $specific = trim((string) ($translation->meta_description ?? ''));
        if ($specific !== '') {
            return $this->truncate($specific, self::META_DESCRIPTION_MAX);
        }

        $localized = trim($this->settingService->seoMetaDescriptionForLocale($locale));
        if ($localized !== '') {
            return $this->truncate($localized, self::META_DESCRIPTION_MAX);
        }

        $global = trim($this->settingService->getSiteSettings()->siteDescription);
        if ($global !== '') {
            return $this->truncate($global, self::META_DESCRIPTION_MAX);
        }

        return '';
    }

    /**
     * @param PageTranslation|PostTranslation $translation
     */
    private function resolveOgImageUrl(PageTranslation|PostTranslation $translation, string $locale): ?string
    {
        $ogImageId = $translation->og_image_id ?? null;
        if ($ogImageId !== null && (int) $ogImageId > 0) {
            $url = $this->mediaService->publicImageUrl((int) $ogImageId);
            if ($url !== null) {
                return $this->absoluteUrl($url);
            }
        }

        $defaultId = $this->settingService->seoOgImageIdForLocale($locale);
        if ($defaultId !== null && $defaultId > 0) {
            $url = $this->mediaService->publicImageUrl($defaultId);
            if ($url !== null) {
                return $this->absoluteUrl($url);
            }
        }

        return null;
    }

    private function absoluteUrl(string $pathOrUrl): string
    {
        $value = trim($pathOrUrl);
        if ($value === '') {
            /** @var AppConfig $app */
            $app = config(AppConfig::class);

            return rtrim($app->baseURL, '/');
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            try {
                return (new Uri($value))->toString();
            } catch (\Throwable) {
                /** @var AppConfig $app */
                $app = config(AppConfig::class);

                return rtrim($app->baseURL, '/');
            }
        }

        /** @var AppConfig $app */
        $app     = config(AppConfig::class);
        $base    = rtrim($app->baseURL, '/');
        $path    = $this->publicUrlBuilder->normalizePath($value);

        return $base . $path;
    }

    private function truncate(string $value, int $max): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
