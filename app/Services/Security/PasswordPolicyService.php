<?php

declare(strict_types=1);

namespace App\Services\Security;

use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Entities\User;

/**
 * Single Shield password-policy entry point (V2-005 / ADR-027 P0-3).
 *
 * Delegates to service('passwords')->check() — no duplicate rules.
 */
final class PasswordPolicyService
{
    public function __construct(
        private readonly Passwords $passwords,
    ) {
    }

    /**
     * Returns a user-facing rejection reason, or null when Shield accepts the password.
     */
    public function validatePassword(string $password, User $user): ?string
    {
        if ($password === '') {
            return 'Password is required.';
        }

        if ($user->email === null) {
            $user->setEmail('');
        }

        $check = $this->passwords->check($password, $user);
        if (! $check->isOK()) {
            return (string) ($check->reason() ?? 'Password does not meet requirements.');
        }

        return null;
    }

    /**
     * Validate a password for an account that is not yet persisted.
     *
     * @param string|null $email When known (e.g. install / user create), include so Shield's
     *                           NothingPersonalValidator can evaluate email parts safely.
     */
    public function validatePasswordForUsername(string $password, string $username, ?string $email = null): ?string
    {
        $probe = new User([
            'id'       => 1,
            'username' => $username,
        ]);
        $probe->setEmail($email ?? '');

        return $this->validatePassword($password, $probe);
    }

    /**
     * @return array<string, string>
     */
    public function validateNewPasswordWithConfirmation(
        string $password,
        string $confirm,
        User $user,
        string $passwordField = 'password',
        string $confirmField = 'password_confirm',
    ): array {
        $errors = [];

        if ($password === '') {
            $errors[$passwordField] = 'Password is required.';
        }

        if ($confirm === '') {
            $errors[$confirmField] = 'Password confirmation is required.';
        } elseif ($password !== '' && $password !== $confirm) {
            $errors[$confirmField] = 'Password confirmation does not match.';
        }

        if ($errors === [] && $password !== '') {
            $reason = $this->validatePassword($password, $user);
            if ($reason !== null) {
                $errors[$passwordField] = $reason;
            }
        }

        return $errors;
    }
}
