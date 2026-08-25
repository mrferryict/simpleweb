<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Editable Media metadata only (ADR-018 / DOC-06 §29).
 */
final readonly class MediaMetadataUpdateDto
{
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $alt,
    ) {
    }
}
