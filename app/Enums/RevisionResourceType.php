<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Revision resource_type values (ADR-019 §7.1).
 */
enum RevisionResourceType: string
{
    case Page = 'page';
    case Post = 'post';
}
