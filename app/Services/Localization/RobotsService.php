<?php

declare(strict_types=1);

namespace App\Services\Localization;

use Config\App as AppConfig;

/**
 * Controlled robots.txt generation (REQ-SEO-004 / DOC-07 §30).
 */
final class RobotsService
{
    public function toText(): string
    {
        /** @var AppConfig $app */
        $app      = config(AppConfig::class);
        $base     = rtrim($app->baseURL, '/');
        $sitemap  = $base . '/sitemap.xml';

        $lines = [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /cp',
            'Disallow: /admin/preview/',
            'Disallow: /logout',
            '',
            'Sitemap: ' . $sitemap,
        ];

        return implode("\n", $lines) . "\n";
    }
}
