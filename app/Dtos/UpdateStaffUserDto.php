<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Staff user update payload (V2-003 / ADR-027 P0-1).
 */
final readonly class UpdateStaffUserDto
{
    public function __construct(
        public string $email,
        public string $group,
        public bool $isActive,
    ) {
    }
}
