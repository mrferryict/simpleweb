<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\PageService;
use App\Services\SettingService;
use App\Services\Theme\ThemeService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

/**
 * Theme Preview (Phase 6 / Task 6.2B / ADR-023).
 *
 * GET-only, read-only Page presentation through a request-scoped candidate Theme.
 * Authorization: permission:theme.preview (Admin only in V1 matrix).
 */
class ThemePreviewController extends BaseController
{
    /** @var list<string> */
    private const ALLOWED_LOCALES = ['id', 'en'];

    /**
     * GET /admin/preview/theme/{themeId}/page/{pageId}
     */
    public function show(string $themeId, int $pageId): ResponseInterface|string
    {
        $normalizedThemeId = strtolower(trim($themeId));
        $errors            = $this->themeService()->validatePreviewCandidate($normalizedThemeId, auth()->user());
        if ($errors !== []) {
            throw PageNotFoundException::forPageNotFound();
        }

        $locale    = $this->resolvePreviewLocale();
        $viewModel = $this->pageService()->findForThemePreview($pageId, $locale, $normalizedThemeId);
        if ($viewModel === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        try {
            $viewName = $this->themeService()->publicViewNameForThemeTemplate(
                $normalizedThemeId,
                $viewModel->templateKey,
            );
        } catch (RuntimeException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $this->applyPreviewSecurityHeaders();

        return view($viewName, [
            'title'           => $viewModel->title,
            'locale'          => $viewModel->locale,
            'slug'            => $viewModel->slug,
            'contentPayload'  => $viewModel->contentPayload,
            'contentMedia'    => $viewModel->contentMedia,
            'requestedLocale' => $viewModel->requestedLocale,
            'isFallback'      => $viewModel->isFallback,
        ]);
    }

    private function resolvePreviewLocale(): string
    {
        $param = $this->request->getGet('locale');
        if ($param !== null && $param !== '') {
            $locale = strtolower(trim((string) $param));
            if (! in_array($locale, self::ALLOWED_LOCALES, true)) {
                throw PageNotFoundException::forPageNotFound();
            }

            return $locale;
        }

        $default = strtolower(trim($this->settingService()->getSiteSettings()->defaultLocale));
        if ($default !== '' && in_array($default, self::ALLOWED_LOCALES, true)) {
            return $default;
        }

        return 'id';
    }

    private function applyPreviewSecurityHeaders(): void
    {
        $this->response
            ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    private function pageService(): PageService
    {
        return service('pageService');
    }

    private function themeService(): ThemeService
    {
        return service('themeService');
    }

    private function settingService(): SettingService
    {
        return service('settingService');
    }
}
