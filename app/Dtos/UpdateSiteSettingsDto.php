<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Writable Site Settings fields (Phase 2 / ADR-024).
 */
final readonly class UpdateSiteSettingsDto
{
    public function __construct(
        public string $siteName,
        public string $siteDescription,
        public string $defaultLocale,
        public string $primaryLocale,
        public string $secondaryLocale,
        public string $timezone,
        public string $contactEmail,
        public string $seoMetaTitleId = '',
        public string $seoMetaTitleEn = '',
        public string $seoMetaDescriptionId = '',
        public string $seoMetaDescriptionEn = '',
        public string $seoOgImageIdId = '',
        public string $seoOgImageIdEn = '',
    ) {
    }
}
