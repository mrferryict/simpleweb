<?php

declare(strict_types=1);

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * Public site root (GET /) — first-run landing, not a Page record (ADR-017 homepage binding deferred).
 */
class Home extends BaseController
{
    public function index(): ResponseInterface|string
    {
        $settings = Services::settingService(getShared: false)->getSiteSettings();
        $theme    = Services::themeService(getShared: false);
        $themeId  = $theme->activeThemeId();
        $viewName = 'themes/' . $themeId . '/templates/home';

        $viewFile = APPPATH . 'Views/' . $viewName . '.php';
        if (! is_file($viewFile)) {
            $viewName = 'themes/default/templates/home';
        }

        return view($viewName, [
            'siteName'        => $settings->siteName !== '' ? $settings->siteName : 'SMITE CMS',
            'siteDescription' => $settings->siteDescription,
            'cpUrl'           => site_url('cp'),
        ]);
    }
}
