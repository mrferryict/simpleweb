<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Staff user creation payload (V2-003 / ADR-027 P0-1).
 */
final readonly class CreateStaffUserDto
{
    public function __construct(
        public string $username,
        public string $email,
        public string $password,
        public string $passwordConfirm,
        public string $group,
        public bool $isActive,
    ) {
    }
}
