<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * scheduled_actions.status (ADR-021 §5.2). Processing states, not editorial status.
 */
enum ScheduledActionStatus: string
{
    case Pending     = 'PENDING';
    case Processing  = 'PROCESSING';
    case Processed   = 'PROCESSED';
    case Skipped     = 'SKIPPED';
    case Cancelled   = 'CANCELLED';
    case Failed      = 'FAILED';
}
