<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Menu item destination types (DOC-01 REQ-MENU-003).
 */
enum MenuTargetType: string
{
    case Page         = 'PAGE';
    case PostCategory = 'POST_CATEGORY';
    case ExternalUrl  = 'EXTERNAL_URL';

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
        $normalized = strtoupper(trim($value));

        return self::tryFrom($normalized);
    }

    public function label(): string
    {
        return match ($this) {
            self::Page         => 'Page',
            self::PostCategory => 'Post Category',
            self::ExternalUrl  => 'External URL',
        };
    }
}
