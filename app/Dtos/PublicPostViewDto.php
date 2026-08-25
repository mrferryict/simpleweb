<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Prepared public Post view-model (ADR-016).
 */
final readonly class PublicPostViewDto
{
    public function __construct(
        public int $postId,
        public string $title,
        public string $manualAuthor,
        public string $locale,
        public string $slug,
        public string $body,
        public string $requestedLocale,
        public bool $isFallback,
        public string $templateKey = 'custom-post',
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $canonicalUrl = null,
        public ?int $ogImageId = null,
    ) {
    }
}
