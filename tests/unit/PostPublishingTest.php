<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PostWriteDto;
use App\Enums\PostStatus;
use App\Services\PostService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Post publishing foundation (Phase 4 / Task 4.1 / DOC-04).
 *
 * @internal
 */
final class PostPublishingTest extends CIUnitTestCase
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

    public function testDraftToPublishedSucceeds(): void
    {
        $id = $this->createDraft('Publish Me', 'publish-me', '<p>Body</p>');
        $this->assertSame([], $this->posts->publish($id));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testPublishedToUnpublishedSucceeds(): void
    {
        $id = $this->createDraft('Unpub Me', 'unpub-me', '<p>Body</p>');
        $this->assertSame([], $this->posts->publish($id));
        $this->assertSame([], $this->posts->unpublish($id));
        $this->assertSame(PostStatus::Unpublished->value, $this->posts->findById($id)?->status);
    }

    public function testUnpublishedCanBePublishedAgain(): void
    {
        $id = $this->createDraft('Again', 'again-me', '<p>Body</p>');
        $this->assertSame([], $this->posts->publish($id));
        $this->assertSame([], $this->posts->unpublish($id));
        $this->assertSame([], $this->posts->publish($id));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testInvalidPublishTransitionIsRejected(): void
    {
        $id = $this->createDraft('Already Live', 'already-live-post', '');
        $this->assertSame([], $this->posts->publish($id));
        $errors = $this->posts->publish($id);
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testArchivedToPublishedSucceeds(): void
    {
        $id = $this->createDraft('Archived Pub', 'archived-pub-post', '<p>Body</p>');
        $this->assertSame([], $this->posts->publish($id));
        $this->assertSame([], $this->posts->archive($id));
        $this->assertSame([], $this->posts->publish($id));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testInvalidUnpublishTransitionIsRejected(): void
    {
        $id = $this->createDraft('Still Draft', 'still-draft', '');
        $errors = $this->posts->unpublish($id);
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame(PostStatus::Draft->value, $this->posts->findById($id)?->status);
    }

    public function testPublishPermissionBoundary(): void
    {
        $id = $this->createDraft('Denied', 'denied-pub', '');
        $actor = $this->userWithout('post.publish');
        $errors = $this->posts->publish($id, $actor);
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PostStatus::Draft->value, $this->posts->findById($id)?->status);
    }

    public function testUnpublishPermissionBoundary(): void
    {
        $id = $this->createDraft('Denied U', 'denied-unpub', '');
        $this->assertSame([], $this->posts->publish($id));
        $actor = $this->userWithout('post.unpublish');
        $errors = $this->posts->unpublish($id, $actor);
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testEmptyBodyIsAllowedOnPublishPerSchema(): void
    {
        $id = $this->createDraft('No Body', 'no-body-pub', '');
        $this->assertSame([], $this->posts->publish($id));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testUnpublishDoesNotModifyContentPayload(): void
    {
        $id = $this->createDraft('Payload', 'payload-keep', '<p>Keep</p>');
        $this->assertSame([], $this->posts->publish($id));
        $before = (string) $this->posts->findEditable($id)['translation']->content_payload;
        $this->assertSame([], $this->posts->unpublish($id));
        $after = (string) $this->posts->findEditable($id)['translation']->content_payload;
        $this->assertSame($before, $after);
        $decoded = json_decode($after, true);
        $this->assertIsArray($decoded);
        $this->assertSame('<p>Keep</p>', $decoded['body'] ?? null);
    }

    public function testPublishedBecomesPubliclyRenderableAndUnpublishedDoesNot(): void
    {
        $id = $this->createDraft('Public Path', 'public-path', '<p>Hi</p>');
        $this->assertNull($this->posts->findPublishedForPublic('public-path', 'id'));

        $this->assertSame([], $this->posts->publish($id));
        $dto = $this->posts->findPublishedForPublic('public-path', 'id');
        $this->assertNotNull($dto);
        $this->assertSame('Public Path', $dto->title);

        $this->assertSame([], $this->posts->unpublish($id));
        $this->assertNull($this->posts->findPublishedForPublic('public-path', 'id'));
    }

    public function testPublishRequiresPrimaryLocaleTranslation(): void
    {
        $id = $this->createDraft('EN Only Setup', 'en-only-slug', '');
        // Replace id translation with en-only (no primary id row).
        db_connect()->table('post_translations')->where('post_id', $id)->delete();
        db_connect()->table('post_translations')->insert([
            'post_id'         => $id,
            'locale'          => 'en',
            'title'           => 'EN Only',
            'slug'            => 'en-only-slug',
            'content_payload' => '{}',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $errors = $this->posts->publish($id);
        $this->assertArrayHasKey('_locale', $errors);
        $this->assertSame(PostStatus::Draft->value, $this->posts->findById($id)?->status);
    }

    private function createDraft(string $title, string $slug, string $body): int
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Jane Doe',
            contentPayload: $body === '' ? [] : ['body' => $body],
        ));
        $this->assertSame([], $errors);

        return $this->posts->listActive()[0]['post']->id;
    }

    private function userWithout(string $deniedPermission): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => $permission !== $deniedPermission,
        );

        return $user;
    }
}
