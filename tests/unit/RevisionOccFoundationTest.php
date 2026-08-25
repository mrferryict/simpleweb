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
 * Revision + Audit + OCC persistence foundation (Phase 4 / Task 4.9B / ADR-019).
 *
 * @internal
 */
final class RevisionOccFoundationTest extends CIUnitTestCase
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

    private PostService $posts;
    private PageService $pages;
    private RevisionService $revisions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->posts     = Services::postService(getShared: false);
        $this->pages     = Services::pageService(getShared: false);
        $this->revisions = Services::revisionService(getShared: false);
    }

    public function testSchemaHasLockVersionAndRevisionAuditTables(): void
    {
        $db = db_connect();
        $this->assertTrue($db->fieldExists('lock_version', 'pages'));
        $this->assertTrue($db->fieldExists('lock_version', 'posts'));
        $this->assertTrue($db->tableExists('revisions'));
        $this->assertTrue($db->tableExists('audit_logs'));

        $revFields = $db->getFieldNames('revisions');
        foreach (['id', 'resource_type', 'resource_id', 'revision_number', 'is_autosave', 'snapshot', 'created_by', 'created_at'] as $col) {
            $this->assertContains($col, $revFields);
        }
        $auditFields = $db->getFieldNames('audit_logs');
        foreach (['id', 'actor_id', 'event', 'resource_type', 'resource_id', 'revision_id', 'metadata', 'created_at'] as $col) {
            $this->assertContains($col, $auditFields);
        }
    }

    public function testPostCreateRecordsRevisionOneAndAudit(): void
    {
        $this->assertSame([], $this->posts->create($this->postDto('Hello', 'hello-rev')));
        $id = (int) $this->posts->listActive()[0]['post']->id;

        $list = $this->revisions->listEditorial(RevisionResourceType::Post, $id);
        $this->assertCount(1, $list);
        $this->assertSame(1, (int) $list[0]->revision_number);
        $this->assertSame(0, (int) $list[0]->is_autosave);

        $snap = $list[0]->decodedSnapshot();
        $this->assertIsArray($snap);
        $this->assertSame(1, $snap['schema_version']);
        $this->assertSame('post', $snap['resource_type']);
        $this->assertSame($id, $snap['resource_id']);
        $this->assertArrayHasKey('translations', $snap);
        $this->assertArrayHasKey('id', $snap['translations']);
        $this->assertStringNotContainsString(WRITEPATH, (string) $list[0]->snapshot);
        $this->assertStringNotContainsString('/uploads/images/', (string) $list[0]->snapshot);

        $audit = db_connect()->table('audit_logs')->where('resource_type', 'post')->where('resource_id', $id)->get()->getRowArray();
        $this->assertNotNull($audit);
        $this->assertSame(AuditEvent::PostCreated->value, $audit['event']);
        $this->assertSame((int) $list[0]->id, (int) $audit['revision_id']);
    }

    public function testPostUpdateIncrementsLockVersionAndRevisionNumber(): void
    {
        $this->assertSame([], $this->posts->create($this->postDto('A', 'occ-a')));
        $post = $this->posts->listActive()[0]['post'];
        $id   = (int) $post->id;
        $this->assertSame(1, (int) $post->lock_version);

        $this->assertSame([], $this->posts->update($id, $this->postDto('B', 'occ-a'), null, 1));
        $updated = $this->posts->findById($id);
        $this->assertNotNull($updated);
        $this->assertSame(2, (int) $updated->lock_version);

        $list = $this->revisions->listEditorial(RevisionResourceType::Post, $id);
        $this->assertCount(2, $list);
        $this->assertSame(2, (int) $list[0]->revision_number);
    }

    public function testStaleLockVersionIsRejectedWithoutPartialPersist(): void
    {
        $this->assertSame([], $this->posts->create($this->postDto('C', 'occ-stale')));
        $id = (int) $this->posts->listActive()[0]['post']->id;
        $this->assertSame([], $this->posts->update($id, $this->postDto('C2', 'occ-stale'), null, 1));

        $errors = $this->posts->update($id, $this->postDto('C3', 'occ-stale'), null, 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertArrayHasKey('lock_version', $errors);
        $this->assertSame('2', $errors['lock_version']);

        $post = $this->posts->findById($id);
        $this->assertNotNull($post);
        $this->assertSame(2, (int) $post->lock_version);
        $this->assertSame(2, count($this->revisions->listEditorial(RevisionResourceType::Post, $id)));
    }

    public function testAutosaveDoesNotBumpLockVersionOrMutateLive(): void
    {
        $this->assertSame([], $this->posts->create($this->postDto('Live', 'auto-live', ['body' => '<p>Live</p>'])));
        $row = $this->posts->listActive()[0];
        $id  = (int) $row['post']->id;

        $errors = $this->posts->autosave(
            $id,
            $this->postDto('Live', 'auto-live', ['body' => '<p>Drafty</p>']),
            null,
            1,
        );
        $this->assertSame([], $errors);

        $post = $this->posts->findById($id);
        $this->assertNotNull($post);
        $this->assertSame(1, (int) $post->lock_version);

        $payload = json_decode((string) $row['translation']->content_payload, true);
        // Reload translation
        $fresh = $this->posts->listActive()[0]['translation'];
        $this->assertNotNull($fresh);
        $this->assertStringContainsString('Live', (string) $fresh->content_payload);
        $this->assertStringNotContainsString('Drafty', (string) $fresh->content_payload);

        $autosaves = db_connect()->table('revisions')
            ->where('resource_type', 'post')
            ->where('resource_id', $id)
            ->where('is_autosave', 1)
            ->countAllResults();
        $this->assertSame(1, $autosaves);
        $this->assertSame(1, count($this->revisions->listEditorial(RevisionResourceType::Post, $id)));

        $history = $this->revisions->listEditorialHistory(RevisionResourceType::Post, $id);
        $this->assertCount(1, $history);
        $this->assertFalse($history[0]['is_autosave']);
        $this->assertArrayHasKey('actor_label', $history[0]);
        $this->assertArrayHasKey('revision_number', $history[0]);
        $this->assertArrayNotHasKey('snapshot', $history[0]);
    }

    public function testPageRestoreRevisionKeepsStatus(): void
    {
        $editor = $this->userWith(['page.restore', 'page.edit', 'page.create', 'page.publish']);
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: 'P',
            slug: 'page-rev',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'One'],
        ), $editor));
        $id = (int) $this->pages->listActive()[0]['page']->id;
        $this->assertSame([], $this->pages->publish($id, $editor, 1));

        $this->assertSame([], $this->pages->update($id, new PageWriteDto(
            title: 'P',
            slug: 'page-rev',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'Two'],
        ), $editor, 2));

        $history = $this->revisions->listEditorial(RevisionResourceType::Page, $id);
        $this->assertGreaterThanOrEqual(2, count($history));
        $first = null;
        foreach ($history as $rev) {
            $snap = $rev->decodedSnapshot();
            if (is_array($snap) && ($snap['translations']['id']['content_payload']['hero_title'] ?? null) === 'One') {
                $first = $rev;
                break;
            }
        }
        $this->assertNotNull($first);

        $pageBefore = $this->pages->findById($id);
        $this->assertNotNull($pageBefore);
        $expected = (int) $pageBefore->lock_version;
        $this->assertSame(PageStatus::Published->value, $pageBefore->status);

        $this->assertSame([], $this->pages->restoreRevision($id, (int) $first->id, $editor, $expected));
        $pageAfter = $this->pages->findById($id);
        $this->assertNotNull($pageAfter);
        $this->assertSame(PageStatus::Published->value, $pageAfter->status);

        $translation = $this->pages->listActive()[0]['translation'];
        $this->assertNotNull($translation);
        $this->assertStringContainsString('One', (string) $translation->content_payload);
    }

    public function testPostPublishCreatesAuditEvent(): void
    {
        $actor = $this->userWith(['post.create', 'post.publish']);
        $this->assertSame([], $this->posts->create($this->postDto('Pub', 'pub-occ'), $actor));
        $id = (int) $this->posts->listActive()[0]['post']->id;
        $this->assertSame([], $this->posts->publish($id, $actor, 1));

        $events = db_connect()->table('audit_logs')
            ->where('resource_type', 'post')
            ->where('resource_id', $id)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $names = array_column($events, 'event');
        $this->assertContains(AuditEvent::PostCreated->value, $names);
        $this->assertContains(AuditEvent::PostPublished->value, $names);
        $this->assertSame(PostStatus::Published->value, $this->posts->findById($id)?->status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postDto(string $title, string $slug, array $payload = ['body' => '<p>x</p>']): PostWriteDto
    {
        return new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Author',
            contentPayload: $payload,
        );
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $p): bool => in_array($p, $permissions, true),
        );
        $user->id = 1;

        return $user;
    }
}
