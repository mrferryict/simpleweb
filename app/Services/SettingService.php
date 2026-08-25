<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\SiteSettingsDto;
use App\Dtos\UpdateSiteSettingsDto;
use App\Services\Cache\PublicContentCacheInvalidator;
use CodeIgniter\Settings\Settings;
use CodeIgniter\Validation\ValidationInterface;
use Config\Site as SiteConfig;

/**
 * Application boundary for global Site Settings (Phase 2 / Task 2.1, Phase 7 / ADR-024).
 */
class SettingService
{
    private const KEY_SITE_NAME        = 'Site.siteName';
    private const KEY_SITE_DESCRIPTION = 'Site.siteDescription';
    private const KEY_DEFAULT_LOCALE   = 'Site.defaultLocale';
    private const KEY_PRIMARY_LOCALE   = 'Site.primaryLocale';
    private const KEY_SECONDARY_LOCALE = 'Site.secondaryLocale';
    private const KEY_TIMEZONE         = 'Site.timezone';
    private const KEY_CONTACT_EMAIL    = 'Site.contactEmail';
    private const KEY_ACTIVE_THEME_ID  = 'Theme.activeThemeId';

    /** @var list<string> Documented V1 locale identifiers (docs/07). */
    public const ALLOWED_LOCALES = ['id', 'en'];

    private const SITE_NAME_MAX        = 150;
    private const SITE_DESCRIPTION_MAX = 500;
    private const LOCALE_MAX           = 16;
    private const TIMEZONE_MAX         = 64;
    private const CONTACT_EMAIL_MAX    = 254;
    private const SEO_META_TITLE_MAX   = 255;
    private const SEO_META_DESC_MAX    = 500;

    public function __construct(
        private readonly Settings $settings,
        private readonly ValidationInterface $validation,
        private readonly PublicContentCacheInvalidator $publicContentCache,
    ) {
    }

    public function getSiteSettings(): SiteSettingsDto
    {
        /** @var SiteConfig $siteConfig */
        $siteConfig = config(SiteConfig::class);

        $primary = (string) ($this->settingsGet(self::KEY_PRIMARY_LOCALE) ?? '');
        if ($primary === '') {
            $primary = (string) ($this->settingsGet(self::KEY_DEFAULT_LOCALE) ?? '');
        }
        if ($primary === '') {
            $primary = $siteConfig->defaultLocale;
        }

        $secondaryRaw = $this->settingsGet(self::KEY_SECONDARY_LOCALE);
        if ($secondaryRaw === null) {
            /** @var SiteConfig $siteConfig */
            $siteConfig    = config(SiteConfig::class);
            $secondaryBoot = trim((string) ($siteConfig->secondaryLocale ?? ''));
            $secondary     = $secondaryBoot !== '' ? strtolower($secondaryBoot) : null;
        } else {
            $secondary = strtolower(trim((string) $secondaryRaw));
            $secondary = $secondary !== '' ? $secondary : null;
        }

        $seoTitles = [];
        $seoDescs  = [];
        $seoOgIds  = [];
        foreach (self::ALLOWED_LOCALES as $locale) {
            $seoTitles[$locale] = (string) ($this->settingsGet($this->seoMetaTitleKey($locale)) ?? '');
            $seoDescs[$locale]  = (string) ($this->settingsGet($this->seoMetaDescriptionKey($locale)) ?? '');
            $ogRaw              = $this->settingsGet($this->seoOgImageIdKey($locale));
            $seoOgIds[$locale]  = is_numeric($ogRaw) && (int) $ogRaw > 0 ? (int) $ogRaw : null;
        }

        return new SiteSettingsDto(
            siteName: (string) ($this->settingsGet(self::KEY_SITE_NAME) ?? $siteConfig->siteName),
            siteDescription: (string) ($this->settingsGet(self::KEY_SITE_DESCRIPTION) ?? $siteConfig->siteDescription),
            defaultLocale: (string) ($this->settingsGet(self::KEY_DEFAULT_LOCALE) ?? $siteConfig->defaultLocale),
            primaryLocale: strtolower(trim($primary)),
            secondaryLocale: $secondary,
            timezone: (string) ($this->settingsGet(self::KEY_TIMEZONE) ?? $siteConfig->timezone),
            contactEmail: (string) ($this->settingsGet(self::KEY_CONTACT_EMAIL) ?? $siteConfig->contactEmail),
            seoMetaTitleByLocale: $seoTitles,
            seoMetaDescriptionByLocale: $seoDescs,
            seoOgImageIdByLocale: $seoOgIds,
        );
    }

