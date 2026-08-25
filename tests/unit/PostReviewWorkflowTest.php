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
 * Contributor submit-for-review foundation (Phase 4 / Task 4.2 / REQ-POST-004).
 *
 * @internal
 */
final class PostReviewWorkflowTest extends CIUnitTestCase
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

    public function testDraftToPendingReviewSucceeds(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $actor = $this->userWith(['post.submit_review', 'post.edit_own'], 10);
        $this->assertSame([], $this->posts->submitForReview($id, $actor));
        $this->assertSame(PostStatus::PendingReview->value, $this->posts->findById($id)?->status);
    }

    public function testPendingReviewToPublishedSucceeds(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $this->assertSame([], $this->posts->submitForReview($id, $this->userWith(['post.submit_review', 'post.edit_own'], 10)));
        $this->assertSame([], $this->posts->reviewAndPublish($id, $this->userWith(['post.review'])));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testPendingReviewToDraftSucceeds(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $this->assertSame([], $this->posts->submitForReview($id, $this->userWith(['post.submit_review', 'post.edit_own'], 10)));
        $this->assertSame([], $this->posts->returnForRevision($id, $this->userWith(['post.review'])));
        $this->assertSame(PostStatus::Draft->value, $this->posts->findById($id)?->status);
    }

    public function testInvalidSubmitTransitionIsRejected(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $this->assertSame([], $this->posts->publish($id));
        $errors = $this->posts->submitForReview($id, $this->userWith(['post.submit_review', 'post.edit_any']));
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testInvalidReviewPublishTransitionIsRejected(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $errors = $this->posts->reviewAndPublish($id, $this->userWith(['post.review']));
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame(PostStatus::Draft->value, $this->posts->findById($id)?->status);
    }

    public function testContributorCannotSubmitAnotherUsersPost(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $other = $this->userWith(['post.submit_review', 'post.edit_own'], 99);
        $errors = $this->posts->submitForReview($id, $other);
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PostStatus::Draft->value, $this->posts->findById($id)?->status);
    }

    public function testContributorCannotPublish(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $contributor = $this->userWith(['post.submit_review', 'post.edit_own'], 10);
        $errors = $this->posts->publish($id, $contributor);
        $this->assertArrayHasKey('_forbidden', $errors);
    }

    public function testContributorCannotReview(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $contributor = $this->userWith(['post.submit_review', 'post.edit_own'], 10);
        $this->assertSame([], $this->posts->submitForReview($id, $contributor));

        $errorsPublish = $this->posts->reviewAndPublish($id, $contributor);
        $this->assertArrayHasKey('_forbidden', $errorsPublish);

        $errorsReturn = $this->posts->returnForRevision($id, $contributor);
        $this->assertArrayHasKey('_forbidden', $errorsReturn);
        $this->assertSame(PostStatus::PendingReview->value, $this->posts->findById($id)?->status);
    }

    public function testEditorCanReviewAndAdminWildcardCanSubmit(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $admin = $this->userWith(['post.submit_review', 'post.edit_any', 'post.review']);
        $this->assertSame([], $this->posts->submitForReview($id, $admin));
        $this->assertSame([], $this->posts->reviewAndPublish($id, $admin));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testPendingReviewIsNotPubliclyRenderable(): void
    {
        $id = $this->createDraftOwnedBy(10, 'review-slug');
        $this->assertSame([], $this->posts->submitForReview($id, $this->userWith(['post.submit_review', 'post.edit_own'], 10)));
        $this->assertNull($this->posts->findPublishedForPublic('review-slug', 'id'));

        $this->assertSame([], $this->posts->reviewAndPublish($id, $this->userWith(['post.review'])));
        $this->assertNotNull($this->posts->findPublishedForPublic('review-slug', 'id'));
    }

    public function testDirectPublishFromDraftStillWorksForEditors(): void
    {
        $id = $this->createDraftOwnedBy(10);
        $this->assertSame([], $this->posts->publish($id, $this->userWith(['post.publish'])));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testReturnForRevisionDoesNotModifyPayload(): void
    {
        $id = $this->createDraftOwnedBy(10, 'ret-slug', '<p>Keep</p>');
        $this->assertSame([], $this->posts->submitForReview($id, $this->userWith(['post.submit_review', 'post.edit_own'], 10)));
        $before = (string) $this->posts->findEditable($id)['translation']->content_payload;
        $this->assertSame([], $this->posts->returnForRevision($id, $this->userWith(['post.review'])));
        $after = (string) $this->posts->findEditable($id)['translation']->content_payload;
        $this->assertSame($before, $after);
    }

    private function createDraftOwnedBy(int $userId, string $slug = 'review-post', string $body = '<p>Body</p>'): int
    {
        $errors = $this->posts->create(new PostWriteDto(
            title: 'Review Post',
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Jane Doe',
            contentPayload: $body === '' ? [] : ['body' => $body],
            createdBy: $userId,
        ));
        $this->assertSame([], $errors);

        return $this->posts->listActive()[0]['post']->id;
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions, ?int $id = null): User
    {
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can'])
            ->getMock();
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => in_array($permission, $permissions, true),
        );
        if ($id !== null) {
            $user->id = $id;
        }

        return $user;
    }
}
