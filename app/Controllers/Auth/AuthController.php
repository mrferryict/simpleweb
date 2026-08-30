<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditService;
use App\Services\Security\AuthThrottleService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Throwable;

/**
 * Control Panel authentication (ADR-001 / ADR-026).
 */
class AuthController extends BaseController
{
    private const GENERIC_AUTH_FAILURE = 'Invalid username or password.';
    private const GENERIC_THROTTLED    = 'Too many attempts. Please try again later.';

    public function login(): ResponseInterface|RedirectResponse|string
    {
        if (! $this->request->is('post')) {
            return view('admin/auth/login', [
                'error' => null,
            ]);
        }

        $ip = $this->request->getIPAddress();
        if (! $this->authThrottle()->allow('login', $ip)) {
            return view('admin/auth/login', [
                'error' => self::GENERIC_THROTTLED,
            ]);
        }

        $usernamePost = $this->request->getPost('username');
        $passwordPost = $this->request->getPost('password');

        $usernameEntered = is_string($usernamePost) ? $usernamePost : '';
        $password        = is_string($passwordPost) ? $passwordPost : '';

        session()->setFlashdata('_ci_old_input', [
            'get'  => [],
            'post' => ['username' => $usernameEntered],
        ]);

        $username = strtolower(trim($usernameEntered));

        if ($username === '' || $password === '') {
            $this->auditAuthFailure();

            return view('admin/auth/login', [
                'error' => self::GENERIC_AUTH_FAILURE,
            ]);
        }

        try {
            $result = auth('session')->attempt([
                'username' => $username,
                'password' => $password,
            ]);
        } catch (Throwable) {
            log_message('error', 'Control Panel authentication failed due to an internal error.');
            $this->auditAuthFailure();

            return view('admin/auth/login', [
                'error' => self::GENERIC_AUTH_FAILURE,
            ]);
        }

        if (! $result->isOK()) {
            $this->auditAuthFailure();

            return view('admin/auth/login', [
                'error' => self::GENERIC_AUTH_FAILURE,
            ]);
        }

        $userId = (int) (auth()->id() ?? 0);
        (void) $this->auditService()->append(
            AuditEvent::Login,
            $userId > 0 ? $userId : null,
            'user',
            $userId > 0 ? $userId : null,
            null,
            ['surface' => 'login'],
        );

        $user = auth()->user();
        if ($user instanceof User && $user->requiresPasswordReset()) {
            return redirect()->to(config('Auth')->forcePasswordResetRedirect());
        }

        return redirect()->to('/admin');
    }

    public function logout(): RedirectResponse
    {
        $userId = (int) (auth()->id() ?? 0);
        auth('session')->logout();

        (void) $this->auditService()->append(
            AuditEvent::Logout,
            $userId > 0 ? $userId : null,
            'user',
            $userId > 0 ? $userId : null,
            null,
            ['surface' => 'logout'],
        );

        return redirect()->to('/cp');
    }

    private function auditAuthFailure(): void
    {
        (void) $this->auditService()->append(
            AuditEvent::LoginFailed,
            null,
            'user',
            null,
            null,
            ['surface' => 'login'],
        );
    }

    private function authThrottle(): AuthThrottleService
    {
        return service('authThrottleService');
    }

    private function auditService(): AuditService
    {
        return service('auditService');
    }
}
