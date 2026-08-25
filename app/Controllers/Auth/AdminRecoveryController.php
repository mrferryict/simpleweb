<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditService;
use App\Services\Security\AuthThrottleService;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

/**
 * Emergency Admin recovery via environment skey (DOC-03 / REQ-AUTH-009 / ADR-026).
 *
 * POST /cp/admin-recovery — throttled; opaque failure messages.
 */
class AdminRecoveryController extends BaseController
{
    private const GENERIC = 'Recovery failed.';
    private const THROTTLED = 'Too many attempts. Please try again later.';

    public function recover(): ResponseInterface|string
    {
        if (! $this->request->is('post')) {
            return view('admin/auth/admin_recovery', [
                'error'   => null,
                'success' => null,
            ]);
        }

        $ip = $this->request->getIPAddress();
        if (! $this->authThrottle()->allow('admin_recovery', $ip)) {
            return view('admin/auth/admin_recovery', [
                'error'   => self::THROTTLED,
                'success' => null,
            ]);
        }

        $skeyPost     = $this->request->getPost('skey');
        $usernamePost = $this->request->getPost('username');
        $passwordPost = $this->request->getPost('password');

        $skey     = is_string($skeyPost) ? $skeyPost : '';
        $username = is_string($usernamePost) ? strtolower(trim($usernamePost)) : '';
        $password = is_string($passwordPost) ? $passwordPost : '';

        $expected = (string) env('skey', '');
        if ($expected === '' || $skey === '' || ! hash_equals($expected, $skey)) {
            return view('admin/auth/admin_recovery', [
                'error'   => self::GENERIC,
                'success' => null,
            ]);
        }

        if ($username === '' || $password === '') {
            return view('admin/auth/admin_recovery', [
                'error'   => self::GENERIC,
                'success' => null,
            ]);
        }

        try {
            /** @var UserModel $users */
            $users = model(UserModel::class);
            /** @var User|null $user */
            $user = $users->where('username', $username)->first();
            if ($user === null || ! $user->inGroup('admin')) {
                return view('admin/auth/admin_recovery', [
                    'error'   => self::GENERIC,
                    'success' => null,
                ]);
            }

            $user->password = $password;
            $users->save($user);

            (void) $this->auditService()->append(
                AuditEvent::AdminRecovery,
                (int) $user->id,
                'user',
                (int) $user->id,
                null,
                ['surface' => 'admin_recovery'],
            );
            (void) $this->auditService()->append(
                AuditEvent::PasswordChanged,
                (int) $user->id,
                'user',
                (int) $user->id,
                null,
                ['surface' => 'admin_recovery'],
            );
        } catch (Throwable) {
            log_message('error', 'Admin recovery failed due to an internal error.');

            return view('admin/auth/admin_recovery', [
                'error'   => self::GENERIC,
                'success' => null,
            ]);
        }

        return view('admin/auth/admin_recovery', [
            'error'   => null,
            'success' => 'Password updated.',
        ]);
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