    public function primaryLocale(): string
    {
        return $this->getSiteSettings()->primaryLocale;
    }

    public function secondaryLocale(): ?string
    {
        return $this->getSiteSettings()->secondaryLocale;
    }

    public function isSecondaryEnabled(): bool
    {
        return $this->secondaryLocale() !== null;
    }

    public function seoMetaTitleForLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return $this->getSiteSettings()->seoMetaTitleByLocale[$locale] ?? '';
    }

    public function seoMetaDescriptionForLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return $this->getSiteSettings()->seoMetaDescriptionByLocale[$locale] ?? '';
    }

    public function seoOgImageIdForLocale(string $locale): ?int
    {
        $locale = strtolower(trim($locale));
        $id     = $this->getSiteSettings()->seoOgImageIdByLocale[$locale] ?? null;

        return $id !== null && $id > 0 ? $id : null;
    }

    /**
     * @return array<string, string> Field errors when validation fails; empty on success.
     */
    #[\NoDiscard]
    public function updateSiteSettings(UpdateSiteSettingsDto $dto): array
    {
        $normalized = $this->normalize($dto);

        $errors = $this->validate($normalized);
        if ($errors !== []) {
            return $errors;
        }

        $this->settings->set(self::KEY_SITE_NAME, $normalized['site_name']);
        $this->settings->set(self::KEY_SITE_DESCRIPTION, $normalized['site_description']);
        $this->settings->set(self::KEY_DEFAULT_LOCALE, $normalized['default_locale']);
        $this->settings->set(self::KEY_PRIMARY_LOCALE, $normalized['primary_locale']);
        $this->settings->set(self::KEY_SECONDARY_LOCALE, $normalized['secondary_locale']);
        $this->settings->set(self::KEY_TIMEZONE, $normalized['timezone']);
        $this->settings->set(self::KEY_CONTACT_EMAIL, $normalized['contact_email']);

        foreach (self::ALLOWED_LOCALES as $locale) {
            $this->settings->set(
                $this->seoMetaTitleKey($locale),
                $normalized['seo_meta_title'][$locale],
            );
            $this->settings->set(
                $this->seoMetaDescriptionKey($locale),
                $normalized['seo_meta_description'][$locale],
            );
            $this->settings->set(
                $this->seoOgImageIdKey($locale),
                $normalized['seo_og_image_id'][$locale],
            );
        }

        // Locale / site SEO defaults affect public URL + SEO packages (ADR-025 §9 / §14).
        $this->publicContentCache->invalidatePublicContentPresentation();

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(UpdateSiteSettingsDto $dto): array
    {
        $secondary = strtolower(trim($dto->secondaryLocale));

        return [
            'site_name'             => trim($dto->siteName),
            'site_description'      => trim($dto->siteDescription),
            'default_locale'        => strtolower(trim($dto->defaultLocale)),
            'primary_locale'        => strtolower(trim($dto->primaryLocale)),
            'secondary_locale'      => $secondary,
            'timezone'              => trim($dto->timezone),
            'contact_email'         => strtolower(trim($dto->contactEmail)),
            'seo_meta_title'        => [
                'id' => trim($dto->seoMetaTitleId),
                'en' => trim($dto->seoMetaTitleEn),
            ],
            'seo_meta_description'  => [
                'id' => trim($dto->seoMetaDescriptionId),
                'en' => trim($dto->seoMetaDescriptionEn),
            ],
            'seo_og_image_id'       => [
                'id' => $this->normalizeOptionalInt($dto->seoOgImageIdId),
                'en' => $this->normalizeOptionalInt($dto->seoOgImageIdEn),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function validate(array $data): array
    {
        $rules = [
            'site_name' => [
                'label' => 'Site name',
                'rules' => 'required|max_length[' . self::SITE_NAME_MAX . ']',
            ],
            'site_description' => [
                'label' => 'Site description',
                'rules' => 'permit_empty|max_length[' . self::SITE_DESCRIPTION_MAX . ']',
            ],
            'default_locale' => [
                'label' => 'Default locale',
                'rules' => 'required|max_length[' . self::LOCALE_MAX . ']|in_list[' . implode(',', self::ALLOWED_LOCALES) . ']',
            ],
            'primary_locale' => [
                'label' => 'Primary locale',
                'rules' => 'required|max_length[' . self::LOCALE_MAX . ']|in_list[' . implode(',', self::ALLOWED_LOCALES) . ']',
            ],
            'secondary_locale' => [
                'label' => 'Secondary locale',
                'rules' => 'permit_empty|max_length[' . self::LOCALE_MAX . ']|in_list[' . implode(',', self::ALLOWED_LOCALES) . ']',
            ],
            'timezone' => [
                'label' => 'Timezone',
                'rules' => 'required|max_length[' . self::TIMEZONE_MAX . ']',
            ],
            'contact_email' => [
                'label' => 'Contact email',
                'rules' => 'required|valid_email|max_length[' . self::CONTACT_EMAIL_MAX . ']',
            ],
        ];

        $this->validation->reset();
        $this->validation->setRules($rules);

        if (! $this->validation->run([
            'site_name'          => $data['site_name'],
            'site_description'   => $data['site_description'],
            'default_locale'     => $data['default_locale'],
            'primary_locale'     => $data['primary_locale'],
            'secondary_locale'   => $data['secondary_locale'],
            'timezone'           => $data['timezone'],
            'contact_email'      => $data['contact_email'],
        ])) {
            /** @var array<string, string> $errors */
            $errors = $this->validation->getErrors();

            return $errors;
        }

        if ($data['secondary_locale'] !== '' && $data['secondary_locale'] === $data['primary_locale']) {
            return ['secondary_locale' => 'Secondary locale must differ from Primary locale.'];
        }

        if (! in_array($data['timezone'], timezone_identifiers_list(), true)) {
            return ['timezone' => 'The Timezone field must be a valid timezone identifier.'];
        }

        /** @var array<string, string> $seoMetaTitle */
        $seoMetaTitle = $data['seo_meta_title'];
        foreach ($seoMetaTitle as $locale => $value) {
            if (strlen($value) > self::SEO_META_TITLE_MAX) {
                return ['seo_meta_title_' . $locale => 'SEO meta title is too long.'];
            }
        }

        /** @var array<string, string> $seoMetaDescription */
        $seoMetaDescription = $data['seo_meta_description'];
        foreach ($seoMetaDescription as $locale => $value) {
            if (strlen($value) > self::SEO_META_DESC_MAX) {
                return ['seo_meta_description_' . $locale => 'SEO meta description is too long.'];
            }
        }

        return [];
    }

    private function seoMetaTitleKey(string $locale): string
    {
        return 'Site.seoMetaTitle.' . strtolower(trim($locale));
    }

    private function seoMetaDescriptionKey(string $locale): string
    {
        return 'Site.seoMetaDescription.' . strtolower(trim($locale));
    }

    private function seoOgImageIdKey(string $locale): string
    {
        return 'Site.seoOgImageId.' . strtolower(trim($locale));
    }

    private function normalizeOptionalInt(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || ! ctype_digit($value)) {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    /**
     * Persisted ACTIVE Theme id from Settings (ADR-022), or null when never set.
     */
    public function getPersistedActiveThemeId(): ?string
    {
        try {
            /** @var \CodeIgniter\Settings\Config\Settings $settingsConfig */
            $settingsConfig = config('Settings');

            $row = db_connect($settingsConfig->database['group'])
                ->table($settingsConfig->database['table'])
                ->where('class', 'Config\Theme')
                ->where('key', 'activeThemeId')
                ->get()
                ->getRowArray();
        } catch (\Throwable) {
            return null;
        }

        if ($row === null || ! isset($row['value']) || ! is_string($row['value'])) {
            return null;
        }

        $normalized = strtolower(trim($row['value']));

        return $normalized !== '' ? $normalized : null;
    }

    public function setPersistedActiveThemeId(string $themeId): void
    {
        $this->settings->set(self::KEY_ACTIVE_THEME_ID, strtolower(trim($themeId)));
    }

    public function siteTimezone(): string
    {
        $timezone = trim($this->getSiteSettings()->timezone);
        if ($timezone === '') {
            /** @var SiteConfig $site */
            $site     = config(SiteConfig::class);
            $timezone = trim($site->timezone);
        }

        if ($timezone === '' || ! in_array($timezone, timezone_identifiers_list(), true)) {
            return 'Asia/Jakarta';
        }

        return $timezone;
    }

    private function settingsGet(string $key): mixed
    {
        try {
            return $this->settings->get($key);
        } catch (\Throwable) {
            return null;
        }
    }
}
