<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Page editorial status (DOC-01 REQ-PAGE-003 / DOC-02 §22).
 */
enum PageStatus: string
{
    case Draft       = 'DRAFT';
    case Published   = 'PUBLISHED';
    case Unpublished = 'UNPUBLISHED';
    case Archived    = 'ARCHIVED';
    case Trash       = 'TRASH';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom(strtoupper(trim($value)));
    }
}
