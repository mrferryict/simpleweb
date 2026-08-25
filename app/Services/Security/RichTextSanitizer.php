<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Enums\ContentFieldType;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Uri\InvalidUriException;
use Uri\Rfc3986\Uri;

/**
 * Server-side RICH_TEXT HTML sanitizer (ADR-014).
 *
 * Canonical persistence format: sanitized HTML string.
 * Quill / client editors are not a security boundary.
 *
 * Allowed tags (exact ADR-014 allowlist):
 * h1–h4, p, strong, em, u, a, ul, ol, li, blockquote, br
 *
 * <a href> protocols: http, https, mailto only.
 * Prohibited: script/style/iframe/object/embed/img/form/input and all on* handlers.
 *
 * Attribute allowlist beyond href on <a> is not defined by ADR-014 — only href is retained.
 */
final class RichTextSanitizer
{
    /** @var list<string> */
    private const ALLOWED_TAGS = [
        'h1',
        'h2',
        'h3',
        'h4',
        'p',
        'strong',
        'em',
        'u',
        'a',
        'ul',
        'ol',
        'li',
        'blockquote',
        'br',
    ];

    /** @var list<string> Removed entirely (including descendants). */
    private const DROP_TAGS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'img',
        'form',
        'input',
    ];

    /** @var list<string> */
    private const ALLOWED_LINK_SCHEMES = [
        'http',
        'https',
        'mailto',
    ];

    /**
     * Sanitize a single RICH_TEXT HTML string.
     */
    #[\NoDiscard]
    public function sanitize(string $html): string
    {
        $trimmed = trim($html);
        if ($trimmed === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="UTF-8"><div id="smite-rich-text-root">' . $html . '</div>';
        $loaded  = $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return '';
        }

        $root = $document->getElementById('smite-rich-text-root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        $clean = new DOMDocument('1.0', 'UTF-8');
        $cleanRoot = $clean->createElement('div');
        $clean->appendChild($cleanRoot);

        $this->copyAllowedChildren($root, $cleanRoot, $clean);

        $output = '';
        foreach ($cleanRoot->childNodes as $child) {
            $output .= $clean->saveHTML($child);
        }

        return $output;
    }

    /**
     * Sanitize RICH_TEXT slots in a content_payload using the active schema map.
     *
     * Non-RICH_TEXT values are left unchanged. Nested REPEATABLE schemas are walked.
     *
     * @param array<string, mixed>                $payload
     * @param array<string, array<string, mixed>> $schema
     *
     * @return array<string, mixed>
     */
    #[\NoDiscard]
    public function sanitizePayload(array $payload, array $schema): array
    {
        foreach ($schema as $fieldKey => $definition) {
            if (! is_string($fieldKey) || ! is_array($definition)) {
                continue;
            }
            if (! array_key_exists($fieldKey, $payload)) {
                continue;
            }

            $type = ContentFieldType::tryFromString(
                isset($definition['type']) && is_string($definition['type']) ? $definition['type'] : '',
            );

            if ($type === ContentFieldType::RichText) {
                if (is_string($payload[$fieldKey])) {
                    $payload[$fieldKey] = $this->sanitize($payload[$fieldKey]);
                }

                continue;
            }

            if ($type === ContentFieldType::Repeatable) {
                $childSchema = $definition['fields'] ?? null;
                if (! is_array($childSchema) || ! is_array($payload[$fieldKey])) {
                    continue;
                }

                /** @var array<string, array<string, mixed>> $childSchema */
                $items = [];
                foreach ($payload[$fieldKey] as $item) {
                    if (! is_array($item)) {
                        $items[] = $item;

                        continue;
                    }

                    /** @var array<string, mixed> $item */
                    $items[] = $this->sanitizePayload($item, $childSchema);
                }
                $payload[$fieldKey] = $items;
            }
        }

        return $payload;
    }

    private function copyAllowedChildren(DOMNode $source, DOMElement $target, DOMDocument $clean): void
    {
        foreach (iterator_to_array($source->childNodes) as $child) {
            if ($child instanceof DOMText) {
                $target->appendChild($clean->createTextNode($child->wholeText));

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_TAGS, true)) {
                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap unknown/non-allowlisted tags: keep safe descendants only.
                $this->copyAllowedChildren($child, $target, $clean);

                continue;
            }

            if ($tag === 'br') {
                $target->appendChild($clean->createElement('br'));

                continue;
            }

            $element = $clean->createElement($tag);

            if ($tag === 'a') {
                $href = $this->safeHref($child->getAttribute('href'));
                if ($href === null) {
                    // Drop unsafe/missing href — keep text/safe children unwrapped.
                    $this->copyAllowedChildren($child, $target, $clean);

                    continue;
                }
                $element->setAttribute('href', $href);
            }

            $this->copyAllowedChildren($child, $element, $clean);
            $target->appendChild($element);
        }
    }

    private function safeHref(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        $lower = strtolower($href);
        foreach (['javascript:', 'data:', 'vbscript:'] as $dangerous) {
            if (str_starts_with($lower, $dangerous)) {
                return null;
            }
        }

        // Relative paths / fragments are not in the ADR-014 protocol allowlist.
        // Only http, https, mailto absolute schemes are permitted.
        if (str_starts_with($lower, 'mailto:')) {
            $address = substr($href, 7);
            if ($address === '' || str_contains($address, "\0") || preg_match('/[\s<>"]/', $address) === 1) {
                return null;
            }

            return 'mailto:' . $address;
        }

        try {
            $uri = new Uri($href);
        } catch (InvalidUriException) {
            return null;
        }

        $scheme = strtolower((string) $uri->getScheme());
        if (! in_array($scheme, self::ALLOWED_LINK_SCHEMES, true)) {
            return null;
        }

        if ($scheme === 'mailto') {
            return $href;
        }

        if ($uri->getHost() === null || $uri->getHost() === '') {
            return null;
        }

        return $href;
    }
}
