<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuditEvent;
use App\Services\Security\PasswordResetEmailService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\CapturingPasswordResetEmailTransport;

/**
 * V2-004 password-reset email delivery (ADR-027 P0-2).
 *
 * @internal
 */
final class PasswordResetEmailTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const INSTALL_PASSWORD = 'InstallPass99!';
    private const RESET_PASSWORD   = 'Xk9$mQn2pL7#vR4wT8';
    private const TOKEN_TTL      = 3600;

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
        CapturingPasswordResetEmailTransport::reset();
        $this->injectThrottle(capacity: 100);
        $this->injectCapturingTransport();
    }

    protected function tearDown(): void
    {
        Services::resetSingle('passwordResetEmailService');
        Services::resetSingle('authThrottleService');
        CapturingPasswordResetEmailTransport::reset();
        parent::tearDown();
    }

    public function testPasswordResetRequestPageStillWorks(): void
    {
        $result = $this->get('cp/password-reset');
        $result->assertStatus(200);
        $this->assertStringContainsString('Password reset', (string) $result->response()->getBody());
    }

    public function testKnownEmailTriggersResetEmailDelivery(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);
        $this->assertSame('reset.email.admin@example.com', $message['to']);
    }

    public function testUnknownEmailRemainsOpaque(): void
    {
        $this->bootstrapAdmin();

        $known = $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        CapturingPasswordResetEmailTransport::reset();

        $unknown = $this->postWithCsrf('cp/password-reset', [
            'email' => 'nobody@example.com',
        ]);

        $this->assertSame(
            $known->response()->getStatusCode(),
            $unknown->response()->getStatusCode(),
        );

        $knownBody   = (string) $known->response()->getBody();
        $unknownBody = (string) $unknown->response()->getBody();
        $this->assertStringContainsString('If the account exists, further instructions were processed.', $knownBody);
        $this->assertStringContainsString('If the account exists, further instructions were processed.', $unknownBody);
        $this->assertNull(CapturingPasswordResetEmailTransport::last());
        $this->assertStringNotContainsString('nobody@example.com', $unknownBody);
    }

    public function testEmailRecipientIsCorrect(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);
        $this->assertSame('reset.email.admin@example.com', $message['to']);
    }

    public function testEmailContainsConfiguredSiteName(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);
        $this->assertStringContainsString('SMITE CMS', $message['subject']);
        $this->assertStringContainsString('SMITE CMS', $message['html']);
        $this->assertStringContainsString('SMITE CMS', $message['text']);
    }

    public function testEmailContainsCorrectResetUrl(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);
        $this->assertStringContainsString('/cp/password-reset/verify?token=', $message['html']);
        $this->assertStringContainsString('/cp/password-reset/verify?token=', $message['text']);
    }

    public function testResetUrlUsesConfiguredAppBaseUrl(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);

        $expectedBase = rtrim((string) config('App')->baseURL, '/');
        $this->assertStringContainsString($expectedBase . '/cp/password-reset/verify?token=', $message['text']);
    }

    public function testEmailDoesNotContainPasswordOrHashOrSecrets(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);

        $combined = $message['html'] . $message['text'] . $message['subject'];
        $this->assertStringNotContainsString(self::INSTALL_PASSWORD, $combined);
        $this->assertStringNotContainsString('EMAIL_ENCRYPTION_KEY', $combined);
        $this->assertStringNotContainsString('EMAIL_LOOKUP_HMAC_KEY', $combined);
        $this->assertStringNotContainsString('skey', $combined);

        $identity = db_connect()->table('auth_identities')->get()->getRowArray();
        $this->assertNotNull($identity);
        if (isset($identity['secret2']) && is_string($identity['secret2']) && $identity['secret2'] !== '') {
            $this->assertStringNotContainsString($identity['secret2'], $combined);
        }
    }

    public function testTokenTtlRemainsEnforced(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);
        $this->assertStringContainsString('1 hour', $message['text']);
    }

    public function testInvalidTokenRejected(): void
    {
        $result = $this->postWithCsrf('cp/password-reset/verify', [
            'token'            => 'invalid-token-value',
            'password'         => self::RESET_PASSWORD,
            'password_confirm' => self::RESET_PASSWORD,
        ]);

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Unable to reset password.', $body);
    }

    public function testExpiredTokenRejected(): void
    {
        $this->bootstrapAdmin();
        $token  = 'expired-token-value-for-test';
        $userId = (int) $this->findUser('reset.email.admin')->id;
        cache()->save('auth.reset.' . $token, $userId, 1);
        cache()->delete('auth.reset.' . $token);

        $result = $this->postWithCsrf('cp/password-reset/verify', [
            'token'            => $token,
            'password'         => self::RESET_PASSWORD,
            'password_confirm' => self::RESET_PASSWORD,
        ]);

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Unable to reset password.', $body);
    }

    public function testTokenCannotBeReused(): void
    {
        $this->bootstrapAdmin();
        $token = bin2hex(random_bytes(32));
        cache()->save('auth.reset.' . $token, (int) $this->findUser('reset.email.admin')->id, self::TOKEN_TTL);

        $first = $this->postWithCsrf('cp/password-reset/verify', [
            'token'            => $token,
            'password'         => self::RESET_PASSWORD,
            'password_confirm' => self::RESET_PASSWORD,
        ]);
        $this->assertStringContainsString('Password updated.', (string) $first->response()->getBody());

        $second = $this->postWithCsrf('cp/password-reset/verify', [
            'token'            => $token,
            'password'         => 'AnotherPass99!Xk',
            'password_confirm' => 'AnotherPass99!Xk',
        ]);
        $this->assertStringContainsString('Unable to reset password.', (string) $second->response()->getBody());
    }

    public function testSuccessfulResetStillWorks(): void
    {
        $this->bootstrapAdmin();
        $token = bin2hex(random_bytes(32));
        cache()->save('auth.reset.' . $token, (int) $this->findUser('reset.email.admin')->id, self::TOKEN_TTL);

        $result = $this->postWithCsrf('cp/password-reset/verify', [
            'token'            => $token,
            'password'         => self::RESET_PASSWORD,
            'password_confirm' => self::RESET_PASSWORD,
        ]);

        $this->assertStringContainsString('Password updated.', (string) $result->response()->getBody());

        $user = $this->findUser('reset.email.admin');
        $this->assertFalse($user->requiresPasswordReset());
    }

    public function testThrottleRemainsActive(): void
    {
        $this->injectThrottle(capacity: 1);
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', ['email' => 'reset.email.admin@example.com']);
        $second = $this->postWithCsrf('cp/password-reset', ['email' => 'reset.email.admin@example.com']);

        $this->assertStringContainsString('Too many attempts', (string) $second->response()->getBody());
    }

    public function testCsrfRemainsActive(): void
    {
        $this->bootstrapAdmin();

        try {
            $result = $this->post('cp/password-reset', [
                'email' => 'reset.email.admin@example.com',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testSmtpFailureDoesNotExposeSensitiveInformation(): void
    {
        $this->bootstrapAdmin();

        Services::resetSingle('passwordResetEmailService');
        Services::injectMock(
            'passwordResetEmailService',
            new PasswordResetEmailService(
                Services::settingService(getShared: false),
                new CapturingPasswordResetEmailTransport(shouldSucceed: false),
            ),
        );

        $result = $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('If the account exists, further instructions were processed.', $body);
        $this->assertStringNotContainsString('smtp', strtolower($body));
        $this->assertStringNotContainsString('reset.email.admin@example.com', $body);
    }

    public function testTokenNotShownInOrdinaryRequestResponse(): void
    {
        $this->bootstrapAdmin();

        $result = $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);
        $token = $this->extractTokenFromMessage($message['text']);

        $body = (string) $result->response()->getBody();
        $this->assertStringNotContainsString($token, $body);
    }

    public function testTokenNotWrittenToAuditMetadata(): void
    {
        $this->bootstrapAdmin();

        $this->postWithCsrf('cp/password-reset', [
            'email' => 'reset.email.admin@example.com',
        ]);

        $message = CapturingPasswordResetEmailTransport::last();
        $this->assertNotNull($message);
        $token = $this->extractTokenFromMessage($message['text']);

        $row = db_connect()->table('audit_logs')
            ->where('event', AuditEvent::PasswordReset->value)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $this->assertNotNull($row);
        $metadata = (string) ($row['metadata'] ?? '');
        $this->assertStringNotContainsString($token, $metadata);
        $this->assertStringNotContainsString('reset.email.admin@example.com', $metadata);
    }

    public function testForceResetClearedAfterSuccessfulReset(): void
    {
        $this->bootstrapAdmin();
        $user = $this->findUser('reset.email.admin');
        if (! $user->requiresPasswordReset()) {
            $user->forcePasswordReset();
            model(UserModel::class)->save($user);
        }
        $user = $this->findUser('reset.email.admin');
        $this->assertTrue($user->requiresPasswordReset());

        $token = bin2hex(random_bytes(32));
        cache()->save('auth.reset.' . $token, (int) $user->id, self::TOKEN_TTL);

        $this->postWithCsrf('cp/password-reset/verify', [
            'token'            => $token,
            'password'         => self::RESET_PASSWORD,
            'password_confirm' => self::RESET_PASSWORD,
        ]);

        $fresh = $this->findUser('reset.email.admin');
        $this->assertFalse($fresh->requiresPasswordReset());
    }

    public function testBuildResetUrlServiceUsesBaseUrl(): void
    {
        $service = new PasswordResetEmailService(
            Services::settingService(getShared: false),
            new CapturingPasswordResetEmailTransport(),
        );

        $url = $service->buildResetUrl('sample-token');
        $expectedBase = rtrim((string) config('App')->baseURL, '/');
        $this->assertSame($expectedBase . '/cp/password-reset/verify?token=sample-token', $url);
    }

    private function bootstrapAdmin(): void
    {
        $result = Services::installService(getShared: false)->install([
            'username' => 'reset.email.admin',
            'email'    => 'reset.email.admin@example.com',
            'password' => self::INSTALL_PASSWORD,
        ]);

        $this->assertSame('fresh', $result['status']);
    }

    private function findUser(string $username): User
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);
        $user  = $users->where('username', $username)->first();
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function injectCapturingTransport(): void
    {
        Services::injectMock(
            'passwordResetEmailService',
            new PasswordResetEmailService(
                Services::settingService(getShared: false),
                new CapturingPasswordResetEmailTransport(),
            ),
        );
    }

    private function injectThrottle(int $capacity): void
    {
        $config = new \Config\AuthThrottle();
        $windowSeconds = 120;
        $config->login                  = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->passwordResetRequest   = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->passwordResetVerify    = ['capacity' => $capacity, 'seconds' => $windowSeconds];
        $config->adminRecovery          = ['capacity' => $capacity, 'seconds' => $windowSeconds];

        Services::injectMock(
            'authThrottleService',
            new \App\Services\Security\AuthThrottleService(
                Services::throttler(getShared: false),
                $config,
            ),
        );
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

    private function extractTokenFromMessage(string $text): string
    {
        if (preg_match('/\/cp\/password-reset\/verify\?token=([A-Fa-f0-9]+)/', $text, $matches) === 1) {
            return $matches[1];
        }

        return '';
    }
}
