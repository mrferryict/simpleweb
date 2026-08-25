<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Content\ContentSchemaValidator;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ContentSchemaValidatorTest extends CIUnitTestCase
{
    private ContentSchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ContentSchemaValidator();
    }

    public function testEmptyPayloadWithEmptySchemaIsValid(): void
    {
        $result = $this->validator->validate([], []);
        $this->assertTrue($result->ok);
        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->normalized);
    }

    public function testValidMinimalPayload(): void
    {
        $schema = [
            'title' => [
                'type'     => 'TEXT',
                'required' => true,
                'validation' => ['max_length' => 100],
            ],
        ];
        $result = $this->validator->validate(['title' => ' Hello '], $schema);
        $this->assertTrue($result->ok);
        $this->assertSame('Hello', $result->normalized['title']);
    }

    public function testValidCompletePayload(): void
    {
        $schema = $this->sampleSchema();
        $payload = [
            'hero_title' => 'Welcome',
            'body'       => "Line 1\nLine 2",
            'blurb'      => '<p>Safe</p>',
            'link'       => 'https://example.com/about',
            'video'      => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'hero_image' => 42,
            'doc'        => 7,
            'slides'     => [
                [
                    'title' => 'Slide A',
                    'url'   => 'https://example.com/a',
                ],
            ],
        ];

        $result = $this->validator->validate($payload, $schema);
        $this->assertTrue($result->ok, json_encode($result->errors) ?: '');
        $this->assertSame('Welcome', $result->normalized['hero_title']);
        $this->assertSame('dQw4w9WgXcQ', $result->normalized['video']);
        $this->assertSame(42, $result->normalized['hero_image']);
        $this->assertCount(1, $result->normalized['slides']);
    }

    public function testMissingRequiredFieldIsRejected(): void
    {
        $schema = [
            'title' => ['type' => 'TEXT', 'required' => true],
        ];
        $result = $this->validator->validate([], $schema);
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('title', $result->errors);
    }

    public function testMissingRequiredNestedPropertyIsRejected(): void
    {
        $schema = [
            'slides' => [
                'type'           => 'REPEATABLE',
                'minimum_items'  => 1,
                'maximum_items'  => 3,
                'fields'         => [
                    'title' => ['type' => 'TEXT', 'required' => true],
                ],
            ],
        ];
        $result = $this->validator->validate([
            'slides' => [
                ['title' => ''],
            ],
        ], $schema);
        $this->assertFalse($result->ok);
        $this->assertNotSame([], $result->errors);
    }

    public function testInvalidFieldTypeInSchemaIsRejected(): void
    {
        $result = $this->validator->validate(
            ['x' => 'y'],
            ['x' => ['type' => 'UNKNOWN_TYPE', 'required' => true]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('x', $result->errors);
    }

    public function testUnknownSubmittedFieldIsRejected(): void
    {
        $result = $this->validator->validate(
            ['extra' => 'nope'],
            ['title' => ['type' => 'TEXT', 'required' => false]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('extra', $result->errors);
    }

    public function testWrongPropertyTypeIsRejected(): void
    {
        $result = $this->validator->validate(
            ['title' => 123],
            ['title' => ['type' => 'TEXT', 'required' => true]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('title', $result->errors);
    }

    public function testInvalidNestingNonListRepeatableIsRejected(): void
    {
        $result = $this->validator->validate(
            ['slides' => ['title' => 'not-a-list']],
            [
                'slides' => [
                    'type'          => 'REPEATABLE',
                    'minimum_items' => 0,
                    'maximum_items' => 2,
                    'fields'        => [
                        'title' => ['type' => 'TEXT', 'required' => true],
                    ],
                ],
            ],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('slides', $result->errors);
    }

    public function testStringMaxLengthIsEnforced(): void
    {
        $result = $this->validator->validate(
            ['title' => 'abcdef'],
            ['title' => ['type' => 'TEXT', 'required' => true, 'validation' => ['max_length' => 3]]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('title', $result->errors);
    }

    public function testInvalidUrlIsRejected(): void
    {
        $result = $this->validator->validate(
            ['link' => 'not-a-url'],
            ['link' => ['type' => 'URL', 'required' => true]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('link', $result->errors);
    }

    public function testJavascriptUrlIsRejected(): void
    {
        $result = $this->validator->validate(
            ['link' => 'javascript:alert(1)'],
            ['link' => ['type' => 'URL', 'required' => true]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('link', $result->errors);
    }

    public function testDataUrlIsRejected(): void
    {
        $result = $this->validator->validate(
            ['link' => 'data:text/html,hi'],
            ['link' => ['type' => 'URL', 'required' => true]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('link', $result->errors);
    }

    public function testRichTextAcceptsStringWithoutSanitizing(): void
    {
        // Validator is not a sanitizer (ADR-004). Dangerous HTML is a sanitizer concern.
        $html = '<p>Hello</p><script>alert(1)</script>';
        $result = $this->validator->validate(
            ['body' => $html],
            ['body' => ['type' => 'RICH_TEXT', 'required' => true]],
        );
        $this->assertTrue($result->ok);
        $this->assertSame($html, $result->normalized['body']);
    }

    public function testRepeatableBoundsTooManyItems(): void
    {
        $schema = [
            'slides' => [
                'type'          => 'REPEATABLE',
                'minimum_items' => 0,
                'maximum_items' => 1,
                'fields'        => [
                    'title' => ['type' => 'TEXT', 'required' => true],
                ],
            ],
        ];
        $result = $this->validator->validate([
            'slides' => [
                ['title' => 'A'],
                ['title' => 'B'],
            ],
        ], $schema);
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('slides', $result->errors);
    }

    public function testRepeatableBoundsTooFewItems(): void
    {
        $schema = [
            'slides' => [
                'type'          => 'REPEATABLE',
                'minimum_items' => 2,
                'maximum_items' => 5,
                'fields'        => [
                    'title' => ['type' => 'TEXT', 'required' => true],
                ],
            ],
        ];
        $result = $this->validator->validate([
            'slides' => [
                ['title' => 'A'],
            ],
        ], $schema);
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('slides', $result->errors);
    }

    public function testUnknownChildFieldInRepeatableIsRejected(): void
    {
        $schema = [
            'slides' => [
                'type'          => 'REPEATABLE',
                'minimum_items' => 1,
                'maximum_items' => 3,
                'fields'        => [
                    'title' => ['type' => 'TEXT', 'required' => true],
                ],
            ],
        ];
        $result = $this->validator->validate([
            'slides' => [
                ['title' => 'A', 'extra' => 'nope'],
            ],
        ], $schema);
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('slides.0.extra', $result->errors);
    }

    public function testDefaultAppliedForOptionalMissingField(): void
    {
        $result = $this->validator->validate([], [
            'subtitle' => [
                'type'     => 'TEXT',
                'required' => false,
                'default'  => 'Default',
            ],
        ]);
        $this->assertTrue($result->ok);
        $this->assertSame('Default', $result->normalized['subtitle']);
    }

    public function testMergePreservesLegacyUnknownKeys(): void
    {
        $schema = [
            'title' => ['type' => 'TEXT', 'required' => true],
        ];
        $existing = [
            'title'          => 'Old',
            'legacy_gallery' => [1, 2, 3],
        ];
        $submitted = $this->validator->validate(['title' => 'New'], $schema);
        $this->assertTrue($submitted->ok);
        $merged = $this->validator->mergePreservingLegacy($existing, $submitted->normalized, $schema);
        $this->assertSame('New', $merged['title']);
        $this->assertSame([1, 2, 3], $merged['legacy_gallery']);
    }

    public function testMediaResolverRejectsInvalidImage(): void
    {
        $validator = new ContentSchemaValidator(
            static fn (int $id, string $kind): bool => $kind === 'IMAGE' && $id === 1,
        );
        $result = $validator->validate(
            ['hero' => 99],
            ['hero' => ['type' => 'IMAGE', 'required' => true]],
        );
        $this->assertFalse($result->ok);
        $this->assertArrayHasKey('hero', $result->errors);
    }

    public function testErrorStructureIsDeterministicFieldMap(): void
    {
        $result = $this->validator->validate(
            ['a' => 1, 'unknown' => true],
            ['a' => ['type' => 'TEXT', 'required' => true]],
        );
        $this->assertFalse($result->ok);
        $this->assertIsArray($result->errors);
        foreach ($result->errors as $key => $message) {
            $this->assertIsString($key);
            $this->assertIsString($message);
            $this->assertStringNotContainsString('SQL', $message);
            $this->assertStringNotContainsString('stack', strtolower($message));
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function sampleSchema(): array
    {
        return [
            'hero_title' => ['type' => 'TEXT', 'required' => true, 'validation' => ['max_length' => 150]],
            'body'       => ['type' => 'TEXTAREA', 'required' => false],
            'blurb'      => ['type' => 'RICH_TEXT', 'required' => false],
            'link'       => ['type' => 'URL', 'required' => true],
            'video'      => ['type' => 'YOUTUBE_URL', 'required' => true],
            'hero_image' => ['type' => 'IMAGE', 'required' => true],
            'doc'        => ['type' => 'DOCUMENT', 'required' => false],
            'slides'     => [
                'type'          => 'REPEATABLE',
                'minimum_items' => 1,
                'maximum_items' => 5,
                'fields'        => [
                    'title' => ['type' => 'TEXT', 'required' => true],
                    'url'   => ['type' => 'URL', 'required' => true],
                ],
            ],
        ];
    }
}
