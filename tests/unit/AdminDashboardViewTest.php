<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AdminController;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Control Panel dashboard presentation (TH-006).
 *
 * @internal
 */
final class AdminDashboardViewTest extends CIUnitTestCase
{
    use ControllerTestTrait;
    use DatabaseTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    protected function tearDown(): void
    {
        Services::resetSingle('auth');
        parent::tearDown();
    }

    public function testDashboardRendersWelcomeAndModuleCards(): void
    {
        $admin = $this->adminUser();
        $this->injectAuth($admin);

        $result = $this->withUri('http://example.com/admin')
            ->controller(AdminController::class)
            ->execute('index');

        $result->assertOK();
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('Welcome back, adminweb', $body);
        $this->assertStringContainsString('admin-shell.css', $body);
        $this->assertStringContainsString('Open Pages', $body);
        $this->assertStringContainsString('Open Posts', $body);
        $this->assertStringContainsString('Open Media', $body);
        $this->assertStringContainsString('Open Menus', $body);
        $this->assertStringContainsString('Open Settings', $body);
        $this->assertStringContainsString('Open Themes', $body);
        $this->assertStringContainsString('Open Audit', $body);
        $this->assertStringContainsString('Create Page', $body);
        $this->assertStringContainsString('Create Post', $body);
        $this->assertStringNotContainsString('Placeholder dashboard content', $body);
        $this->assertStringNotContainsString('Modules will be added in later tasks', $body);
    }

    public function testDashboardNavigationIncludesCoreModules(): void
    {
        $admin = $this->adminUser();
        $this->injectAuth($admin);

        $result = $this->withUri('http://example.com/admin')
            ->controller(AdminController::class)
            ->execute('index');

        $body = (string) $result->response()->getBody();

        foreach (['Dashboard', 'Pages', 'Posts', 'Categories', 'Tags', 'Media', 'Menus', 'Settings', 'Themes', 'Audit'] as $label) {
            $this->assertStringContainsString('>' . $label . '</a>', $body, 'Missing nav link: ' . $label);
        }
    }

    public function testDashboardOmitsRestrictedCardsForEditor(): void
    {
        $editor = $this->userWithPermissions([
            'page.edit',
            'post.create',
            'media.upload',
            'menu.manage',
        ], 'editoruser');
        $this->injectAuth($editor);

        $result = $this->withUri('http://example.com/admin')
            ->controller(AdminController::class)
            ->execute('index');

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('Welcome back, editoruser', $body);
        $this->assertStringContainsString('Open Pages', $body);
        $this->assertStringNotContainsString('Open Settings', $body);
        $this->assertStringNotContainsString('Open Themes', $body);
        $this->assertStringNotContainsString('Open Audit', $body);
        $this->assertStringNotContainsString('Create Page', $body);
        $this->assertStringNotContainsString('admin/pages/new', $body);
        $this->assertStringNotContainsString('admin/themes', $body);
        $this->assertStringNotContainsString('admin/audit', $body);
    }

    public function testSharedAdminLayoutLoadsShellStylesheet(): void
    {
        $html = view('admin/pages/index', [
            'rows'                 => [],
            'isTrash'              => false,
            'success'              => null,
            'error'                => null,
            'canTrash'             => true,
            'canRestore'           => true,
            'canPermanentDelete'   => false,
        ]);

        $this->assertStringContainsString('admin-shell.css', $html);
        $this->assertStringContainsString('admin/pages', $html);
    }

    public function testNavigationGuardsThemesAndAuditByPermission(): void
    {
        $nav = file_get_contents(APPPATH . 'Views/admin/_partials/navigation.php');
        $this->assertNotFalse($nav);
        $this->assertStringContainsString("can('theme.activate')", $nav);
        $this->assertStringContainsString("can('audit.view')", $nav);
        $this->assertStringContainsString('admin/themes', $nav);
        $this->assertStringContainsString('admin/audit', $nav);
    }

    private function adminUser(): User
    {
        return new TestDashboardUser([
            'page.create',
            'page.edit',
            'page.publish',
            'post.create',
            'media.upload',
            'menu.manage',
            'site.manage',
            'theme.activate',
            'audit.view',
        ], 'adminweb');
    }

    /**
     * @param list<string> $permissions
     */
    private function userWithPermissions(array $permissions, string $username): User
    {
        return new TestDashboardUser($permissions, $username);
    }

    private function injectAuth(User $user): void
    {
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['user', 'id', 'setAuthenticator'])
            ->getMock();
        $auth->method('setAuthenticator')->willReturnSelf();
        $auth->method('user')->willReturn($user);
        $auth->method('id')->willReturn((int) $user->id);

        Services::injectMock('auth', $auth);
    }
}

/**
 * @internal
 */
final class TestDashboardUser extends User
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        private readonly array $permissions,
        string $username,
    ) {
        parent::__construct([
            'id'       => 1,
            'username' => $username,
        ]);
    }

    public function can(string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! in_array($permission, $this->permissions, true)) {
                return false;
            }
        }

        return $permissions !== [];
    }
}
