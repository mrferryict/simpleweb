<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\CategoryWriteDto;
use App\Dtos\PostWriteDto;
use App\Dtos\TagWriteDto;
use App\Enums\PostStatus;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Services\TagService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Post foundation service tests (Phase 3 / Task 3.7).
 *
 * @internal
 */
final class PostServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private PostService $posts;
    private CategoryService $categories;
    private TagService $tags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->posts      = Services::postService(getShared: false);
        $this->categories = Services::categoryService(getShared: false);
        $this->tags       = Services::tagService(getShared: false);
    }

    public function testCreateValidDraftPost(): void
    {
        $errors = $this->posts->create($this->dto('Hello', 'hello', 'id', 'Jane Doe'));
        $this->assertSame([], $errors);

        $rows = $this->posts->listActive();
        $this->assertCount(1, $rows);
        $this->assertSame(PostStatus::Draft->value, $rows[0]['post']->status);
        $this->assertSame('Hello', $rows[0]['translation']?->title);
        $this->assertSame('hello', $rows[0]['translation']?->slug);
        $this->assertSame('Jane Doe', $rows[0]['post']->manual_author);
        $this->assertSame('{}', $rows[0]['translation']?->content_payload);
    }

    public function testMissingTitleIsRejected(): void
    {
        $errors = $this->posts->create($this->dto('', 'hello', 'id', 'Jane'));
        $this->assertArrayHasKey('title', $errors);
        $this->assertSame([], $this->posts->listActive());
    }

    public function testMissingManualAuthorIsRejected(): void
    {
        $errors = $this->posts->create($this->dto('Hello', 'hello', 'id', ''));
        $this->assertArrayHasKey('manual_author', $errors);
    }

    public function testInvalidSlugIsRejected(): void
    {
        $errors = $this->posts->create($this->dto('Bad', '!!!', 'id', 'Jane'));
        $this->assertArrayHasKey('slug', $errors);
    }

    public function testReservedSlugIsRejected(): void
    {
        // Post slugs under /news/{slug} do not claim top-level reserved paths (ADR-024 global namespace).
        $this->assertSame([], $this->posts->create($this->dto('Admin route post', 'admin', 'id', 'Jane')));
    }

    public function testDuplicateSlugSameLocaleIsRejected(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('One', 'same', 'id', 'A')));
        $errors = $this->posts->create($this->dto('Two', 'same', 'id', 'B'));
        $this->assertArrayHasKey('slug', $errors);
        $this->assertCount(1, $this->posts->listActive());
    }

    public function testSameSlugDifferentLocaleIsAllowed(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('ID', 'news', 'id', 'A')));
        $this->assertSame([], $this->posts->create($this->dto('EN', 'news', 'en', 'B')));
        $this->assertCount(2, $this->posts->listActive());
    }

    public function testInvalidLocaleIsRejected(): void
    {
        $errors = $this->posts->create($this->dto('X', 'x', 'fr', 'A'));
        $this->assertArrayHasKey('locale', $errors);
    }

    public function testUnknownContentFieldIsRejected(): void
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: 'X',
            slug: 'x',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['invented' => 'nope'],
        ));
        $this->assertArrayHasKey('invented', $errors);
    }

    public function testUpdateValidPost(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('Old', 'old', 'id', 'A')));
        $id = $this->posts->listActive()[0]['post']->id;

        $errors = $this->posts->update($id, $this->dto('New', 'new-slug', 'en', 'B'));
        $this->assertSame([], $errors);

        $editable = $this->posts->findEditable($id);
        $this->assertSame('New', $editable['translation']->title);
        $this->assertSame('new-slug', $editable['translation']->slug);
        $this->assertSame('en', $editable['translation']->locale);
        $this->assertSame('B', $editable['post']->manual_author);
    }

    public function testCategoryAndTagRelations(): void
    {
        $this->assertSame([], $this->categories->create(new CategoryWriteDto('News', 'news')));
        $this->assertSame([], $this->tags->create(new TagWriteDto('Featured', 'featured')));
        $categoryId = $this->categories->listActive()[0]->id;
        $tagId      = $this->tags->listAll()[0]->id;

        $errors = $this->posts->create(new PostWriteDto(
            title: 'With tax',
            slug: 'with-tax',
            locale: 'id',
            manualAuthor: 'A',
            categoryIds: [$categoryId],
            tagIds: [$tagId],
        ));
        $this->assertSame([], $errors);

        $row = $this->posts->listActive()[0];
        $this->assertSame([$categoryId], $row['category_ids']);
        $this->assertSame([$tagId], $row['tag_ids']);
    }

    public function testInactiveCategoryIsRejected(): void
    {
        $this->assertSame([], $this->categories->create(new CategoryWriteDto('Old', 'old')));
        $id = $this->categories->listAll()[0]->id;
        $this->assertSame([], $this->categories->deactivate($id));

        $errors = $this->posts->create(new PostWriteDto(
            title: 'X',
            slug: 'x',
            locale: 'id',
            manualAuthor: 'A',
            categoryIds: [$id],
        ));
        $this->assertArrayHasKey('categories', $errors);
    }

    public function testInvalidTagIsRejected(): void
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: 'X',
            slug: 'x',
            locale: 'id',
            manualAuthor: 'A',
            tagIds: [99999],
        ));
        $this->assertArrayHasKey('tags', $errors);
    }

    public function testTrashMovesToTrashStatus(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('T', 't', 'id', 'A')));
        $id = $this->posts->listActive()[0]['post']->id;
        $this->assertSame([], $this->posts->trash($id));
        $this->assertSame([], $this->posts->listActive());
        $this->assertNull($this->posts->findEditable($id));
        $trashed = $this->posts->findById($id);
        $this->assertSame(PostStatus::Trash->value, $trashed?->status);
    }

    public function testContentSchemaResolvesCustomPostBodyRichText(): void
    {
        $this->assertSame('custom-post', $this->posts->postTemplateKey());
        $schema = $this->posts->contentSchema();
        $this->assertArrayHasKey('body', $schema);
        $this->assertSame('RICH_TEXT', $schema['body']['type']);
        $this->assertFalse((bool) ($schema['body']['required'] ?? false));
    }

    public function testCreateWithBodyContent(): void
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: 'Body Post',
            slug: 'body-post',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>Hello world</p>'],
        ));
        $this->assertSame([], $errors);

        $payload = json_decode(
            (string) $this->posts->listActive()[0]['translation']?->content_payload,
            true,
        );
        $this->assertIsArray($payload);
        $this->assertSame('<p>Hello world</p>', $payload['body']);
    }

    public function testUpdateWithBodyContent(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('Old', 'old-body', 'id', 'A')));
        $id = $this->posts->listActive()[0]['post']->id;

        $errors = $this->posts->update($id, new PostWriteDto(
            title: 'Old',
            slug: 'old-body',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>Updated</p>'],
        ));
        $this->assertSame([], $errors);

        $payload = json_decode(
            (string) $this->posts->findEditable($id)['translation']->content_payload,
            true,
        );
        $this->assertIsArray($payload);
        $this->assertSame('<p>Updated</p>', $payload['body']);
    }

    public function testBodyIsSanitizedBeforePersist(): void
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: 'Sanitize',
            slug: 'sanitize-body',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: [
                'body' => '<p>Hello</p><script>alert(1)</script><img src=x onerror=alert(1)>',
            ],
        ));
        $this->assertSame([], $errors);

        $payload = json_decode(
            (string) $this->posts->listActive()[0]['translation']?->content_payload,
            true,
        );
        $this->assertIsArray($payload);
        $this->assertStringContainsString('<p>Hello</p>', (string) $payload['body']);
        $this->assertStringNotContainsString('<script', strtolower((string) $payload['body']));
        $this->assertStringNotContainsString('<img', strtolower((string) $payload['body']));
        $this->assertStringNotContainsString('onerror', strtolower((string) $payload['body']));
    }

    public function testEmptyBodyIsAllowedForDraft(): void
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: 'Empty Body',
            slug: 'empty-body',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: [],
        ));
        $this->assertSame([], $errors);
        $this->assertSame('{}', $this->posts->listActive()[0]['translation']?->content_payload);
    }

    public function testUpdatePreservesLegacyPayloadKeys(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('Legacy', 'legacy-post', 'id', 'A')));
        $id = $this->posts->listActive()[0]['post']->id;

        db_connect()->table('post_translations')->where('post_id', $id)->update([
            'content_payload' => json_encode([
                'legacy_widget' => 'keep-me',
                'body'          => '<p>Old</p>',
            ], JSON_THROW_ON_ERROR),
        ]);

        $errors = $this->posts->update($id, new PostWriteDto(
            title: 'Legacy',
            slug: 'legacy-post',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: [
                'body' => '<p>New</p><script>x()</script>',
            ],
        ));
        $this->assertSame([], $errors);

        $payload = json_decode(
            (string) $this->posts->findEditable($id)['translation']->content_payload,
            true,
        );
        $this->assertIsArray($payload);
        $this->assertSame('keep-me', $payload['legacy_widget']);
        $this->assertStringContainsString('<p>New</p>', (string) $payload['body']);
        $this->assertStringNotContainsString('<script', strtolower((string) $payload['body']));
    }

    public function testPostDoesNotAcceptUserSuppliedTemplateKey(): void
    {
        $this->assertSame('custom-post', $this->posts->postTemplateKey());
        $this->assertFalse(property_exists(PostWriteDto::class, 'templateKey'));
        $this->assertFalse(property_exists(PostWriteDto::class, 'template_key'));

        $ref = new \ReflectionClass(PostWriteDto::class);
        $params = array_map(
            static fn (\ReflectionParameter $p): string => $p->getName(),
            $ref->getConstructor()?->getParameters() ?? [],
        );
        $this->assertNotContains('templateKey', $params);
        $this->assertNotContains('template_key', $params);
    }

    private function dto(string $title, string $slug, string $locale, string $author): PostWriteDto
    {
        return new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            manualAuthor: $author,
        );
    }
}
