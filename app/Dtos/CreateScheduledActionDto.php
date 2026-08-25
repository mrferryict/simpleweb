<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Admin schedule-create input. executeAtLocal is Site-timezone wall time.
 */
final readonly class CreateScheduledActionDto
{
    public function __construct(
        public string $targetType,
        public int $targetId,
        public string $actionType,
        public string $executeAtLocal,
    ) {
    }
}
