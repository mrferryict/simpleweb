<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\AuditController;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\AuditEvent;
use App\Enums\RevisionResourceType;
use App\Services\Audit\AuditService;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Audit Trail read-only list (Phase 4 / Task 4.9E / ADR-019).
 *
 * @internal
 */
final class AuditTrailVisibilityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use ControllerTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    private AuditService $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audit = Services::auditService(getShared: false);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('auth');
        parent::tearDown();
    }

    public function testListRecentNewestFirstAndExcludesMetadata(): void
    {
        (void) $this->audit->append(AuditEvent::PageCreated, 1, 'page', 1, 1);
        (void) $this->audit->append(AuditEvent::PagePublished, 1, 'page', 1, 2);

        $rows = $this->audit->listRecentForAdmin();
        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame(AuditEvent::PagePublished->value, $rows[0]['event']);
        $this->assertSame(AuditEvent::PageCreated->value, $rows[1]['event']);
        $this->assertArrayHasKey('actor_label', $rows[0]);
        $this->assertArrayHasKey('resource_type', $rows[0]);
        $this->assertArrayHasKey('resource_id', $rows[0]);
        $this->assertArrayHasKey('revision_id', $rows[0]);
        $this->assertArrayNotHasKey('metadata', $rows[0]);
        $this->assertArrayNotHasKey('snapshot', $rows[0]);
    }

    public function testOpeningAuditIndexCreatesNoAuditRow(): void
    {
        $admin = $this->actorWith(['audit.view']);
        $before = db_connect()->table('audit_logs')->countAllResults();

        $this->injectAuth($admin);
        $result = $this->withUri('http://example.com/admin/audit')
            ->controller(AuditController::class)
            ->execute('index');
        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Audit Trail', $body);

        $this->assertSame($before, db_connect()->table('audit_logs')->countAllResults());
    }

    public function testPagePublishAppearsInAuditList(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create', 'page.publish', 'audit.view']);
        $pages  = Services::pageService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Audit Page',
            slug: 'audit-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'A'],
        ), $editor));
        $id = (int) $pages->listActive()[0]['page']->id;
        $this->assertSame([], $pages->publish($id, $editor, 1));

        $rows  = $this->audit->listRecentForAdmin();
        $events = array_column($rows, 'event');
        $this->assertContains(AuditEvent::PageCreated->value, $events);
        $this->assertContains(AuditEvent::PagePublished->value, $events);

        $published = null;
        foreach ($rows as $row) {
            if ($row['event'] === AuditEvent::PagePublished->value && $row['resource_id'] === (string) $id) {
                $published = $row;
                break;
            }
        }
        $this->assertNotNull($published);
        $this->assertSame('page', $published['resource_type']);
    }

    public function testAutosaveDoesNotCreateAuditEvent(): void
    {
        $editor = $this->actorWith(['post.create', 'post.edit_any']);
        $posts  = Services::postService(getShared: false);

        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'As Audit',
            slug: 'as-audit',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>a</p>'],
            createdBy: 1,
        ), $editor));
        $id = (int) $posts->listActive($editor)[0]['post']->id;
        $auditBefore = db_connect()->table('audit_logs')
            ->where('resource_type', 'post')
            ->where('resource_id', $id)
            ->countAllResults();

        $this->assertSame([], $posts->autosave($id, new PostWriteDto(
            title: 'As Audit',
            slug: 'as-audit',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>b</p>'],
            createdBy: 1,
        ), $editor, 1));

        $this->assertSame(
            $auditBefore,
            db_connect()->table('audit_logs')
                ->where('resource_type', 'post')
                ->where('resource_id', $id)
                ->countAllResults(),
        );
        $this->assertNotNull(
            Services::revisionService(getShared: false)
                ->findLatestAutosave(RevisionResourceType::Post, $id),
        );

        foreach ($this->audit->listRecentForAdmin() as $row) {
            $this->assertStringNotContainsString('AUTOSAVE', $row['event']);
        }
    }

    public function testAuthGroupsAuditViewAdminOnly(): void
    {
        /** @var \Config\AuthGroups $groups */
        $groups = config('AuthGroups');
        $this->assertContains('audit.*', $groups->matrix['admin']);
        $this->assertNotContains('audit.view', $groups->matrix['editor']);
        $this->assertNotContains('audit.*', $groups->matrix['editor']);
        $this->assertNotContains('audit.view', $groups->matrix['contributor']);
        $this->assertNotContains('audit.*', $groups->matrix['contributor']);
    }

    /**
     * @param list<string> $permissions
     */
    private function actorWith(array $permissions, int $id = 1): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $p): bool => in_array($p, $permissions, true),
        );
        $user->id = $id;

        return $user;
    }

    private function injectAuth(User $user): void
    {
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['user', 'id', 'setAuthenticator'])
            ->getMock();
        $auth->method('setAuthenticator')->willReturnSelf();
        $auth->method('user')->willReturn($user);
        $auth->method('id')->willReturn((int) $user->id);

        Services::injectMock('auth', $auth);
    }
}
