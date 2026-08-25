<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Writable Tag foundation fields (REQ-TAG-002).
 */
final readonly class TagWriteDto
{
    public function __construct(
        public string $name,
        public string $slug,
    ) {
    }
}
