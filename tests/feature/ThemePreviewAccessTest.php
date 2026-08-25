<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Theme Preview HTTP boundaries (Phase 6 / Task 6.2B / ADR-023).
 *
 * @internal
 */
final class ThemePreviewAccessTest extends CIUnitTestCase
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

    public function testPreviewRouteRequiresAuthentication(): void
    {
        $result = $this->get('admin/preview/theme/classic/page/1');
        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testNoPublicPreviewRoute(): void
    {
        try {
            $result = $this->get('preview/theme/classic/page/1');
            $this->assertTrue(in_array($result->response()->getStatusCode(), [404, 302, 303], true));
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testNoPostPreviewRoute(): void
    {
        try {
            $result = $this->call('POST', 'admin/preview/theme/classic/page/1');
            $this->assertTrue(in_array($result->response()->getStatusCode(), [404, 302, 303, 403], true));
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testPublicPageRouteStillRegistered(): void
    {
        $routesFile = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertNotFalse($routesFile);
        $this->assertStringContainsString("Site\\PageController::show/\$1", $routesFile);
        $this->assertStringContainsString("Site\\PageController::showEn/\$1", $routesFile);
    }
}
