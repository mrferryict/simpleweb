<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Theme discovery and lifecycle configuration (ADR-002 / ADR-022).
 *
 * `$enabledThemeIds` is the developer-controlled ENABLED registry (deploy only).
 * `$activeThemeId` is bootstrap/fallback when Settings has no persisted ACTIVE Theme.
 * Live ACTIVE is persisted as Settings `Theme.activeThemeId` after Admin activation.
 */
class Theme extends BaseConfig
{
    /**
     * Bootstrap / fallback ACTIVE theme id when Settings has no `Theme.activeThemeId`.
     */
    public string $activeThemeId = '2026';

    /**
     * Developer-controlled ENABLED Theme registry (ADR-022).
     *
     * Must include the bootstrap Theme so the site is never stranded.
     *
     * @var list<string>
     */
    public array $enabledThemeIds = [
        'default',
        'classic',
        '2026',
    ];
}
