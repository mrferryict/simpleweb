<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\PostService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Control Panel Post content schema field rendering (Phase 3 / Task 3.8 / ADR-015).
 *
 * @internal
 */
final class PostContentFormViewTest extends CIUnitTestCase
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

    private PostService $postService;

    /** @var array<string, array<string, mixed>> */
    private array $schema = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->postService = Services::postService(getShared: false);
        $this->schema      = $this->postService->contentSchema();
    }

    public function testCreateFormRendersBodyRichTextField(): void
    {
        $html = $this->renderFullForm('create', [], []);

        $this->assertStringContainsString('New post', $html);
        $this->assertStringContainsString('Body', $html);
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
        $this->assertStringNotContainsString('cdn.jsdelivr', $html);
        $this->assertStringNotContainsString('unpkg.com', $html);
    }

    public function testFormDoesNotExposeTemplateSelectorOrDeferredContentFields(): void
    {
        $html = $this->renderFullForm('create', [], []);

        $this->assertStringNotContainsString('name="template_key"', $html);
        $this->assertStringNotContainsString('name="templateKey"', $html);
        $this->assertStringNotContainsString('name="content[excerpt]"', $html);
        $this->assertStringNotContainsString('name="content[seo_title]"', $html);
        $this->assertStringNotContainsString('name="content[meta_description]"', $html);
        $this->assertStringNotContainsString('Excerpt', $html);
    }

    public function testFormRendersSeoFieldset(): void
    {
        $html = $this->renderFullForm('create', [], []);

        $this->assertStringContainsString('<legend>SEO</legend>', $html);
        $this->assertStringContainsString('name="meta_title"', $html);
        $this->assertStringContainsString('name="meta_description"', $html);
        $this->assertStringContainsString('name="canonical_url"', $html);
        $this->assertStringContainsString('name="og_image_id"', $html);
    }

    public function testEditFormPreservesExistingBodyContentEscaped(): void
    {
        $html = $this->renderFullForm('edit', [
            'id'                => 1,
            'title'             => 'News',
            'slug'              => 'news',
            'locale'            => 'id',
            'manual_author'     => 'Jane',
            'status'            => 'DRAFT',
            'category_ids'      => [],
            'tag_ids'           => [],
            'content_payload'   => [
                'body' => '<p>Hello</p><script>alert(1)</script>',
            ],
            'meta_title'        => 'Post SEO Title',
            'meta_description'  => 'Post SEO description',
            'canonical_url'     => 'https://example.com/news/custom',
            'og_image_id'       => 42,
        ], []);

        $this->assertStringContainsString('Edit post', $html);
        $this->assertStringContainsString('name="content[body]"', $html);
        $this->assertStringContainsString('&lt;p&gt;Hello&lt;/p&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('value="News"', $html);
        $this->assertStringContainsString('value="Jane"', $html);
        $this->assertStringContainsString('Post&#x20;SEO&#x20;Title', $html);
        $this->assertStringContainsString('Post SEO description', $html);
        $this->assertStringContainsString('news&#x2F;custom', $html);
        $this->assertStringContainsString('value="42"', $html);
    }

    public function testSchemaMatchesActiveThemeCustomPost(): void
    {
        $this->assertSame('custom-post', $this->postService->postTemplateKey());
        $this->assertArrayHasKey('body', $this->schema);
        $this->assertSame('RICH_TEXT', $this->schema['body']['type']);
        $this->assertSame('Body', $this->schema['body']['label']);
        $this->assertCount(1, $this->schema);
    }

    /**
     * @param array<string, mixed>  $item
     * @param array<string, string> $errors
     */
    private function renderFullForm(string $mode, array $item, array $errors): string
    {
        $defaults = [
            'title'             => '',
            'slug'              => '',
            'locale'            => 'id',
            'manual_author'     => '',
            'category_ids'      => [],
            'tag_ids'           => [],
            'featured_image_id' => null,
            'content_payload'   => [],
            'meta_title'        => '',
            'meta_description'  => '',
            'canonical_url'     => '',
            'og_image_id'       => '',
        ];
        $item    = array_merge($defaults, $item);
        $payload = is_array($item['content_payload'] ?? null) ? $item['content_payload'] : [];

        return view('admin/posts/form', [
            'mode'           => $mode,
            'item'           => $item,
            'locales'        => ['id', 'en'],
            'categories'     => [],
            'tags'           => [],
            'errors'         => $errors,
            'formAction'     => site_url($mode === 'edit' ? 'admin/posts/1' : 'admin/posts'),
            'contentSchema'  => $this->schema,
            'contentPayload' => $payload,
        ]);
    }
}
