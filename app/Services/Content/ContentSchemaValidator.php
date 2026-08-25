<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Dtos\ContentSchemaValidationResult;
use App\Enums\ContentFieldType;
use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

/**
 * Native Content Schema validation engine (ADR-004).
 *
 * Validates a submitted content_payload against a developer-defined schema
 * array (from Theme Manifest templates — Theme Manifest loading is separate).
 *
 * Does NOT sanitize HTML. RICH_TEXT must already be sanitized before validate().
 * Does NOT invent Theme Manifest structure — schema is an associative map of
 * field definitions keyed by content slot name (DOC-05 §11–13).
 *
 * Field definition keys (documented):
 * - type (required): TEXT|TEXTAREA|RICH_TEXT|IMAGE|YOUTUBE_URL|URL|DOCUMENT|REPEATABLE
 * - required (optional bool)
 * - default (optional)
 * - validation.max_length (optional int, TEXT/TEXTAREA/RICH_TEXT)
 * - minimum_items / maximum_items / fields (REPEATABLE)
 *
 * IMAGE/DOCUMENT: integer media_id (ADR-004). Existence checks are optional via
 * $mediaResolver — media module is not required for this foundation.
 */
final class ContentSchemaValidator
{
    /**
     * @param (callable(int, string): bool)|null $mediaResolver
     *        Receives (mediaId, expectedKind) where kind is IMAGE or DOCUMENT.
     *        Return true when the media reference is valid for the schema.
     */
    public function __construct(
        private readonly mixed $mediaResolver = null,
    ) {
    }

    /**
     * Validate submitted payload keys against the active schema.
     *
     * Unknown submitted keys are rejected (ADR-004). Legacy keys already stored
     * in the database are preserved by PageService merge — not by this method.
     *
     * @param array<string, mixed>                $payload Submitted content_payload
     * @param array<string, array<string, mixed>> $schema  Field definitions keyed by slot name
     */
    #[\NoDiscard]
    public function validate(array $payload, array $schema): ContentSchemaValidationResult
    {
        $errors     = [];
        $normalized = [];

        foreach (array_keys($payload) as $key) {
            if (! is_string($key) || $key === '') {
                $errors['_payload'] = 'Content payload keys must be non-empty strings.';

                continue;
            }

            if (! array_key_exists($key, $schema)) {
                $errors[$key] = 'Unknown content field is not allowed.';
            }
        }

        if ($errors !== []) {
            return ContentSchemaValidationResult::failure($errors);
        }

        foreach ($schema as $fieldKey => $definition) {
            if (! is_string($fieldKey) || $fieldKey === '' || ! is_array($definition)) {
                $errors['_schema'] = 'Content schema field definitions are invalid.';

                continue;
            }

            $fieldErrors = $this->validateField(
                path: $fieldKey,
                definition: $definition,
                present: array_key_exists($fieldKey, $payload),
                value: $payload[$fieldKey] ?? null,
                normalized: $normalized,
            );

            foreach ($fieldErrors as $path => $message) {
                $errors[$path] = $message;
            }
        }

        if ($errors !== []) {
            return ContentSchemaValidationResult::failure($errors);
        }

        return ContentSchemaValidationResult::success($normalized);
    }

