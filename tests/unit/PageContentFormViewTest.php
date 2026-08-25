<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Services\PageService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Control Panel Content Schema field rendering (Phase 3 / Task 3.3).
 *
 * @internal
 */
final class PageContentFormViewTest extends CIUnitTestCase
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

    private PageService $pageService;

    /** @var array<string, array<string, mixed>> */
    private array $schema = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pageService = Services::pageService(getShared: false);
        $this->schema      = $this->pageService->contentSchemaForTemplate('custom-page');
    }

    public function testCreateFormRendersActiveThemeManifestFields(): void
    {
        $html = $this->renderFullForm('create', [], []);

        $this->assertStringContainsString('New page', $html);
        $this->assertStringContainsString('Hero Title', $html);
        $this->assertStringContainsString('Hero Description', $html);
        $this->assertStringContainsString('Body', $html);
        $this->assertStringContainsString('Hero Image', $html);
        $this->assertStringContainsString('Video URL', $html);
        $this->assertStringContainsString('CTA URL', $html);
        $this->assertStringContainsString('Attachment', $html);
        $this->assertStringContainsString('Hero Slides', $html);
        $this->assertStringContainsString('name="content[hero_title]"', $html);
        $this->assertStringContainsString('name="content[cta_url]"', $html);
        $this->assertStringContainsString('name="content[hero_image]"', $html);
        $this->assertStringContainsString('class="media-picker"', $html);
        $this->assertStringContainsString('assets/admin/js/media-picker.js', $html);
        $this->assertStringContainsString('data-content-type="RICH_TEXT"', $html);
        $this->assertStringContainsString('data-rich-text="quill"', $html);
        $this->assertStringContainsString('x-data="quillEditor()"', $html);
        $this->assertStringContainsString('data-rich-text-backing="1"', $html);
        $this->assertStringContainsString('name="content[body]"', $html);
        $this->assertStringContainsString('assets/admin/js/quill.min.js', $html);
        $this->assertStringContainsString('assets/admin/js/alpine.min.js', $html);
        $this->assertStringContainsString('assets/admin/js/htmx.min.js', $html);
        $this->assertStringContainsString('assets/admin/js/htmx-csrf.js', $html);
        $this->assertStringContainsString('name="csrf-token"', $html);
        $this->assertStringContainsString('name="csrf-header"', $html);
        $this->assertStringContainsString('name="csrf-param"', $html);
        $this->assertStringContainsString('content="X-CSRF-TOKEN"', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
    }

    public function testRichTextBackingFieldPreservesExistingEscapedContent(): void
    {
        $html = $this->renderFields([
            'body' => '<p>Hello</p><script>alert(1)</script>',
        ], []);

        $this->assertStringContainsString('data-rich-text-backing="1"', $html);
        $this->assertStringContainsString('&lt;p&gt;Hello&lt;/p&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString("initialContent:", $html);
    }

    public function testMultipleRichTextFieldsAreIndependent(): void
    {
        $schema = [
            'intro' => ['type' => 'RICH_TEXT', 'label' => 'Intro', 'required' => false],
            'outro' => ['type' => 'RICH_TEXT', 'label' => 'Outro', 'required' => false],
            'title' => ['type' => 'TEXT', 'label' => 'Title', 'required' => false],
        ];
        $html = view('admin/pages/_partials/content_fields', [
            'contentSchema'  => $schema,
            'contentPayload' => [
                'intro' => '<p>One</p>',
                'outro' => '<p>Two</p>',
                'title' => 'Plain',
            ],
            'errors'         => [],
        ]);

        $this->assertSame(2, substr_count($html, 'x-data="quillEditor()"'));
        $this->assertStringContainsString('id="content_intro"', $html);
        $this->assertStringContainsString('id="content_outro"', $html);
        $this->assertStringContainsString('name="content[intro]"', $html);
        $this->assertStringContainsString('name="content[outro]"', $html);
        $this->assertStringContainsString('&lt;p&gt;One&lt;/p&gt;', $html);
        $this->assertStringContainsString('&lt;p&gt;Two&lt;/p&gt;', $html);
        $this->assertStringContainsString('name="content[title]"', $html);
        $this->assertStringContainsString('value="Plain"', $html);
    }

    public function testValidationErrorContentRemainsInBackingField(): void
    {
        $html = $this->renderFields(
            ['body' => '<p>Keep me</p>'],
            ['body' => 'This content field exceeds the maximum length.'],
        );
        $this->assertStringContainsString('&lt;p&gt;Keep me&lt;/p&gt;', $html);
        $this->assertStringContainsString('This content field exceeds the maximum length.', $html);
    }

    public function testEditFormPopulatesExistingContentValues(): void
    {
        $html = $this->renderFields([
            'hero_title'  => 'Welcome & Friends',
            'cta_url'     => 'https://example.com/a?x="y"',
            'body'        => '<script>alert(1)</script><p>Hi</p>',
            'hero_slides' => [
                ['title' => 'Slide <b>One</b>', 'url' => 'https://example.com/1'],
            ],
        ], []);

        $this->assertStringContainsString('value="Welcome &amp; Friends"', $html);
        $this->assertStringContainsString('https://example.com/a?x=&quot;y&quot;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('Slide &lt;b&gt;One&lt;/b&gt;', $html);
        $this->assertStringContainsString('name="content[hero_slides][0][title]"', $html);

        $full = $this->renderFullForm('edit', [
            'id'              => 1,
            'title'           => 'About',
            'slug'            => 'about',
            'locale'          => 'id',
            'template_key'    => 'custom-page',
            'parent_id'       => null,
            'status'          => 'DRAFT',
            'content_payload' => [
                'hero_title' => 'Welcome & Friends',
            ],
        ], []);
        $this->assertStringContainsString('Edit page', $full);
        $this->assertStringContainsString('Status: DRAFT', $full);
        $this->assertStringContainsString('value="Welcome &amp; Friends"', $full);
    }

    public function testEmptyCreateValuesAreSafe(): void
    {
        $html = $this->renderFields([], []);
        $this->assertStringContainsString('name="content[hero_title]"', $html);
        $this->assertStringContainsString('value=""', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testValidationErrorsAreDisplayedNextToFields(): void
    {
        $html = $this->renderFields(
            ['cta_url' => 'javascript:alert(1)'],
            [
                'cta_url' => 'The URL scheme is not allowed.',
                'title'   => 'The Title field is required.',
            ],
        );

        $this->assertStringContainsString('The URL scheme is not allowed.', $html);
        $this->assertStringContainsString('javascript:alert(1)', $html);

        $full = $this->renderFullForm(
            'create',
            [
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'template_key'    => 'custom-page',
                'parent_id'       => null,
                'content_payload' => ['cta_url' => 'javascript:alert(1)'],
            ],
            [
                'cta_url' => 'The URL scheme is not allowed.',
                'title'   => 'The Title field is required.',
            ],
        );
        $this->assertStringContainsString('The Title field is required.', $full);
        $this->assertStringContainsString('The URL scheme is not allowed.', $full);
    }

    public function testRequiredStateIsReflectedFromSchema(): void
    {
        $this->assertFalse(! empty($this->schema['hero_title']['required']));
        $this->assertTrue(! empty($this->schema['hero_slides']['fields']['title']['required']));

        $html = $this->renderFields([], []);
        $this->assertStringContainsString('(required when row used)', $html);
    }

    public function testRepeatableSlotsRespectMaximumItems(): void
    {
        $html = $this->renderFields([], []);
        $this->assertSame(5, substr_count($html, 'data-repeatable-index='));
        $this->assertStringContainsString('Items: 0–5', $html);
    }

    public function testInvalidContentPayloadRemainsRejectedServerSide(): void
    {
        $errors = $this->pageService->create(new PageWriteDto(
            title: 'Bad Content',
            slug: 'bad-content',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [
                'cta_url' => 'javascript:alert(1)',
            ],
        ));
        $this->assertArrayHasKey('cta_url', $errors);
        $this->assertSame([], $this->pageService->listActive());
    }

    public function testFieldLabelsMatchThemeManifest(): void
    {
        $this->assertSame('Hero Title', $this->schema['hero_title']['label']);
        $this->assertSame('CTA URL', $this->schema['cta_url']['label']);
        $this->assertSame('Hero Slides', $this->schema['hero_slides']['label']);
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $errors
     */
    private function renderFields(array $payload, array $errors): string
    {
        return view('admin/pages/_partials/content_fields', [
            'contentSchema'  => $this->schema,
            'contentPayload' => $payload,
            'errors'         => $errors,
        ]);
    }

    /**
     * @param array<string, mixed>  $item
     * @param array<string, string> $errors
     */
    private function renderFullForm(string $mode, array $item, array $errors): string
    {
        $defaults = [
            'title'           => '',
            'slug'            => '',
            'locale'          => 'id',
            'template_key'    => 'custom-page',
            'parent_id'       => null,
            'content_payload' => [],
        ];
        $item = array_merge($defaults, $item);
        $payload = is_array($item['content_payload'] ?? null) ? $item['content_payload'] : [];

        return view('admin/pages/form', [
            'mode'           => $mode,
            'item'           => $item,
            'parents'        => [],
            'locales'        => ['id', 'en'],
            'errors'         => $errors,
            'formAction'     => site_url($mode === 'edit' ? 'admin/pages/1' : 'admin/pages'),
            'contentSchema'  => $this->schema,
            'contentPayload' => $payload,
            'success'        => null,
            'flashError'     => null,
            'canPublish'     => false,
            'canUnpublish'   => false,
        ]);
    }
}
