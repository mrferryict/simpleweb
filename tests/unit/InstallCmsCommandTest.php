<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Install\InstallService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use RuntimeException;

/**
 * cms:install bootstrap contract (Phase 10 / Task 10.1B).
 *
 * @internal
 */
final class InstallCmsCommandTest extends CIUnitTestCase
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

    public function testCmsInstallCommandIsRegistered(): void
    {
        $commands = service('commands')->getCommands();
        $this->assertArrayHasKey('cms:install', $commands);
        $this->assertSame(\App\Commands\InstallCms::class, $commands['cms:install']['class']);
    }

    public function testFreshInstallCreatesSingleAdminAndSettingsOnce(): void
    {
        $installer = Services::installService(getShared: false);

        $first = $installer->install([
            'username' => 'bootstrap.admin',
            'email'    => 'bootstrap.admin@example.com',
            'password' => 'ChangeMeNow99!',
        ]);

        $this->assertSame('fresh', $first['status']);
        $this->assertTrue($first['admin_created']);
        $this->assertTrue($first['settings_bootstrapped']);

        $db = db_connect();
        $admins = $db->table('auth_groups_users')->where('group', 'admin')->countAllResults();
        $this->assertSame(1, $admins);

        $user = $db->table('users')->where('username', 'bootstrap.admin')->get()->getRowArray();
        $this->assertNotNull($user);
        $this->assertSame(1, (int) $user['active']);
        $this->assertNotSame('', (string) $user['email_ciphertext']);
        $this->assertNotSame('', (string) $user['email_lookup_hash']);
        $this->assertStringNotContainsString('bootstrap.admin@example.com', (string) $user['email_ciphertext']);

        $identity = $db->table('auth_identities')
            ->where('user_id', (int) $user['id'])
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        $this->assertNotNull($identity);
        $this->assertStringNotContainsString('bootstrap.admin@example.com', (string) $identity['secret']);
        $this->assertSame(1, (int) $identity['force_reset']);

        $this->assertSame(0, $db->table('pages')->countAllResults());
        $this->assertSame(0, $db->table('posts')->countAllResults());

        $second = $installer->install([
            'username' => 'other.admin',
            'email'    => 'other@example.com',
            'password' => 'AnotherPass99!',
        ]);

        $this->assertSame('already_installed', $second['status']);
        $this->assertFalse($second['admin_created']);
        $this->assertSame(InstallService::ALREADY_INSTALLED_MESSAGE, $second['message']);
        $this->assertSame(1, $db->table('auth_groups_users')->where('group', 'admin')->countAllResults());
        $this->assertSame(1, $db->table('users')->countAllResults());
        $this->assertSame(
            'bootstrap.admin',
            $db->table('users')->get()->getRowArray()['username'] ?? null,
        );
    }

    public function testMissingCredentialsFailWithoutCreatingAdmin(): void
    {
        $installer = Services::installService(getShared: false);

        try {
            $installer->install([]);
            $this->fail('Expected RuntimeException');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('credentials are required', $e->getMessage());
            $this->assertStringNotContainsString('ChangeMe', $e->getMessage());
        }

        $this->assertFalse($installer->adminExists());
        $this->assertSame(0, db_connect()->table('users')->countAllResults());
    }

    public function testEnvironmentCredentialsUsedWhenCliOptionsOmitted(): void
    {
        $this->withInstallEnvCredentials(
            username: 'env.only.admin',
            email: 'env.only.admin@example.com',
            password: 'EnvOnlyPass99!',
            callback: function (): void {
                $installer = Services::installService(getShared: false);
                $result    = $installer->install([]);

                $this->assertSame('fresh', $result['status']);
                $this->assertTrue($result['admin_created']);

                $db   = db_connect();
                $user = $db->table('users')->where('username', 'env.only.admin')->get()->getRowArray();
                $this->assertNotNull($user);
                $this->assertSame(1, $db->table('auth_groups_users')->where('group', 'admin')->countAllResults());
                $this->assertNotSame('', (string) $user['email_ciphertext']);
                $this->assertNotSame('', (string) $user['email_lookup_hash']);
                $this->assertStringNotContainsString('env.only.admin@example.com', (string) $user['email_ciphertext']);

                $identity = $db->table('auth_identities')
                    ->where('user_id', (int) $user['id'])
                    ->where('type', 'email_password')
                    ->get()
                    ->getRowArray();
                $this->assertNotNull($identity);
                $this->assertSame(1, (int) $identity['force_reset']);
                $this->assertStringNotContainsString('EnvOnlyPass99!', (string) $identity['secret']);
                $this->assertSame(0, $db->table('pages')->countAllResults());
            },
        );
    }

    public function testEmptyCredentialStringsDoNotSuppressEnvironmentFallback(): void
    {
        $this->withInstallEnvCredentials(
            username: 'empty.cli.admin',
            email: 'empty.cli.admin@example.com',
            password: 'EmptyCliPass99!',
            callback: function (): void {
                $installer = Services::installService(getShared: false);
                $result    = $installer->install([
                    'username' => '',
                    'email'    => '',
                    'password' => '',
                ]);

                $this->assertSame('fresh', $result['status']);
                $this->assertTrue($result['admin_created']);
                $this->assertSame(
                    'empty.cli.admin',
                    db_connect()->table('users')->get()->getRowArray()['username'] ?? null,
                );
            },
        );
    }

    public function testExplicitCredentialsOverrideEnvironmentCredentials(): void
    {
        $this->withInstallEnvCredentials(
            username: 'env.should.not.win',
            email: 'env.should.not.win@example.com',
            password: 'EnvShouldNotWin99!',
            callback: function (): void {
                $installer = Services::installService(getShared: false);
                $result    = $installer->install([
                    'username' => 'cli.wins.admin',
                    'email'    => 'cli.wins.admin@example.com',
                    'password' => 'CliWinsPass99!',
                ]);

                $this->assertSame('fresh', $result['status']);
                $db = db_connect();
                $this->assertSame(1, $db->table('users')->countAllResults());
                $this->assertSame(
                    'cli.wins.admin',
                    $db->table('users')->get()->getRowArray()['username'] ?? null,
                );
                $this->assertSame(
                    0,
                    $db->table('users')->where('username', 'env.should.not.win')->countAllResults(),
                );
            },
        );
    }

    public function testInstallerDoesNotLeakSecretsInAlreadyInstalledMessage(): void
    {
        $installer = Services::installService(getShared: false);
        $installer->install([
            'username' => 'secret.admin',
            'email'    => 'secret.admin@example.com',
            'password' => 'SuperSecretPass1!',
        ]);

        $again = $installer->install([
            'username' => 'secret.admin',
            'email'    => 'secret.admin@example.com',
            'password' => 'SuperSecretPass1!',
        ]);

        $this->assertStringNotContainsString('SuperSecretPass1!', $again['message']);
        $this->assertStringNotContainsString('secret.admin@example.com', $again['message']);
        $this->assertStringNotContainsString('EMAIL_', $again['message']);
    }

    /**
     * Temporarily set cms.install.admin_* for the callback, then clear them.
     */
    private function withInstallEnvCredentials(
        string $username,
        string $email,
        string $password,
        callable $callback,
    ): void {
        $keys = [
            'cms.install.admin_username' => $username,
            'cms.install.admin_email'    => $email,
            'cms.install.admin_password' => $password,
        ];

        $previous = [];
        foreach ($keys as $key => $value) {
            $previous[$key] = [
                'env'    => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key),
            ];
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }

        try {
            $callback();
        } finally {
            foreach ($previous as $key => $state) {
                if ($state['env'] === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $state['env'];
                }

                if ($state['server'] === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $state['server'];
                }

                if ($state['getenv'] === false) {
                    putenv($key);
                } else {
                    putenv($key . '=' . $state['getenv']);
                }
            }
        }
    }
}
