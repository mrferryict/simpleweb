<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Theme Admin HTTP access boundary (Phase 6 / Task 6.1B / ADR-022).
 *
 * @internal
 */
final class ThemeAccessTest extends CIUnitTestCase
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

    public function testGetAdminThemesRequiresAuthentication(): void
    {
        $result = $this->get('admin/themes');
        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testThemeNavGuardedByThemeActivatePermission(): void
    {
        $navigation = file_get_contents(APPPATH . 'Views/admin/_partials/navigation.php');
        $this->assertNotFalse($navigation);
        $this->assertStringContainsString("can('theme.activate')", $navigation);
        $this->assertStringContainsString('admin/themes', $navigation);
    }
}
