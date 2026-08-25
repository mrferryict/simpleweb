<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Writable Post foundation fields (create/update).
 *
 * contentPayload validated by ContentSchemaValidator (ADR-004).
 * Schema resolves ACTIVE Theme → templates.custom-post (ADR-015).
 * No template_key — Posts never store or accept a user-selected template.
 *
 * @param list<int>            $categoryIds
 * @param list<int>            $tagIds
 * @param array<string, mixed> $contentPayload
 */
final readonly class PostWriteDto
{
    /**
     * @param list<int>            $categoryIds
     * @param list<int>            $tagIds
     * @param array<string, mixed> $contentPayload
     */
    public function __construct(
        public string $title,
        public string $slug,
        public string $locale,
        public string $manualAuthor,
        public array $categoryIds = [],
        public array $tagIds = [],
        public array $contentPayload = [],
        public ?int $featuredImageId = null,
        public ?int $createdBy = null,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $canonicalUrl = null,
        public ?int $ogImageId = null,
    ) {
    }
}
