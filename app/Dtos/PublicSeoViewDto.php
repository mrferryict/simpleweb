<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Normalized SEO data for Theme head rendering (DOC-08 §52 / ADR-024 §11).
 */
final readonly class PublicSeoViewDto
{
    /**
     * @param list<array{hreflang: string, href: string}> $hreflangAlternates
     */
    public function __construct(
        public string $documentTitle,
        public string $metaDescription,
        public string $canonicalUrl,
        public array $hreflangAlternates,
        public string $xDefaultUrl,
        public ?string $ogImageUrl,
    ) {
    }
}
