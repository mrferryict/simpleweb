<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Writable Category foundation fields (REQ-CAT-002).
 */
final readonly class CategoryWriteDto
{
    public function __construct(
        public string $name,
        public string $slug,
        public bool $isActive = true,
    ) {
    }
}
