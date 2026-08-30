<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dtos\PostWriteDto;
use App\Enums\PostStatus;
use App\Services\PostService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Public Post HTTP rendering (Phase 3 / Task 3.9 / ADR-016).
 *
 * @internal
 */
final class PublicPostRenderTest extends CIUnitTestCase
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

    private PostService $posts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->posts = Services::postService(getShared: false);
    }

    public function testGetNewsSlugReturnsPublishedContent(): void
    {
        $this->createPublished('News Title', 'news-one', 'id', '<p>Hello</p>');

        $result = $this->get('news/news-one');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('News Title', $body);
        $this->assertStringContainsString('Jane Doe', $body);
        $this->assertStringContainsString('<p>Hello</p>', $body);
        $this->assertStringNotContainsString('DRAFT', $body);
    }

    public function testGetEnNewsSlugReturnsEnglishContent(): void
    {
        $id = $this->createPublished('ID Title', 'bilingual', 'id', '<p>ID</p>');
        db_connect()->table('post_translations')->insert([
            'post_id'         => $id,
            'locale'          => 'en',
            'title'           => 'EN Title',
            'slug'            => 'bilingual',
            'content_payload' => json_encode(['body' => '<p>EN</p>'], JSON_THROW_ON_ERROR),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $result = $this->get('en/news/bilingual');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('EN Title', $body);
        $this->assertStringContainsString('<p>EN</p>', $body);
    }

    public function testGetEnNewsFallsBackToPrimaryWhenSecondaryMissing(): void
    {
        $this->createPublished('Fallback Title', 'fb-post', 'id', '<p>Primary body</p>');

        $result = $this->get('en/news/fb-post');
        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Fallback Title', $body);
        $this->assertStringContainsString('<p>Primary body</p>', $body);
    }

    public function testMissingSlugReturns404(): void
    {
        $this->assertPublicNotFound('news/does-not-exist');
    }

    public function testDraftReturns404(): void
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: 'Secret Draft',
            slug: 'secret-draft',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>Nope</p>'],
        )));

        $this->assertPublicNotFound('news/secret-draft', [
            'Secret Draft',
            'DRAFT',
            '<p>Nope</p>',
        ]);
    }

    public function testUnpublishedArchivedTrashReturn404WithoutLeak(): void
    {
        foreach (
            [
                ['unpub-x', PostStatus::Unpublished],
                ['arch-x', PostStatus::Archived],
                ['trash-x', PostStatus::Trash],
            ] as [$slug, $status]
        ) {
            $this->assertSame([], $this->posts->create(new PostWriteDto(
                title: 'Hidden ' . $slug,
                slug: $slug,
                locale: 'id',
                manualAuthor: 'A',
                contentPayload: ['body' => '<p>Hidden</p>'],
            )));
            $row = db_connect()->table('post_translations')
                ->where('slug', $slug)
                ->where('locale', 'id')
                ->get()
                ->getRowArray();
            $this->assertIsArray($row);
            $id = (int) $row['post_id'];
            db_connect()->table('posts')->where('id', $id)->update([
                'status'     => $status->value,
                'deleted_at' => $status === PostStatus::Trash ? date('Y-m-d H:i:s') : null,
            ]);

            $this->assertPublicNotFound('news/' . $slug, [
                'Hidden ' . $slug,
                $status->value,
            ]);
        }
    }

    public function testAdminPostsRouteRemainsProtected(): void
    {
        $result = $this->get('admin/posts');
        $result->assertRedirect();
    }

    public function testTitleAndAuthorAreEscapedInThemeView(): void
    {
        $html = view('themes/default/templates/custom-post', [
            'title'           => '<script>alert(1)</script>',
            'manualAuthor'    => 'A & B',
            'locale'          => 'id',
            'slug'            => 'x',
            'body'            => '<p>Safe</p>',
            'requestedLocale' => 'id',
            'isFallback'      => false,
        ]);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('A &amp; B', $html);
        $this->assertStringContainsString('<p>Safe</p>', $html);
    }

    public function testCustomPostThemeViewFileExists(): void
    {
        $path = Services::themeService(getShared: false)->publicViewPathForTemplate('custom-post');
        $this->assertFileExists($path);
        $name = Services::themeService(getShared: false)->publicViewNameForTemplate('custom-post');
        $this->assertSame('themes/2026/templates/custom-post', $name);
    }

    /**
     * FeatureTestTrait surfaces PageNotFoundException instead of an HTTP 404 body.
     *
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

    private function createPublished(string $title, string $slug, string $locale, string $body): int
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            manualAuthor: 'Jane Doe',
            contentPayload: ['body' => $body],
        ));
        $this->assertSame([], $errors);
        $id = $this->posts->listActive()[0]['post']->id;
        db_connect()->table('posts')->where('id', $id)->update([
            'status' => PostStatus::Published->value,
        ]);

        return $id;
    }
}
