<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\BaseController;
use App\Services\Localization\PublicUrlBuilder;
use App\Services\Localization\UrlRedirectService;
use App\Services\PostService;
use App\Services\Theme\ThemeService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Public Post rendering (Phase 3 / Task 3.9 / ADR-016 / ADR-025).
 */
class PostController extends BaseController
{
    public function show(string $slug): ResponseInterface|RedirectResponse|string
    {
        return $this->renderPublished($slug, $this->settingService()->primaryLocale());
    }

    public function showEn(string $slug): ResponseInterface|RedirectResponse|string
    {
        $secondary = $this->settingService()->secondaryLocale();
        if ($secondary === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->renderPublished($slug, $secondary);
    }

    private function renderPublished(string $slug, string $locale): ResponseInterface|RedirectResponse|string
    {
        $path = $this->publicUrlBuilder()->postPath($slug, $locale);
        $redirectTarget = $this->urlRedirectService()->findActiveTarget($path);
        if ($redirectTarget !== null) {
            return redirect()->to($redirectTarget, 301);
        }

        $package = $this->postService()->findPublishedPackageForPublic($slug, $locale);
        if ($package === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $viewModel = $package->view;

        try {
            $viewName = $this->themeService()->publicViewNameForTemplate($viewModel->templateKey);
        } catch (RuntimeException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $themeId    = $this->themeService()->activeThemeId();
        $seoPartial = 'themes/' . $themeId . '/_partials/seo_head';

        return view($viewName, [
            'title'           => $viewModel->title,
            'manualAuthor'    => $viewModel->manualAuthor,
            'locale'          => $viewModel->locale,
            'slug'            => $viewModel->slug,
            'body'            => $viewModel->body,
            'requestedLocale' => $viewModel->requestedLocale,
            'isFallback'      => $viewModel->isFallback,
            'seo'             => $package->seo,
            'seoPartial'      => $seoPartial,
        ]);
    }

    private function postService(): PostService
    {
        return service('postService');
    }

    private function themeService(): ThemeService
    {
        return service('themeService');
    }

    private function urlRedirectService(): UrlRedirectService
    {
        return service('urlRedirectService');
    }

    private function publicUrlBuilder(): PublicUrlBuilder
    {
        return service('publicUrlBuilder');
    }

    private function settingService(): \App\Services\SettingService
    {
        return service('settingService');
    }
}
