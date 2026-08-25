<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Public Post File Cache package (ADR-025 §7).
 */
final readonly class PublicPostCacheEntry
{
    public function __construct(
        public PublicPostViewDto $view,
        public PublicSeoViewDto $seo,
    ) {
    }
}
