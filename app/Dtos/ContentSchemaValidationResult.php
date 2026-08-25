<?php

declare(strict_types=1);

namespace App\Dtos;

/**
 * Result of ContentSchemaValidator::validate() (ADR-004 field-level errors).
 *
 * @phpstan-type FieldErrors array<string, string>
 */
final readonly class ContentSchemaValidationResult
{
    /**
     * @param array<string, string>       $errors     Field-path => message
     * @param array<string, mixed>        $normalized Normalized submitted values (schema keys only)
     */
    public function __construct(
        public bool $ok,
        public array $errors,
        public array $normalized,
    ) {
    }

    /**
     * @param array<string, string> $errors
     */
    public static function failure(array $errors): self
    {
        return new self(ok: false, errors: $errors, normalized: []);
    }

    /**
     * @param array<string, mixed> $normalized
     */
    public static function success(array $normalized): self
    {
        return new self(ok: true, errors: [], normalized: $normalized);
    }
}
