<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Security\AuthThrottleService;
use App\Services\Security\PiiCipherService;
use App\Services\Security\UserEmailService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Throttle\Throttler;
use Config\AuthThrottle;
use Config\EmailPii;
use Config\Services;

/**
 * ADR-008 UserEmail + ADR-026 AuthThrottle (Phase 9 / Task 9.1B).
 *
 * @internal
 */
final class AuthSecurityServicesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * Shield users + App PII columns + audit foundation.
     *
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
        $this->restoreAuthThrottleTestFixture();

        parent::tearDown();
    }

    public function testUserEmailStoresCipherAndLookupSeparately(): void
    {
        $db = db_connect();
        $db->table('users')->insert([
            'username'   => 'editor1',
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = (int) $db->insertID();

        $emails = Services::userEmailService(getShared: false);
        $emails->setEmail($userId, 'Editor@Example.COM');

        $row = $db->table('users')
            ->select('email_ciphertext, email_lookup_hash')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $this->assertNotSame('', (string) $row['email_ciphertext']);
        $this->assertNotSame('', (string) $row['email_lookup_hash']);
        $this->assertNotSame($row['email_ciphertext'], $row['email_lookup_hash']);
        $this->assertStringNotContainsString('editor@example.com', (string) $row['email_ciphertext']);
        $this->assertStringNotContainsString('Editor@Example.COM', (string) $row['email_lookup_hash']);

        $this->assertSame($userId, $emails->findUserIdByEmail('editor@example.com'));
        $this->assertSame($userId, $emails->findUserIdByEmail('EDITOR@example.com'));
        $this->assertNull($emails->findUserIdByEmail('missing@example.com'));
        $this->assertSame('editor@example.com', $emails->getDecryptedEmail($userId));
    }

    public function testLookupDoesNotRequirePlaintextComparison(): void
    {
        $config = new EmailPii();
        $config->encryptionKeyHex = (string) env('EMAIL_ENCRYPTION_KEY');
        $config->lookupHmacKeyHex = (string) env('EMAIL_LOOKUP_HMAC_KEY');
        $cipher = new PiiCipherService($config);

        $db = db_connect();
        $db->table('users')->insert([
            'username'          => 'lookupuser',
            'active'            => 1,
            'email_ciphertext'  => $cipher->encrypt('lookup@example.com'),
            'email_lookup_hash' => $cipher->getLookupHash('lookup@example.com'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $emails = new UserEmailService($cipher, $db);
        $found  = $emails->findUserIdByEmail('LOOKUP@example.com');
        $this->assertNotNull($found);

        $row = $db->table('users')->select('email_ciphertext')->where('id', $found)->get()->getRowArray();
        $this->assertNotNull($row);
        // Lookup path never needs to decrypt for matching.
        $this->assertNotSame('lookup@example.com', $row['email_ciphertext']);
    }

    public function testAuthThrottleConfigHasNoInventedNumericDefaults(): void
    {
        $ref = new \ReflectionClass(AuthThrottle::class);

        foreach (['login', 'passwordResetRequest', 'passwordResetVerify', 'adminRecovery'] as $property) {
            $this->assertNull($ref->getProperty($property)->getDefaultValue());
        }
    }

    public function testAuthThrottleUnconfiguredFailsClosed(): void
    {
        $this->clearAuthThrottleEnvironment();

        $config  = new AuthThrottle();
        $service = new AuthThrottleService(Services::throttler(getShared: false), $config);

        $this->assertFalse($service->allow('login', '203.0.113.50'));
        $this->assertFalse($service->allow('password_reset_request', '203.0.113.50'));
        $this->assertFalse($service->allow('password_reset_verify', '203.0.113.50'));
        $this->assertFalse($service->allow('admin_recovery', '203.0.113.50'));
    }

    public function testAuthThrottleDeploymentFixtureFromEnvironmentAllows(): void
    {
        $config = new AuthThrottle();

        $this->assertNotNull($config->login);
        $this->assertNotNull($config->passwordResetRequest);
        $this->assertNotNull($config->passwordResetVerify);
        $this->assertNotNull($config->adminRecovery);

        $service = new AuthThrottleService(Services::throttler(getShared: false), $config);

        $this->assertTrue($service->allow('login', '203.0.113.60'));
        $this->assertTrue($service->allow('password_reset_request', '203.0.113.61'));
        $this->assertTrue($service->allow('password_reset_verify', '203.0.113.62'));
        $this->assertTrue($service->allow('admin_recovery', '203.0.113.63'));
    }

    public function testAuthThrottleInvalidEnvironmentFailsClosed(): void
    {
        $this->setAuthThrottleEnvironment([
            'auth.throttle.login.capacity' => '0',
            'auth.throttle.login.seconds'   => '60',
        ]);

        $config  = new AuthThrottle();
        $service = new AuthThrottleService(Services::throttler(getShared: false), $config);

        $this->assertNull($config->login);
        $this->assertFalse($service->allow('login', '203.0.113.70'));
    }

    public function testAuthThrottleAllowsThenRejects(): void
    {
        $config = new AuthThrottle();
        $config->login = [
            'capacity' => 2,
            'seconds'  => 120,
        ];
        $config->adminRecovery = [
            'capacity' => 2,
            'seconds'  => 120,
        ];

        /** @var Throttler $throttler */
        $throttler = Services::throttler(getShared: false);
        $service   = new AuthThrottleService($throttler, $config);

        $this->assertTrue($service->allow('login', '203.0.113.10'));
        $this->assertTrue($service->allow('login', '203.0.113.10'));
        $this->assertFalse($service->allow('login', '203.0.113.10'));
        // Other configured surfaces / IPs remain independent.
        $this->assertTrue($service->allow('admin_recovery', '203.0.113.10'));
        $this->assertTrue($service->allow('login', '203.0.113.11'));
    }

    public function testAuthThrottleSurfacesAreWiredIndependently(): void
    {
        $config = new AuthThrottle();
        foreach (['login', 'passwordResetRequest', 'passwordResetVerify', 'adminRecovery'] as $prop) {
            $config->{$prop} = ['capacity' => 1, 'seconds' => 120];
        }

        $throttler = Services::throttler(getShared: false);
        $service   = new AuthThrottleService($throttler, $config);

        $this->assertTrue($service->allow('password_reset_request', '198.51.100.1'));
        $this->assertFalse($service->allow('password_reset_request', '198.51.100.1'));
        $this->assertTrue($service->allow('password_reset_verify', '198.51.100.1'));
        $this->assertTrue($service->allow('admin_recovery', '198.51.100.1'));
    }

    /**
     * @param array<string, string|null> $values null removes the key
     */
    private function setAuthThrottleEnvironment(array $values): void
    {
        $this->clearAuthThrottleEnvironment();

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }

    private function clearAuthThrottleEnvironment(): void
    {
        $keys = [
            'auth.throttle.login.capacity',
            'auth.throttle.login.seconds',
            'auth.throttle.password_reset_request.capacity',
            'auth.throttle.password_reset_request.seconds',
            'auth.throttle.password_reset_verify.capacity',
            'auth.throttle.password_reset_verify.seconds',
            'auth.throttle.admin_recovery.capacity',
            'auth.throttle.admin_recovery.seconds',
        ];

        foreach ($keys as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function restoreAuthThrottleTestFixture(): void
    {
        $fixture = [
            'auth.throttle.login.capacity'                  => '10',
            'auth.throttle.login.seconds'                   => '60',
            'auth.throttle.password_reset_request.capacity' => '5',
            'auth.throttle.password_reset_request.seconds'  => '300',
            'auth.throttle.password_reset_verify.capacity'  => '5',
            'auth.throttle.password_reset_verify.seconds'   => '300',
            'auth.throttle.admin_recovery.capacity'         => '3',
            'auth.throttle.admin_recovery.seconds'          => '600',
        ];

        foreach ($fixture as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}
