<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Auth\AdminRecoveryController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Auth\PasswordResetController;
use App\Enums\AuditEvent;
use App\Services\Security\AuthThrottleService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\AuthThrottle;
use Config\Filters as FiltersConfig;
use Config\Services;
use ReflectionClass;

/**
 * ADR-026 auth audit, throttle wiring, SecureHeaders (Phase 9 / Task 9.1B).
 *
 * @internal
 */
final class SecurityHardeningTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

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
        Services::resetSingle('authThrottleService');
        parent::tearDown();
    }

    public function testAuditEventVocabularyIncludesDoc03Section25(): void
    {
        $required = [
            'LOGIN',
            'LOGIN_FAILED',
            'LOGOUT',
            'PASSWORD_CHANGED',
            'PASSWORD_RESET',
            'ADMIN_RECOVERY',
            'USER_CREATED',
            'USER_ACTIVATED',
            'USER_DEACTIVATED',
        ];

        $values = array_map(static fn (AuditEvent $e): string => $e->value, AuditEvent::cases());
        foreach ($required as $name) {
            $this->assertContains($name, $values);
        }
    }

    public function testSecureHeadersFilterIsEnabledGlobally(): void
    {
        /** @var FiltersConfig $filters */
        $filters = config(FiltersConfig::class);
        $this->assertContains('secureheaders', $filters->globals['after']);
        $this->assertArrayHasKey('secureheaders', $filters->aliases);
    }

    public function testSecureHeadersBaselineValues(): void
    {
        $filter = new \CodeIgniter\Filters\SecureHeaders();
        $ref    = new ReflectionClass($filter);
        $prop   = $ref->getProperty('headers');
        $prop->setAccessible(true);
        /** @var array<string, string> $headers */
        $headers = $prop->getValue($filter);

        $this->assertSame('SAMEORIGIN', $headers['X-Frame-Options']);
        $this->assertSame('nosniff', $headers['X-Content-Type-Options']);
        $this->assertSame('same-origin', $headers['Referrer-Policy']);
        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);
        $this->assertArrayNotHasKey('Permissions-Policy', $headers);
    }

    public function testFailedLoginWritesLoginFailedAuditWithoutSecrets(): void
    {
        $this->injectThrottle(capacity: 5);

        $request = $this->postRequest([
            'username' => 'nobody',
            'password' => 'secret-password-value',
        ]);
        $this->assertTrue($request->is('post'), 'precondition: request must be POST');

        $this->withRequest($request)
            ->controller(AuthController::class)
            ->execute('login');

        $row = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::LoginFailed->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $this->assertNull($row['actor_id']);
        $this->assertStringNotContainsString('secret-password-value', (string) $row['metadata']);
        $this->assertStringNotContainsString('nobody', (string) $row['metadata']);
        $this->assertStringContainsString('login', (string) $row['metadata']);
    }

    public function testThrottledLoginDoesNotAttemptAuthAndShowsOpaqueMessage(): void
    {
        $request = $this->postRequest([
            'username' => 'admin',
            'password' => 'anything',
        ]);
        $this->injectThrottle(capacity: 1);
        $ip = $request->getIPAddress();
        service('authThrottleService')->allow('login', $ip);

        $result = $this->withRequest($request)
            ->controller(AuthController::class)
            ->execute('login');

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Too many attempts', $body);
        $this->assertStringNotContainsString('anything', $body);

        $failed = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::LoginFailed->value)
            ->countAllResults();
        $this->assertSame(0, $failed);
    }

    public function testPasswordResetRequestIsThrottledOpaquely(): void
    {
        $request = $this->postRequest(['email' => 'user@example.com']);
        $this->injectThrottle(capacity: 1);
        service('authThrottleService')->allow('password_reset_request', $request->getIPAddress());

        $result = $this->withRequest($request)
            ->controller(PasswordResetController::class)
            ->execute('requestSubmit');

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Too many attempts', $body);
        $this->assertStringNotContainsString('user@example.com', $body);
    }

    public function testAdminRecoveryRejectsWrongSkeyWithoutLoggingSecret(): void
    {
        $this->injectThrottle(capacity: 5);

        $result = $this->withRequest($this->postRequest([
                'skey'             => 'wrong-skey-value',
                'username'         => 'admin',
                'password'         => 'NewPass123!',
                'password_confirm' => 'NewPass123!',
            ]))
            ->controller(AdminRecoveryController::class)
            ->execute('recover');

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Recovery failed', $body);
        $this->assertStringNotContainsString('wrong-skey-value', $body);
        $this->assertStringNotContainsString('NewPass123!', $body);

        $count = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::AdminRecovery->value)
            ->countAllResults();
        $this->assertSame(0, $count);
    }

    public function testPasswordResetRequestAuditsWithoutEmailPlaintext(): void
    {
        $this->injectThrottle(capacity: 5);

        $db = db_connect();
        $db->table('users')->insert([
            'username'   => 'resetme',
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $userId = (int) $db->insertID();
        Services::userEmailService(getShared: false)->setEmail($userId, 'resetme@example.com');

        $result = $this->withRequest($this->postRequest(['email' => 'resetme@example.com']))
            ->controller(PasswordResetController::class)
            ->execute('requestSubmit');

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('If the account exists', $body);
        $this->assertStringNotContainsString('resetme@example.com', $body);

        $row = $db->table('audit_logs')
            ->where('event', AuditEvent::PasswordReset->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        $this->assertNotNull($row);
        $this->assertNull($row['actor_id']);
        $this->assertSame($userId, (int) $row['resource_id']);
        $this->assertStringNotContainsString('resetme@example.com', (string) $row['metadata']);
    }

    public function testUnconfiguredThrottleDeniesOpaquelyWithoutLeakingInternals(): void
    {
        $config = new AuthThrottle();
        // Explicitly unconfigured — deployment env may supply operational values in other tests.
        $config->login                  = null;
        $config->passwordResetRequest   = null;
        $config->passwordResetVerify    = null;
        $config->adminRecovery          = null;

        Services::injectMock(
            'authThrottleService',
            new AuthThrottleService(Services::throttler(getShared: false), $config),
        );

        $result = $this->withRequest($this->postRequest([
            'username' => 'admin',
            'password' => 'secret-value',
        ]))
            ->controller(AuthController::class)
            ->execute('login');

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Too many attempts', $body);
        $this->assertStringNotContainsString('secret-value', $body);
        $this->assertStringNotContainsString('capacity', $body);
        $this->assertStringNotContainsString('auth.throttle', $body);
    }

    private function injectThrottle(int $capacity): void
    {
        $config = new AuthThrottle();
        // Explicit test fixture values — not product policy defaults.
        $windowSeconds = 120;
        $config->login = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->passwordResetRequest = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->passwordResetVerify = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->adminRecovery = ['capacity' => $capacity, 'seconds' => $windowSeconds];

        Services::injectMock(
            'authThrottleService',
            new AuthThrottleService(Services::throttler(getShared: false), $config),
        );
    }

    /**
     * @param array<string, string> $post
     */
    private function postRequest(array $post): IncomingRequest
    {
        /** @var IncomingRequest $request */
        $request = service('incomingrequest', null, false);
        $request->setGlobal('post', $post);
        $request->setMethod('POST');

        return $request;
    }
}
