<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Services\PageService;
use App\Services\Security\RichTextSanitizer;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * ADR-014 RICH_TEXT sanitization foundation (Phase 3 / Task 3.4).
 *
 * @internal
 */
final class RichTextSanitizerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    private RichTextSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = Services::richTextSanitizer(getShared: false);
    }

    public function testSafePermittedMarkupIsPreserved(): void
    {
        $input = '<h2>Title</h2><p>Hello <strong>world</strong> and <em>friends</em>.</p>'
            . '<ul><li>One</li></ul><p><a href="https://example.com/path">Link</a></p>';
        $output = $this->sanitizer->sanitize($input);

        $this->assertStringContainsString('<h2>Title</h2>', $output);
        $this->assertStringContainsString('<strong>world</strong>', $output);
        $this->assertStringContainsString('<em>friends</em>', $output);
        $this->assertStringContainsString('<a href="https://example.com/path">Link</a>', $output);
        $this->assertStringContainsString('<ul><li>One</li></ul>', $output);
    }

    public function testScriptInjectionIsRemoved(): void
    {
        $output = $this->sanitizer->sanitize('<p>Safe</p><script>alert(1)</script><p>After</p>');
        $this->assertStringNotContainsString('<script', strtolower($output));
        $this->assertStringNotContainsString('alert(1)', $output);
        $this->assertStringContainsString('<p>Safe</p>', $output);
        $this->assertStringContainsString('<p>After</p>', $output);
    }

    public function testImgAndIframeAreRemoved(): void
    {
        $output = $this->sanitizer->sanitize(
            '<p>X</p><img src="x" onerror="alert(1)"><iframe src="https://evil.test"></iframe>',
        );
        $this->assertStringNotContainsString('<img', strtolower($output));
        $this->assertStringNotContainsString('<iframe', strtolower($output));
        $this->assertStringContainsString('<p>X</p>', $output);
    }

    public function testEventHandlerAttributesAreRemoved(): void
    {
        $output = $this->sanitizer->sanitize('<p onclick="alert(1)" onerror="alert(2)">Click</p>');
        $this->assertStringNotContainsString('onclick', strtolower($output));
        $this->assertStringNotContainsString('onerror', strtolower($output));
        $this->assertStringContainsString('<p>Click</p>', $output);
    }

    public function testJavascriptUrlSchemeIsRemovedFromAnchors(): void
    {
        $output = $this->sanitizer->sanitize('<p><a href="javascript:alert(1)">Go</a></p>');
        $this->assertStringNotContainsString('javascript:', strtolower($output));
        $this->assertStringNotContainsString('<a ', strtolower($output));
        $this->assertStringContainsString('Go', $output);
    }

    public function testDataAndVbscriptUrlSchemesAreRemoved(): void
    {
        $data = $this->sanitizer->sanitize('<a href="data:text/html;base64,xx">D</a>');
        $vbs  = $this->sanitizer->sanitize('<a href="vbscript:MsgBox(1)">V</a>');
        $this->assertStringNotContainsString('data:', strtolower($data));
        $this->assertStringNotContainsString('vbscript:', strtolower($vbs));
        $this->assertStringNotContainsString('<a ', strtolower($data));
        $this->assertStringNotContainsString('<a ', strtolower($vbs));
    }

    public function testMailtoAndHttpsLinksAreAllowed(): void
    {
        $output = $this->sanitizer->sanitize(
            '<p><a href="mailto:info@example.com">Mail</a> '
            . '<a href="http://example.com">Http</a></p>',
        );
        $this->assertStringContainsString('href="mailto:info@example.com"', $output);
        $this->assertStringContainsString('href="http://example.com"', $output);
    }

    public function testDisallowedTagsAreUnwrappedNotExecuted(): void
    {
        $output = $this->sanitizer->sanitize('<div><span>Text</span></div>');
        $this->assertStringNotContainsString('<div', strtolower($output));
        $this->assertStringNotContainsString('<span', strtolower($output));
        $this->assertStringContainsString('Text', $output);
    }

    public function testSanitizeIsDeterministic(): void
    {
        $input = '<p>Hello <strong>World</strong></p>';
        $this->assertSame(
            $this->sanitizer->sanitize($input),
            $this->sanitizer->sanitize($input),
        );
    }

    public function testSanitizePayloadOnlyTouchesRichTextFields(): void
    {
        $schema = [
            'body'  => ['type' => 'RICH_TEXT', 'required' => false],
            'title' => ['type' => 'TEXT', 'required' => false],
            'note'  => ['type' => 'TEXTAREA', 'required' => false],
            'link'  => ['type' => 'URL', 'required' => false],
        ];
        $payload = [
            'body'  => '<p>Ok</p><script>bad()</script>',
            'title' => '<script>keep-as-text</script>',
            'note'  => '<b>plain textarea</b>',
            'link'  => 'https://example.com',
        ];

        $result = $this->sanitizer->sanitizePayload($payload, $schema);
        $this->assertStringNotContainsString('<script', strtolower((string) $result['body']));
        $this->assertStringContainsString('<p>Ok</p>', (string) $result['body']);
        $this->assertSame('<script>keep-as-text</script>', $result['title']);
        $this->assertSame('<b>plain textarea</b>', $result['note']);
        $this->assertSame('https://example.com', $result['link']);
    }

    public function testSanitizePayloadWalksRepeatableNestedRichText(): void
    {
        $schema = [
            'blocks' => [
                'type'   => 'REPEATABLE',
                'fields' => [
                    'html' => ['type' => 'RICH_TEXT'],
                    'name' => ['type' => 'TEXT'],
                ],
            ],
        ];
        $result = $this->sanitizer->sanitizePayload([
            'blocks' => [
                [
                    'html' => '<p>A</p><img src=x onerror=alert(1)>',
                    'name' => '<b>not rich</b>',
                ],
            ],
        ], $schema);

        $this->assertStringContainsString('<p>A</p>', (string) $result['blocks'][0]['html']);
        $this->assertStringNotContainsString('<img', strtolower((string) $result['blocks'][0]['html']));
        $this->assertSame('<b>not rich</b>', $result['blocks'][0]['name']);
    }

    public function testPageServiceSanitizesRichTextBeforePersist(): void
    {
        /** @var PageService $service */
        $service = Services::pageService(getShared: false);
        $errors  = $service->create(new PageWriteDto(
            title: 'Sanitize Persist',
            slug: 'sanitize-persist',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [
                'body' => '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(1)>',
            ],
        ));
        $this->assertSame([], $errors);

        $row     = $service->listActive()[0];
        $payload = json_decode((string) $row['translation']?->content_payload, true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('body', $payload);
        $this->assertStringContainsString('<p>Hello</p>', (string) $payload['body']);
        $this->assertStringNotContainsString('<script', strtolower((string) $payload['body']));
        $this->assertStringNotContainsString('<img', strtolower((string) $payload['body']));
        $this->assertStringNotContainsString('onerror', strtolower((string) $payload['body']));
    }

    public function testPageServiceUpdatePreservesLegacyKeysAfterSanitize(): void
    {
        /** @var PageService $service */
        $service = Services::pageService(getShared: false);
        $this->assertSame([], $service->create(new PageWriteDto(
            title: 'Legacy San',
            slug: 'legacy-san',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [],
        )));
        $id = $service->listActive()[0]['page']->id;

        db_connect()->table('page_translations')->where('page_id', $id)->update([
            'content_payload' => json_encode([
                'legacy_widget' => 'keep-me',
                'body'          => '<p>Old</p>',
            ], JSON_THROW_ON_ERROR),
        ]);

        $errors = $service->update($id, new PageWriteDto(
            title: 'Legacy San',
            slug: 'legacy-san',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [
                'body' => '<p>New</p><script>x()</script>',
            ],
        ));
        $this->assertSame([], $errors);

        $payload = json_decode(
            (string) $service->findEditable($id)['translation']->content_payload,
            true,
        );
        $this->assertIsArray($payload);
        $this->assertSame('keep-me', $payload['legacy_widget']);
        $this->assertStringContainsString('<p>New</p>', (string) $payload['body']);
        $this->assertStringNotContainsString('<script', strtolower((string) $payload['body']));
    }
}
