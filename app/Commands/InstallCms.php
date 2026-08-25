<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Install\InstallService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Throwable;

/**
 * Idempotent production installer (DOC-09 §5, DOC-10 §63, DOC-11 §11–12).
 */
class InstallCms extends BaseCommand
{
    /**
     * @var string
     */
    protected $group = 'cms';

    /**
     * @var string
     */
    protected $name = 'cms:install';

    /**
     * @var string
     */
    protected $description = 'Install or upgrade SMITE CMS schema/bootstrap (idempotent).';

    /**
     * @var string
     */
    protected $usage = 'cms:install [--username <user>] [--email <email>] [--password <password>]';

    /**
     * @var array<string, string>
     */
    protected $arguments = [];

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--username' => 'Initial Admin username (or env cms.install.admin_username).',
        '--email'    => 'Initial Admin email (or env cms.install.admin_email).',
        '--password' => 'Initial Admin password (or env cms.install.admin_password).',
    ];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        try {
            CLI::write('Running SMITE CMS installation…');
            $result = $this->installer()->install($this->readCredentialsFromCli());
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        if ($result['migrated']) {
            CLI::write('Migrations applied.');
        } else {
            CLI::write('Migrations already up to date.');
        }

        if ($result['settings_bootstrapped']) {
            CLI::write('Default Site settings bootstrapped from Config\\Site.');
        }

        if ($result['admin_created']) {
            CLI::write('Initial Admin account created. Password must be changed at first login.');
        }

        if ($result['status'] === 'already_installed') {
            CLI::write('[INFO] ' . InstallService::ALREADY_INSTALLED_MESSAGE);
        } else {
            CLI::write($result['message']);
        }

        return EXIT_SUCCESS;
    }

    /**
     * Collect only non-empty CLI credential options.
     *
     * Omitted or empty options are left unset so InstallService can fall back
     * to cms.install.admin_* environment values (DOC-11 §12).
     *
     * Passwords are not trimmed — an empty password remains empty/unset;
     * a non-empty password is passed through unchanged.
     *
     * @return array{username?: string, email?: string, password?: string}
     */
    private function readCredentialsFromCli(): array
    {
        $credentials = [];

        foreach (['username', 'email', 'password'] as $name) {
            $value = CLI::getOption($name);
            if (is_string($value) && $value !== '') {
                $credentials[$name] = $value;
            }
        }

        return $credentials;
    }

    private function installer(): InstallService
    {
        return Services::installService(getShared: false);
    }
}
