<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditService;
use App\Services\Security\AuthThrottleService;
use App\Services\Security\PasswordPolicyService;
use App\Services\Security\PasswordResetEmailService;
use App\Services\Security\UserEmailService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
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

                $recipientEmail = $this->userEmail()->getDecryptedEmail($userId);
                if ($recipientEmail !== null) {
                    (void) $this->passwordResetEmailService()->sendResetEmail(
                        $recipientEmail,
                        $token,
                        self::TOKEN_TTL,
                    );
                }

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
            'errors'  => [],
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
                'errors'  => [],
                'success' => null,
                'token'   => '',
            ]);
        }

        $tokenPost    = $this->request->getPost('token');
        $passwordPost = $this->request->getPost('password');
        $confirmPost  = $this->request->getPost('password_confirm');
        $token        = is_string($tokenPost) ? trim($tokenPost) : '';
        $password     = is_string($passwordPost) ? $passwordPost : '';
        $confirm      = is_string($confirmPost) ? $confirmPost : '';

        if ($token === '') {
            return view('admin/auth/password_reset_verify', [
                'error'   => self::GENERIC,
                'errors'  => [],
                'success' => null,
                'token'   => '',
            ]);
        }

        try {
            $userId = cache()->get('auth.reset.' . $token);
            if (! is_int($userId) && ! (is_string($userId) && ctype_digit($userId))) {
                return view('admin/auth/password_reset_verify', [
                    'error'   => self::GENERIC,
                    'errors'  => [],
                    'success' => null,
                    'token'   => '',
                ]);
            }
            $userId = (int) $userId;

            /** @var UserModel $users */
            $users = model(UserModel::class);
            $user  = $users->find($userId);
            if (! $user instanceof User) {
                return view('admin/auth/password_reset_verify', [
                    'error'   => self::GENERIC,
                    'errors'  => [],
                    'success' => null,
                    'token'   => '',
                ]);
            }

            $errors = $this->passwordPolicy()->validateNewPasswordWithConfirmation(
                $password,
                $confirm,
                $user,
            );
            if ($errors !== []) {
                return view('admin/auth/password_reset_verify', [
                    'error'   => null,
                    'errors'  => $errors,
                    'success' => null,
                    'token'   => $token,
                ]);
            }

            $user->password = $password;
            $users->save($user);
            $user->undoForcePasswordReset();
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
                'errors'  => [],
                'success' => null,
                'token'   => $token,
            ]);
        }

        return view('admin/auth/password_reset_verify', [
            'error'   => null,
            'errors'  => [],
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

    private function passwordResetEmailService(): PasswordResetEmailService
    {
        return service('passwordResetEmailService');
    }

    private function passwordPolicy(): PasswordPolicyService
    {
        return service('passwordPolicyService');
    }
}
