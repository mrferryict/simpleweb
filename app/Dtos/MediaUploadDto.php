<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Media upload transport (ADR-018).
 */
final readonly class MediaUploadDto
{
    public function __construct(
        public string $tmpPath,
        public string $originalFilename,
        public string $clientMime,
        public int $sizeBytes,
        public ?string $title = null,
        public ?string $alt = null,
        public ?string $description = null,
    ) {
    }
}
