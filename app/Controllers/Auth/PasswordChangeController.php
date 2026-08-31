<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditService;
use App\Services\Security\PasswordPolicyService;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Throwable;

/**
 * Mandatory password change for accounts with force_reset (cms:install / ADR-026).
 */
class PasswordChangeController extends BaseController
{
    private const GENERIC_FAILURE = 'Unable to update password.';

    public function show(): ResponseInterface|RedirectResponse|string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->requiresPasswordReset()) {
            return redirect()->to('/admin');
        }

        return view('admin/auth/password_change', [
            'errors' => [],
            'error'  => null,
        ]);
    }

    public function submit(): ResponseInterface|RedirectResponse|string
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! $user->requiresPasswordReset()) {
            return redirect()->to('/admin');
        }

        $currentPost  = $this->request->getPost('password');
        $newPost      = $this->request->getPost('password_new');
        $confirmPost  = $this->request->getPost('password_confirm');

        $current = is_string($currentPost) ? $currentPost : '';
        $new     = is_string($newPost) ? $newPost : '';
        $confirm = is_string($confirmPost) ? $confirmPost : '';

        $errors = $this->validateSubmission($user, $current, $new, $confirm);
        if ($errors !== []) {
            return view('admin/auth/password_change', [
                'errors' => $errors,
                'error'  => null,
            ]);
        }

        try {
            $user->password = $new;
            /** @var UserModel $users */
            $users = model(UserModel::class);
            if (! $users->save($user)) {
                return view('admin/auth/password_change', [
                    'errors' => [],
                    'error'  => self::GENERIC_FAILURE,
                ]);
            }

            $saved = $users->find((int) $user->id);
            if ($saved instanceof User) {
                $saved->undoForcePasswordReset();
            }

            (void) $this->auditService()->append(
                AuditEvent::PasswordChanged,
                (int) $user->id,
                'user',
                (int) $user->id,
                null,
                ['surface' => 'force_password_change'],
            );
        } catch (Throwable) {
            log_message('error', 'Forced password change failed due to an internal error.');

            return view('admin/auth/password_change', [
                'errors' => [],
                'error'  => self::GENERIC_FAILURE,
            ]);
        }

        return redirect()->to('/admin')->with('success', 'Password updated.');
    }

    /**
     * @return array<string, string>
     */
    private function validateSubmission(User $user, string $current, string $new, string $confirm): array
    {
        $errors = [];

        if ($current === '') {
            $errors['password'] = 'Current password is required.';
        } elseif (! $this->passwords()->verify($current, (string) $user->password_hash)) {
            $errors['password'] = 'Current password is incorrect.';
        }

        if ($new === '') {
            $errors['password_new'] = 'New password is required.';
        } elseif ($confirm === '') {
            $errors['password_confirm'] = 'Password confirmation is required.';
        } elseif ($new !== $confirm) {
            $errors['password_confirm'] = 'Password confirmation does not match.';
        } elseif ($errors === []) {
            $reason = $this->passwordPolicy()->validatePassword($new, $user);
            if ($reason !== null) {
                $errors['password_new'] = $reason;
            }
        }

        return $errors;
    }

    private function passwords(): Passwords
    {
        return service('passwords');
    }

    private function passwordPolicy(): PasswordPolicyService
    {
        return service('passwordPolicyService');
    }

    private function auditService(): AuditService
    {
        return service('auditService');
    }
}
