<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Authentication surface presentation (TH-025).
 *
 * @internal
 */
final class AuthSurfaceViewTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    private const INSTALL_PASSWORD = 'InstallPass99!';

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

    public function testPasswordChangeScreenRendersExpectedFormStructure(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->get('cp/password-change');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('Change your password', $body);
        $this->assertStringContainsString('name="password"', $body);
        $this->assertStringContainsString('name="password_new"', $body);
        $this->assertStringContainsString('name="password_confirm"', $body);
        $this->assertStringContainsString('csrf_test_name', $body);
        $this->assertStringContainsString('action="' . site_url('cp/password-change') . '"', $body);
        $this->assertStringContainsString('auth.css', $body);
        $this->assertStringContainsString('admin-auth-card', $body);
        $this->assertStringNotContainsString('value="' . self::INSTALL_PASSWORD . '"', $body);
        $this->assertStringNotContainsString('admin-sidebar', $body);
    }

    public function testPasswordChangeValidationErrorsRenderWithoutPasswordEcho(): void
    {
        $this->bootstrapAdmin();
        $this->loginAs('force.reset.admin');

        $result = $this->postWithCsrf('cp/password-change', [
            'password'         => self::INSTALL_PASSWORD,
            'password_new'     => 'short',
            'password_confirm' => 'short',
        ]);

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('admin-field-error', $body);
        $this->assertStringNotContainsString('value="short"', $body);
        $this->assertStringNotContainsString('value="' . self::INSTALL_PASSWORD . '"', $body);
    }

    public function testPasswordResetRequestScreenRendersExpectedFormStructure(): void
    {
        $result = $this->get('cp/password-reset');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('Password reset', $body);
        $this->assertStringContainsString('name="email"', $body);
        $this->assertStringContainsString('csrf_test_name', $body);
        $this->assertStringContainsString('action="' . site_url('cp/password-reset') . '"', $body);
        $this->assertStringContainsString('auth.css', $body);
        $this->assertStringContainsString('admin-auth-card', $body);
    }

    public function testPasswordResetRequestShowsOpaqueMessageWithoutEmailEcho(): void
    {
        $result = $this->postWithCsrf('cp/password-reset', [
            'email' => 'nobody@example.com',
        ]);

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('If the account exists, further instructions were processed.', $body);
        $this->assertStringNotContainsString('nobody@example.com', $body);
    }

    public function testPasswordResetVerifyScreenRendersExpectedFormStructure(): void
    {
        $result = $this->get('cp/password-reset/verify?token=sample-token-value');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('Set new password', $body);
        $this->assertStringContainsString('name="token"', $body);
        $this->assertStringContainsString('name="password"', $body);
        $this->assertStringContainsString('value="sample-token-value"', $body);
        $this->assertStringContainsString('csrf_test_name', $body);
        $this->assertStringContainsString('action="' . site_url('cp/password-reset/verify') . '"', $body);
        $this->assertStringContainsString('auth.css', $body);
    }

    public function testPasswordResetVerifyDoesNotEchoSubmittedPassword(): void
    {
        $result = $this->postWithCsrf('cp/password-reset/verify', [
            'token'    => 'invalid-token',
            'password' => 'secret-new-password-value',
        ]);

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('Unable to reset password.', $body);
        $this->assertStringNotContainsString('secret-new-password-value', $body);
        $this->assertStringNotContainsString('value="secret-new-password-value"', $body);
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
        /** @var UserModel $users */
        $users = model(UserModel::class);
        $user  = $users->where('username', $username)->first();
        $this->assertInstanceOf(User::class, $user);
        auth('session')->login($user);
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
