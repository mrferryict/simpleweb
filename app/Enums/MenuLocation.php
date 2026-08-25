<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Fixed Control Panel / public menu locations (DOC-01 REQ-MENU-001).
 */
enum MenuLocation: string
{
    case Primary = 'PRIMARY';
    case Footer  = 'FOOTER';

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
