<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Writable Page foundation fields (create/update).
 *
 * contentPayload is validated by ContentSchemaValidator (ADR-004) before persist.
 *
 * @param array<string, mixed> $contentPayload
 */
final readonly class PageWriteDto
{
    /**
     * @param array<string, mixed> $contentPayload
     */
    public function __construct(
        public string $title,
        public string $slug,
        public string $locale,
        public string $templateKey,
        public ?int $parentId,
        public array $contentPayload = [],
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $canonicalUrl = null,
        public ?int $ogImageId = null,
    ) {
    }
}
