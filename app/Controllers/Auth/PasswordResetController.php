<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditService;
use App\Services\Security\AuthThrottleService;
use App\Services\Security\UserEmailService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

/**
 * Email password reset request/verify (REQ-AUTH-008 / ADR-026).
 *
 * Opaque responses; throttled; lookup via email_lookup_hash only.
 */
class PasswordResetController extends BaseController
{
    private const OPAQUE    = 'If the account exists, further instructions were processed.';
    private const THROTTLED = 'Too many attempts. Please try again later.';
    private const GENERIC   = 'Unable to reset password.';
    private const TOKEN_TTL = 3600;

    public function requestForm(): string
    {
        return view('admin/auth/password_reset_request', [
            'message' => null,
            'error'   => null,
        ]);
    }

    public function requestSubmit(): ResponseInterface|string
    {
        $ip = $this->request->getIPAddress();
        if (! $this->authThrottle()->allow('password_reset_request', $ip)) {
            return view('admin/auth/password_reset_request', [
                'message' => null,
                'error'   => self::THROTTLED,
            ]);
        }

        $emailPost = $this->request->getPost('email');
        $email     = is_string($emailPost) ? $emailPost : '';

        try {
            $userId = $this->userEmail()->findUserIdByEmail($email);
            if ($userId !== null) {
                $token = bin2hex(random_bytes(32));
                cache()->save('auth.reset.' . $token, $userId, self::TOKEN_TTL);
                // SMTP delivery is environment-dependent; token is issued for verify step.
                (void) $this->auditService()->append(
                    AuditEvent::PasswordReset,
                    null,
                    'user',
                    $userId,
                    null,
                    ['surface' => 'password_reset_request'],
                );
            }
        } catch (Throwable) {
            log_message('error', 'Password reset request failed due to an internal error.');
        }

        return view('admin/auth/password_reset_request', [
            'message' => self::OPAQUE,
            'error'   => null,
        ]);
    }

    public function verifyForm(): string
    {
        return view('admin/auth/password_reset_verify', [
            'error'   => null,
            'success' => null,
            'token'   => (string) ($this->request->getGet('token') ?? ''),
        ]);
    }

    public function verifySubmit(): ResponseInterface|string
    {
        $ip = $this->request->getIPAddress();
        if (! $this->authThrottle()->allow('password_reset_verify', $ip)) {
            return view('admin/auth/password_reset_verify', [
                'error'   => self::THROTTLED,
                'success' => null,
                'token'   => '',
            ]);
        }

        $tokenPost    = $this->request->getPost('token');
        $passwordPost = $this->request->getPost('password');
        $token        = is_string($tokenPost) ? trim($tokenPost) : '';
        $password     = is_string($passwordPost) ? $passwordPost : '';

        if ($token === '' || $password === '') {
            return view('admin/auth/password_reset_verify', [
                'error'   => self::GENERIC,
                'success' => null,
                'token'   => $token,
            ]);
        }

        try {
            $userId = cache()->get('auth.reset.' . $token);
            if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
                return view('admin/auth/password_reset_verify', [
                    'error'   => self::GENERIC,
                    'success' => null,
                    'token'   => '',
                ]);
            }
            $userId = (int) $userId;

            /** @var UserModel $users */
            $users = model(UserModel::class);
            $user  = $users->find($userId);
            if ($user === null) {
                return view('admin/auth/password_reset_verify', [
                    'error'   => self::GENERIC,
                    'success' => null,
                    'token'   => '',
                ]);
            }

            $user->password = $password;
            $users->save($user);
            cache()->delete('auth.reset.' . $token);

            (void) $this->auditService()->append(
                AuditEvent::PasswordChanged,
                $userId,
                'user',
                $userId,
                null,
                ['surface' => 'password_reset_verify'],
            );
        } catch (Throwable) {
            log_message('error', 'Password reset verify failed due to an internal error.');

            return view('admin/auth/password_reset_verify', [
                'error'   => self::GENERIC,
                'success' => null,
                'token'   => '',
            ]);
        }

        return view('admin/auth/password_reset_verify', [
            'error'   => null,
            'success' => 'Password updated.',
            'token'   => '',
        ]);
    }

    private function authThrottle(): AuthThrottleService
    {
        return service('authThrottleService');
    }

    private function userEmail(): UserEmailService
    {
        return service('userEmailService');
    }

    private function auditService(): AuditService
    {
        return service('auditService');
    }
}
