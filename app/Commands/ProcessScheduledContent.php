<?php

declare(strict_types=1);

namespace App\Commands;

use App\Dtos\ScheduledContentRunResult;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;

/**
 * Due scheduled PUBLISH/UNPUBLISH (ADR-021). CLI/cron only — no HTTP route.
 */
class ProcessScheduledContent extends BaseCommand
{
    /**
     * @var string
     */
    protected $group = 'cms';

    /**
     * @var string
     */
    protected $name = 'cms:scheduled-content';

    /**
     * @var string
     */
    protected $description = 'Process due scheduled Page/Post publish and unpublish actions.';

    /**
     * @var string
     */
    protected $usage = 'cms:scheduled-content';

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
        $result = Services::scheduledContentService(getShared: false)->processDue();

        CLI::write(sprintf(
            'claimed=%d applied=%d skipped=%d failed=%d',
            $result->claimed,
            $result->applied,
            $result->skipped,
            $result->failed,
        ));

        return $this->exitCode($result);
    }

    private function exitCode(ScheduledContentRunResult $result): int
    {
        return $result->failed > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
