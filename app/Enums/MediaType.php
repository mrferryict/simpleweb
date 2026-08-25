<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Media asset type (ADR-018 / DOC-06).
 */
enum MediaType: string
{
    case Image    = 'IMAGE';
    case Document = 'DOCUMENT';
}
