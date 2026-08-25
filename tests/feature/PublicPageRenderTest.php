<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dtos\MediaUploadDto;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Services\PageService;
use App\Services\PostService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Public Page HTTP rendering (Phase 4 / Task 4.4 / ADR-017).
 *
 * @internal
 */
final class PublicPageRenderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    private PageService $pages;
    private PostService $posts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pages = Services::pageService(getShared: false);
        $this->posts = Services::postService(getShared: false);
    }

    public function testGetSlugReturnsPublishedContent(): void
    {
        $this->createPublishedPage('About Us', 'about-us', ['body' => '<p>Hello</p>', 'hero_title' => 'Welcome']);

        $result = $this->get('about-us');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('About Us', $body);
        $this->assertStringContainsString('Welcome', $body);
        $this->assertStringContainsString('<p>Hello</p>', $body);
        $this->assertStringNotContainsString('DRAFT', $body);
        $this->assertStringNotContainsString('PUBLISHED', $body);
    }

    public function testGetEnSlugReturnsEnglishContent(): void
    {
        $id = $this->createPublishedPage('ID Title', 'bilingual-page', ['body' => '<p>ID</p>']);
        db_connect()->table('page_translations')->insert([
            'page_id'         => $id,
            'locale'          => 'en',
            'title'           => 'EN Title',
            'slug'            => 'bilingual-page',
            'content_payload' => json_encode(['body' => '<p>EN</p>'], JSON_THROW_ON_ERROR),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get('en/bilingual-page');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('EN Title', $body);
        $this->assertStringContainsString('<p>EN</p>', $body);
    }

    public function testGetEnFallsBackToPrimaryWhenSecondaryMissing(): void
    {
        $this->createPublishedPage('Fallback Title', 'fb-page', ['body' => '<p>Primary body</p>']);

        $result = $this->get('en/fb-page');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Fallback Title', $body);
        $this->assertStringContainsString('<p>Primary body</p>', $body);
    }

    public function testMissingSlugReturns404(): void
    {
        $this->assertPublicNotFound('missing-page-xyz');
    }

    public function testDraftReturns404(): void
    {
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: 'Secret Draft',
            slug: 'secret-draft-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['body' => '<p>Nope</p>'],
        )));

        $this->assertPublicNotFound('secret-draft-page', [
            'Secret Draft',
            'DRAFT',
            '<p>Nope</p>',
        ]);
    }

    public function testUnpublishedArchivedTrashReturn404WithoutLeak(): void
    {
        foreach (
            [
                ['unpub-pg', PageStatus::Unpublished],
                ['arch-pg', PageStatus::Archived],
                ['trash-pg', PageStatus::Trash],
            ] as [$slug, $status]
        ) {
            $this->assertSame([], $this->pages->create(new PageWriteDto(
                title: 'Hidden ' . $slug,
                slug: $slug,
                locale: 'id',
                templateKey: 'custom-page',
                parentId: null,
                contentPayload: ['body' => '<p>Hidden</p>'],
            )));
            $row = db_connect()->table('page_translations')
                ->where('slug', $slug)
                ->where('locale', 'id')
                ->get()
                ->getRowArray();
            $this->assertIsArray($row);
            $id = (int) $row['page_id'];
            db_connect()->table('pages')->where('id', $id)->update([
                'status'     => $status->value,
                'deleted_at' => $status === PageStatus::Trash ? date('Y-m-d H:i:s') : null,
            ]);

            $this->assertPublicNotFound($slug, [
                'Hidden ' . $slug,
                $status->value,
            ]);
        }
    }

    public function testUnavailableTemplateReturns404(): void
    {
        $id = $this->createPublishedPage('Bad Template', 'bad-template-page', ['body' => '<p>X</p>']);
        db_connect()->table('pages')->where('id', $id)->update([
            'template_key' => 'missing-template',
        ]);

        $this->assertPublicNotFound('bad-template-page', [
            'Bad Template',
            'missing-template',
            '<p>X</p>',
        ]);
    }

    public function testNestedParentChildPathIsNotAPageRoute(): void
    {
        $parentId = $this->createPublishedPage('Parent', 'parent-pg', []);
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: 'Child',
            slug: 'child-pg',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: $parentId,
            contentPayload: [],
        )));
        $childId = (int) db_connect()->table('page_translations')
            ->where('slug', 'child-pg')
            ->where('locale', 'id')
            ->get()
            ->getRowArray()['page_id'];
        db_connect()->table('pages')->where('id', $childId)->update([
            'status' => PageStatus::Published->value,
        ]);

        $child = $this->get('child-pg');
        $child->assertStatus(200);

        $this->assertPublicNotFound('parent-pg/child-pg');
    }

    public function testNewsNamespaceRemainsPostController(): void
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: 'News Item',
            slug: 'news-item',
            locale: 'id',
            manualAuthor: 'Author',
            contentPayload: ['body' => '<p>Post body</p>'],
        )));
        $postId = $this->posts->listActive()[0]['post']->id;
        db_connect()->table('posts')->where('id', $postId)->update([
            'status' => PostStatus::Published->value,
        ]);

        $result = $this->get('news/news-item');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('News Item', $body);
        $this->assertStringContainsString('Author', $body);
        $this->assertStringContainsString('<p>Post body</p>', $body);
        $this->assertStringContainsString('post-body', $body);
    }

    public function testEnNewsNamespaceRemainsPostController(): void
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: 'EN News',
            slug: 'en-news-item',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>P</p>'],
        )));
        $postId = $this->posts->listActive()[0]['post']->id;
        db_connect()->table('posts')->where('id', $postId)->update([
            'status' => PostStatus::Published->value,
        ]);

        $result = $this->get('en/news/en-news-item');
        $result->assertStatus(200);
        $this->assertStringContainsString('EN News', (string) $result->response()->getBody());
    }

    public function testAdminPagesRouteRemainsProtected(): void
    {
        $result = $this->get('admin/pages');
        $result->assertRedirect();
    }

    public function testTitleIsEscapedAndRichTextRendered(): void
    {
        $html = view('themes/default/templates/custom-page', [
            'title'           => '<script>alert(1)</script>',
            'locale'          => 'id',
            'slug'            => 'x',
            'contentPayload'  => [
                'hero_title' => 'A & B',
                'body'       => '<p>Safe</p>',
            ],
            'requestedLocale' => 'id',
            'isFallback'      => false,
        ]);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('A &amp; B', $html);
        $this->assertStringContainsString('<p>Safe</p>', $html);
        $this->assertStringNotContainsString('class="hero-image"', $html);
    }

    public function testCustomPageThemeViewResolvesViaThemeService(): void
    {
        $theme = Services::themeService(getShared: false);
        $path  = $theme->publicViewPathForTemplate('custom-page');
        $this->assertFileExists($path);
        $this->assertSame('themes/default/templates/custom-page', $theme->publicViewNameForTemplate('custom-page'));
    }

    public function testPublishedPageRendersActiveImageAndDocument(): void
    {
        $media   = Services::mediaService(getShared: false);
        $pngPath = $this->makePng('hero2.png');
        $img     = $media->upload(new MediaUploadDto(
            tmpPath: $pngPath,
            originalFilename: 'hero2.png',
            clientMime: 'image/png',
            sizeBytes: (int) filesize($pngPath),
        ));
        $pdfPath = $this->makePdf('attach.pdf');
        $doc     = $media->upload(new MediaUploadDto(
            tmpPath: $pdfPath,
            originalFilename: 'attach.pdf',
            clientMime: 'application/pdf',
            sizeBytes: (int) filesize($pdfPath),
        ));
        $this->assertSame([], $img['errors']);
        $this->assertSame([], $doc['errors']);
        $imgId    = (int) $img['asset']->id;
        $docId    = (int) $doc['asset']->id;
        $imageUrl = $media->publicImageUrl($imgId);
        $docUrl   = $media->publicDocumentUrl($docId);
        $this->assertNotNull($imageUrl);
        $this->assertNotNull($docUrl);

        $this->createPublishedPage('Media Page', 'media-page', [
            'body'       => '<p>With media</p>',
            'hero_image' => $imgId,
            'attachment' => $docId,
        ]);

        $result = $this->get('media-page');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Media Page', $body);
        $this->assertStringContainsString('<p>With media</p>', $body);
        $this->assertStringContainsString('src="' . esc($imageUrl, 'attr') . '"', $body);
        $this->assertStringContainsString('href="' . esc($docUrl, 'attr') . '"', $body);
        $this->assertStringNotContainsString(WRITEPATH, $body);
        $this->assertStringNotContainsString('uploads/documents', $body);
        $this->assertStringNotContainsString('data-storage-key', $body);
    }

    public function testPublishedPageOmitsTrashAndMissingMedia(): void
    {
        $media   = Services::mediaService(getShared: false);
        $pngPath = $this->makePng('trash-hero.png');
        $img     = $media->upload(new MediaUploadDto(
            tmpPath: $pngPath,
            originalFilename: 'trash-hero.png',
            clientMime: 'image/png',
            sizeBytes: (int) filesize($pngPath),
        ));
        $pdfPath = $this->makePdf('trash-doc.pdf');
        $doc     = $media->upload(new MediaUploadDto(
            tmpPath: $pdfPath,
            originalFilename: 'trash-doc.pdf',
            clientMime: 'application/pdf',
            sizeBytes: (int) filesize($pdfPath),
        ));
        $imgId = (int) $img['asset']->id;
        $docId = (int) $doc['asset']->id;

        $pageId = $this->createPublishedPage('Trash Media Page', 'trash-media-page', [
            'body'       => '<p>Still visible</p>',
            'hero_image' => $imgId,
            'attachment' => $docId,
        ]);

        $this->assertSame([], $media->trash($imgId));
        $this->assertSame([], $media->trash($docId));
        // Simulate a stale missing reference without failing create-time validation.
        db_connect()->table('page_translations')->where('page_id', $pageId)->where('locale', 'id')->update([
            'content_payload' => json_encode([
                'body'       => '<p>Still visible</p>',
                'hero_image' => $imgId,
                'attachment' => 999999,
            ], JSON_THROW_ON_ERROR),
        ]);

        $result = $this->get('trash-media-page');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('<p>Still visible</p>', $body);
        $this->assertStringNotContainsString('class="hero-image"', $body);
        $this->assertStringNotContainsString('class="attachment"', $body);
        $this->assertStringNotContainsString('/uploads/images/', $body);
        $this->assertStringNotContainsString('/download/document/', $body);
        $this->assertStringNotContainsString(WRITEPATH, $body);
    }

    public function testContentMediaResolvedOnPublicDtoWithoutMutatingPayload(): void
    {
        $media = Services::mediaService(getShared: false);
        $pngPath = $this->makePng('dto.png');
        $img = $media->upload(new MediaUploadDto(
            tmpPath: $pngPath,
            originalFilename: 'dto.png',
            clientMime: 'image/png',
            sizeBytes: (int) filesize($pngPath),
        ));
        $imgId = (int) $img['asset']->id;

        $this->createPublishedPage('DTO Media', 'dto-media', [
            'hero_image' => $imgId,
            'body'       => '<p>x</p>',
        ]);

        $dto = $this->pages->findPublishedForPublic('dto-media', 'id');
        $this->assertNotNull($dto);
        $this->assertSame($imgId, $dto->contentPayload['hero_image']);
        $this->assertIsArray($dto->contentMedia['hero_image'] ?? null);
        $this->assertSame($imgId, $dto->contentMedia['hero_image']['media_id']);
        $this->assertStringStartsWith('/uploads/images/', $dto->contentMedia['hero_image']['url']);
    }

    /**
     * @param list<string> $forbiddenSnippets
     */
    private function assertPublicNotFound(string $path, array $forbiddenSnippets = []): void
    {
        try {
            $result = $this->get($path);
            $result->assertStatus(404);
            $body = (string) $result->response()->getBody();
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
            $body = $e->getMessage();
        }

        foreach ($forbiddenSnippets as $snippet) {
            $this->assertStringNotContainsString($snippet, $body);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createPublishedPage(string $title, string $slug, array $payload): int
    {
        $errors = $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: $payload,
        ));
        $this->assertSame([], $errors);
        $id = $this->pages->listActive()[0]['page']->id;
        db_connect()->table('pages')->where('id', $id)->update([
            'status' => PageStatus::Published->value,
        ]);

        return $id;
    }

    private function makePng(string $name): string
    {
        $dir = WRITEPATH . 'uploads/tmp/fixtures';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $name;
        $im   = imagecreatetruecolor(24, 24);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    private function makePdf(string $name): string
    {
        $dir = WRITEPATH . 'uploads/tmp/fixtures';
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir . '/' . $name;
        file_put_contents($path, "%PDF-1.4\n%%EOF\n");

        return $path;
    }
}
