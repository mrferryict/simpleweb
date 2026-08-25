<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Dtos\PublicPageCacheEntry;
use App\Dtos\PublicPageViewDto;
use App\Dtos\PublicPostCacheEntry;
use App\Dtos\PublicPostViewDto;
use App\Dtos\PublicSeoViewDto;
use App\Dtos\UpdateSiteSettingsDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Services\Cache\PublicContentCacheInvalidator;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\SettingService;
use App\Services\Theme\ThemeService;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use RuntimeException;

/**
 * Public Page/Post File Cache population (Phase 8 / Task 8.1B / ADR-025).
 *
 * @internal
 */
final class PublicContentCachePopulationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    private PageService $pages;
    private PostService $posts;
    private SettingService $settings;
    private ThemeService $themes;
    private PublicContentCacheInvalidator $invalidator;

    protected function setUp(): void
    {
        parent::setUp();
        cache()->clean();
        $this->pages       = Services::pageService(getShared: false);
        $this->posts       = Services::postService(getShared: false);
        $this->settings    = Services::settingService(getShared: false);
        $this->themes      = Services::themeService(getShared: false);
        $this->invalidator = Services::publicContentCacheInvalidator(getShared: false);
        $this->enableSecondaryLocale();
    }

    public function testPageMissPopulatesThenHitReturnsSamePackage(): void
    {
        $id      = $this->createPublishedPage('About', 'cache-about');
        $themeId = $this->themes->activeThemeId();
        $key     = PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'cache-about');

        $this->assertFalse($this->cacheHas($key));

        $first = $this->pages->findPublishedPackageForPublic('cache-about', 'id');
        $this->assertNotNull($first);
        $this->assertSame($id, $first->view->pageId);
        $this->assertNotSame(false, cache()->get($key));
        $this->assertSame(PublicContentCacheInvalidator::TTL_SECONDS, 3600);

        db_connect()->table('page_translations')->where('page_id', $id)->where('locale', 'id')->update([
            'title' => 'Mutated Without Invalidation',
        ]);

        $second = $this->pages->findPublishedPackageForPublic('cache-about', 'id');
        $this->assertNotNull($second);
        $this->assertSame('About', $second->view->title);
        $this->assertSame($first->seo->canonicalUrl, $second->seo->canonicalUrl);
    }

    public function testPostMissPopulatesThenHitReturnsSamePackage(): void
    {
        $id      = $this->createPublishedPost('Hello', 'cache-hello');
        $themeId = $this->themes->activeThemeId();
        $key     = PublicContentCacheInvalidator::postPopulationKey($themeId, 'id', 'cache-hello');

        $first = $this->posts->findPublishedPackageForPublic('cache-hello', 'id');
        $this->assertNotNull($first);
        $this->assertNotSame(false, cache()->get($key));

        db_connect()->table('post_translations')->where('post_id', $id)->where('locale', 'id')->update([
            'title' => 'Mutated Post',
        ]);

        $second = $this->posts->findPublishedPackageForPublic('cache-hello', 'id');
        $this->assertNotNull($second);
        $this->assertSame('Hello', $second->view->title);
        $this->assertSame($first->seo->documentTitle, $second->seo->documentTitle);
    }

    public function testNonPublishedStatusesDoNotPopulatePageCache(): void
    {
        $themeId = $this->themes->activeThemeId();

        foreach ([
            PageStatus::Draft,
            PageStatus::Unpublished,
            PageStatus::Archived,
            PageStatus::Trash,
        ] as $status) {
            $slug = 'page-' . strtolower($status->value);
            $this->createPageWithStatus('X', $slug, $status);
            $this->assertNull($this->pages->findPublishedPackageForPublic($slug, 'id'));
            $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', $slug)));
        }
    }

    public function testNonPublishedStatusesDoNotPopulatePostCache(): void
    {
        $themeId = $this->themes->activeThemeId();

        foreach ([
            PostStatus::Draft,
            PostStatus::Unpublished,
            PostStatus::Archived,
            PostStatus::Trash,
            PostStatus::PendingReview,
        ] as $status) {
            $slug = 'post-' . strtolower(str_replace('_', '-', $status->value));
            $this->createPostWithStatus('X', $slug, $status);
            $this->assertNull($this->posts->findPublishedPackageForPublic($slug, 'id'));
            $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::postPopulationKey($themeId, 'id', $slug)));
        }
    }

    public function testNullLookupDoesNotPopulate(): void
    {
        $themeId = $this->themes->activeThemeId();
        $this->assertNull($this->pages->findPublishedPackageForPublic('missing-page', 'id'));
        $this->assertNull($this->posts->findPublishedPackageForPublic('missing-post', 'id'));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'missing-page')));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::postPopulationKey($themeId, 'id', 'missing-post')));
    }

    public function testRedirectResponseDoesNotPopulateContentCache(): void
    {
        $this->createPublishedPage('About', 'about-old');
        $pageId = $this->pages->listActive()[0]['page']->id;
        $this->assertSame([], $this->pages->update($pageId, new PageWriteDto(
            title: 'About',
            slug: 'about-new',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        )));

        $themeId = $this->themes->activeThemeId();
        cache()->delete(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'about-old'));
        cache()->delete(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'about-new'));

        $result = $this->get('about-old');
        $result->assertStatus(301);

        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'about-old')));
    }

    public function testFallbackLocaleUsesRequestedLocaleInKey(): void
    {
        $this->createPublishedPage('Primary Only', 'fallback-cache');
        $themeId = $this->themes->activeThemeId();

        $package = $this->pages->findPublishedPackageForPublic('fallback-cache', 'en');
        $this->assertNotNull($package);
        $this->assertTrue($package->view->isFallback);
        $this->assertSame('en', $package->view->requestedLocale);
        $this->assertTrue($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'en', 'fallback-cache')));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'fallback-cache')));
        $this->assertStringContainsString('/fallback-cache', $package->seo->canonicalUrl);
        $this->assertSame([], array_filter(
            $package->seo->hreflangAlternates,
            static fn (array $row): bool => ($row['hreflang'] ?? '') === 'en',
        ));
    }

    public function testDisabledSecondaryDoesNotPopulate(): void
    {
        $this->createPublishedPage('About', 'no-secondary-cache');
        $this->disableSecondaryLocale();
        $pages = Services::pageService(getShared: false);

        $themeId = $this->themes->activeThemeId();
        $this->assertNull($pages->findPublishedPackageForPublic('no-secondary-cache', 'en'));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'en', 'no-secondary-cache')));
    }

    public function testIdPrefixedPathRemainsRejected(): void
    {
        $this->createPublishedPage('About', 'id-path-page');

        try {
            $this->get('id/id-path-page');
            $this->fail('Expected /id/... to be rejected.');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException) {
            // Missing route and/or LocaleFilter — must not serve public content.
        }

        $themeId = $this->themes->activeThemeId();
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'id-path-page')));
    }

    public function testCachedPackageIncludesSeoAndThemeIdInKey(): void
    {
        $this->createPublishedPage('SEO Page', 'seo-cache-page', [
            'body'       => '<p>Hi</p>',
            'hero_title' => 'Hero',
        ]);
        $themeId = $this->themes->activeThemeId();
        $package = $this->pages->findPublishedPackageForPublic('seo-cache-page', 'id');

        $this->assertNotNull($package);
        $this->assertNotSame('', $package->seo->documentTitle);
        $this->assertNotSame('', $package->seo->canonicalUrl);
        $this->assertNotSame('', $package->seo->xDefaultUrl);
        $this->assertTrue($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'seo-cache-page')));
        $this->assertStringContainsString('.' . $themeId . '.', PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'seo-cache-page'));
    }

    public function testInvalidatePageRemovesAllLocaleAndSlugPopulationKeys(): void
    {
        $id = $this->createPublishedPage('Multi', 'multi-a');
        db_connect()->table('page_translations')->insert([
            'page_id'         => $id,
            'locale'          => 'en',
            'title'           => 'Multi EN',
            'slug'            => 'multi-a',
            'content_payload' => '{}',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $themeId = $this->themes->activeThemeId();
        $this->assertNotNull($this->pages->findPublishedPackageForPublic('multi-a', 'id'));
        $this->assertNotNull($this->pages->findPublishedPackageForPublic('multi-a', 'en'));

        $oldKey = PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'stale-slug');
        cache()->save($oldKey, ['type' => 'page'], 3600);
        $index = cache()->get(PublicContentCacheInvalidator::pageKey($id));
        $this->assertIsArray($index);
        $index[] = $oldKey;
        cache()->save(PublicContentCacheInvalidator::pageKey($id), array_values(array_unique($index)), 3600);

        $this->invalidator->invalidatePage($id);

        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'multi-a')));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pagePopulationKey($themeId, 'en', 'multi-a')));
        $this->assertFalse($this->cacheHas($oldKey));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pageKey($id)));
    }

    public function testInvalidatePostRemovesPopulationKeys(): void
    {
        $id = $this->createPublishedPost('Post Multi', 'post-multi');
        db_connect()->table('post_translations')->insert([
            'post_id'         => $id,
            'locale'          => 'en',
            'title'           => 'Post EN',
            'slug'            => 'post-multi',
            'content_payload' => json_encode(['body' => '<p>en</p>'], JSON_THROW_ON_ERROR),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $themeId = $this->themes->activeThemeId();
        $this->assertNotNull($this->posts->findPublishedPackageForPublic('post-multi', 'id'));
        $this->assertNotNull($this->posts->findPublishedPackageForPublic('post-multi', 'en'));

        $this->invalidator->invalidatePost($id);

        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::postPopulationKey($themeId, 'id', 'post-multi')));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::postPopulationKey($themeId, 'en', 'post-multi')));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::postKey($id)));
    }

    public function testThemeActivationInvalidatesContentPopulation(): void
    {
        $this->createPublishedPage('Theme Cache', 'theme-cache-page');
        $themeId = $this->themes->activeThemeId();
        $package = $this->pages->findPublishedPackageForPublic('theme-cache-page', 'id');
        $this->assertNotNull($package);
        $key = PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'theme-cache-page');
        $this->assertTrue($this->cacheHas($key));

        $this->assertSame([], $this->themes->activate('classic', $this->adminUser()));
        $this->assertFalse($this->cacheHas($key));
    }

    public function testLocaleSettingsChangeInvalidatesContentPopulation(): void
    {
        $this->createPublishedPage('Locale Cache', 'locale-cache-page');
        $themeId = $this->themes->activeThemeId();
        $package = $this->pages->findPublishedPackageForPublic('locale-cache-page', 'id');
        $this->assertNotNull($package);
        $key = PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'locale-cache-page');
        $this->assertTrue($this->cacheHas($key));

        $this->disableSecondaryLocale();
        $this->assertFalse($this->cacheHas($key));
    }

    public function testCorruptPackageFallsBackToDb(): void
    {
        $id      = $this->createPublishedPage('Corrupt', 'corrupt-page');
        $themeId = $this->themes->activeThemeId();
        $key     = PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'corrupt-page');
        cache()->save($key, ['type' => 'page', 'view' => ['broken' => true]], 3600);

        $package = $this->pages->findPublishedPackageForPublic('corrupt-page', 'id');
        $this->assertNotNull($package);
        $this->assertSame($id, $package->view->pageId);
        $this->assertSame('Corrupt', $package->view->title);
        $raw = cache()->get($key);
        $this->assertIsArray($raw);
        $this->assertSame('page', $raw['type'] ?? null);
        $this->assertArrayHasKey('seo', $raw);
    }

    public function testCacheReadFailureFallsBackToDb(): void
    {
        $this->createPublishedPage('Read Fail', 'read-fail-page');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willThrowException(new RuntimeException('read failed'));
        $cache->method('save')->willReturn(true);
        $cache->method('delete')->willReturn(true);

        Services::injectMock('cache', $cache);
        $pages = Services::pageService(getShared: false);

        $package = $pages->findPublishedPackageForPublic('read-fail-page', 'id');
        $this->assertNotNull($package);
        $this->assertSame('Read Fail', $package->view->title);

        Services::resetSingle('cache');
        Services::resetSingle('pageService');
        Services::resetSingle('publicContentCacheInvalidator');
    }

    public function testCacheWriteFailureDoesNotFailPublicLookup(): void
    {
        $this->createPublishedPage('Write Fail', 'write-fail-page');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->method('save')->willThrowException(new RuntimeException('write failed'));
        $cache->method('delete')->willReturn(true);

        Services::injectMock('cache', $cache);
        $pages = Services::pageService(getShared: false);

        $package = $pages->findPublishedPackageForPublic('write-fail-page', 'id');
        $this->assertNotNull($package);
        $this->assertSame('Write Fail', $package->view->title);

        Services::resetSingle('cache');
        Services::resetSingle('pageService');
        Services::resetSingle('publicContentCacheInvalidator');
    }

    public function testThemePreviewDoesNotReadOrWritePublicCache(): void
    {
        $id = $this->createPublishedPage('Preview', 'preview-cache-page');
        $themeId = $this->themes->activeThemeId();
        $key = PublicContentCacheInvalidator::pagePopulationKey($themeId, 'id', 'preview-cache-page');

        $preview = $this->pages->findForThemePreview($id, 'id', $themeId);
        $this->assertNotNull($preview);
        $this->assertFalse($this->cacheHas($key));
        $this->assertFalse($this->cacheHas(PublicContentCacheInvalidator::pageKey($id)));

        $seed = new PublicPageCacheEntry(
            view: new PublicPageViewDto(
                pageId: $id,
                title: 'Cached Stale',
                locale: 'id',
                slug: 'preview-cache-page',
                contentPayload: [],
                requestedLocale: 'id',
                isFallback: false,
                templateKey: 'custom-page',
            ),
            seo: new PublicSeoViewDto(
                documentTitle: 'Cached Stale',
                metaDescription: '',
                canonicalUrl: 'http://example.com/preview-cache-page',
                hreflangAlternates: [],
                xDefaultUrl: 'http://example.com/preview-cache-page',
                ogImageUrl: null,
            ),
        );
        $this->invalidator->savePagePackage($id, $themeId, 'id', 'preview-cache-page', $seed);
        $this->assertTrue($this->cacheHas($key));

        $previewAgain = $this->pages->findForThemePreview($id, 'id', $themeId);
        $this->assertNotNull($previewAgain);
        $this->assertSame('Preview', $previewAgain->title);
        $this->assertNotSame('Cached Stale', $previewAgain->title);
    }

    public function testInvalidatorSaveUsesTtl3600(): void
    {
        $this->assertSame(3600, PublicContentCacheInvalidator::TTL_SECONDS);

        $entry = new PublicPostCacheEntry(
            view: new PublicPostViewDto(
                postId: 99,
                title: 'T',
                manualAuthor: 'A',
                locale: 'id',
                slug: 'ttl-post',
                body: '<p>x</p>',
                requestedLocale: 'id',
                isFallback: false,
            ),
            seo: new PublicSeoViewDto(
                documentTitle: 'T',
                metaDescription: '',
                canonicalUrl: 'http://example.com/news/ttl-post',
                hreflangAlternates: [],
                xDefaultUrl: 'http://example.com/news/ttl-post',
                ogImageUrl: null,
            ),
        );

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->atLeastOnce())
            ->method('save')
            ->with(
                $this->anything(),
                $this->anything(),
                3600,
            )
            ->willReturn(true);
        $cache->method('get')->willReturn(null);

        $invalidator = new PublicContentCacheInvalidator($cache);
        $invalidator->savePostPackage(99, 'default', 'id', 'ttl-post', $entry);
    }

    private function cacheHas(string $key): bool
    {
        $value = cache()->get($key);

        return $value !== null && $value !== false;
    }

    private function enableSecondaryLocale(): void
    {
        $errors = $this->settings->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'SMITE CMS',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: 'en',
            timezone: 'Asia/Jakarta',
            contactEmail: 'ok@example.com',
        ));
        $this->assertSame([], $errors);
        cache()->clean();
    }

    private function disableSecondaryLocale(): void
    {
        $errors = $this->settings->updateSiteSettings(new UpdateSiteSettingsDto(
            siteName: 'SMITE CMS',
            siteDescription: '',
            defaultLocale: 'id',
            primaryLocale: 'id',
            secondaryLocale: '',
            timezone: 'Asia/Jakarta',
            contactEmail: 'ok@example.com',
        ));
        $this->assertSame([], $errors);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createPublishedPage(string $title, string $slug, array $payload = []): int
    {
        $errors = $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: $payload === [] ? ['body' => '<p>x</p>'] : $payload,
        ));
        $this->assertSame([], $errors);
        $id = $this->pages->listActive()[0]['page']->id;
        db_connect()->table('pages')->where('id', $id)->update([
            'status' => PageStatus::Published->value,
        ]);

        return $id;
    }

    private function createPageWithStatus(string $title, string $slug, PageStatus $status): int
    {
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [],
        )));
        $id = $this->pages->listActive()[0]['page']->id;
        db_connect()->table('pages')->where('id', $id)->update([
            'status'     => $status->value,
            'deleted_at' => $status === PageStatus::Trash ? date('Y-m-d H:i:s') : null,
        ]);

        return $id;
    }

    private function createPublishedPost(string $title, string $slug): int
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Author',
            contentPayload: ['body' => '<p>body</p>'],
        ));
        $this->assertSame([], $errors);
        $id = $this->posts->listActive()[0]['post']->id;
        db_connect()->table('posts')->where('id', $id)->update([
            'status' => PostStatus::Published->value,
        ]);

        return $id;
    }

    private function createPostWithStatus(string $title, string $slug, PostStatus $status): int
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Author',
        )));
        $id = $this->posts->listActive()[0]['post']->id;
        db_connect()->table('posts')->where('id', $id)->update([
            'status'     => $status->value,
            'deleted_at' => $status === PostStatus::Trash ? date('Y-m-d H:i:s') : null,
        ]);

        return $id;
    }

    private function adminUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturn(true);
        $user->method('__get')->willReturnMap([
            ['id', 1],
        ]);

        return $user;
    }

    protected function tearDown(): void
    {
        try {
            Services::resetSingle('cache');
            Services::resetSingle('pageService');
            Services::resetSingle('postService');
            Services::resetSingle('publicContentCacheInvalidator');
            service('settings')->forget('Theme.activeThemeId');
        } catch (\Throwable) {
        }

        parent::tearDown();
    }
}
