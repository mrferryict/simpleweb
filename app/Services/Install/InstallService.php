<?php

declare(strict_types=1);

namespace App\Services\Install;

use App\Services\Security\UserEmailService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\MigrationRunner;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Settings\Settings;
use Config\Services;
use Config\Site as SiteConfig;
use RuntimeException;
use Throwable;

/**
 * Production bootstrap for `cms:install` (DOC-09 §5, DOC-10 §63, DOC-11 §11–12).
 *
 * Uses CI4 migrations; does not invent product defaults or demo content.
 */
final class InstallService
{
    public const ALREADY_INSTALLED_MESSAGE = 'SMITE CMS is already installed. No changes made.';

    /**
     * @param array{username?: string, email?: string, password?: string} $credentials
     *
     * @return array{
     *     status: 'fresh'|'already_installed',
     *     migrated: bool,
     *     admin_created: bool,
     *     settings_bootstrapped: bool,
     *     message: string
     * }
     */
    public function install(array $credentials = []): array
    {
        $this->assertRequiredSecrets();

        $migrated = $this->runMigrations();

        if ($this->adminExists()) {
            return [
                'status'                => 'already_installed',
                'migrated'              => $migrated,
                'admin_created'         => false,
                'settings_bootstrapped' => false,
                'message'               => self::ALREADY_INSTALLED_MESSAGE,
            ];
        }

        $normalized = $this->normalizeCredentials($credentials);
        $this->assertAdminCredentials($normalized);

        $settingsBootstrapped = $this->bootstrapDefaultSettingsIfAbsent();

        $this->db->transStart();

        try {
            $this->createInitialAdmin(
                $normalized['username'],
                $normalized['email'],
                $normalized['password'],
            );
            $this->db->transComplete();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        if ($this->db->transStatus() === false) {
            throw new RuntimeException('Admin bootstrap transaction failed.');
        }

        return [
            'status'                => 'fresh',
            'migrated'              => $migrated,
            'admin_created'         => true,
            'settings_bootstrapped' => $settingsBootstrapped,
            'message'               => 'SMITE CMS installation completed.',
        ];
    }

    public function adminExists(): bool
    {
        if (! $this->db->tableExists('auth_groups_users') || ! $this->db->tableExists('users')) {
            return false;
        }

        $count = $this->db->table('auth_groups_users')
            ->where('group', 'admin')
            ->countAllResults();

        return $count > 0;
    }

    private function assertRequiredSecrets(): void
    {
        $required = [
            'EMAIL_ENCRYPTION_KEY',
            'EMAIL_LOOKUP_HMAC_KEY',
            'skey',
        ];

        foreach ($required as $key) {
            $value = env($key, null);
            if (! is_string($value) || trim($value) === '') {
                throw new RuntimeException(
                    'Required environment configuration is missing or empty: ' . $key,
                );
            }
        }

        $enc  = trim((string) env('EMAIL_ENCRYPTION_KEY'));
        $hmac = trim((string) env('EMAIL_LOOKUP_HMAC_KEY'));
        if ($enc === $hmac) {
            throw new RuntimeException(
                'EMAIL_ENCRYPTION_KEY and EMAIL_LOOKUP_HMAC_KEY must differ.',
            );
        }
        if (! preg_match('/^[0-9a-fA-F]{64}$/', $enc) || ! preg_match('/^[0-9a-fA-F]{64}$/', $hmac)) {
            throw new RuntimeException(
                'EMAIL_ENCRYPTION_KEY and EMAIL_LOOKUP_HMAC_KEY must each be 64 hexadecimal characters.',
            );
        }
    }

    private function runMigrations(): bool
    {
        /** @var MigrationRunner $runner */
        $runner = service('migrations');
        $runner->setNamespace(null);

        $before = $this->latestBatch();
        $ok     = $runner->latest();
        if ($ok === false) {
            throw new RuntimeException('Database migrations failed.');
        }

        return $this->latestBatch() > $before;
    }

    private function latestBatch(): int
    {
        if (! $this->db->tableExists('migrations')) {
            return 0;
        }

        $row = $this->db->table('migrations')
            ->selectMax('batch', 'max_batch')
            ->get()
            ->getRowArray();

        return isset($row['max_batch']) ? (int) $row['max_batch'] : 0;
    }

    /**
     * Persist Config\Site bootstrap values once when Settings rows are absent.
     * Does not invent values — copies existing Config\Site contract defaults.
     * Uses the settings table (not Settings::get fallback to Config).
     */
    private function bootstrapDefaultSettingsIfAbsent(): bool
    {
        if (! $this->db->tableExists('settings')) {
            return false;
        }

        $existing = $this->db->table('settings')
            ->where('class', SiteConfig::class)
            ->where('key', 'siteName')
            ->countAllResults();
        if ($existing > 0) {
            return false;
        }

        /** @var SiteConfig $site */
        $site = config(SiteConfig::class);

        $this->settings->set('Site.siteName', $site->siteName);
        $this->settings->set('Site.siteDescription', $site->siteDescription);
        $this->settings->set('Site.defaultLocale', $site->defaultLocale);
        $this->settings->set('Site.primaryLocale', $site->defaultLocale);
        $secondary = trim((string) ($site->secondaryLocale ?? ''));
        $this->settings->set('Site.secondaryLocale', $secondary);
        $this->settings->set('Site.timezone', $site->timezone);
        $this->settings->set('Site.contactEmail', $site->contactEmail);

        return true;
    }

    /**
     * @param array{username: string, email: string, password: string} $credentials
     */
    private function createInitialAdmin(string $username, string $email, string $password): void
    {
        /** @var UserModel $users */
        $users = model(UserModel::class);

        $user = new User([
            'username' => $username,
            'active'   => 1,
        ]);
        // Password identity without placing plaintext email into auth_identities.secret (ADR-008).
        $user->password = $password;

        if (! $users->save($user)) {
            throw new RuntimeException('Unable to create the initial Admin user.');
        }

        $userId = (int) $users->getInsertID();
        if ($userId < 1) {
            $row = $users->where('username', $username)->first();
            $userId = $row instanceof User ? (int) $row->id : 0;
        }

        $created = $users->find($userId);
        if (! $created instanceof User) {
            throw new RuntimeException('Unable to load the initial Admin user after create.');
        }

        $created->addGroup('admin');
        $created->forcePasswordReset();

        $this->userEmail->setEmail((int) $created->id, $email);

        // Ensure identity secret is not plaintext email if Shield wrote one.
        $identity = $this->db->table('auth_identities')
            ->where('user_id', (int) $created->id)
            ->where('type', 'email_password')
            ->get()
            ->getRowArray();
        if ($identity !== null && isset($identity['secret']) && is_string($identity['secret'])) {
            $secret = strtolower(trim($identity['secret']));
            if ($secret === strtolower(trim($email)) || str_contains($secret, '@')) {
                $this->db->table('auth_identities')
                    ->where('id', (int) $identity['id'])
                    ->update(['secret' => '']);
            }
        }
    }

    /**
     * @param array{username?: string, email?: string, password?: string} $credentials
     *
     * @return array{username: string, email: string, password: string}
     */
    private function normalizeCredentials(array $credentials): array
    {
        $username = $this->resolveCredential($credentials, 'username', 'cms.install.admin_username');
        $email    = $this->resolveCredential($credentials, 'email', 'cms.install.admin_email');
        $password = $this->resolveCredential($credentials, 'password', 'cms.install.admin_password');

        return [
            'username' => strtolower(trim($username)),
            'email'    => strtolower(trim($email)),
            'password' => $password,
        ];
    }

    /**
     * Prefer an explicitly supplied non-empty credential; otherwise use env.
     *
     * Empty strings are treated as unset so callers (including InstallCms when a
     * CLI flag is present but blank) do not suppress cms.install.admin_* env.
     * Password values are never trimmed.
     *
     * @param array{username?: string, email?: string, password?: string} $credentials
     */
    private function resolveCredential(array $credentials, string $key, string $envKey): string
    {
        $value = $credentials[$key] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $fromEnv = env($envKey);

        return is_string($fromEnv) ? $fromEnv : '';
    }

    /**
     * @param array{username: string, email: string, password: string} $credentials
     */
    private function assertAdminCredentials(array $credentials): void
    {
        if ($credentials['username'] === '' || $credentials['email'] === '' || $credentials['password'] === '') {
            throw new RuntimeException(
                'Initial Admin credentials are required via --username/--email/--password '
                . 'or cms.install.admin_username / cms.install.admin_email / cms.install.admin_password.',
            );
        }

        if (! preg_match('/\A[a-z0-9.]+\z/', $credentials['username'])) {
            throw new RuntimeException('Admin username format is invalid.');
        }

        if (strlen($credentials['username']) < 3 || strlen($credentials['username']) > 30) {
            throw new RuntimeException('Admin username length is invalid.');
        }

        if (! filter_var($credentials['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Admin email format is invalid.');
        }

        $policyError = Services::passwordPolicyService(getShared: false)
            ->validatePasswordForUsername(
                $credentials['password'],
                $credentials['username'],
                $credentials['email'],
            );
        if ($policyError !== null) {
            throw new RuntimeException($policyError);
        }
    }

    public function __construct(
        private readonly BaseConnection $db,
        private readonly Settings $settings,
        private readonly UserEmailService $userEmail,
    ) {
    }
}
