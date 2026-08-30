<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\Demo\DemoContentService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Throwable;

/**
 * Optional starter content installer (post-V1 / TH-004).
 */
class DemoCms extends BaseCommand
{
    /**
     * @var string
     */
    protected $group = 'cms';

    /**
     * @var string
     */
    protected $name = 'cms:demo';

    /**
     * @var string
     */
    protected $description = 'Install optional SMITE CMS starter Pages and Posts (idempotent).';

    /**
     * @var string
     */
    protected $usage = 'cms:demo';

    /**
     * @var array<string, string>
     */
    protected $arguments = [];

    /**
     * @var array<string, string>
     */
    protected $options = [];

    /**
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): int
    {
        try {
            CLI::write('Installing SMITE CMS demo content…');
            $result = $this->demoContentService()->install();
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return EXIT_ERROR;
        }

        if ($result['status'] === 'already_installed') {
            CLI::write('[INFO] ' . $result['message']);

            return EXIT_SUCCESS;
        }

        CLI::write($result['message']);
        CLI::write('Pages created: ' . $result['pages_created']);
        CLI::write('Posts created: ' . $result['posts_created']);

        foreach ($result['skipped'] as $skipped) {
            CLI::write('Skipped existing content: ' . $skipped);
        }

        return EXIT_SUCCESS;
    }

    private function demoContentService(): DemoContentService
    {
        return Services::demoContentService(getShared: false);
    }
}
