<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\AuditEvent;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\RevisionResourceType;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\Revision\RevisionService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Page/Post Archive lifecycle (Phase 4 / Task 4.11B / ADR-020).
 *
 * @internal
 */
final class ContentArchiveTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private PageService $pages;
    private PostService $posts;
    private RevisionService $revisions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pages     = Services::pageService(getShared: false);
        $this->posts     = Services::postService(getShared: false);
        $this->revisions = Services::revisionService(getShared: false);
    }

    public function testPagePublishedToArchivedSucceeds(): void
    {
        $id = $this->createPublishedPage('Archive Me', 'archive-me-page');
        $this->assertSame([], $this->pages->archive($id));
        $page = $this->pages->findById($id);
        $this->assertNotNull($page);
        $this->assertSame(PageStatus::Archived->value, $page->status);
        $this->assertNull($page->deleted_at);
    }

    /**
     * @dataProvider pageInvalidArchiveStatuses
     */
    public function testPageCannotArchiveFromInvalidStatus(string $status): void
    {
        $id = $this->createDraftPage('Invalid Arch', 'invalid-arch-page-' . strtolower($status));
        db_connect()->table('pages')->where('id', $id)->update(['status' => $status]);
        $lockBefore = (int) $this->pages->findById($id)?->lock_version;
        $revBefore  = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $auditBefore = $this->auditCount(AuditEvent::PageArchived->value, 'page', $id);

        $errors = $this->pages->archive($id);
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame($status, $this->pages->findById($id)?->status);
        $this->assertSame($lockBefore, (int) $this->pages->findById($id)?->lock_version);
        $this->assertSame($revBefore, count($this->revisions->listEditorial(RevisionResourceType::Page, $id)));
        $this->assertSame($auditBefore, $this->auditCount(AuditEvent::PageArchived->value, 'page', $id));
    }

    public function testPageTrashCannotArchive(): void
    {
        $id = $this->createDraftPage('Trash Arch', 'trash-arch-page');
        $this->assertSame([], $this->pages->trash($id));
        $errors = $this->pages->archive($id);
        $this->assertArrayHasKey('_not_found', $errors);
        $this->assertSame(PageStatus::Trash->value, $this->pages->findById($id)?->status);
        $this->assertSame(0, $this->auditCount(AuditEvent::PageArchived->value, 'page', $id));
    }

    public function testPageArchiveUnauthorizedIsRejected(): void
    {
        $id = $this->createPublishedPage('No Arch', 'no-arch-page');
        $lockBefore = (int) $this->pages->findById($id)?->lock_version;
        $errors = $this->pages->archive($id, $this->userWithout('page.archive'));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
        $this->assertSame($lockBefore, (int) $this->pages->findById($id)?->lock_version);
        $this->assertSame(0, $this->auditCount(AuditEvent::PageArchived->value, 'page', $id));
    }

    public function testPageArchiveIncrementsLockCreatesRevisionAndAudit(): void
    {
        $id = $this->createPublishedPage('Lock Arch', 'lock-arch-page');
        $lockBefore = (int) $this->pages->findById($id)?->lock_version;
        $revBefore  = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));

        $this->assertSame([], $this->pages->archive($id, $this->userWith(['page.archive'])));

        $page = $this->pages->findById($id);
        $this->assertNotNull($page);
        $this->assertSame($lockBefore + 1, (int) $page->lock_version);
        $this->assertSame(PageStatus::Archived->value, $page->status);

        $list = $this->revisions->listEditorial(RevisionResourceType::Page, $id);
        $this->assertCount($revBefore + 1, $list);
        $this->assertFalse((bool) $list[0]->is_autosave);
        $snap = $list[0]->decodedSnapshot();
        $this->assertIsArray($snap);
        $this->assertSame(1, $snap['schema_version']);
        $this->assertSame('page', $snap['resource_type']);
        $this->assertSame(PageStatus::Archived->value, $snap['status']);

        $this->assertSame(1, $this->auditCount(AuditEvent::PageArchived->value, 'page', $id));
        $this->assertSame(0, $this->auditCount(AuditEvent::PageRestored->value, 'page', $id));
    }

    public function testPageStaleArchiveReturnsConflictWithoutMutation(): void
    {
        $id = $this->createPublishedPage('Stale Arch', 'stale-arch-page');
        $lock = (int) $this->pages->findById($id)?->lock_version;
        $revBefore = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));

        $errors = $this->pages->archive($id, null, $lock - 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertArrayHasKey('lock_version', $errors);
        $this->assertSame((string) $lock, $errors['lock_version']);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
        $this->assertSame($lock, (int) $this->pages->findById($id)?->lock_version);
        $this->assertSame($revBefore, count($this->revisions->listEditorial(RevisionResourceType::Page, $id)));
        $this->assertSame(0, $this->auditCount(AuditEvent::PageArchived->value, 'page', $id));
    }

    public function testPageArchivedRepublishCreatesPublishedAudit(): void
    {
        $id = $this->createPublishedPage('Repub Page', 'repub-page');
        $this->assertSame([], $this->pages->archive($id));
        $lockBefore = (int) $this->pages->findById($id)?->lock_version;
        $revBefore  = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $publishedBefore = $this->auditCount(AuditEvent::PagePublished->value, 'page', $id);

        $this->assertSame([], $this->pages->publish($id, $this->userWith(['page.publish'])));
        $page = $this->pages->findById($id);
        $this->assertNotNull($page);
        $this->assertSame(PageStatus::Published->value, $page->status);
        $this->assertSame($lockBefore + 1, (int) $page->lock_version);
        $this->assertCount($revBefore + 1, $this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $this->assertSame($publishedBefore + 1, $this->auditCount(AuditEvent::PagePublished->value, 'page', $id));
        $this->assertSame(0, $this->auditCount('PAGE_UNARCHIVED', 'page', $id));
    }

    public function testPageUnauthorizedRepublishIsRejected(): void
    {
        $id = $this->createPublishedPage('No Repub', 'no-repub-page');
        $this->assertSame([], $this->pages->archive($id));
        $errors = $this->pages->publish($id, $this->userWithout('page.publish'));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PageStatus::Archived->value, $this->pages->findById($id)?->status);
    }

    public function testPageStaleRepublishReturnsConflictWithoutMutation(): void
    {
        $id = $this->createPublishedPage('Stale Repub', 'stale-repub-page');
        $this->assertSame([], $this->pages->archive($id));
        $lock = (int) $this->pages->findById($id)?->lock_version;
        $errors = $this->pages->publish($id, null, $lock - 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertSame((string) $lock, $errors['lock_version']);
        $this->assertSame(PageStatus::Archived->value, $this->pages->findById($id)?->status);
        $this->assertSame($lock, (int) $this->pages->findById($id)?->lock_version);
    }

    public function testPageArchivedIsNotPublicUntilRepublished(): void
    {
        $id = $this->createPublishedPage('Public Arch', 'public-arch-page');
        $this->assertNotNull($this->pages->findPublishedForPublic('public-arch-page', 'id'));
        $this->assertSame([], $this->pages->archive($id));
        $this->assertNull($this->pages->findPublishedForPublic('public-arch-page', 'id'));
        $this->assertSame([], $this->pages->publish($id));
        $this->assertNotNull($this->pages->findPublishedForPublic('public-arch-page', 'id'));
    }

    public function testPostPublishedToArchivedSucceeds(): void
    {
        $id = $this->createPublishedPost('Archive Post', 'archive-me-post');
        $this->assertSame([], $this->posts->archive($id));
        $post = $this->posts->findById($id);
        $this->assertNotNull($post);
        $this->assertSame(PostStatus::Archived->value, $post->status);
        $this->assertNull($post->deleted_at);
    }

    /**
     * @dataProvider postInvalidArchiveStatuses
     */
    public function testPostCannotArchiveFromInvalidStatus(string $status): void
    {
        $id = $this->createDraftPost('Invalid Arch Post', 'invalid-arch-post-' . strtolower($status));
        if ($status === PostStatus::PendingReview->value) {
            $this->assertSame([], $this->posts->submitForReview($id));
        } else {
            db_connect()->table('posts')->where('id', $id)->update(['status' => $status]);
        }
        $lockBefore = (int) $this->posts->findById($id)?->lock_version;
        $errors = $this->posts->archive($id);
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame($status, $this->posts->findById($id)?->status);
        $this->assertSame($lockBefore, (int) $this->posts->findById($id)?->lock_version);
        $this->assertSame(0, $this->auditCount(AuditEvent::PostArchived->value, 'post', $id));
    }

    public function testPostTrashCannotArchive(): void
    {
        $id = $this->createDraftPost('Trash Arch Post', 'trash-arch-post');
        $this->assertSame([], $this->posts->trash($id));
        $errors = $this->posts->archive($id);
        $this->assertArrayHasKey('_not_found', $errors);
        $this->assertSame(PostStatus::Trash->value, $this->posts->findById($id)?->status);
    }

    public function testPostArchiveUnauthorizedIsRejected(): void
    {
        $id = $this->createPublishedPost('No Arch Post', 'no-arch-post');
        $errors = $this->posts->archive($id, $this->userWithout('post.archive'));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    public function testPostArchiveIncrementsLockCreatesRevisionAndAudit(): void
    {
        $id = $this->createPublishedPost('Lock Arch Post', 'lock-arch-post');
        $lockBefore = (int) $this->posts->findById($id)?->lock_version;
        $revBefore  = count($this->revisions->listEditorial(RevisionResourceType::Post, $id));

        $this->assertSame([], $this->posts->archive($id, $this->userWith(['post.archive'])));

        $post = $this->posts->findById($id);
        $this->assertNotNull($post);
        $this->assertSame($lockBefore + 1, (int) $post->lock_version);
        $list = $this->revisions->listEditorial(RevisionResourceType::Post, $id);
        $this->assertCount($revBefore + 1, $list);
        $this->assertFalse((bool) $list[0]->is_autosave);
        $snap = $list[0]->decodedSnapshot();
        $this->assertIsArray($snap);
        $this->assertSame(1, $snap['schema_version']);
        $this->assertSame('post', $snap['resource_type']);
        $this->assertSame(PostStatus::Archived->value, $snap['status']);
        $this->assertSame(1, $this->auditCount(AuditEvent::PostArchived->value, 'post', $id));
        $this->assertSame(0, $this->auditCount(AuditEvent::PostRestored->value, 'post', $id));
    }

    public function testPostStaleArchiveReturnsConflictWithoutMutation(): void
    {
        $id = $this->createPublishedPost('Stale Arch Post', 'stale-arch-post');
        $lock = (int) $this->posts->findById($id)?->lock_version;
        $revBefore = count($this->revisions->listEditorial(RevisionResourceType::Post, $id));
        $errors = $this->posts->archive($id, null, $lock - 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertSame((string) $lock, $errors['lock_version']);
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
        $this->assertSame($lock, (int) $this->posts->findById($id)?->lock_version);
        $this->assertSame($revBefore, count($this->revisions->listEditorial(RevisionResourceType::Post, $id)));
        $this->assertSame(0, $this->auditCount(AuditEvent::PostArchived->value, 'post', $id));
    }

    public function testPostArchivedRepublishCreatesPublishedAudit(): void
    {
        $id = $this->createPublishedPost('Repub Post', 'repub-post');
        $this->assertSame([], $this->posts->archive($id));
        $lockBefore = (int) $this->posts->findById($id)?->lock_version;
        $publishedBefore = $this->auditCount(AuditEvent::PostPublished->value, 'post', $id);
        $this->assertSame([], $this->posts->publish($id, $this->userWith(['post.publish'])));
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
        $this->assertSame($lockBefore + 1, (int) $this->posts->findById($id)?->lock_version);
        $this->assertSame($publishedBefore + 1, $this->auditCount(AuditEvent::PostPublished->value, 'post', $id));
        $this->assertSame(0, $this->auditCount('POST_UNARCHIVED', 'post', $id));
    }

    public function testPostUnauthorizedRepublishIsRejected(): void
    {
        $id = $this->createPublishedPost('No Repub Post', 'no-repub-post');
        $this->assertSame([], $this->posts->archive($id));
        $errors = $this->posts->publish($id, $this->userWithout('post.publish'));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PostStatus::Archived->value, $this->posts->findById($id)?->status);
    }

    public function testPostStaleRepublishReturnsConflictWithoutMutation(): void
    {
        $id = $this->createPublishedPost('Stale Repub Post', 'stale-repub-post');
        $this->assertSame([], $this->posts->archive($id));
        $lock = (int) $this->posts->findById($id)?->lock_version;
        $errors = $this->posts->publish($id, null, $lock - 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertSame(PostStatus::Archived->value, $this->posts->findById($id)?->status);
        $this->assertSame($lock, (int) $this->posts->findById($id)?->lock_version);
    }

    public function testPostArchivedIsNotPublicUntilRepublished(): void
    {
        $id = $this->createPublishedPost('Public Arch Post', 'public-arch-post');
        $this->assertNotNull($this->posts->findPublishedForPublic('public-arch-post', 'id'));
        $this->assertSame([], $this->posts->archive($id));
        $this->assertNull($this->posts->findPublishedForPublic('public-arch-post', 'id'));
        $this->assertSame([], $this->posts->publish($id));
        $this->assertNotNull($this->posts->findPublishedForPublic('public-arch-post', 'id'));
    }

    public function testUnarchiveServiceMethodsDoNotExist(): void
    {
        $this->assertFalse(method_exists(PageService::class, 'unarchive'));
        $this->assertFalse(method_exists(PostService::class, 'unarchive'));
        $this->assertFalse(method_exists(PageService::class, 'republish'));
        $this->assertFalse(method_exists(PostService::class, 'republish'));
    }

    /**
     * @return list<list<string>>
     */
    public static function pageInvalidArchiveStatuses(): array
    {
        return [
            ['DRAFT'],
            ['PENDING_REVIEW'],
            ['UNPUBLISHED'],
            ['ARCHIVED'],
            ['BOGUS'],
        ];
    }

    /**
     * @return list<list<string>>
     */
    public static function postInvalidArchiveStatuses(): array
    {
        return [
            ['DRAFT'],
            ['PENDING_REVIEW'],
            ['UNPUBLISHED'],
            ['ARCHIVED'],
            ['BOGUS'],
        ];
    }

    private function createDraftPage(string $title, string $slug): int
    {
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['body' => '<p>Hello</p>'],
        )));

        return (int) $this->pages->listActive()[0]['page']->id;
    }

    private function createPublishedPage(string $title, string $slug): int
    {
        $id = $this->createDraftPage($title, $slug);
        $this->assertSame([], $this->pages->publish($id));

        return $id;
    }

    private function createDraftPost(string $title, string $slug): int
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Jane Doe',
            contentPayload: ['body' => '<p>Body</p>'],
        )));

        return (int) $this->posts->listActive()[0]['post']->id;
    }

    private function createPublishedPost(string $title, string $slug): int
    {
        $id = $this->createDraftPost($title, $slug);
        $this->assertSame([], $this->posts->publish($id));

        return $id;
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => in_array($permission, $permissions, true),
        );

        return $user;
    }

    private function userWithout(string $denied): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => $permission !== $denied,
        );

        return $user;
    }

    private function auditCount(string $event, string $resourceType, int $id): int
    {
        return db_connect()->table('audit_logs')
            ->where('event', $event)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $id)
            ->countAllResults();
    }
}
