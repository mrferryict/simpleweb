<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * V1 Content Schema scalar / block types (DOC-01 REQ-CONT-003 / ADR-004).
 */
enum ContentFieldType: string
{
    case Text        = 'TEXT';
    case Textarea    = 'TEXTAREA';
    case RichText    = 'RICH_TEXT';
    case Image       = 'IMAGE';
    case YoutubeUrl  = 'YOUTUBE_URL';
    case Url         = 'URL';
    case Document    = 'DOCUMENT';
    case Repeatable  = 'REPEATABLE';

    public static function tryFromString(string $value): ?self
    {
        return self::tryFrom(strtoupper(trim($value)));
    }
}
