<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\CreateScheduledActionDto;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\RevisionResourceType;
use App\Enums\ScheduledActionResultCode;
use App\Enums\ScheduledActionStatus;
use App\Services\Cache\PublicContentCacheInvalidator;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\Revision\RevisionService;
use App\Services\ScheduledContentService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Post-commit public Page/Post cache invalidation (Phase 4 / Task 4.13 / ADR-009).
 *
 * @internal
 */
final class PublicContentCacheInvalidationTest extends CIUnitTestCase
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

    private PageService $pages;
    private PostService $posts;
    private RevisionService $revisions;
    private ScheduledContentService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pages     = Services::pageService(getShared: false);
        $this->posts     = Services::postService(getShared: false);
        $this->revisions = Services::revisionService(getShared: false);
        $this->scheduler = Services::scheduledContentService(getShared: false);
    }

    public function testPublishedPageUpdateInvalidatesPublicCacheAfterCommit(): void
    {
        $id   = $this->createPublishedPage('Cache Page', 'cache-page-upd');
        $lock = (int) $this->pages->findById($id)?->lock_version;
        $this->seedPage($id);

        $this->assertSame([], $this->pages->update($id, $this->pageDto('Cache Page', 'cache-page-upd', [
            'hero_title' => 'Updated',
            'body'       => '<p>Hello</p>',
        ]), null, $lock));

        $this->assertPageCacheCleared($id);
    }

    public function testPublishedPostUpdateInvalidatesPublicCacheAfterCommit(): void
    {
        $id   = $this->createPublishedPost('Cache Post', 'cache-post-upd');
        $lock = (int) $this->posts->findById($id)?->lock_version;
        $this->seedPost($id);

        $this->assertSame([], $this->posts->update($id, $this->postDto('Cache Post', 'cache-post-upd', [
            'body' => '<p>Updated</p>',
        ]), null, $lock));

        $this->assertPostCacheCleared($id);
    }

    public function testPublishInvalidatesPublicCache(): void
    {
        $pageId = $this->createDraftPage('Pub Cache', 'pub-cache-page');
        $this->seedPage($pageId);
        $this->assertSame([], $this->pages->publish($pageId));
        $this->assertPageCacheCleared($pageId);

        $postId = $this->createDraftPost('Pub Cache Post', 'pub-cache-post');
        $this->seedPost($postId);
        $this->assertSame([], $this->posts->publish($postId));
        $this->assertPostCacheCleared($postId);
    }

    public function testUnpublishInvalidatesPublicCache(): void
    {
        $pageId = $this->createPublishedPage('Unpub Cache', 'unpub-cache-page');
        $this->seedPage($pageId);
        $this->assertSame([], $this->pages->unpublish($pageId));
        $this->assertPageCacheCleared($pageId);

        $postId = $this->createPublishedPost('Unpub Cache Post', 'unpub-cache-post');
        $this->seedPost($postId);
        $this->assertSame([], $this->posts->unpublish($postId));
        $this->assertPostCacheCleared($postId);
    }

    public function testArchiveInvalidatesPublicCache(): void
    {
        $pageId = $this->createPublishedPage('Arch Cache', 'arch-cache-page');
        $this->seedPage($pageId);
        $this->assertSame([], $this->pages->archive($pageId));
        $this->assertPageCacheCleared($pageId);

        $postId = $this->createPublishedPost('Arch Cache Post', 'arch-cache-post');
        $this->seedPost($postId);
        $this->assertSame([], $this->posts->archive($postId));
        $this->assertPostCacheCleared($postId);
    }

    public function testRepublishInvalidatesPublicCache(): void
    {
        $pageId = $this->createPublishedPage('Repub Cache', 'repub-cache-page');
        $this->assertSame([], $this->pages->archive($pageId));
        $this->seedPage($pageId);
        $this->assertSame([], $this->pages->publish($pageId));
        $this->assertPageCacheCleared($pageId);

        $postId = $this->createPublishedPost('Repub Cache Post', 'repub-cache-post');
        $this->assertSame([], $this->posts->archive($postId));
        $this->seedPost($postId);
        $this->assertSame([], $this->posts->publish($postId));
        $this->assertPostCacheCleared($postId);
    }

    public function testTrashOfPubliclyVisibleContentInvalidatesPublicCache(): void
    {
        $pageId = $this->createPublishedPage('Trash Cache', 'trash-cache-page');
        $this->seedPage($pageId);
        $this->assertSame([], $this->pages->trash($pageId));
        $this->assertPageCacheCleared($pageId);

        $postId = $this->createPublishedPost('Trash Cache Post', 'trash-cache-post');
        $this->seedPost($postId);
        $this->assertSame([], $this->posts->trash($postId));
        $this->assertPostCacheCleared($postId);
    }

    public function testRevisionRestoreInvalidatesPublicCacheWhenPublished(): void
    {
        $editor = $this->userWith(['page.restore', 'page.edit', 'page.create', 'page.publish']);
        $this->assertSame([], $this->pages->create($this->pageDto('Rev Cache', 'rev-cache-page', [
            'hero_title' => 'One',
            'body'       => '<p>Hello</p>',
        ]), $editor));
        $id = (int) $this->pages->listActive()[0]['page']->id;
        $this->assertSame([], $this->pages->publish($id, $editor, 1));

        $this->assertSame([], $this->pages->update($id, $this->pageDto('Rev Cache', 'rev-cache-page', [
            'hero_title' => 'Two',
            'body'       => '<p>Hello</p>',
        ]), $editor, 2));

        $history = $this->revisions->listEditorial(RevisionResourceType::Page, $id);
        $first   = null;
        foreach ($history as $rev) {
            $snap = $rev->decodedSnapshot();
            if (is_array($snap) && ($snap['translations']['id']['content_payload']['hero_title'] ?? null) === 'One') {
                $first = $rev;
                break;
            }
        }
        $this->assertNotNull($first);

        $expected = (int) $this->pages->findById($id)?->lock_version;
        $this->seedPage($id);
        $this->assertSame([], $this->pages->restoreRevision($id, (int) $first->id, $editor, $expected));
        $this->assertPageCacheCleared($id);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
    }

    public function testFailedMutationDoesNotInvalidatePublicCache(): void
    {
        $id = $this->createPublishedPage('Fail Cache', 'fail-cache-page');
        $this->seedPage($id);
        $errors = $this->pages->publish($id);
        $this->assertArrayHasKey('_status', $errors);
        $this->assertPageCachePresent($id);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
    }

    public function testOccConflictDoesNotInvalidatePublicCache(): void
    {
        $id = $this->createPublishedPage('Occ Cache', 'occ-cache-page');
        $this->seedPage($id);
        $errors = $this->pages->update($id, $this->pageDto('Occ Cache', 'occ-cache-page'), null, 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertPageCachePresent($id);
    }

    public function testValidationFailureDoesNotInvalidatePublicCache(): void
    {
        $id   = $this->createPublishedPage('Val Cache', 'val-cache-page');
        $lock = (int) $this->pages->findById($id)?->lock_version;
        $this->seedPage($id);
        $errors = $this->pages->update($id, $this->pageDto('', 'val-cache-page'), null, $lock);
        $this->assertNotSame([], $errors);
        $this->assertArrayNotHasKey('_conflict', $errors);
        $this->assertPageCachePresent($id);
    }

    public function testAutosaveDoesNotInvalidatePublicCache(): void
    {
        $id   = $this->createPublishedPage('Auto Cache', 'auto-cache-page');
        $lock = (int) $this->pages->findById($id)?->lock_version;
        $this->seedPage($id);

        $this->assertSame([], $this->pages->autosave($id, $this->pageDto('Auto Cache', 'auto-cache-page', [
            'hero_title' => 'Drafty',
            'body'       => '<p>Hello</p>',
        ]), null, $lock));

        $this->assertPageCachePresent($id);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
        $this->assertSame($lock, (int) $this->pages->findById($id)?->lock_version);

        $postId   = $this->createPublishedPost('Auto Cache Post', 'auto-cache-post');
        $postLock = (int) $this->posts->findById($postId)?->lock_version;
        $this->seedPost($postId);
        $this->assertSame([], $this->posts->autosave($postId, $this->postDto('Auto Cache Post', 'auto-cache-post', [
            'body' => '<p>Drafty</p>',
        ]), null, $postLock));
        $this->assertPostCachePresent($postId);
    }

    public function testScheduledAppliedMutationInvalidatesPublicCache(): void
    {
        $id = $this->createDraftPage('Sched Apply', 'sched-apply-page');
        $this->seedPage($id);
        $this->insertDue('page', $id, 'PUBLISH');
        $this->assertSame(1, $this->scheduler->processDue()->applied);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
        $this->assertPageCacheCleared($id);
    }

    public function testScheduledSkippedMutationDoesNotInvalidatePublicCache(): void
    {
        $id = $this->createPublishedPage('Sched Skip', 'sched-skip-page');
        $this->assertSame([], $this->pages->archive($id));
        $this->insertDue('page', $id, 'PUBLISH');
        $this->seedPage($id);
        $this->assertSame(1, $this->scheduler->processDue()->skipped);
        $this->assertPageCachePresent($id);
        $this->assertSame(PageStatus::Archived->value, $this->pages->findById($id)?->status);
    }

    public function testScheduledCancelledMutationDoesNotInvalidatePublicCache(): void
    {
        $id    = $this->createDraftPage('Sched Cancel', 'sched-cancel-page');
        $actor = $this->userWith(['page.publish'], 3);
        $this->assertSame([], $this->scheduler->create(
            new CreateScheduledActionDto(
                targetType: 'page',
                targetId: $id,
                actionType: 'PUBLISH',
                executeAtLocal: (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))
                    ->modify('+5 hours')
                    ->format('Y-m-d H:i:s'),
            ),
            $actor,
        ));
        $scheduleId = (int) $this->scheduler->listForTarget('page', $id)[0]->id;
        $this->seedPage($id);
        $this->assertSame([], $this->scheduler->cancel($scheduleId, $actor, 'page', $id));
        $this->assertSame(ScheduledActionStatus::Cancelled->value, $this->scheduler->listForTarget('page', $id)[0]->status);
        $this->assertPageCachePresent($id);
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($id)?->status);
    }

    public function testScheduledFailedMutationDoesNotInvalidatePublicCache(): void
    {
        $id = $this->createDraftPage('Sched Fail', 'sched-fail-page');
        db_connect()->table('page_translations')->where('page_id', $id)->delete();
        db_connect()->table('page_translations')->insert([
            'page_id'         => $id,
            'locale'          => 'en',
            'title'           => 'EN Only',
            'slug'            => 'sched-fail-page',
            'content_payload' => '{}',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        $this->insertDue('page', $id, 'PUBLISH');
        $this->seedPage($id);
        $this->assertSame(1, $this->scheduler->processDue()->failed);
        $row = $this->scheduler->listForTarget('page', $id)[0];
        $this->assertSame(ScheduledActionStatus::Failed->value, $row->status);
        $this->assertSame(ScheduledActionResultCode::ValidationFailed->value, $row->result_code);
        $this->assertPageCachePresent($id);
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($id)?->status);
    }

    public function testReviewPublishInvalidatesPublicCache(): void
    {
        $id = $this->createDraftPost('Review Cache', 'review-cache-post', 10);
        $this->assertSame([], $this->posts->submitForReview($id, $this->userWith(['post.submit_review', 'post.edit_own'], 10)));
        $this->seedPost($id);
        $this->assertSame([], $this->posts->reviewAndPublish($id, $this->userWith(['post.review'])));
        $this->assertPostCacheCleared($id);
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function pageDto(string $title, string $slug, array $payload = ['hero_title' => 'Hero', 'body' => '<p>Hello</p>']): PageWriteDto
    {
        return new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: $payload,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postDto(string $title, string $slug, array $payload = ['body' => '<p>Body</p>'], ?int $createdBy = null): PostWriteDto
    {
        return new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Jane Doe',
            contentPayload: $payload,
            createdBy: $createdBy,
        );
    }

    private function createDraftPage(string $title, string $slug): int
    {
        $this->assertSame([], $this->pages->create($this->pageDto($title, $slug)));

        return (int) $this->pages->listActive()[0]['page']->id;
    }

    private function createPublishedPage(string $title, string $slug): int
    {
        $id = $this->createDraftPage($title, $slug);
        $this->assertSame([], $this->pages->publish($id));

        return $id;
    }

    private function createDraftPost(string $title, string $slug, int $createdBy = 1): int
    {
        $this->assertSame([], $this->posts->create($this->postDto($title, $slug, ['body' => '<p>Body</p>'], $createdBy)));

        return (int) $this->posts->listActive()[0]['post']->id;
    }

    private function createPublishedPost(string $title, string $slug): int
    {
        $id = $this->createDraftPost($title, $slug);
        $this->assertSame([], $this->posts->publish($id));

        return $id;
    }

    private function seedPage(int $id): void
    {
        cache()->save(PublicContentCacheInvalidator::pageKey($id), 'cached', 300);
        $this->assertPageCachePresent($id);
    }

    private function seedPost(int $id): void
    {
        cache()->save(PublicContentCacheInvalidator::postKey($id), 'cached', 300);
        $this->assertPostCachePresent($id);
    }

    private function assertPageCachePresent(int $id): void
    {
        $this->assertSame('cached', cache()->get(PublicContentCacheInvalidator::pageKey($id)));
    }

    private function assertPageCacheCleared(int $id): void
    {
        $this->assertNotSame('cached', cache()->get(PublicContentCacheInvalidator::pageKey($id)));
    }

    private function assertPostCachePresent(int $id): void
    {
        $this->assertSame('cached', cache()->get(PublicContentCacheInvalidator::postKey($id)));
    }

    private function assertPostCacheCleared(int $id): void
    {
        $this->assertNotSame('cached', cache()->get(PublicContentCacheInvalidator::postKey($id)));
    }

    private function insertDue(string $type, int $id, string $action): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        db_connect()->table('scheduled_actions')->insert([
            'target_type' => $type,
            'target_id'   => $id,
            'action_type' => $action,
            'execute_at'  => $now,
            'status'      => ScheduledActionStatus::Pending->value,
            'attempts'    => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions, ?int $id = null): User
    {
        $user = $this->getMockBuilder(User::class)->onlyMethods(['can'])->getMock();
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => in_array($permission, $permissions, true),
        );
        if ($id !== null) {
            $user->id = $id;
        }

        return $user;
    }
}