    /**
     * Merge normalized submitted values into an existing payload while preserving
     * legacy keys that are not in the active schema (ADR-002 / ADR-004).
     *
     * @param array<string, mixed>                $existing
     * @param array<string, mixed>                $normalizedSubmitted
     * @param array<string, array<string, mixed>> $schema
     *
     * @return array<string, mixed>
     */
    public function mergePreservingLegacy(array $existing, array $normalizedSubmitted, array $schema): array
    {
        $merged = $existing;

        foreach (array_keys($schema) as $key) {
            if (array_key_exists($key, $normalizedSubmitted)) {
                $merged[$key] = $normalizedSubmitted[$key];
            }
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $normalized
     *
     * @return array<string, string>
     */
    private function validateField(
        string $path,
        array $definition,
        bool $present,
        mixed $value,
        array &$normalized,
    ): array {
        $typeRaw = isset($definition['type']) && is_string($definition['type'])
            ? $definition['type']
            : '';
        $type = ContentFieldType::tryFromString($typeRaw);
        if ($type === null) {
            return [$path => 'Content field type is invalid.'];
        }

        $required = ! empty($definition['required']);

        if (! $present || $this->isEmptyValue($value, $type)) {
            if ($required) {
                return [$path => 'This content field is required.'];
            }

            if (array_key_exists('default', $definition)) {
                $normalized[$path] = $definition['default'];
            }

            return [];
        }

        return match ($type) {
            ContentFieldType::Text => $this->validateText($path, $value, $definition, $normalized, singleLine: true),
            ContentFieldType::Textarea => $this->validateText($path, $value, $definition, $normalized, singleLine: false),
            ContentFieldType::RichText => $this->validateRichText($path, $value, $definition, $normalized),
            ContentFieldType::Url => $this->validateUrl($path, $value, $normalized),
            ContentFieldType::YoutubeUrl => $this->validateYoutubeUrl($path, $value, $normalized),
            ContentFieldType::Image => $this->validateMediaId($path, $value, 'IMAGE', $normalized),
            ContentFieldType::Document => $this->validateMediaId($path, $value, 'DOCUMENT', $normalized),
            ContentFieldType::Repeatable => $this->validateRepeatable($path, $value, $definition, $normalized),
        };
    }

    private function isEmptyValue(mixed $value, ContentFieldType $type): bool
    {
        if ($value === null) {
            return true;
        }

        if ($type === ContentFieldType::Repeatable) {
            return $value === [] || $value === null;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        return false;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $normalized
     *
     * @return array<string, string>
     */
    private function validateText(
        string $path,
        mixed $value,
        array $definition,
        array &$normalized,
        bool $singleLine,
    ): array {
        if (! is_string($value)) {
            return [$path => 'This content field must be a string.'];
        }

        $normalizedValue = $singleLine ? trim($value) : $value;

        $maxLength = $this->maxLength($definition);
        if ($maxLength !== null && strlen($normalizedValue) > $maxLength) {
            return [$path => 'This content field exceeds the maximum length.'];
        }

        if ($singleLine && str_contains($normalizedValue, "\n")) {
            return [$path => 'This content field must be a single line.'];
        }

        $normalized[$path] = $normalizedValue;

        return [];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $normalized
     *
     * @return array<string, string>
     */
    private function validateRichText(
        string $path,
        mixed $value,
        array $definition,
        array &$normalized,
    ): array {
        // Sanitization is a separate step (ADR-004). Validator only type-checks.
        if (! is_string($value)) {
            return [$path => 'This content field must be a string.'];
        }

        $maxLength = $this->maxLength($definition);
        if ($maxLength !== null && strlen($value) > $maxLength) {
            return [$path => 'This content field exceeds the maximum length.'];
        }

        $normalized[$path] = $value;

        return [];
    }

    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, string>
     */
    private function validateUrl(string $path, mixed $value, array &$normalized): array
    {
        if (! is_string($value)) {
            return [$path => 'This content field must be a string URL.'];
        }

        $url   = trim($value);
        $lower = strtolower($url);
        foreach (['javascript:', 'data:', 'vbscript:'] as $dangerous) {
            if (str_starts_with($lower, $dangerous)) {
                return [$path => 'The URL scheme is not allowed.'];
            }
        }

        try {
            $uri = new Uri($url);
        } catch (InvalidUriException) {
            return [$path => 'The URL is not valid.'];
        }

        $scheme = strtolower((string) $uri->getScheme());
        if ($scheme !== 'http' && $scheme !== 'https') {
            return [$path => 'The URL must use the http or https scheme.'];
        }

        if ($uri->getHost() === null || $uri->getHost() === '') {
            return [$path => 'The URL must include a host.'];
        }

        $normalized[$path] = $url;

        return [];
    }

    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, string>
     */
    private function validateYoutubeUrl(string $path, mixed $value, array &$normalized): array
    {
        if (! is_string($value)) {
            return [$path => 'This content field must be a YouTube URL string.'];
        }

        $raw = trim($value);
        $id  = $this->extractYoutubeVideoId($raw);
        if ($id === null) {
            return [$path => 'The YouTube URL is not valid.'];
        }

        $normalized[$path] = $id;

        return [];
    }

    private function extractYoutubeVideoId(string $raw): ?string
    {
        $lower = strtolower($raw);
        foreach (['javascript:', 'data:', 'vbscript:'] as $dangerous) {
            if (str_starts_with($lower, $dangerous)) {
                return null;
            }
        }

        // Already a bare video id
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $raw) === 1) {
            return $raw;
        }

        try {
            $uri = new Uri($raw);
        } catch (InvalidUriException) {
            return null;
        }

        $host = strtolower((string) $uri->getHost());
        if (! in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true)) {
            return null;
        }

        if ($host === 'youtu.be') {
            $path = trim((string) $uri->getPath(), '/');
            $id   = explode('/', $path)[0] ?? '';

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
        }

        $path = (string) $uri->getPath();
        if (str_starts_with($path, '/embed/') || str_starts_with($path, '/shorts/')) {
            $parts = explode('/', trim($path, '/'));
            $id    = $parts[1] ?? '';

            return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
        }

        $query = (string) $uri->getQuery();
        parse_str($query, $params);
        $id = isset($params['v']) && is_string($params['v']) ? $params['v'] : '';

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }

    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, string>
     */
    private function validateMediaId(
        string $path,
        mixed $value,
        string $kind,
        array &$normalized,
    ): array {
        if (is_string($value) && ctype_digit($value)) {
            $value = (int) $value;
        }

        if (! is_int($value) || $value < 1) {
            return [$path => 'This content field must be a positive media ID.'];
        }

        if ($this->mediaResolver !== null && is_callable($this->mediaResolver)) {
            if (! ($this->mediaResolver)($value, $kind)) {
                return [$path => 'The selected media reference is not valid.'];
            }
        }

        $normalized[$path] = $value;

        return [];
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $normalized
     *
     * @return array<string, string>
     */
    private function validateRepeatable(
        string $path,
        mixed $value,
        array $definition,
        array &$normalized,
    ): array {
        if (! is_array($value) || ! array_is_list($value)) {
            return [$path => 'This content field must be a list of items.'];
        }

        $min = isset($definition['minimum_items']) && is_int($definition['minimum_items'])
            ? $definition['minimum_items']
            : (isset($definition['min_items']) && is_int($definition['min_items']) ? $definition['min_items'] : 0);
        $max = isset($definition['maximum_items']) && is_int($definition['maximum_items'])
            ? $definition['maximum_items']
            : (isset($definition['max_items']) && is_int($definition['max_items']) ? $definition['max_items'] : null);

        $count = count($value);
        if ($count < $min) {
            return [$path => 'This content block has too few items.'];
        }

        if ($max !== null && $count > $max) {
            return [$path => 'This content block has too many items.'];
        }

        $childSchema = $definition['fields'] ?? null;
        if (! is_array($childSchema)) {
            return [$path => 'Repeatable block schema fields are missing.'];
        }

        /** @var array<string, array<string, mixed>> $childSchema */
        $items  = [];
        $errors = [];

        foreach ($value as $index => $item) {
            if (! is_array($item)) {
                $errors[$path . '.' . $index] = 'Each repeatable item must be an object.';

                continue;
            }

            /** @var array<string, mixed> $item */
            $childResult = $this->validate($item, $childSchema);
            if (! $childResult->ok) {
                foreach ($childResult->errors as $childPath => $message) {
                    $errors[$path . '.' . $index . '.' . $childPath] = $message;
                }

                continue;
            }

            $items[] = $childResult->normalized;
        }

        if ($errors !== []) {
            return $errors;
        }

        $normalized[$path] = $items;

        return [];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function maxLength(array $definition): ?int
    {
        $validation = $definition['validation'] ?? null;
        if (! is_array($validation)) {
            return null;
        }

        $max = $validation['max_length'] ?? null;

        return is_int($max) && $max > 0 ? $max : null;
    }
}
