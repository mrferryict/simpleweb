<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Default Site Settings values (Phase 2 / Task 2.1).
 *
 * Persisted overrides are stored via codeigniter4/settings using keys:
 * Site.siteName, Site.siteDescription, Site.defaultLocale, Site.timezone, Site.contactEmail.
 */
class Site extends BaseConfig
{
    public string $siteName = 'SMITE CMS';

    public string $siteDescription = '';

    /**
     * Documented V1 locale identifiers (docs/07): id, en.
     */
    public string $defaultLocale = 'id';

    /**
     * Bootstrap Secondary locale when Settings key is absent (ADR-024).
     * Persisted empty string in Settings explicitly disables Secondary.
     */
    public ?string $secondaryLocale = 'en';

    public string $timezone = 'Asia/Jakarta';

    public string $contactEmail = '';
}
