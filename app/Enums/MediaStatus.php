<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Media lifecycle status (ADR-018 / DOC-06).
 */
enum MediaStatus: string
{
    case Active = 'ACTIVE';
    case Trash  = 'TRASH';
}
