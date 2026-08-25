<?php

declare(strict_types=1);

namespace App\Services\Localization;

use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Models\PageModel;
use App\Models\PageTranslationModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Models\UrlRedirectModel;
use App\Services\SettingService;
use Config\App as AppConfig;

/**
 * Dynamic sitemap generation (REQ-SEO-003 / ADR-024).
 */
final class SitemapService
{
    public function __construct(
        private readonly PageModel $pageModel,
        private readonly PageTranslationModel $pageTranslationModel,
        private readonly PostModel $postModel,
        private readonly PostTranslationModel $postTranslationModel,
        private readonly UrlRedirectModel $urlRedirectModel,
        private readonly PublicUrlBuilder $publicUrlBuilder,
        private readonly SettingService $settingService,
    ) {
    }

    public function toXml(): string
    {
        $urls = $this->collectUrls();
        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $loc) {
            $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</loc></url>' . "\n";
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * @return list<string>
     */
    public function collectUrls(): array
    {
        $redirectSources = array_flip($this->urlRedirectModel->listActiveSourcePaths());
        $urls            = [];

        /** @var list<\App\Entities\Page> $pages */
        $pages = $this->pageModel
            ->where('status', PageStatus::Published->value)
            ->where('deleted_at', null)
            ->findAll();

        foreach ($pages as $page) {
            $translations = $this->pageTranslationModel->where('page_id', (int) $page->id)->findAll();
            foreach ($translations as $translation) {
                if (! $this->isLocalePubliclyRoutable((string) $translation->locale)) {
                    continue;
                }

                $path = $this->publicUrlBuilder->normalizePath(
                    $this->publicUrlBuilder->pagePath((string) $translation->slug, (string) $translation->locale),
                );
                if (isset($redirectSources[$path])) {
                    continue;
                }
                $urls[$path] = $this->absoluteUrl($path);
            }
        }

        /** @var list<\App\Entities\Post> $posts */
        $posts = $this->postModel
            ->where('status', PostStatus::Published->value)
            ->where('deleted_at', null)
            ->findAll();

        foreach ($posts as $post) {
            $translations = $this->postTranslationModel->where('post_id', (int) $post->id)->findAll();
            foreach ($translations as $translation) {
                if (! $this->isLocalePubliclyRoutable((string) $translation->locale)) {
                    continue;
                }

                $path = $this->publicUrlBuilder->normalizePath(
                    $this->publicUrlBuilder->postPath((string) $translation->slug, (string) $translation->locale),
                );
                if (isset($redirectSources[$path])) {
                    continue;
                }
                $urls[$path] = $this->absoluteUrl($path);
            }
        }

        return array_values($urls);
    }

    private function absoluteUrl(string $path): string
    {
        /** @var AppConfig $app */
        $app = config(AppConfig::class);

        return rtrim($app->baseURL, '/') . $path;
    }

    /**
     * REQ-SEO-003 / DOC-07 §28 — emit only currently public, routable locale URLs.
     */
    private function isLocalePubliclyRoutable(string $locale): bool
    {
        $locale  = strtolower(trim($locale));
        $primary = $this->settingService->primaryLocale();

        if ($locale === $primary) {
            return true;
        }

        if (! $this->settingService->isSecondaryEnabled()) {
            return false;
        }

        $secondary = $this->settingService->secondaryLocale();

        return $secondary !== null && $locale === $secondary;
    }
}
