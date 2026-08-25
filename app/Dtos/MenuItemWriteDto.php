<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Input for creating/updating a menu item.
 *
 * Type consistency is enforced in MenuService (not the DTO).
 */
final readonly class MenuItemWriteDto
{
    public function __construct(
        public string $location,
        public string $label,
        public string $targetType,
        public ?int $targetId,
        public string $externalUrl,
        public int $displayOrder,
        public bool $isActive,
        public ?int $parentId = null,
    ) {
    }
}
