<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * scheduled_actions.target_type (ADR-021 §5.2).
 */
enum ScheduledActionTargetType: string
{
    case Page = 'page';
    case Post = 'post';
}
