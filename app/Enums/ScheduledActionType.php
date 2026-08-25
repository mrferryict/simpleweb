<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * scheduled_actions.action_type (ADR-021 §5.2).
 */
enum ScheduledActionType: string
{
    case Publish   = 'PUBLISH';
    case Unpublish = 'UNPUBLISH';
}
