<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Install\InstallService;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use RuntimeException;

/**
 * V2-005 password policy consistency across all password-setting surfaces (ADR-027 P0-3).
 *
 * @internal
 */
final class PasswordPolicyConsistencyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const INSTALL_PASSWORD = 'InstallPass99!';
    private const STRONG_PASSWORD  = 'Xk9$mQn2pL7#vR4wT8';
    private const TOKEN_TTL        = 3600;

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
        $this->injectThrottle(capacity: 100);
    }

    protected function tearDown(): void
    {
        if (auth('session')->loggedIn()) {
            auth('session')->logout();
        }

        Services::resetSingle('authThrottleService');

        parent::tearDown();
    }

    // --- A. Password change ---

    public function testPasswordChangeAcceptsValidPassword(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('policy.test.admin');

        $result = $this->postPasswordChange(self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $result->assertRedirect();
        $this->assertSame(0, $this->forceResetFlag());
    }

    public function testPasswordChangeRejectsWeakPassword(): void
    {
        $this->bootstrapAdmin();
        $user = $this->findUser('policy.test.admin');
        $weak = $this->weakPasswordFor($user);
        $this->loginAs('policy.test.admin');

        $result = $this->postPasswordChange($weak, $weak);

        $result->assertStatus(200);
        $this->assertStringNotContainsString($weak, (string) $result->response()->getBody());
        $this->assertSame(1, $this->forceResetFlag());
    }

    public function testPasswordChangeRejectsConfirmationMismatch(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('policy.test.admin');

        $result = $this->postPasswordChange(self::STRONG_PASSWORD, 'MismatchPass99!Xk');

        $result->assertStatus(200);
        $this->assertStringContainsString('does not match', (string) $result->response()->getBody());
        $this->assertSame(1, $this->forceResetFlag());
    }

    public function testPasswordChangeKeepsForceResetAfterFailure(): void
    {
        $this->bootstrapAdmin();
        $user = $this->findUser('policy.test.admin');
        $weak = $this->weakPasswordFor($user);
        $this->loginAs('policy.test.admin');

        $this->postPasswordChange($weak, $weak);

        $this->assertSame(1, $this->forceResetFlag());
    }

    public function testPasswordChangeClearsForceResetAfterSuccess(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('policy.test.admin');

        $this->postPasswordChange(self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $this->assertSame(0, $this->forceResetFlag());
    }

    public function testOldPasswordFailsAfterPasswordChange(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('policy.test.admin');
        $this->postPasswordChange(self::STRONG_PASSWORD, self::STRONG_PASSWORD);
        auth('session')->logout();

        $result = $this->postWithCsrf('cp', [
            'username' => 'policy.test.admin',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $this->assertStringContainsString('Invalid username or password', (string) $result->response()->getBody());
    }

    public function testNewPasswordWorksAfterPasswordChange(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('policy.test.admin');
        $this->postPasswordChange(self::STRONG_PASSWORD, self::STRONG_PASSWORD);
        auth('session')->logout();

        $result = $this->postWithCsrf('cp', [
            'username' => 'policy.test.admin',
            'password' => self::STRONG_PASSWORD,
        ]);

        $result->assertRedirect();
    }

    // --- B. Password reset ---

    public function testPasswordResetAcceptsValidPassword(): void
    {
        $this->bootstrapAdmin();
        $token = $this->issueResetToken('policy.test.admin');

        $result = $this->postResetVerify($token, self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $this->assertStringContainsString('Password updated.', (string) $result->response()->getBody());
    }

    public function testPasswordResetRejectsWeakPassword(): void
    {
        $this->bootstrapAdmin();
        $user  = $this->findUser('policy.test.admin');
        $weak  = $this->weakPasswordFor($user);
        $token = $this->issueResetToken('policy.test.admin');

        $result = $this->postResetVerify($token, $weak, $weak);

        $result->assertStatus(200);
        $this->assertStringNotContainsString($weak, (string) $result->response()->getBody());
        $this->assertNotNull(cache()->get('auth.reset.' . $token));
    }

    public function testPasswordResetRejectsConfirmationMismatch(): void
    {
        $this->bootstrapAdmin();
        $token = $this->issueResetToken('policy.test.admin');

        $result = $this->postResetVerify($token, self::STRONG_PASSWORD, 'MismatchPass99!Xk');

        $result->assertStatus(200);
        $this->assertStringContainsString('does not match', (string) $result->response()->getBody());
        $this->assertNotNull(cache()->get('auth.reset.' . $token));
    }

    public function testPasswordResetRejectsInvalidToken(): void
    {
        $result = $this->postResetVerify('invalid-token', self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $this->assertStringContainsString('Unable to reset password.', (string) $result->response()->getBody());
    }

    public function testPasswordResetRejectsExpiredToken(): void
    {
        $this->bootstrapAdmin();
        $token  = 'expired-policy-token';
        $userId = (int) $this->findUser('policy.test.admin')->id;
        cache()->save('auth.reset.' . $token, $userId, 1);
        cache()->delete('auth.reset.' . $token);

        $result = $this->postResetVerify($token, self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $this->assertStringContainsString('Unable to reset password.', (string) $result->response()->getBody());
    }

    public function testWeakPasswordDoesNotConsumeResetToken(): void
    {
        $this->bootstrapAdmin();
        $user  = $this->findUser('policy.test.admin');
        $weak  = $this->weakPasswordFor($user);
        $token = $this->issueResetToken('policy.test.admin');

        $this->postResetVerify($token, $weak, $weak);

        $this->assertNotNull(cache()->get('auth.reset.' . $token));
    }

    public function testSuccessfulResetConsumesToken(): void
    {
        $this->bootstrapAdmin();
        $token = $this->issueResetToken('policy.test.admin');

        $this->postResetVerify($token, self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $this->assertNull(cache()->get('auth.reset.' . $token));
    }

    public function testPasswordResetClearsForceReset(): void
    {
        $this->bootstrapAdmin();
        $token = $this->issueResetToken('policy.test.admin');

        $this->postResetVerify($token, self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $this->assertSame(0, $this->forceResetFlag());
    }

    public function testOldPasswordRejectedAfterReset(): void
    {
        $this->bootstrapAdmin();
        $token = $this->issueResetToken('policy.test.admin');
        $this->postResetVerify($token, self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $result = $this->postWithCsrf('cp', [
            'username' => 'policy.test.admin',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $this->assertStringContainsString('Invalid username or password', (string) $result->response()->getBody());
    }

    public function testNewPasswordAcceptedAfterReset(): void
    {
        $this->bootstrapAdmin();
        $token = $this->issueResetToken('policy.test.admin');
        $this->postResetVerify($token, self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $result = $this->postWithCsrf('cp', [
            'username' => 'policy.test.admin',
            'password' => self::STRONG_PASSWORD,
        ]);

        $result->assertRedirect();
    }

    // --- C. Admin recovery ---

    public function testAdminRecoveryAcceptsValidPassword(): void
    {
        $this->bootstrapReadyAdmin();

        $result = $this->postAdminRecovery(self::STRONG_PASSWORD, self::STRONG_PASSWORD);

        $this->assertStringContainsString('Password updated.', (string) $result->response()->getBody());
    }

    public function testAdminRecoveryRejectsWeakPassword(): void
    {
        $this->bootstrapReadyAdmin();
        $user = $this->findUser('policy.test.admin');
        $weak = $this->weakPasswordFor($user);

        $result = $this->postAdminRecovery($weak, $weak);

        $result->assertStatus(200);
        $this->assertStringNotContainsString($weak, (string) $result->response()->getBody());
        $this->assertStringNotContainsString('Password updated.', (string) $result->response()->getBody());
    }

    public function testAdminRecoveryRejectsConfirmationMismatch(): void
    {
        $this->bootstrapReadyAdmin();

        $result = $this->postAdminRecovery(self::STRONG_PASSWORD, 'MismatchPass99!Xk');

        $result->assertStatus(200);
        $this->assertStringContainsString('does not match', (string) $result->response()->getBody());
    }

    // --- D. User creation ---

    public function testUserCreationAcceptsValidInitialPassword(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('policy.test.admin');

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'staff.policy',
            'email'            => 'staff.policy@example.com',
            'password'         => self::STRONG_PASSWORD,
            'password_confirm' => self::STRONG_PASSWORD,
            'group'            => 'editor',
            'is_active'        => '1',
        ]);

        $result->assertRedirect();
        $this->assertNotNull($this->findUser('staff.policy'));
    }

    public function testUserCreationRejectsWeakInitialPassword(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('policy.test.admin');
        $probe = new User(['id' => 1, 'username' => 'staff.weak']);
        $weak  = $this->weakPasswordFor($probe);

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'staff.weak',
            'email'            => 'staff.weak@example.com',
            'password'         => $weak,
            'password_confirm' => $weak,
            'group'            => 'editor',
            'is_active'        => '1',
        ]);

        $result->assertStatus(200);
        $this->assertStringNotContainsString($weak, (string) $result->response()->getBody());
        $user = model(UserModel::class)->where('username', 'staff.weak')->first();
        $this->assertNull($user);
    }

    public function testUserCreationNeverReturnsPasswordInResponse(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('policy.test.admin');

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'staff.nopw',
            'email'            => 'staff.nopw@example.com',
            'password'         => self::STRONG_PASSWORD,
            'password_confirm' => self::STRONG_PASSWORD,
            'group'            => 'contributor',
            'is_active'        => '1',
        ]);

        $body = (string) $result->response()->getBody();
        $this->assertStringNotContainsString(self::STRONG_PASSWORD, $body);
    }

    public function testUserCreationNeverReturnsPasswordHashInResponse(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('policy.test.admin');

        $this->postWithCsrf('admin/users', [
            'username'         => 'staff.nohash',
            'email'            => 'staff.nohash@example.com',
            'password'         => self::STRONG_PASSWORD,
            'password_confirm' => self::STRONG_PASSWORD,
            'group'            => 'contributor',
            'is_active'        => '1',
        ]);

        $identity = db_connect()->table('auth_identities')
            ->join('users', 'users.id = auth_identities.user_id')
            ->where('users.username', 'staff.nohash')
            ->get()
            ->getRowArray();
        $this->assertNotNull($identity);
        $hash = (string) ($identity['secret2'] ?? '');
        $this->assertNotSame('', $hash);

        $list = $this->get('admin/users');
        $this->assertStringNotContainsString($hash, (string) $list->response()->getBody());
    }

    // --- E. Installation ---

    public function testInitialAdminInstallStillWorks(): void
    {
        $result = Services::installService(getShared: false)->install([
            'username' => 'bootstrap.policy.admin',
            'email'    => 'bootstrap.policy.admin@example.com',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $this->assertSame('fresh', $result['status']);
        $this->assertTrue($result['admin_created']);
    }

    public function testInstallRejectsWeakUserSuppliedPassword(): void
    {
        $probe = new User(['id' => 1, 'username' => 'install.weak']);
        $weak  = $this->weakPasswordFor($probe);
        $installer = Services::installService(getShared: false);

        try {
            $installer->install([
                'username' => 'install.weak',
                'email'    => 'install.weak@example.com',
                'password' => $weak,
            ]);
            $this->fail('Expected RuntimeException for weak install password.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString($weak, $e->getMessage());
            $this->assertFalse($installer->adminExists());
        }
    }

    // --- F. Cross-surface consistency ---

    public function testSameWeakPasswordRejectedAcrossAllSurfaces(): void
    {
        $this->bootstrapAdmin();
        $user = $this->findUser('policy.test.admin');
        $weak = $this->weakPasswordFor($user);
        $this->assertFalse(service('passwords')->check($weak, $user)->isOK());

        $this->loginAs('policy.test.admin');
        $change = $this->postPasswordChange($weak, $weak);
        $this->assertSame(200, $change->response()->getStatusCode());

        $token = $this->issueResetToken('policy.test.admin');
        $reset = $this->postResetVerify($token, $weak, $weak);
        $this->assertSame(200, $reset->response()->getStatusCode());
        $this->assertNotNull(cache()->get('auth.reset.' . $token));

        auth('session')->logout();
        $user->undoForcePasswordReset();
        model(UserModel::class)->save($user);

        $recovery = $this->postAdminRecovery($weak, $weak);
        $this->assertSame(200, $recovery->response()->getStatusCode());

        $this->loginAs('policy.test.admin');
        $create = $this->postWithCsrf('admin/users', [
            'username'         => 'staff.cross',
            'email'            => 'staff.cross@example.com',
            'password'         => $weak,
            'password_confirm' => $weak,
            'group'            => 'editor',
            'is_active'        => '1',
        ]);
        $this->assertSame(200, $create->response()->getStatusCode());

        $this->assertNotNull(
            Services::passwordPolicyService(getShared: false)
                ->validatePasswordForUsername($weak, 'cross.surface', 'cross.surface@example.com'),
        );
    }

    private function injectThrottle(int $capacity): void
    {
        $config = new \Config\AuthThrottle();
        $windowSeconds = 120;
        $config->login                = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->passwordResetRequest = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->passwordResetVerify  = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->adminRecovery        = ['capacity' => $capacity, 'seconds' => $windowSeconds];

        Services::injectMock(
            'authThrottleService',
            new \App\Services\Security\AuthThrottleService(
                Services::throttler(getShared: false),
                $config,
            ),
        );
    }

    private function bootstrapAdmin(): void
    {
        $result = Services::installService(getShared: false)->install([
            'username' => 'policy.test.admin',
            'email'    => 'policy.test.admin@example.com',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $this->assertSame('fresh', $result['status']);
    }

    private function bootstrapReadyAdmin(): void
    {
        $this->bootstrapAdmin();
        $admin = $this->findUser('policy.test.admin');
        $admin->undoForcePasswordReset();
        model(UserModel::class)->save($admin);
    }

    private function loginAs(string $username): void
    {
        auth('session')->login($this->findUser($username));
    }

    private function findUser(string $username): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);
        $user  = $users->where('username', $username)->first();
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function forceResetFlag(): int
    {
        $identity = db_connect()->table('auth_identities')
            ->where('type', Session::ID_TYPE_EMAIL_PASSWORD)
            ->get()
            ->getRowArray();

        return (int) ($identity['force_reset'] ?? 0);
    }

    private function issueResetToken(string $username): string
    {
        $token = bin2hex(random_bytes(32));
        cache()->save('auth.reset.' . $token, (int) $this->findUser($username)->id, self::TOKEN_TTL);

        return $token;
    }

    /**
     * @return \CodeIgniter\Test\TestResponse
     */
    private function postPasswordChange(string $new, string $confirm)
    {
        return $this->postWithCsrf('cp/password-change', [
            'password'         => self::INSTALL_PASSWORD,
            'password_new'     => $new,
            'password_confirm' => $confirm,
        ]);
    }

    /**
     * @return \CodeIgniter\Test\TestResponse
     */
    private function postResetVerify(string $token, string $password, string $confirm)
    {
        return $this->postWithCsrf('cp/password-reset/verify', [
            'token'            => $token,
            'password'         => $password,
            'password_confirm' => $confirm,
        ]);
    }

    /**
     * @return \CodeIgniter\Test\TestResponse
     */
    private function postAdminRecovery(string $password, string $confirm)
    {
        return $this->postWithCsrf('cp/admin-recovery', [
            'skey'             => (string) env('skey'),
            'username'         => 'policy.test.admin',
            'password'         => $password,
            'password_confirm' => $confirm,
        ]);
    }

    private function weakPasswordFor(User $user): string
    {
        foreach (['short', 'abc', '12345678', 'password'] as $candidate) {
            if (! service('passwords')->check($candidate, $user)->isOK()) {
                return $candidate;
            }
        }

        $this->fail('Unable to derive a Shield-rejected password fixture.');
    }

    /**
     * @param array<string, string> $data
     *
     * @return \CodeIgniter\Test\TestResponse
     */
    private function postWithCsrf(string $path, array $data)
    {
        $tokenName = config('Security')->tokenName;

        return $this->post($path, array_merge($data, [
            $tokenName => csrf_hash(),
        ]));
    }
}
