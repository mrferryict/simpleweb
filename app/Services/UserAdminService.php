<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\CreateStaffUserDto;
use App\Dtos\UpdateStaffUserDto;
use App\Enums\AuditEvent;
use App\Services\Audit\AuditService;
use App\Services\Security\PasswordPolicyService;
use App\Services\Security\UserEmailService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Config\AuthGroups;
use RuntimeException;
use Throwable;

/**
 * Control Panel staff user management (V2-003 / ADR-027 P0-1).
 *
 * Admin group is never assignable via this service. Single-Admin invariant enforced server-side.
 */
final class UserAdminService
{
    /** @var list<string> */
    public const ASSIGNABLE_GROUPS = ['editor', 'contributor'];

    private const USERNAME_MIN = 3;
    private const USERNAME_MAX = 30;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly UserEmailService $userEmail,
        private readonly AuditService $audit,
        private readonly PasswordPolicyService $passwordPolicy,
        private readonly AuthGroups $authGroups,
    ) {
    }

    /**
     * @return list<array{
     *     id: int,
     *     username: string,
     *     email_display: string,
     *     group: string,
     *     group_label: string,
     *     is_active: bool,
     *     is_admin: bool,
     *     can_edit: bool,
     *     can_deactivate: bool,
     *     created_at: string,
     *     updated_at: string
     * }>
     */
    public function listForAdmin(): array
    {
        if (! $this->db->tableExists('users')) {
            return [];
        }

        $rows = $this->db->table('users')
            ->select('id, username, active, created_at, updated_at')
            ->orderBy('username', 'ASC')
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['id'] ?? 0);
            if ($userId < 1) {
                continue;
            }

            $group      = $this->resolvePrimaryGroup($userId);
            $isAdmin    = $group === 'admin';
            $email      = $this->userEmail->getDecryptedEmail($userId);
            $onlyAdmin  = $isAdmin && $this->countAdminGroupMembers() === 1;

            $out[] = [
                'id'             => $userId,
                'username'       => (string) ($row['username'] ?? ''),
                'email_display'  => self::maskEmailForDisplay($email),
                'group'          => $group,
                'group_label'    => $this->groupLabel($group),
                'is_active'      => (int) ($row['active'] ?? 0) === 1,
                'is_admin'       => $isAdmin,
                'can_edit'       => true,
                'can_deactivate' => ! $onlyAdmin,
                'created_at'     => $this->formatTimestamp($row['created_at'] ?? null),
                'updated_at'     => $this->formatTimestamp($row['updated_at'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *     multiple_admins: bool,
     *     admin_count: int,
     *     message: string|null
     * }
     */
    public function getAdminInvariantStatus(): array
    {
        $count = $this->countAdminGroupMembers();

        return [
            'multiple_admins' => $count > 1,
            'admin_count'     => $count,
            'message'         => $count > 1
                ? 'Multiple Admin accounts were detected in the database. This UI does not repair legacy data automatically. Review accounts manually.'
                : null,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     username: string,
     *     email: string,
     *     group: string,
     *     is_active: bool,
     *     is_admin: bool,
     *     can_change_group: bool,
     *     can_change_active: bool
     * }|null
     */
    public function findForEdit(int $id): ?array
    {
        $user = $this->findUser($id);
        if ($user === null) {
            return null;
        }

        $group     = $this->resolvePrimaryGroup($id);
        $isAdmin   = $group === 'admin';
        $onlyAdmin = $isAdmin && $this->countAdminGroupMembers() === 1;

        return [
            'id'                => $id,
            'username'          => (string) $user->username,
            'email'             => $this->userEmail->getDecryptedEmail($id) ?? '',
            'group'             => $group,
            'is_active'         => (bool) $user->active,
            'is_admin'          => $isAdmin,
            'can_change_group'  => ! $isAdmin,
            'can_change_active' => ! $onlyAdmin,
        ];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function create(CreateStaffUserDto $dto, int $actorId): array
    {
        $normalized = $this->normalizeCreate($dto);
        $errors     = $this->validateCreate($normalized);
        if ($errors !== []) {
            return $errors;
        }

        if ($normalized['group'] === 'admin' || ! in_array($normalized['group'], self::ASSIGNABLE_GROUPS, true)) {
            return ['group' => 'Only Editor or Contributor roles can be assigned.'];
        }

        /** @var UserModel $users */
        $users = model(UserModel::class);

        try {
            $user = new User([
                'username' => $normalized['username'],
                'active'   => $normalized['is_active'] ? 1 : 0,
            ]);
            // Shield stores User::$email in auth_identities.secret. Use a unique non-PII
            // placeholder so the (type, secret) unique index is satisfied (ADR-008).
            $user->email    = 'shield-' . $normalized['username'] . '@identity.smite';
            $user->password = $normalized['password'];

            if (! $users->save($user)) {
                return ['_persist' => 'Unable to create user account.'];
            }

            $userId = (int) $users->getInsertID();
            if ($userId < 1) {
                $row = $users->where('username', $normalized['username'])->first();
                $userId = $row instanceof User ? (int) $row->id : 0;
            }

            if ($userId < 1) {
                return ['_persist' => 'Unable to create user account.'];
            }

            $created = $users->find($userId);
            if (! $created instanceof User) {
                return ['_persist' => 'Unable to load user after create.'];
            }

            $created->addGroup($normalized['group']);
            $created->forcePasswordReset();

            try {
                $this->userEmail->setEmail($userId, $normalized['email']);
            } catch (RuntimeException $e) {
                return $this->mapEmailException($e);
            }

            $this->assignUsernameLoginIdentitySecret($userId, $normalized['username'], $normalized['email']);

            (void) $this->audit->append(
                AuditEvent::UserCreated,
                $actorId,
                'user',
                $userId,
                null,
                [
                    'username' => $normalized['username'],
                    'group'    => $normalized['group'],
                    'active'   => $normalized['is_active'],
                ],
            );
        } catch (Throwable $e) {
            log_message('error', 'UserAdminService::create failed: {message}', [
                'message' => $e->getMessage(),
            ]);

            return ['_persist' => 'Unable to create user account.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function update(int $id, UpdateStaffUserDto $dto, int $actorId): array
    {
        $existing = $this->findForEdit($id);
        if ($existing === null) {
            return ['_not_found' => 'User not found.'];
        }

        $normalized = $this->normalizeUpdate($dto);
        $errors     = $this->validateUpdate($normalized, $existing);
        if ($errors !== []) {
            return $errors;
        }

        $user = $this->findUser($id);
        if ($user === null) {
            return ['_not_found' => 'User not found.'];
        }

        $oldGroup = $existing['group'];
        $newGroup = $existing['is_admin'] ? 'admin' : $normalized['group'];

        if (! $existing['is_admin'] && ($newGroup === 'admin' || ! in_array($newGroup, self::ASSIGNABLE_GROUPS, true))) {
            return ['group' => 'Only Editor or Contributor roles can be assigned.'];
        }

        $newActive = $existing['can_change_active'] ? $normalized['is_active'] : $existing['is_active'];
        if ($existing['is_admin'] && ! $newActive && $this->countAdminGroupMembers() === 1) {
            return ['is_active' => 'The only Admin account cannot be deactivated.'];
        }

        $this->db->transStart();

        try {
            if ($existing['can_change_active'] && (bool) $user->active !== $newActive) {
                $user->active = $newActive ? 1 : 0;
            }

            /** @var UserModel $users */
            $users = model(UserModel::class);
            if (! $users->save($user)) {
                $this->db->transRollback();

                return ['_persist' => 'Unable to update user account.'];
            }

            try {
                $this->userEmail->setEmail($id, $normalized['email']);
            } catch (RuntimeException $e) {
                $this->db->transRollback();

                return $this->mapEmailException($e);
            }

            if (! $existing['is_admin'] && $newGroup !== $oldGroup) {
                $this->replaceStaffGroup($id, $newGroup);
                (void) $this->audit->append(
                    AuditEvent::UserRoleChanged,
                    $actorId,
                    'user',
                    $id,
                    null,
                    ['from' => $oldGroup, 'to' => $newGroup],
                );
            }

            $this->db->transComplete();
        } catch (Throwable) {
            $this->db->transRollback();
            log_message('error', 'UserAdminService::update failed for user {id}', ['id' => $id]);

            return ['_persist' => 'Unable to update user account.'];
        }

        if ($this->db->transStatus() === false) {
            return ['_persist' => 'Unable to update user account.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function activate(int $id, int $actorId): array
    {
        return $this->setActiveState($id, true, $actorId);
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function deactivate(int $id, int $actorId): array
    {
        return $this->setActiveState($id, false, $actorId);
    }

    /**
     * @return array<string, string>
     */
    public function getAssignableGroups(): array
    {
        $out = [];
        foreach (self::ASSIGNABLE_GROUPS as $key) {
            $out[$key] = $this->groupLabel($key);
        }

        return $out;
    }

    public static function maskEmailForDisplay(?string $email): string
    {
        if ($email === null || $email === '') {
            return '—';
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[1] === '') {
            return '***';
        }

        $local   = $parts[0];
        $visible = $local !== '' ? mb_substr($local, 0, 1) : '';

        return $visible . '***@' . $parts[1];
    }

    /**
     * @return array<string, string>
     */
    private function setActiveState(int $id, bool $active, int $actorId): array
    {
        $user = $this->findUser($id);
        if ($user === null) {
            return ['_not_found' => 'User not found.'];
        }

        $group   = $this->resolvePrimaryGroup($id);
        $isAdmin = $group === 'admin';

        if (! $active && $isAdmin && $this->countAdminGroupMembers() === 1) {
            return ['_invariant' => 'The only Admin account cannot be deactivated.'];
        }

        if ((bool) $user->active === $active) {
            return [];
        }

        $user->active = $active ? 1 : 0;

        /** @var UserModel $users */
        $users = model(UserModel::class);
        if (! $users->save($user)) {
            return ['_persist' => 'Unable to update account status.'];
        }

        (void) $this->audit->append(
            $active ? AuditEvent::UserActivated : AuditEvent::UserDeactivated,
            $actorId,
            'user',
            $id,
        );

        return [];
    }

    /**
     * @return array{
     *     username: string,
     *     email: string,
     *     password: string,
     *     password_confirm: string,
     *     group: string,
     *     is_active: bool
     * }
     */
    private function normalizeCreate(CreateStaffUserDto $dto): array
    {
        return [
            'username'         => strtolower(trim($dto->username)),
            'email'            => strtolower(trim($dto->email)),
            'password'         => $dto->password,
            'password_confirm' => $dto->passwordConfirm,
            'group'            => strtolower(trim($dto->group)),
            'is_active'        => $dto->isActive,
        ];
    }

    /**
     * @return array{email: string, group: string, is_active: bool}
     */
    private function normalizeUpdate(UpdateStaffUserDto $dto): array
    {
        return [
            'email'     => strtolower(trim($dto->email)),
            'group'     => strtolower(trim($dto->group)),
            'is_active' => $dto->isActive,
        ];
    }

    /**
     * @param array{
     *     username: string,
     *     email: string,
     *     password: string,
     *     password_confirm: string,
     *     group: string,
     *     is_active: bool
     * } $data
     *
     * @return array<string, string>
     */
    private function validateCreate(array $data): array
    {
        $errors = [];

        if ($data['username'] === '') {
            $errors['username'] = 'Username is required.';
        } elseif (! preg_match('/\A[a-z0-9.]+\z/', $data['username'])) {
            $errors['username'] = 'Username may only contain lowercase letters, numbers, and dots.';
        } elseif (strlen($data['username']) < self::USERNAME_MIN || strlen($data['username']) > self::USERNAME_MAX) {
            $errors['username'] = 'Username must be between ' . self::USERNAME_MIN . ' and ' . self::USERNAME_MAX . ' characters.';
        } elseif ($this->usernameExists($data['username'])) {
            $errors['username'] = 'Username is already in use.';
        }

        if ($data['email'] === '') {
            $errors['email'] = 'Email is required.';
        } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        if ($data['password'] === '') {
            $errors['password'] = 'Password is required.';
        } elseif ($data['password_confirm'] === '') {
            $errors['password_confirm'] = 'Password confirmation is required.';
        } elseif ($data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'Password confirmation does not match.';
        } elseif ($errors === []) {
            $reason = $this->passwordPolicy->validatePasswordForUsername(
                $data['password'],
                $data['username'],
                $data['email'],
            );
            if ($reason !== null) {
                $errors['password'] = $reason;
            }
        }

        if ($data['group'] === '') {
            $errors['group'] = 'Role is required.';
        } elseif ($data['group'] === 'admin' || ! in_array($data['group'], self::ASSIGNABLE_GROUPS, true)) {
            $errors['group'] = 'Only Editor or Contributor roles can be assigned.';
        }

        return $errors;
    }

    /**
     * @param array{email: string, group: string, is_active: bool} $data
     * @param array{
     *     id: int,
     *     username: string,
     *     email: string,
     *     group: string,
     *     is_active: bool,
     *     is_admin: bool,
     *     can_change_group: bool,
     *     can_change_active: bool
     * } $existing
     *
     * @return array<string, string>
     */
    private function validateUpdate(array $data, array $existing): array
    {
        $errors = [];

        if ($data['email'] === '') {
            $errors['email'] = 'Email is required.';
        } elseif (! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        if (! $existing['is_admin']) {
            if ($data['group'] === '') {
                $errors['group'] = 'Role is required.';
            } elseif ($data['group'] === 'admin' || ! in_array($data['group'], self::ASSIGNABLE_GROUPS, true)) {
                $errors['group'] = 'Only Editor or Contributor roles can be assigned.';
            }
        }

        return $errors;
    }

    private function usernameExists(string $username): bool
    {
        return $this->db->table('users')
            ->where('username', $username)
            ->countAllResults() > 0;
    }

    private function findUser(int $id): ?User
    {
        if ($id < 1) {
            return null;
        }

        /** @var UserModel $users */
        $users = model(UserModel::class);
        $user  = $users->find($id);

        return $user instanceof User ? $user : null;
    }

    private function resolvePrimaryGroup(int $userId): string
    {
        if (! $this->db->tableExists('auth_groups_users')) {
            return 'contributor';
        }

        $rows = $this->db->table('auth_groups_users')
            ->select('group')
            ->where('user_id', $userId)
            ->get()
            ->getResultArray();

        $groups = array_map(static fn (array $row): string => (string) ($row['group'] ?? ''), $rows);

        if (in_array('admin', $groups, true)) {
            return 'admin';
        }

        foreach (self::ASSIGNABLE_GROUPS as $staffGroup) {
            if (in_array($staffGroup, $groups, true)) {
                return $staffGroup;
            }
        }

        return $groups[0] ?? 'contributor';
    }

    private function countAdminGroupMembers(): int
    {
        if (! $this->db->tableExists('auth_groups_users')) {
            return 0;
        }

        return $this->db->table('auth_groups_users')
            ->where('group', 'admin')
            ->countAllResults();
    }

    private function replaceStaffGroup(int $userId, string $newGroup): void
    {
        $this->db->table('auth_groups_users')
            ->where('user_id', $userId)
            ->whereIn('group', self::ASSIGNABLE_GROUPS)
            ->delete();

        $user = $this->findUser($userId);
        if ($user !== null) {
            $user->addGroup($newGroup);
        }
    }

    private function assignUsernameLoginIdentitySecret(int $userId, string $username, string $email): void
    {
        $identity = $this->db->table('auth_identities')
            ->where('user_id', $userId)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();

        if ($identity === null || ! isset($identity['id'])) {
            return;
        }

        $secret = isset($identity['secret']) && is_string($identity['secret'])
            ? strtolower(trim($identity['secret']))
            : '';

        $normalizedEmail = strtolower(trim($email));

        if (
            $secret === ''
            || $secret === $normalizedEmail
            || str_contains($secret, '@')
        ) {
            $this->db->table('auth_identities')
                ->where('id', (int) $identity['id'])
                ->update(['secret' => $username]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function mapEmailException(RuntimeException $e): array
    {
        $message = $e->getMessage();
        if ($message === 'Email is already in use.') {
            return ['email' => $message];
        }
        if ($message === 'Invalid email.') {
            return ['email' => 'Email format is invalid.'];
        }

        return ['email' => 'Unable to save email.'];
    }

    private function groupLabel(string $group): string
    {
        return (string) ($this->authGroups->groups[$group]['title'] ?? ucfirst($group));
    }

    private function formatTimestamp(mixed $value): string
    {
        if (is_object($value) && method_exists($value, 'format')) {
            return $value->format('Y-m-d H:i:s');
        }

        return is_string($value) && $value !== '' ? $value : '—';
    }
}
