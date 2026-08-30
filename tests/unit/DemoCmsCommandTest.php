<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Services\Demo\DemoContentService;
use App\Services\Install\InstallService;
use App\Services\PageService;
use App\Services\PostService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * cms:demo starter content contract (post-V1 / TH-004).
 *
 * @internal
 */
final class DemoCmsCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    private InstallService $installer;
    private DemoContentService $demo;
    private PageService $pages;
    private PostService $posts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installer = Services::installService(getShared: false);
        $this->demo      = Services::demoContentService(getShared: false);
        $this->pages     = Services::pageService(getShared: false);
        $this->posts     = Services::postService(getShared: false);
    }

    public function testCmsDemoCommandIsRegistered(): void
    {
        $commands = service('commands')->getCommands();
        $this->assertArrayHasKey('cms:demo', $commands);
        $this->assertSame(\App\Commands\DemoCms::class, $commands['cms:demo']['class']);
    }

    public function testFreshDemoInstallCreatesStarterContent(): void
    {
        $this->bootstrapAdmin();

        $result = $this->demo->install();

        $this->assertSame('installed', $result['status']);
        $this->assertSame(3, $result['pages_created']);
        $this->assertSame(1, $result['posts_created']);
        $this->assertSame([], $result['skipped']);

        $db = db_connect();
        $this->assertSame(3, $db->table('pages')->countAllResults());
        $this->assertSame(1, $db->table('posts')->countAllResults());
        $this->assertSame(1, $db->table('auth_groups_users')->where('group', 'admin')->countAllResults());
        $this->assertSame(1, $db->table('users')->countAllResults());

        foreach (['about', 'contact', DemoContentService::DEMO_NEWS_LANDING_SLUG] as $slug) {
            $translation = $db->table('page_translations')
                ->where('slug', $slug)
                ->where('locale', 'id')
                ->get()
                ->getRowArray();
            $this->assertNotNull($translation, 'Missing page translation for ' . $slug);

            $page = $db->table('pages')->where('id', (int) $translation['page_id'])->get()->getRowArray();
            $this->assertNotNull($page);
            $this->assertSame(PageStatus::Published->value, $page['status']);
        }

        $postTranslation = $db->table('post_translations')
            ->where('slug', 'welcome')
            ->where('locale', 'id')
            ->get()
            ->getRowArray();
        $this->assertNotNull($postTranslation);

        $post = $db->table('posts')->where('id', (int) $postTranslation['post_id'])->get()->getRowArray();
        $this->assertNotNull($post);
        $this->assertSame(PostStatus::Published->value, $post['status']);
    }

    public function testDemoInstallIsIdempotent(): void
    {
        $this->bootstrapAdmin();

        $first = $this->demo->install();
        $this->assertSame('installed', $first['status']);

        $second = $this->demo->install();
        $this->assertSame('already_installed', $second['status']);
        $this->assertSame(DemoContentService::ALREADY_INSTALLED_MESSAGE, $second['message']);
        $this->assertSame(0, $second['pages_created']);
        $this->assertSame(0, $second['posts_created']);

        $db = db_connect();
        $this->assertSame(3, $db->table('pages')->countAllResults());
        $this->assertSame(1, $db->table('posts')->countAllResults());
        $this->assertSame(3, $db->table('page_translations')->countAllResults());
        $this->assertSame(1, $db->table('post_translations')->countAllResults());
    }

    public function testTh004PartialInstallAddsBeritaNewsLanding(): void
    {
        $this->bootstrapAdmin();

        $legacyPages = [
            ['slug' => 'about', 'title' => 'About'],
            ['slug' => 'contact', 'title' => 'Contact'],
        ];
        foreach ($legacyPages as $page) {
            $this->assertSame([], $this->pages->create(new PageWriteDto(
                title: $page['title'],
                slug: $page['slug'],
                locale: 'id',
                templateKey: 'custom-page',
                parentId: null,
                contentPayload: ['body' => '<p>TH-004 legacy demo.</p>'],
            )));
            $pageId = (int) db_connect()->table('page_translations')->where('slug', $page['slug'])->get()->getRowArray()['page_id'];
            $this->assertSame([], $this->pages->publish($pageId));
        }

        $this->assertSame([], $this->posts->create(new \App\Dtos\PostWriteDto(
            title: 'Welcome to SMITE CMS',
            slug: DemoContentService::DEMO_POST_SLUG,
            locale: 'id',
            manualAuthor: 'SMITE CMS',
            contentPayload: ['body' => '<p>TH-004 legacy welcome post.</p>'],
            createdBy: 1,
        )));
        $postId = (int) db_connect()->table('post_translations')->where('slug', DemoContentService::DEMO_POST_SLUG)->get()->getRowArray()['post_id'];
        $this->assertSame([], $this->posts->publish($postId));

        $result = $this->demo->install();

        $this->assertSame('installed', $result['status']);
        $this->assertSame(1, $result['pages_created']);
        $this->assertSame(0, $result['posts_created']);
        $this->assertContains('page:about', $result['skipped']);
        $this->assertContains('page:contact', $result['skipped']);
        $this->assertContains('post:welcome', $result['skipped']);

        $db = db_connect();
        $this->assertSame(3, $db->table('pages')->countAllResults());
        $this->assertSame(1, $db->table('posts')->countAllResults());
        $berita = $db->table('page_translations')
            ->where('slug', DemoContentService::DEMO_NEWS_LANDING_SLUG)
            ->where('locale', 'id')
            ->get()
            ->getRowArray();
        $this->assertNotNull($berita);
        $this->assertSame('News', $berita['title']);
    }

    public function testExistingCustomerBeritaPageIsPreserved(): void
    {
        $this->bootstrapAdmin();

        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: 'Customer News',
            slug: DemoContentService::DEMO_NEWS_LANDING_SLUG,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['body' => '<p>Customer-owned news landing.</p>'],
        )));
        $beritaId = (int) db_connect()->table('page_translations')
            ->where('slug', DemoContentService::DEMO_NEWS_LANDING_SLUG)
            ->get()
            ->getRowArray()['page_id'];
        $this->assertSame([], $this->pages->publish($beritaId));

        $result = $this->demo->install();

        $this->assertSame('installed', $result['status']);
        $this->assertContains('page:' . DemoContentService::DEMO_NEWS_LANDING_SLUG, $result['skipped']);

        $db = db_connect();
        $this->assertSame(
            'Customer News',
            $db->table('page_translations')
                ->where('slug', DemoContentService::DEMO_NEWS_LANDING_SLUG)
                ->get()
                ->getRowArray()['title'] ?? null,
        );
    }

    public function testNoPageIsCreatedAtReservedNewsSlug(): void
    {
        $this->bootstrapAdmin();

        $this->demo->install();

        $db = db_connect();
        $this->assertNull(
            $db->table('page_translations')->where('slug', 'news')->get()->getRowArray(),
        );
        $this->assertNotNull(
            $db->table('page_translations')
                ->where('slug', DemoContentService::DEMO_NEWS_LANDING_SLUG)
                ->get()
                ->getRowArray(),
        );
    }

    public function testExistingCustomerAboutPageIsPreserved(): void
    {
        $this->bootstrapAdmin();

        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: 'Customer About',
            slug: 'about',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['body' => '<p>Customer-owned content must remain.</p>'],
        )));
        $aboutId = (int) db_connect()->table('page_translations')->where('slug', 'about')->get()->getRowArray()['page_id'];
        $this->assertSame([], $this->pages->publish($aboutId));

        $result = $this->demo->install();

        $this->assertSame('installed', $result['status']);
        $this->assertSame(2, $result['pages_created']);
        $this->assertSame(1, $result['posts_created']);
        $this->assertContains('page:about', $result['skipped']);

        $db = db_connect();
        $this->assertSame(3, $db->table('pages')->countAllResults());
        $this->assertSame(
            'Customer About',
            $db->table('page_translations')->where('slug', 'about')->get()->getRowArray()['title'] ?? null,
        );
        $this->assertStringContainsString(
            'Customer-owned content must remain.',
            (string) ($db->table('page_translations')->where('slug', 'about')->get()->getRowArray()['content_payload'] ?? ''),
        );
    }

    public function testDemoRequiresInstalledAdmin(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMITE CMS must be installed before demo content can be added.');

        $this->demo->install();
    }

    private function bootstrapAdmin(): void
    {
        $result = $this->installer->install([
            'username' => 'demo.bootstrap.admin',
            'email'    => 'demo.bootstrap.admin@example.com',
            'password' => 'ChangeMeNow99!',
        ]);

        $this->assertSame('fresh', $result['status']);
    }
}
