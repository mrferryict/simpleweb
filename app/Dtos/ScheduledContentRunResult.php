<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Spark cms:scheduled-content batch summary (ADR-021).
 */
final readonly class ScheduledContentRunResult
{
    public function __construct(
        public int $claimed,
        public int $applied,
        public int $skipped,
        public int $failed,
    ) {
    }
}
