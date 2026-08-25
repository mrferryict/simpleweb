<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Dtos\SiteSettingsDto;
use App\Dtos\UpdateSiteSettingsDto;
use App\Services\SettingService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Control Panel Site Settings (Phase 2 / Task 2.1, Phase 7 / ADR-024).
 */
class SettingsController extends BaseController
{
    public function index(): ResponseInterface|RedirectResponse|string
    {
        if ($this->request->is('post')) {
            return $this->save();
        }

        return view('admin/settings/index', [
            'settings' => $this->settingService()->getSiteSettings()->toFormArray(),
            'errors'   => [],
            'success'  => session()->getFlashdata('success'),
        ]);
    }

    private function save(): ResponseInterface|RedirectResponse|string
    {
        $dto = new UpdateSiteSettingsDto(
            siteName: (string) ($this->request->getPost('site_name') ?? ''),
            siteDescription: (string) ($this->request->getPost('site_description') ?? ''),
            defaultLocale: (string) ($this->request->getPost('default_locale') ?? ''),
            primaryLocale: (string) ($this->request->getPost('primary_locale') ?? ''),
            secondaryLocale: (string) ($this->request->getPost('secondary_locale') ?? ''),
            timezone: (string) ($this->request->getPost('timezone') ?? ''),
            contactEmail: (string) ($this->request->getPost('contact_email') ?? ''),
            seoMetaTitleId: (string) ($this->request->getPost('seo_meta_title_id') ?? ''),
            seoMetaTitleEn: (string) ($this->request->getPost('seo_meta_title_en') ?? ''),
            seoMetaDescriptionId: (string) ($this->request->getPost('seo_meta_description_id') ?? ''),
            seoMetaDescriptionEn: (string) ($this->request->getPost('seo_meta_description_en') ?? ''),
            seoOgImageIdId: (string) ($this->request->getPost('seo_og_image_id_id') ?? ''),
            seoOgImageIdEn: (string) ($this->request->getPost('seo_og_image_id_en') ?? ''),
        );

        $errors = $this->settingService()->updateSiteSettings($dto);
        if ($errors !== []) {
            return view('admin/settings/index', [
                'settings' => (new SiteSettingsDto(
                    siteName: $dto->siteName,
                    siteDescription: $dto->siteDescription,
                    defaultLocale: $dto->defaultLocale,
                    primaryLocale: $dto->primaryLocale,
                    secondaryLocale: $dto->secondaryLocale !== '' ? $dto->secondaryLocale : null,
                    timezone: $dto->timezone,
                    contactEmail: $dto->contactEmail,
                    seoMetaTitleByLocale: [
                        'id' => $dto->seoMetaTitleId,
                        'en' => $dto->seoMetaTitleEn,
                    ],
                    seoMetaDescriptionByLocale: [
                        'id' => $dto->seoMetaDescriptionId,
                        'en' => $dto->seoMetaDescriptionEn,
                    ],
                    seoOgImageIdByLocale: [
                        'id' => is_numeric($dto->seoOgImageIdId) && (int) $dto->seoOgImageIdId > 0 ? (int) $dto->seoOgImageIdId : null,
                        'en' => is_numeric($dto->seoOgImageIdEn) && (int) $dto->seoOgImageIdEn > 0 ? (int) $dto->seoOgImageIdEn : null,
                    ],
                ))->toFormArray(),
                'errors'  => $errors,
                'success' => null,
            ]);
        }

        return redirect()->to('/admin/settings')->with('success', 'Site settings saved.');
    }

    private function settingService(): SettingService
    {
        return service('settingService');
    }
}
