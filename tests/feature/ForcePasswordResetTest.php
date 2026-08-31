<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Services\Install\InstallService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Filters as FiltersConfig;
use Config\Services;

/**
 * First-login force_reset enforcement (TH-015).
 *
 * @internal
 */
final class ForcePasswordResetTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const INSTALL_PASSWORD = 'InstallPass99!';
    private const NEW_PASSWORD     = 'NewSecurePass99!';

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

    protected function tearDown(): void
    {
        if (auth('session')->loggedIn()) {
            auth('session')->logout();
        }

        parent::tearDown();
    }

    public function testForceResetFilterIsRegistered(): void
    {
        /** @var FiltersConfig $filters */
        $filters = config(FiltersConfig::class);
        $this->assertArrayHasKey('force-reset', $filters->aliases);
        $this->assertSame(
            \CodeIgniter\Shield\Filters\ForcePasswordResetFilter::class,
            $filters->aliases['force-reset'],
        );
    }

    public function testFreshInstallLeavesForceResetSet(): void
    {
        $this->bootstrapAdmin();

        $identity = db_connect()->table('auth_identities')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->get()
            ->getRowArray();

        $this->assertNotNull($identity);
        $this->assertSame(1, (int) $identity['force_reset']);
    }

    public function testLoginRedirectsToPasswordChangeWhenForceResetIsSet(): void
    {
        $this->bootstrapAdmin();

        $result = $this->postWithCsrf('cp', [
            'username' => 'force.reset.admin',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $result->assertRedirect();
        $this->assertStringContainsString('password-change', $result->response()->getHeaderLine('Location'));
    }

    public function testForceResetUserCannotAccessAdminDashboard(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->get('admin');

        $result->assertRedirect();
        $this->assertStringContainsString('password-change', $result->response()->getHeaderLine('Location'));
    }

    public function testForceResetUserCannotAccessAdminPages(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->get('admin/pages');

        $result->assertRedirect();
        $this->assertStringContainsString('password-change', $result->response()->getHeaderLine('Location'));
    }

    public function testRepeatedAdminAccessDoesNotRedirectLoop(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $first  = $this->get('admin');
        $second = $this->get('admin');

        $this->assertStringContainsString('password-change', $first->response()->getHeaderLine('Location'));
        $this->assertStringContainsString('password-change', $second->response()->getHeaderLine('Location'));
        $this->assertStringNotContainsString('admin', strtolower($first->response()->getHeaderLine('Location')));
    }

    public function testForceResetUserCanAccessPasswordChangeScreen(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->get('cp/password-change');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Change your password', $body);
        $this->assertStringContainsString('name="password_new"', $body);
        $this->assertStringContainsString('auth.css', $body);
        $this->assertStringNotContainsString('admin-sidebar', $body);
        $this->assertStringNotContainsString('admin-layout', $body);
    }

    public function testPasswordChangeClearsForceResetAndAllowsAdmin(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $change = $this->postWithCsrf('cp/password-change', [
            'password'         => self::INSTALL_PASSWORD,
            'password_new'     => self::NEW_PASSWORD,
            'password_confirm' => self::NEW_PASSWORD,
        ]);

        $change->assertRedirect();
        $this->assertStringContainsString('admin', $change->response()->getHeaderLine('Location'));

        $identity = db_connect()->table('auth_identities')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->get()
            ->getRowArray();
        $this->assertSame(0, (int) ($identity['force_reset'] ?? 1));

        $dashboard = $this->get('admin');
        $dashboard->assertStatus(200);
        $this->assertStringContainsString('Dashboard', (string) $dashboard->response()->getBody());
    }

    public function testInvalidPasswordIsRejected(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->postWithCsrf('cp/password-change', [
            'password'         => self::INSTALL_PASSWORD,
            'password_new'     => 'short',
            'password_confirm' => 'short',
        ]);

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Change your password', $body);
        $this->assertStringNotContainsString('short', $body);

        $identity = db_connect()->table('auth_identities')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->get()
            ->getRowArray();
        $this->assertSame(1, (int) ($identity['force_reset'] ?? 0));
    }

    public function testPasswordConfirmationMismatchIsRejected(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->postWithCsrf('cp/password-change', [
            'password'         => self::INSTALL_PASSWORD,
            'password_new'     => self::NEW_PASSWORD,
            'password_confirm' => 'MismatchPass99!',
        ]);

        $result->assertStatus(200);
        $this->assertStringContainsString('does not match', (string) $result->response()->getBody());
    }

    public function testPasswordChangeRequiresCsrf(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        try {
            $result = $this->post('cp/password-change', [
                'password'         => self::INSTALL_PASSWORD,
                'password_new'     => self::NEW_PASSWORD,
                'password_confirm' => self::NEW_PASSWORD,
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testOldPasswordFailsAfterChange(): void
    {
        $this->bootstrapAdmin();
        $this->changePasswordWhileLoggedIn();

        auth('session')->logout();

        $result = $this->postWithCsrf('cp', [
            'username' => 'force.reset.admin',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $result->assertStatus(200);
        $this->assertStringContainsString('Invalid username or password', (string) $result->response()->getBody());
    }

    public function testNewPasswordSucceedsAfterChange(): void
    {
        $this->bootstrapAdmin();
        $this->changePasswordWhileLoggedIn();

        auth('session')->logout();

        $result = $this->postWithCsrf('cp', [
            'username' => 'force.reset.admin',
            'password' => self::NEW_PASSWORD,
        ]);

        $result->assertRedirect();
        $this->assertStringContainsString('admin', $result->response()->getHeaderLine('Location'));
    }

    public function testForceResetUserCanLogout(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->get('logout');

        $result->assertRedirect();
        $this->assertStringContainsString('cp', $result->response()->getHeaderLine('Location'));
        $this->assertFalse(auth('session')->loggedIn());
    }

    public function testUserWithoutForceResetCanAccessAdmin(): void
    {
        $this->bootstrapAdmin();
        $user = $this->findUser('force.reset.admin');
        $user->undoForcePasswordReset();
        $this->loginAs('force.reset.admin');

        $result = $this->get('admin');

        $result->assertStatus(200);
        $this->assertStringContainsString('Dashboard', (string) $result->response()->getBody());
    }

    public function testPasswordChangeWritesAuditWithoutSecrets(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $this->postWithCsrf('cp/password-change', [
            'password'         => self::INSTALL_PASSWORD,
            'password_new'     => self::NEW_PASSWORD,
            'password_confirm' => self::NEW_PASSWORD,
        ]);

        $row = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::PasswordChanged->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $metadata = (string) ($row['metadata'] ?? '');
        $this->assertStringContainsString('force_password_change', $metadata);
        $this->assertStringNotContainsString(self::NEW_PASSWORD, $metadata);
        $this->assertStringNotContainsString(self::INSTALL_PASSWORD, $metadata);
    }

    private function bootstrapAdmin(): void
    {
        $result = Services::installService(getShared: false)->install([
            'username' => 'force.reset.admin',
            'email'    => 'force.reset.admin@example.com',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $this->assertSame('fresh', $result['status']);
    }

    private function loginAs(string $username): void
    {
        $user = $this->findUser($username);
        auth('session')->login($user);
    }

    private function findUser(string $username): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);
        $user  = $users->where('username', $username)->first();
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function changePasswordWhileLoggedIn(): void
    {
        $this->loginAs('force.reset.admin');

        $result = $this->postWithCsrf('cp/password-change', [
            'password'         => self::INSTALL_PASSWORD,
            'password_new'     => self::NEW_PASSWORD,
            'password_confirm' => self::NEW_PASSWORD,
        ]);

        $result->assertRedirect();
    }

    /**
     * @param array<string, string> $data
     */
    private function postWithCsrf(string $path, array $data)
    {
        $tokenName = config('Security')->tokenName;

        return $this->post($path, array_merge($data, [
            $tokenName => csrf_hash(),
        ]));
    }
}
