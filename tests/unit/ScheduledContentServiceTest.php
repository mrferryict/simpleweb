<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\CreateScheduledActionDto;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\AuditEvent;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\RevisionResourceType;
use App\Enums\ScheduledActionResultCode;
use App\Enums\ScheduledActionStatus;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\Revision\RevisionService;
use App\Services\ScheduledContentService;
use CodeIgniter\Settings\Config\Services as SettingsServices;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Scheduled publish/unpublish foundation (Phase 4 / Task 4.12C / ADR-021).
 *
 * @internal
 */
final class ScheduledContentServiceTest extends CIUnitTestCase
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
        SettingsServices::settings(getShared: true)->flush();
        $this->pages     = Services::pageService(getShared: false);
        $this->posts     = Services::postService(getShared: false);
        $this->revisions = Services::revisionService(getShared: false);
        $this->scheduler = Services::scheduledContentService(getShared: false);
    }

    public function testScheduledActionsTableSchemaAndPendingUniqueness(): void
    {
        $db     = db_connect();
        $fields = $db->getFieldNames('scheduled_actions');
        foreach ([
            'id', 'target_type', 'target_id', 'action_type', 'execute_at', 'status',
            'claimed_at', 'lease_until', 'processed_at', 'result_code', 'result_message',
            'attempts', 'last_error', 'failed_at', 'created_by', 'created_at', 'updated_at',
        ] as $col) {
            $this->assertContains($col, $fields);
        }

        $prefixed = $db->prefixTable('scheduled_actions');
        $xinfo    = $db->query('PRAGMA table_xinfo(' . $prefixed . ')')->getResultArray();
        $xnames   = array_map(static fn (array $row): string => (string) ($row['name'] ?? ''), $xinfo);
        $this->assertContains('pending_guard', $xnames);

        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $row = [
            'target_type' => 'page',
            'target_id'   => 1,
            'action_type' => 'PUBLISH',
            'execute_at'  => '2032-01-01 00:00:00',
            'status'      => ScheduledActionStatus::Pending->value,
            'attempts'    => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
        $db->table('scheduled_actions')->insert($row);
        $duplicateFailed = false;
        try {
            $db->table('scheduled_actions')->insert($row);
        } catch (\Throwable) {
            $duplicateFailed = true;
        }
        $this->assertTrue($duplicateFailed);

        $row['status'] = ScheduledActionStatus::Cancelled->value;
        $db->table('scheduled_actions')->insert($row);
        $this->assertGreaterThan(1, $db->table('scheduled_actions')->countAllResults());
    }

    public function testCreatePagePublishAndUnpublish(): void
    {
        $pageId = $this->createDraftPage('Sched Page', 'sched-page');
        $actor  = $this->userWith(['page.publish', 'page.unpublish'], 9);

        $this->assertSame([], $this->scheduler->create($this->createDto('page', $pageId, 'PUBLISH', $this->futureLocal('+2 hours')), $actor));
        $this->assertSame([], $this->scheduler->create($this->createDto('page', $pageId, 'UNPUBLISH', $this->futureLocal('+3 hours')), $actor));

        $rows = $this->scheduler->listForTarget('page', $pageId);
        $this->assertCount(2, $rows);
        $this->assertSame(9, (int) $rows[0]->created_by);
        $this->assertSame(ScheduledActionStatus::Pending->value, $rows[0]->status);
    }

    public function testCreatePostPublishAndUnpublish(): void
    {
        $postId = $this->createDraftPost('Sched Post', 'sched-post', 4);
        $actor  = $this->userWith(['post.publish', 'post.unpublish', 'post.edit_any'], 4);

        $this->assertSame([], $this->scheduler->create($this->createDto('post', $postId, 'PUBLISH', $this->futureLocal('+2 hours')), $actor));
        $this->assertSame([], $this->scheduler->create($this->createDto('post', $postId, 'UNPUBLISH', $this->futureLocal('+4 hours')), $actor));
        $this->assertCount(2, $this->scheduler->listForTarget('post', $postId));
    }

    public function testPermissionDenialAndContributorOwnership(): void
    {
        $pageId = $this->createDraftPage('No Perm', 'no-perm-page');
        $denied = $this->scheduler->create(
            $this->createDto('page', $pageId, 'PUBLISH', $this->futureLocal('+1 hour')),
            $this->userWith(['page.edit'], 1),
        );
        $this->assertArrayHasKey('_forbidden', $denied);

        $ownerId = 12;
        $postId  = $this->createDraftPost('Own Post', 'own-sched-post', $ownerId);
        $other   = $this->userWith(['post.publish', 'post.edit_own'], 99);
        $this->assertArrayHasKey('_forbidden', $this->scheduler->create(
            $this->createDto('post', $postId, 'PUBLISH', $this->futureLocal('+1 hour')),
            $other,
        ));

        $ownerNoPublish = $this->userWith(['post.edit_own'], $ownerId);
        $this->assertArrayHasKey('_forbidden', $this->scheduler->create(
            $this->createDto('post', $postId, 'PUBLISH', $this->futureLocal('+1 hour')),
            $ownerNoPublish,
        ));
    }

    public function testInvalidTargetAndAction(): void
    {
        $actor = $this->userWith(['page.publish'], 1);
        $this->assertArrayHasKey('target_id', $this->scheduler->create(
            $this->createDto('page', 999999, 'PUBLISH', $this->futureLocal('+1 hour')),
            $actor,
        ));
        $pageId = $this->createDraftPage('Bad Act', 'bad-act-page');
        $this->assertArrayHasKey('action_type', $this->scheduler->create(
            $this->createDto('page', $pageId, 'ARCHIVE', $this->futureLocal('+1 hour')),
            $actor,
        ));
    }

    public function testPastRejectedNowAcceptedTimezoneAndDuplicates(): void
    {
        $pageId = $this->createDraftPage('Time Page', 'time-page');
        $actor  = $this->userWith(['page.publish', 'page.unpublish'], 1);

        $this->assertArrayHasKey('execute_at', $this->scheduler->create(
            $this->createDto('page', $pageId, 'PUBLISH', $this->pastLocal()),
            $actor,
        ));

        $this->assertSame([], $this->scheduler->create(
            $this->createDto('page', $pageId, 'PUBLISH', $this->nowLocal()),
            $actor,
        ));

        $local = '2031-06-15 10:00:00';
        $this->assertSame([], $this->scheduler->create(
            $this->createDto('page', $pageId, 'PUBLISH', $local),
            $actor,
        ));
        $rows = $this->scheduler->listForTarget('page', $pageId);
        $utc  = null;
        foreach ($rows as $row) {
            if ((string) $row->action_type === 'PUBLISH' && str_starts_with((string) $row->execute_at, '2031-06-15')) {
                $utc = (string) $row->execute_at;
            }
        }
        $this->assertSame('2031-06-15 03:00:00', $utc);

        $dup = $this->scheduler->create($this->createDto('page', $pageId, 'PUBLISH', $local), $actor);
        $this->assertNotSame([], $dup);

        $this->assertSame([], $this->scheduler->create(
            $this->createDto('page', $pageId, 'PUBLISH', '2031-06-15 11:00:00'),
            $actor,
        ));
        $this->assertSame([], $this->scheduler->create(
            $this->createDto('page', $pageId, 'UNPUBLISH', $local),
            $actor,
        ));
    }

    public function testScheduledPublishSkipsTrashArchivedPendingReviewAndInvalid(): void
    {
        $trashId = $this->createDraftPage('Trash Sch', 'trash-sch-page');
        $this->insertDue('page', $trashId, 'PUBLISH');
        $this->assertSame([], $this->pages->trash($trashId));
        $this->assertSame(1, $this->scheduler->processDue()->skipped);
        $this->assertSkip('page', $trashId, ScheduledActionResultCode::TargetTrash->value);
        $this->assertSame(PageStatus::Trash->value, $this->pages->findById($trashId)?->status);
        $this->assertSame(0, $this->auditCount(AuditEvent::PagePublished->value, 'page', $trashId));

        $archId = $this->createPublishedPage('Arch Sch', 'arch-sch-page');
        $this->assertSame([], $this->pages->archive($archId));
        $revBefore = count($this->revisions->listEditorial(RevisionResourceType::Page, $archId));
        $this->insertDue('page', $archId, 'PUBLISH');
        cache()->save('page.public.' . $archId, 'cached');
        $this->assertGreaterThan(0, $this->scheduler->processDue()->skipped);
        $this->assertSkip('page', $archId, ScheduledActionResultCode::TargetArchived->value);
        $this->assertSame(PageStatus::Archived->value, $this->pages->findById($archId)?->status);
        $this->assertSame($revBefore, count($this->revisions->listEditorial(RevisionResourceType::Page, $archId)));
        $this->assertSame('cached', cache()->get('page.public.' . $archId));
        $this->assertSame([], $this->pages->publish($archId));

        $postId = $this->createDraftPost('Review Sch', 'review-sch-post');
        db_connect()->table('posts')->where('id', $postId)->update(['status' => PostStatus::PendingReview->value]);
        $this->insertDue('post', $postId, 'PUBLISH');
        $this->assertSame(1, $this->scheduler->processDue()->skipped);
        $this->assertSkip('post', $postId, ScheduledActionResultCode::TargetPendingReview->value);

        $pubId = $this->createPublishedPage('Already', 'already-pub-page');
        $this->insertDue('page', $pubId, 'PUBLISH');
        $this->assertSame(1, $this->scheduler->processDue()->skipped);
        $this->assertSkip('page', $pubId, ScheduledActionResultCode::TargetAlreadyPublished->value);
    }

    public function testScheduledUnpublishAppliesAndInvalidSourceSkips(): void
    {
        $id = $this->createPublishedPage('Unpub Sch', 'unpub-sch-page');
        cache()->save('page.public.' . $id, 'cached');
        $revBefore = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $this->insertDue('page', $id, 'UNPUBLISH');
        $result = $this->scheduler->processDue();
        $this->assertSame(1, $result->applied);
        $this->assertSame(PageStatus::Unpublished->value, $this->pages->findById($id)?->status);
        $this->assertCount($revBefore + 1, $this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $this->assertSame(1, $this->auditCount(AuditEvent::PageUnpublished->value, 'page', $id));
        $this->assertNull($this->latestAuditActor(AuditEvent::PageUnpublished->value, 'page', $id));
        $this->assertFalse(cache()->get('page.public.' . $id) === 'cached');

        $draftId = $this->createDraftPage('Draft Unpub', 'draft-unpub-page');
        $this->insertDue('page', $draftId, 'UNPUBLISH');
        $this->assertSame(1, $this->scheduler->processDue()->skipped);
        $this->assertSkip('page', $draftId, ScheduledActionResultCode::InvalidSourceState->value);
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($draftId)?->status);
    }

    public function testScheduledPublishAppliesWithRevisionAuditAndCache(): void
    {
        $id = $this->createDraftPage('Apply Pub', 'apply-pub-page');
        cache()->save('page.public.' . $id, 'cached');
        $lockBefore = (int) $this->pages->findById($id)?->lock_version;
        $revBefore  = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $this->insertDue('page', $id, 'PUBLISH');
        $this->assertSame(1, $this->scheduler->processDue()->applied);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
        $this->assertSame($lockBefore + 1, (int) $this->pages->findById($id)?->lock_version);
        $this->assertCount($revBefore + 1, $this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $this->assertSame(1, $this->auditCount(AuditEvent::PagePublished->value, 'page', $id));
        $this->assertNull($this->latestAuditActor(AuditEvent::PagePublished->value, 'page', $id));
        $this->assertNotSame('cached', cache()->get('page.public.' . $id));
        $row = $this->scheduler->listForTarget('page', $id)[0];
        $this->assertSame(ScheduledActionStatus::Processed->value, $row->status);
        $this->assertSame(ScheduledActionResultCode::Applied->value, $row->result_code);
    }

    public function testOccConflictSkipsWithoutRevisionOrHttp(): void
    {
        $id = $this->createDraftPage('Occ Sch', 'occ-sch-page');
        $expected = (int) $this->pages->findById($id)?->lock_version;
        $this->insertDue('page', $id, 'PUBLISH');
        $claimed = $this->scheduler->claimDue();
        $this->assertCount(1, $claimed);
        db_connect()->table('pages')->where('id', $id)->update(['lock_version' => $expected + 1]);
        $revBefore = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $outcome   = $this->scheduler->executeClaimed($claimed[0], $expected);
        $this->assertSame('skipped', $outcome);
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($id)?->status);
        $this->assertSame($revBefore, count($this->revisions->listEditorial(RevisionResourceType::Page, $id)));
        $this->assertSame(0, $this->auditCount(AuditEvent::PagePublished->value, 'page', $id));
        $this->assertSkip('page', $id, ScheduledActionResultCode::LockVersionConflict->value);
    }

    public function testCancelPendingDoesNotMutateContent(): void
    {
        $id    = $this->createDraftPage('Cancel Sch', 'cancel-sch-page');
        $actor = $this->userWith(['page.publish'], 3);
        $this->assertSame([], $this->scheduler->create(
            $this->createDto('page', $id, 'PUBLISH', $this->futureLocal('+5 hours')),
            $actor,
        ));
        $scheduleId = (int) $this->scheduler->listForTarget('page', $id)[0]->id;
        $lockBefore = (int) $this->pages->findById($id)?->lock_version;
        $revBefore  = count($this->revisions->listEditorial(RevisionResourceType::Page, $id));
        $this->assertSame([], $this->scheduler->cancel($scheduleId, $actor, 'page', $id));
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($id)?->status);
        $this->assertSame($lockBefore, (int) $this->pages->findById($id)?->lock_version);
        $this->assertSame($revBefore, count($this->revisions->listEditorial(RevisionResourceType::Page, $id)));
        $this->assertSame(0, $this->auditCount(AuditEvent::PagePublished->value, 'page', $id));
        $row = $this->scheduler->listForTarget('page', $id)[0];
        $this->assertSame(ScheduledActionStatus::Cancelled->value, $row->status);
        $this->assertSame(ScheduledActionResultCode::Cancelled->value, $row->result_code);
    }

    public function testDueFutureLeaseLimitAndReclaim(): void
    {
        $id = $this->createDraftPage('Batch Sch', 'batch-sch-page');
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        for ($i = 0; $i < 51; $i++) {
            $this->insertRaw([
                'target_type' => 'page',
                'target_id'   => $id,
                'action_type' => 'PUBLISH',
                'execute_at'  => '2020-01-01 00:00:' . sprintf('%02d', $i),
                'status'      => ScheduledActionStatus::Pending->value,
                'attempts'    => 0,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
        $this->insertRaw([
            'target_type' => 'page',
            'target_id'   => $id,
            'action_type' => 'UNPUBLISH',
            'execute_at'  => '2039-01-01 00:00:00',
            'status'      => ScheduledActionStatus::Pending->value,
            'attempts'    => 0,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $first = $this->scheduler->claimDue();
        $this->assertCount(50, $first);
        $second = $this->scheduler->claimDue();
        $this->assertCount(1, $second);
        $this->assertSame([], array_values(array_intersect($first, $second)));

        $pending = db_connect()->table('scheduled_actions')
            ->where('target_id', $id)
            ->where('status', ScheduledActionStatus::Pending->value)
            ->countAllResults();
        $this->assertSame(1, $pending);

        $row = db_connect()->table('scheduled_actions')
            ->where('status', ScheduledActionStatus::Processing->value)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();
        $this->assertIsArray($row);
        db_connect()->table('scheduled_actions')->where('id', $row['id'])->update([
            'lease_until' => '2020-01-01 00:00:00',
        ]);
        $reclaim = $this->scheduler->claimDue();
        $this->assertContains((int) $row['id'], $reclaim);
        $this->assertGreaterThan(1, (int) db_connect()->table('scheduled_actions')->where('id', $row['id'])->get()->getRowArray()['attempts']);
    }

    public function testCommandIsRegistered(): void
    {
        $commands = service('commands')->getCommands();
        $this->assertArrayHasKey('cms:scheduled-content', $commands);
    }

    private function createDto(string $type, int $id, string $action, string $local): CreateScheduledActionDto
    {
        return new CreateScheduledActionDto(
            targetType: $type,
            targetId: $id,
            actionType: $action,
            executeAtLocal: $local,
        );
    }

    private function insertDue(string $type, int $id, string $action): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $this->insertRaw([
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
     * @param array<string, mixed> $row
     */
    private function insertRaw(array $row): void
    {
        db_connect()->table('scheduled_actions')->insert($row);
    }

    private function assertSkip(string $type, int $id, string $code): void
    {
        $row = $this->scheduler->listForTarget($type, $id)[0];
        $this->assertSame(ScheduledActionStatus::Skipped->value, $row->status);
        $this->assertSame($code, $row->result_code);
    }

    private function futureLocal(string $modify): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->modify($modify)->format('Y-m-d H:i:s');
    }

    private function nowLocal(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d H:i:s');
    }

    private function pastLocal(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->modify('-1 hour')->format('Y-m-d H:i:s');
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

    private function createDraftPost(string $title, string $slug, int $createdBy = 1): int
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Jane Doe',
            contentPayload: ['body' => '<p>Body</p>'],
            createdBy: $createdBy,
        )));

        return (int) $this->posts->listActive()[0]['post']->id;
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions, int $id): User
    {
        $user = $this->getMockBuilder(User::class)->onlyMethods(['can'])->getMock();
        $user->id = $id;
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => in_array($permission, $permissions, true),
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

    private function latestAuditActor(string $event, string $resourceType, int $id): ?int
    {
        $row = db_connect()->table('audit_logs')
            ->where('event', $event)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $id)
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();
        if ($row === null || $row['actor_id'] === null) {
            return null;
        }

        return (int) $row['actor_id'];
    }
}
