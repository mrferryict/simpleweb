<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Demo\DemoContentService;
use App\Services\Install\InstallService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Public rendering of cms:demo starter content (TH-004).
 *
 * @internal
 */
final class DemoContentPublicRenderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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

    protected function setUp(): void
    {
        parent::setUp();
        Services::installService(getShared: false)->install([
            'username' => 'demo.public.admin',
            'email'    => 'demo.public.admin@example.com',
            'password' => 'ChangeMeNow99!',
        ]);
        Services::demoContentService(getShared: false)->install();
    }

    public function testAboutPageRendersWithTheme2026(): void
    {
        $result = $this->get('about');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Tentang Kami', $body);
        $this->assertStringContainsString('app.css', $body);
    }

    public function testContactPageRenders(): void
    {
        $result = $this->get('contact');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Hubungi Kami', $body);
    }

    public function testBeritaNewsLandingPageRenders(): void
    {
        $result = $this->get('berita');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Berita', $body);
        $this->assertStringContainsString('app.css', $body);
    }

    public function testTheme2026SiteNavigationLinksNewsToBerita(): void
    {
        $result = $this->get('about');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('>News</a>', $body);
        $this->assertStringContainsString('berita', $body);
        $this->assertStringContainsString('about', $body);
        $this->assertStringContainsString('contact', $body);
    }

    public function testReservedNewsPathDoesNotResolveAsPage(): void
    {
        try {
            $this->get('news');
            $this->fail('Expected /news to be not found.');
        } catch (PageNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testWelcomePostRendersAtNewsSlugPath(): void
    {
        $result = $this->get('news/welcome');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Welcome to SMITE CMS', $body);
    }
}
