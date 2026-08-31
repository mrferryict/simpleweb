<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Services\UserAdminService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * V2-003 User Management UI (ADR-027 P0-1).
 *
 * @internal
 */
final class UserManagementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const INSTALL_PASSWORD = 'InstallPass99!';
    private const STAFF_PASSWORD   = 'Xk9$mQn2pL7#vR4wT8';

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

    public function testAdminWithUserManageCanOpenUserList(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $result = $this->get('admin/users');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Users', $body);
        $this->assertStringContainsString('Create User', $body);
        $this->assertStringContainsString('user.mgmt.admin', $body);
    }

    public function testAdminCanCreateEditor(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'staff.editor',
            'email'            => 'staff.editor@example.com',
            'password'         => self::STAFF_PASSWORD,
            'password_confirm' => self::STAFF_PASSWORD,
            'group'            => 'editor',
            'is_active'        => '1',
        ]);

        $result->assertRedirect();
        $this->assertStringContainsString('admin/users', $result->response()->getHeaderLine('Location'));

        $user = $this->findUser('staff.editor');
        $this->assertSame('editor', $this->primaryGroup((int) $user->id));
        $this->assertTrue((bool) $user->active);
    }

    public function testAdminCanCreateContributor(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'staff.contributor',
            'email'            => 'staff.contributor@example.com',
            'password'         => self::STAFF_PASSWORD,
            'password_confirm' => self::STAFF_PASSWORD,
            'group'            => 'contributor',
            'is_active'        => '1',
        ]);

        $result->assertRedirect();
        $user = $this->findUser('staff.contributor');
        $this->assertSame('contributor', $this->primaryGroup((int) $user->id));
    }

    public function testEditorCannotAccessUserManagement(): void
    {
        $this->bootstrapReadyAdmin();
        $this->createStaffViaService('access.editor', 'access.editor@example.com', 'editor');
        $this->loginAs('access.editor');

        $result = $this->get('admin/users');
        $status = $result->response()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 303, 403], true));
        $this->assertStringNotContainsString('Create User', (string) $result->response()->getBody());
    }

    public function testContributorCannotAccessUserManagement(): void
    {
        $this->bootstrapReadyAdmin();
        $this->createStaffViaService('access.contributor', 'access.contributor@example.com', 'contributor');
        $this->loginAs('access.contributor');

        $result = $this->get('admin/users');
        $status = $result->response()->getStatusCode();
        $this->assertTrue(in_array($status, [302, 303, 403], true));
        $this->assertStringNotContainsString('Create User', (string) $result->response()->getBody());
    }

    public function testUnauthorizedPostIsRejected(): void
    {
        $this->bootstrapReadyAdmin();

        try {
            $result = $this->post('admin/users', [
                'username' => 'intruder',
                'email'    => 'intruder@example.com',
                'group'    => 'contributor',
            ]);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
            if ($status < 400) {
                $this->assertStringContainsString('cp', $result->response()->getHeaderLine('Location'));
            }
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testCsrfProtectionWorks(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        try {
            $result = $this->post('admin/users', [
                'username'         => 'csrf.test',
                'email'            => 'csrf.test@example.com',
                'password'         => self::STAFF_PASSWORD,
                'password_confirm' => self::STAFF_PASSWORD,
                'group'            => 'contributor',
                'is_active'        => '1',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $this->createStaffViaService('first.staff', 'duplicate@example.com', 'contributor');

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'second.staff',
            'email'            => 'duplicate@example.com',
            'password'         => self::STAFF_PASSWORD,
            'password_confirm' => self::STAFF_PASSWORD,
            'group'            => 'contributor',
            'is_active'        => '1',
        ]);

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('already in use', $body);
    }

    public function testInvalidRoleIsRejected(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'bad.role',
            'email'            => 'bad.role@example.com',
            'password'         => self::STAFF_PASSWORD,
            'password_confirm' => self::STAFF_PASSWORD,
            'group'            => 'admin',
            'is_active'        => '1',
        ]);

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Only Editor or Contributor', $body);
    }

    public function testSecondAdminCannotBeCreated(): void
    {
        $this->testInvalidRoleIsRejected();
    }

    public function testExistingUserCannotBePromotedToAdmin(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $this->createStaffViaService('promote.test', 'promote.test@example.com', 'contributor');
        $staff = $this->findUser('promote.test');

        $result = $this->postWithCsrf('admin/users/' . $staff->id, [
            'email'     => 'promote.test@example.com',
            'group'     => 'admin',
            'is_active' => '1',
        ]);

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Only Editor or Contributor', $body);
        $this->assertSame('contributor', $this->primaryGroup((int) $staff->id));
    }

    public function testOnlyAdminCannotBeDeactivated(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $admin = $this->findUser('user.mgmt.admin');

        $result = $this->postWithCsrf('admin/users/' . $admin->id . '/deactivate', []);

        $result->assertRedirect();
        $this->assertStringContainsString('admin/users', $result->response()->getHeaderLine('Location'));

        $fresh = $this->findUser('user.mgmt.admin');
        $this->assertTrue((bool) $fresh->active);
    }

    public function testOnlyAdminCannotLoseAdminRoleViaUpdate(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $admin = $this->findUser('user.mgmt.admin');

        $result = $this->postWithCsrf('admin/users/' . $admin->id, [
            'email'     => 'user.mgmt.admin@example.com',
            'group'     => 'contributor',
            'is_active' => '1',
        ]);

        $result->assertRedirect();
        $this->assertSame('admin', $this->primaryGroup((int) $admin->id));
    }

    public function testActivationWorks(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $this->createStaffViaService('inactive.staff', 'inactive.staff@example.com', 'contributor');
        $staff = $this->findUser('inactive.staff');
        $staff->active = 0;
        model(UserModel::class)->save($staff);

        $result = $this->postWithCsrf('admin/users/' . $staff->id . '/activate', []);
        $result->assertRedirect();

        $fresh = $this->findUser('inactive.staff');
        $this->assertTrue((bool) $fresh->active);
    }

    public function testDeactivationWorks(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $this->createStaffViaService('deactivate.staff', 'deactivate.staff@example.com', 'contributor');
        $staff = $this->findUser('deactivate.staff');

        $result = $this->postWithCsrf('admin/users/' . $staff->id . '/deactivate', []);
        $result->assertRedirect();

        $fresh = $this->findUser('deactivate.staff');
        $this->assertFalse((bool) $fresh->active);
    }

    public function testUserCreatedAuditEventExists(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $this->postWithCsrf('admin/users', [
            'username'         => 'audit.created',
            'email'            => 'audit.created@example.com',
            'password'         => self::STAFF_PASSWORD,
            'password_confirm' => self::STAFF_PASSWORD,
            'group'            => 'contributor',
            'is_active'        => '1',
        ]);

        $row = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::UserCreated->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $metadata = (string) ($row['metadata'] ?? '');
        $this->assertStringContainsString('audit.created', $metadata);
        $this->assertStringNotContainsString(self::STAFF_PASSWORD, $metadata);
    }

    public function testUserActivatedAuditEventExists(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $this->createStaffViaService('audit.activate', 'audit.activate@example.com', 'contributor');
        $staff = $this->findUser('audit.activate');
        $staff->active = 0;
        model(UserModel::class)->save($staff);

        $this->postWithCsrf('admin/users/' . $staff->id . '/activate', []);

        $row = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::UserActivated->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
    }

    public function testUserDeactivatedAuditEventExists(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $this->createStaffViaService('audit.deactivate', 'audit.deactivate@example.com', 'contributor');
        $staff = $this->findUser('audit.deactivate');

        $this->postWithCsrf('admin/users/' . $staff->id . '/deactivate', []);

        $row = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::UserDeactivated->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
    }

    public function testRoleChangeAuditExists(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');
        $this->createStaffViaService('audit.role', 'audit.role@example.com', 'contributor');
        $staff = $this->findUser('audit.role');

        $this->postWithCsrf('admin/users/' . $staff->id, [
            'email'     => 'audit.role@example.com',
            'group'     => 'editor',
            'is_active' => '1',
        ]);

        $row = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::UserRoleChanged->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $metadata = (string) ($row['metadata'] ?? '');
        $this->assertStringContainsString('contributor', $metadata);
        $this->assertStringContainsString('editor', $metadata);
    }

    public function testPasswordsNeverAppearInResponse(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $result = $this->postWithCsrf('admin/users', [
            'username'         => 'secret.check',
            'email'            => 'secret.check@example.com',
            'password'         => self::STAFF_PASSWORD,
            'password_confirm' => self::STAFF_PASSWORD,
            'group'            => 'contributor',
            'is_active'        => '1',
        ]);

        $body = (string) $result->response()->getBody();
        $this->assertStringNotContainsString(self::STAFF_PASSWORD, $body);
    }

    public function testPasswordsNeverAppearInAuditMetadata(): void
    {
        $this->testUserCreatedAuditEventExists();
    }

    public function testPasswordHashesNeverAppearInResponse(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $result = $this->get('admin/users');
        $body = (string) $result->response()->getBody();

        $identity = db_connect()->table('auth_identities')->get()->getRowArray();
        $this->assertNotNull($identity);
        $hash = (string) ($identity['secret2'] ?? '');
        if ($hash !== '') {
            $this->assertStringNotContainsString($hash, $body);
        }
    }

    public function testEncryptedEmailAndHmacBehaviorRemainsCorrect(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $this->postWithCsrf('admin/users', [
            'username'         => 'pii.check',
            'email'            => 'PII.Check@Example.COM',
            'password'         => self::STAFF_PASSWORD,
            'password_confirm' => self::STAFF_PASSWORD,
            'group'            => 'contributor',
            'is_active'        => '1',
        ]);

        $user = $this->findUser('pii.check');
        $row  = db_connect()->table('users')->where('id', (int) $user->id)->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row['email_ciphertext']);
        $this->assertNotEmpty($row['email_lookup_hash']);
        $this->assertStringNotContainsString('pii.check@example.com', (string) $row['email_ciphertext']);
        $this->assertSame(64, strlen((string) $row['email_lookup_hash']));

        $decrypted = Services::userEmailService(getShared: false)->getDecryptedEmail((int) $user->id);
        $this->assertSame('pii.check@example.com', $decrypted);
    }

    public function testUserRoutesRequireUserManagePermission(): void
    {
        $routes = (string) file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $this->assertStringContainsString("permission:user.manage", $routes);
        $this->assertStringContainsString("Admin\\UserController", $routes);
    }

    public function testListMasksEmail(): void
    {
        $this->bootstrapReadyAdmin();
        $this->loginAs('user.mgmt.admin');

        $result = $this->get('admin/users');
        $body   = (string) $result->response()->getBody();

        $this->assertStringContainsString('***@example.com', $body);
        $this->assertStringNotContainsString('user.mgmt.admin@example.com', $body);
    }

    private function bootstrapReadyAdmin(): void
    {
        $result = Services::installService(getShared: false)->install([
            'username' => 'user.mgmt.admin',
            'email'    => 'user.mgmt.admin@example.com',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $this->assertSame('fresh', $result['status']);

        $admin = $this->findUser('user.mgmt.admin');
        $admin->undoForcePasswordReset();
        model(UserModel::class)->save($admin);
    }

    private function createStaffViaService(string $username, string $email, string $group): void
    {
        $admin = $this->findUser('user.mgmt.admin');
        $dto   = new \App\Dtos\CreateStaffUserDto(
            username: $username,
            email: $email,
            password: self::STAFF_PASSWORD,
            passwordConfirm: self::STAFF_PASSWORD,
            group: $group,
            isActive: true,
        );

        $errors = Services::userAdminService(getShared: false)->create($dto, (int) $admin->id);
        $this->assertSame([], $errors, implode(', ', $errors));

        $created = $this->findUser($username);
        $created->undoForcePasswordReset();
        model(UserModel::class)->save($created);
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

    private function primaryGroup(int $userId): string
    {
        return Services::userAdminService(getShared: false)
            ->findForEdit($userId)['group'] ?? 'contributor';
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
