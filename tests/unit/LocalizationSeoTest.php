<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Dtos\UpdateSiteSettingsDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Models\UrlRedirectModel;
use App\Services\Localization\RobotsService;
use App\Services\Localization\SeoService;
use App\Services\Localization\SitemapService;
use App\Services\Localization\UrlRedirectService;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\SettingService;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Phase 7 / Task 7.1B — Localization, URL & SEO (ADR-024).
 *
 * @internal
 */
final class LocalizationSeoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /** @var list<string> */
    protected $namespace = ['App', 'CodeIgniter\Settings'];

    protected $migrate = true;
    protected $refresh = true;

    private PageService $pages;
    private PostService $posts;
    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();
        SettingsServices::settings(getShared: true)->flush();
        $this->pages    = Services::pageService(getShared: false);
        $this->posts    = Services::postService(getShared: false);
        $this->settings = Services::settingService(getShared: false);
    }

    protected function tearDown(): void
    {
        (void) $this->settings->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: 'en',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));
        SettingsServices::settings(getShared: true)->flush();
        Services::resetSingle('settingService');
        parent::tearDown();
    }

    public function testPrimaryAndSecondaryLocaleSettings(): void
    {
        $errors = $this->settings->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: 'en',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));
        $this->assertSame([], $errors);
        $this->assertSame('id', $this->settings->primaryLocale());
        $this->assertSame('en', $this->settings->secondaryLocale());
        $this->assertTrue($this->settings->isSecondaryEnabled());
    }

    public function testSecondaryDisabledViaEmptySettings(): void
    {
        $errors = $this->settings->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: '',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));
        $this->assertSame([], $errors);
        $this->assertNull($this->settings->secondaryLocale());
        $this->assertFalse($this->settings->isSecondaryEnabled());
    }

    public function testPublishedSlugChangeCreatesFlattenedRedirect(): void
    {
        $this->createPublishedPage('About', 'about', 'id');
        $pageId = $this->pages->listActive()[0]['page']->id;

        $errors = $this->pages->update($pageId, new PageWriteDto(
            title: 'About',
            slug: 'company',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        ));
        $this->assertSame([], $errors);

        $redirect = model(UrlRedirectModel::class)->findActiveBySourcePath('/about');
        $this->assertNotNull($redirect);
        $this->assertSame('/company', $redirect->target_path);
        $this->assertSame(301, $redirect->http_code);
    }

    public function testRedirectChainFlattening(): void
    {
        $redirects = Services::urlRedirectService(getShared: false);
        $redirects->recordPublishedSlugChange('/a', '/b', 'page', 1, 'id');
        $redirects->recordPublishedSlugChange('/b', '/c', 'page', 1, 'id');

        $target = $redirects->findActiveTarget('/a');
        $this->assertSame('/c', $target);
    }

    public function testSecondaryFallbackCanonicalViaSeoService(): void
    {
        $errors = $this->settings->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: 'en',
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));
        $this->assertSame([], $errors);

        $this->createPublishedPage('About', 'about', 'id');
        $pageId = $this->pages->listActive()[0]['page']->id;
        $view   = $this->pages->findPublishedForPublic('about', 'en');

        $this->assertNotNull($view);
        $this->assertTrue($view->isFallback);

        $seo = Services::seoService(getShared: false)->forPageView($view);
        $this->assertStringContainsString('/about', $seo->canonicalUrl);
        $this->assertSame([], array_filter(
            $seo->hreflangAlternates,
            static fn (array $row): bool => ($row['hreflang'] ?? '') === 'en',
        ));
    }

    public function testSitemapExcludesFallbackAndNonPublished(): void
    {
        $this->createPublishedPage('About', 'about', 'id');
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: 'Draft',
            slug: 'draft-only',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        )));

        $urls = Services::sitemapService(getShared: false)->collectUrls();
        $this->assertNotEmpty($urls);
        $this->assertTrue(
            array_any($urls, static fn (string $url): bool => str_ends_with($url, '/about')),
        );
        $this->assertFalse(
            array_any($urls, static fn (string $url): bool => str_contains($url, 'draft-only')),
        );
    }

    public function testRobotsTxtDisallowsAdminAndReferencesSitemap(): void
    {
        $text = Services::robotsService(getShared: false)->toText();
        $this->assertStringContainsString('Disallow: /admin/', $text);
        $this->assertStringContainsString('Disallow: /cp', $text);
        $this->assertStringContainsString('Sitemap:', $text);
        $this->assertStringNotContainsString('/var/www', $text);
    }

    public function testSitemapExcludesSecondaryLocaleWhenDisabled(): void
    {
        $this->configureLocales(secondaryLocale: '');
        $this->createPublishedPage('About ID', 'about-id', 'id');
        $this->createPublishedPage('About EN', 'about-en', 'en');
        $this->createPublishedPost('News ID', 'news-id', 'id');
        $this->createPublishedPost('News EN', 'news-en', 'en');

        $urls = Services::sitemapService(getShared: false)->collectUrls();

        $this->assertTrue(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/about-id')));
        $this->assertTrue(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/news/news-id')));
        $this->assertFalse(array_any($urls, static fn (string $url): bool => str_contains($url, '/en/')));
        $this->assertFalse(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/about-en')));
        $this->assertFalse(array_any($urls, static fn (string $url): bool => str_contains($url, 'news-en')));

        $this->assertSame(2, $this->countTranslations('page_translations'));
        $this->assertSame(2, $this->countTranslations('post_translations'));
    }

    public function testSitemapIncludesSecondaryLocaleWhenEnabled(): void
    {
        $this->configureLocales(secondaryLocale: 'en');
        $this->createPublishedPage('About ID', 'about-id', 'id');
        $this->createPublishedPage('About EN', 'about-en', 'en');
        $this->createPublishedPost('News ID', 'news-id', 'id');
        $this->createPublishedPost('News EN', 'news-en', 'en');

        $urls = Services::sitemapService(getShared: false)->collectUrls();

        $this->assertTrue(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/about-id')));
        $this->assertTrue(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/en/about-en')));
        $this->assertTrue(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/news/news-id')));
        $this->assertTrue(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/en/news/news-en')));
    }

    public function testSitemapPrimaryRemainsWhenSecondaryDisabled(): void
    {
        $this->configureLocales(secondaryLocale: '');
        $this->createPublishedPage('Primary Only', 'primary-only', 'id');

        $urls = Services::sitemapService(getShared: false)->collectUrls();

        $this->assertCount(1, $urls);
        $this->assertTrue(array_any($urls, static fn (string $url): bool => str_ends_with($url, '/primary-only')));
    }

    private function configureLocales(string $secondaryLocale): void
    {
        $errors = $this->settings->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'Site',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: $secondaryLocale,
            timezone: 'UTC',
            contactEmail: 'ok@example.com',
        ));
        $this->assertSame([], $errors);
    }

    private function createPublishedPost(string $title, string $slug, string $locale): void
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            manualAuthor: 'Author',
            contentPayload: ['body' => '<p>Body</p>'],
        )));
        $postId = $this->postIdForSlug($slug);
        db_connect()->table('posts')->where('id', $postId)->update([
            'status' => PostStatus::Published->value,
        ]);
    }

    private function postIdForSlug(string $slug): int
    {
        foreach ($this->posts->listActive() as $row) {
            if ($row['translation']?->slug === $slug) {
                return (int) $row['post']->id;
            }
        }

        $this->fail('Expected post slug not found: ' . $slug);
    }

    private function countTranslations(string $table): int
    {
        return (int) db_connect()->table($table)->countAllResults();
    }

    private function createPublishedPage(string $title, string $slug, string $locale): void
    {
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            templateKey: 'custom-page',
            parentId: null,
        )));
        $pageId = $this->pageIdForSlug($slug);
        db_connect()->table('pages')->where('id', $pageId)->update([
            'status' => PageStatus::Published->value,
        ]);
    }

    private function pageIdForSlug(string $slug): int
    {
        foreach ($this->pages->listActive() as $row) {
            if ($row['translation']?->slug === $slug) {
                return (int) $row['page']->id;
            }
        }

        $this->fail('Expected page slug not found: ' . $slug);
    }
}
