<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Public Page File Cache package (ADR-025 §7).
 */
final readonly class PublicPageCacheEntry
{
    public function __construct(
        public PublicPageViewDto $view,
        public PublicSeoViewDto $seo,
    ) {
    }
}
