<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Post editorial status (DOC-01 REQ-POST-003 / DOC-02 §22).
 *
 * Storage tokens match PageStatus SCREAMING_SNAKE style.
 * PENDING_REVIEW maps the documented "Pending Review" state.
 * Publishing transitions remain deferred (Phase 4); foundation persists DRAFT / TRASH.
 */
enum PostStatus: string
{
    case Draft         = 'DRAFT';
    case PendingReview = 'PENDING_REVIEW';
    case Published     = 'PUBLISHED';
    case Unpublished   = 'UNPUBLISHED';
    case Archived      = 'ARCHIVED';
    case Trash         = 'TRASH';

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
        $normalized = strtoupper(trim(str_replace([' ', '-'], '_', $value)));

        return self::tryFrom($normalized);
    }
}
