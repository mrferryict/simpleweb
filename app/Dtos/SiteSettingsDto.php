<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Immutable Site Settings transport object (ADR-013 / ADR-024).
 */
final readonly class SiteSettingsDto
{
    /**
     * @param array<string, string> $seoMetaTitleByLocale
     * @param array<string, string> $seoMetaDescriptionByLocale
     * @param array<string, int|null> $seoOgImageIdByLocale
     */
    public function __construct(
        public string $siteName,
        public string $siteDescription,
        public string $defaultLocale,
        public string $primaryLocale,
        public ?string $secondaryLocale,
        public string $timezone,
        public string $contactEmail,
        public array $seoMetaTitleByLocale = [],
        public array $seoMetaDescriptionByLocale = [],
        public array $seoOgImageIdByLocale = [],
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toFormArray(): array
    {
        return [
            'site_name'                   => $this->siteName,
            'site_description'            => $this->siteDescription,
            'default_locale'              => $this->defaultLocale,
            'primary_locale'              => $this->primaryLocale,
            'secondary_locale'            => $this->secondaryLocale ?? '',
            'timezone'                    => $this->timezone,
            'contact_email'               => $this->contactEmail,
            'seo_meta_title_id'           => $this->seoMetaTitleByLocale['id'] ?? '',
            'seo_meta_title_en'           => $this->seoMetaTitleByLocale['en'] ?? '',
            'seo_meta_description_id'     => $this->seoMetaDescriptionByLocale['id'] ?? '',
            'seo_meta_description_en'     => $this->seoMetaDescriptionByLocale['en'] ?? '',
            'seo_og_image_id_id'          => isset($this->seoOgImageIdByLocale['id']) && $this->seoOgImageIdByLocale['id'] !== null
                ? (string) $this->seoOgImageIdByLocale['id']
                : '',
            'seo_og_image_id_en'          => isset($this->seoOgImageIdByLocale['en']) && $this->seoOgImageIdByLocale['en'] !== null
                ? (string) $this->seoOgImageIdByLocale['en']
                : '',
        ];
    }
}
