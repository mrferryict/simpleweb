<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Theme lifecycle states (ADR-022).
 */
enum ThemeState: string
{
    case Draft   = 'DRAFT';
    case Enabled = 'ENABLED';
    case Active  = 'ACTIVE';
}
