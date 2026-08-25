<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\PageService;
use App\Services\Theme\ThemeService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Theme lifecycle Admin surface (Phase 6 / Task 6.1B / ADR-022).
 *
 * Authorization: permission:theme.activate (DOC-03 AUTHZ-005).
 */
class ThemeController extends BaseController
{
    /**
     * GET /admin/themes
     */
    public function index(): string
    {
        return view('admin/themes/index', [
            'themes'       => $this->themeService()->listEnabledThemesForAdmin(),
            'previewPages' => $this->pageService()->listPreviewPageOptions(),
            'success'      => session()->getFlashdata('success'),
            'error'        => session()->getFlashdata('error'),
        ]);
    }

    /**
     * POST /admin/themes/{themeId}/activate
     */
    public function activate(string $themeId): RedirectResponse|ResponseInterface|string
    {
        $errors = $this->themeService()->activate($themeId, auth()->user());

        if (isset($errors['_forbidden'])) {
            return redirect()->to('/admin/themes')->with('error', $errors['_forbidden']);
        }

        if ($errors !== []) {
            $message = $errors['_persist'] ?? $errors['theme_id'] ?? 'Unable to activate Theme.';

            return redirect()->to('/admin/themes')->with('error', $message);
        }

        return redirect()->to('/admin/themes')->with('success', 'Theme activated.');
    }

    private function themeService(): ThemeService
    {
        return service('themeService');
    }

    private function pageService(): PageService
    {
        return service('pageService');
    }
}
