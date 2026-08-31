<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Control Panel login presentation (TH-024).
 *
 * @internal
 */
final class AuthLoginViewTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

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

    public function testLoginPageRendersExpectedFormStructure(): void
    {
        $result = $this->get('cp');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('Control Panel', $body);
        $this->assertStringContainsString('name="username"', $body);
        $this->assertStringContainsString('name="password"', $body);
        $this->assertStringContainsString('type="password"', $body);
        $this->assertStringContainsString('csrf_test_name', $body);
        $this->assertStringContainsString('action="' . site_url('cp') . '"', $body);
        $this->assertStringContainsString('auth.css', $body);
        $this->assertStringContainsString('admin-auth-card', $body);
        $this->assertStringContainsString('Sign in', $body);
        $this->assertStringNotContainsString('value="secret-password"', $body);
    }

    public function testLoginPageRendersConfiguredSiteNameSafely(): void
    {
        service('settings')->set('Site.siteName', 'KPKS Jakarta');

        $result = $this->get('cp');
        $body   = (string) $result->response()->getBody();

        $this->assertStringContainsString('KPKS Jakarta', $body);
        $this->assertStringNotContainsString('<script', $body);
    }

    public function testFailedLoginRendersGenericErrorWithoutPasswordEcho(): void
    {
        $result = $this->postWithCsrf('cp', [
            'username' => 'nobody',
            'password' => 'secret-password-value',
        ]);

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('role="alert"', $body);
        $this->assertStringContainsString('Invalid username or password.', $body);
        $this->assertStringNotContainsString('secret-password-value', $body);
        $this->assertStringNotContainsString('value="secret-password-value"', $body);
    }

    public function testLoginViewViaRoutePreservesPostTarget(): void
    {
        $result = $this->get('cp');

        $body = (string) $result->response()->getBody();

        $this->assertStringContainsString('method="post"', $body);
        $this->assertStringContainsString(site_url('cp'), $body);
        $this->assertStringContainsString('csrf_test_name', $body);
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
