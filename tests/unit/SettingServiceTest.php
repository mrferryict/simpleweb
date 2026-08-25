<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\UpdateSiteSettingsDto;
use App\Services\SettingService;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class SettingServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'CodeIgniter\Settings';
    protected $migrate   = true;
    protected $refresh   = true;

    private SettingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        SettingsServices::settings(getShared: true)->flush();
        $this->service = Services::settingService(getShared: false);
    }

    public function testGetSiteSettingsReturnsDefaultsFromConfig(): void
    {
        $settings = $this->service->getSiteSettings();

        $this->assertSame('SMITE CMS', $settings->siteName);
        $this->assertSame('', $settings->siteDescription);
        $this->assertSame('id', $settings->defaultLocale);
        $this->assertSame('id', $settings->primaryLocale);
        $this->assertSame('en', $settings->secondaryLocale);
        $this->assertSame('Asia/Jakarta', $settings->timezone);
        $this->assertSame('', $settings->contactEmail);
    }

    public function testUpdatePersistsValidSettings(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Example Site',
            siteDescription: 'A short description',
            defaultLocale: 'en',
            primaryLocale: 'id',
            secondaryLocale: 'en',
            timezone: 'UTC',
            contactEmail: 'hello@example.com',
        ));

        $this->assertSame([], $errors);

        $settings = $this->service->getSiteSettings();
        $this->assertSame('Example Site', $settings->siteName);
        $this->assertSame('A short description', $settings->siteDescription);
        $this->assertSame('en', $settings->defaultLocale);
        $this->assertSame('id', $settings->primaryLocale);
        $this->assertSame('en', $settings->secondaryLocale);
        $this->assertSame('UTC', $settings->timezone);
        $this->assertSame('hello@example.com', $settings->contactEmail);
    }

    public function testUpdatePersistsPrimaryAndSecondaryLocale(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: '',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));

        $this->assertSame([], $errors);
        $settings = $this->service->getSiteSettings();
        $this->assertSame('id', $settings->primaryLocale);
        $this->assertNull($settings->secondaryLocale);
    }

    public function testPrimaryLocalePrecedenceOverDefaultLocaleFallback(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'en',
            secondaryLocale: '',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));
        $this->assertSame([], $errors);

        $this->assertSame('en', $this->service->primaryLocale());
    }

    public function testUpdateRejectsMissingSiteName(): void
    {
        $before = $this->service->getSiteSettings();

        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: '',
            siteDescription: 'ok',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: '',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));

        $this->assertArrayHasKey('site_name', $errors);
        $this->assertSame($before->siteName, $this->service->getSiteSettings()->siteName);
    }

    public function testUpdateRejectsInvalidTimezone(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: '',
            timezone: 'Not/A_Real_Zone',
            contactEmail: 'ok@example.com',
        ));

        $this->assertArrayHasKey('timezone', $errors);
        $this->assertNotSame('Not/A_Real_Zone', $this->service->getSiteSettings()->timezone);
    }

    public function testUpdateRejectsInvalidContactEmail(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: '',
            timezone: 'UTC',
            contactEmail: 'not-an-email',
        ));

        $this->assertArrayHasKey('contact_email', $errors);
        $this->assertSame('', $this->service->getSiteSettings()->contactEmail);
    }

    public function testUpdateRejectsInvalidLocale(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'fr',
            primaryLocale: 'id',
            secondaryLocale: '',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));

        $this->assertArrayHasKey('default_locale', $errors);
    }

    public function testUpdateRejectsInvalidPrimaryLocale(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'fr',
            secondaryLocale: '',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));

        $this->assertArrayHasKey('primary_locale', $errors);
    }

    public function testUpdateNormalizesContactEmail(): void
    {
        $errors = $this->service->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'ID',
            primaryLocale: 'id',
            secondaryLocale: 'en',
            timezone: 'Asia/Jakarta',
            contactEmail: 'Admin@Example.COM',
        ));

        $this->assertSame([], $errors);
        $saved = $this->service->getSiteSettings();
        $this->assertSame('id', $saved->defaultLocale);
        $this->assertSame('admin@example.com', $saved->contactEmail);
    }
}
