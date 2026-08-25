<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Prepared public Page view-model (ADR-017 / Task 4.7).
 */
final readonly class PublicPageViewDto
{
    /**
     * @param array<string, mixed> $contentPayload
     * @param array<string, mixed> $contentMedia
     */
    public function __construct(
        public int $pageId,
        public string $title,
        public string $locale,
        public string $slug,
        public array $contentPayload,
        public string $requestedLocale,
        public bool $isFallback,
        public string $templateKey,
        public array $contentMedia = [],
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $canonicalUrl = null,
        public ?int $ogImageId = null,
    ) {
    }
}
