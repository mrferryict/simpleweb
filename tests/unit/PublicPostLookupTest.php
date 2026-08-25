<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PostWriteDto;
use App\Enums\PostStatus;
use App\Services\PostService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Public Post lookup (Phase 3 / Task 3.9 / ADR-016).
 *
 * @internal
 */
final class PublicPostLookupTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private PostService $posts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->posts = Services::postService(getShared: false);
    }

    public function testPublishedPrimaryPostResolves(): void
    {
        $id = $this->createPublished('Hello', 'hello-id', 'id', '<p>Body</p>');
        $dto = $this->posts->findPublishedForPublic('hello-id', 'id');

        $this->assertNotNull($dto);
        $this->assertSame('Hello', $dto->title);
        $this->assertSame('hello-id', $dto->slug);
        $this->assertSame('id', $dto->locale);
        $this->assertSame('<p>Body</p>', $dto->body);
        $this->assertSame('id', $dto->requestedLocale);
        $this->assertFalse($dto->isFallback);
        $this->assertSame('custom-post', $dto->templateKey);
        $this->assertSame('Jane Doe', $dto->manualAuthor);
        unset($id);
    }

    public function testDraftDoesNotResolve(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('Draft', 'draft-post', 'id')));
        $this->assertNull($this->posts->findPublishedForPublic('draft-post', 'id'));
        $this->assertNull($this->posts->findPublishedForPublic('draft-post', 'en'));
    }

    public function testUnpublishedDoesNotResolve(): void
    {
        $id = $this->createWithStatus('Unpub', 'unpub-post', PostStatus::Unpublished);
        $this->assertNull($this->posts->findPublishedForPublic('unpub-post', 'id'));
        unset($id);
    }

    public function testArchivedDoesNotResolve(): void
    {
        $id = $this->createWithStatus('Arch', 'arch-post', PostStatus::Archived);
        $this->assertNull($this->posts->findPublishedForPublic('arch-post', 'id'));
        unset($id);
    }

    public function testTrashDoesNotResolve(): void
    {
        $id = $this->createWithStatus('Trash', 'trash-post', PostStatus::Trash);
        $this->assertNull($this->posts->findPublishedForPublic('trash-post', 'id'));
        unset($id);
    }

    public function testMissingSlugReturnsNull(): void
    {
        $this->assertNull($this->posts->findPublishedForPublic('no-such-slug', 'id'));
        $this->assertNull($this->posts->findPublishedForPublic('no-such-slug', 'en'));
    }

    public function testPublishedSecondaryTranslationResolves(): void
    {
        $id = $this->createPublished('ID Title', 'shared-slug', 'id', '<p>ID</p>');
        $this->insertTranslation($id, 'en', 'EN Title', 'shared-slug', '<p>EN</p>');

        $dto = $this->posts->findPublishedForPublic('shared-slug', 'en');
        $this->assertNotNull($dto);
        $this->assertSame('EN Title', $dto->title);
        $this->assertSame('<p>EN</p>', $dto->body);
        $this->assertSame('en', $dto->locale);
        $this->assertFalse($dto->isFallback);
    }

    public function testMissingSecondaryFallsBackToPublishedPrimary(): void
    {
        $this->createPublished('Primary Only', 'fallback-slug', 'id', '<p>Primary</p>');

        $dto = $this->posts->findPublishedForPublic('fallback-slug', 'en');
        $this->assertNotNull($dto);
        $this->assertTrue($dto->isFallback);
        $this->assertSame('Primary Only', $dto->title);
        $this->assertSame('id', $dto->locale);
        $this->assertSame('en', $dto->requestedLocale);
        $this->assertSame('<p>Primary</p>', $dto->body);
    }

    public function testSecondaryDoesNotFallBackToNonPublishedPrimary(): void
    {
        $this->assertSame([], $this->posts->create($this->dto('Draft Primary', 'no-fb', 'id')));
        $this->assertNull($this->posts->findPublishedForPublic('no-fb', 'en'));
    }

    public function testNonPublicPostHiddenEvenWhenTranslationExists(): void
    {
        $id = $this->createWithStatus('Hidden', 'hidden-en', PostStatus::Draft);
        $this->insertTranslation($id, 'en', 'Hidden EN', 'hidden-en', '<p>Secret</p>');

        $this->assertNull($this->posts->findPublishedForPublic('hidden-en', 'en'));
        $this->assertNull($this->posts->findPublishedForPublic('hidden-en', 'id'));
    }

    public function testNonPublicSecondaryDoesNotFallBackToOtherPublishedPrimarySlug(): void
    {
        // Post A published with id slug.
        $this->createPublished('Public A', 'collision-slug', 'id', '<p>A</p>');
        // Post B draft owns the EN row for the same slug token.
        $draftId = $this->createWithStatus('Draft B', 'other-id-slug', PostStatus::Draft);
        $this->insertTranslation($draftId, 'en', 'Draft EN', 'collision-slug', '<p>B</p>');

        $this->assertNull($this->posts->findPublishedForPublic('collision-slug', 'en'));
    }

    public function testTemplateKeyIsNeverReadFromPostData(): void
    {
        $this->createPublished('T', 'tpl-key', 'id', '');
        $dto = $this->posts->findPublishedForPublic('tpl-key', 'id');
        $this->assertNotNull($dto);
        $this->assertSame('custom-post', $dto->templateKey);
        $this->assertSame('custom-post', $this->posts->postTemplateKey());
    }

    private function createPublished(string $title, string $slug, string $locale, string $body): int
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            manualAuthor: 'Jane Doe',
            contentPayload: $body === '' ? [] : ['body' => $body],
        ));
        $this->assertSame([], $errors);
        $id = $this->posts->listActive()[0]['post']->id;
        $this->setStatus($id, PostStatus::Published);

        return $id;
    }

    private function createWithStatus(string $title, string $slug, PostStatus $status): int
    {
        $this->assertSame([], $this->posts->create($this->dto($title, $slug, 'id')));
        $id = $this->posts->listActive()[0]['post']->id;
        // Trash may already be excluded from listActive — find via create path.
        if ($status === PostStatus::Trash) {
            $this->setStatus($id, $status);
            db_connect()->table('posts')->where('id', $id)->update([
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);

            return $id;
        }

        $this->setStatus($id, $status);

        return $id;
    }

    private function setStatus(int $id, PostStatus $status): void
    {
        db_connect()->table('posts')->where('id', $id)->update([
            'status' => $status->value,
        ]);
    }

    private function insertTranslation(int $postId, string $locale, string $title, string $slug, string $body): void
    {
        db_connect()->table('post_translations')->insert([
            'post_id'         => $postId,
            'locale'          => $locale,
            'title'           => $title,
            'slug'            => $slug,
            'content_payload' => json_encode(['body' => $body], JSON_THROW_ON_ERROR),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function dto(string $title, string $slug, string $locale): PostWriteDto
    {
        return new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            manualAuthor: 'Jane Doe',
        );
    }
}
