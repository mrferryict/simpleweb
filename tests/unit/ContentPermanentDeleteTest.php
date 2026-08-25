<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\CategoryWriteDto;
use App\Dtos\MenuItemWriteDto;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Dtos\TagWriteDto;
use App\Enums\AuditEvent;
use App\Enums\MenuLocation;
use App\Enums\MenuTargetType;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\RevisionResourceType;
use App\Services\CategoryService;
use App\Services\MenuService;
use App\Services\PageService;
use App\Services\PostService;
use App\Services\TagService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Page/Post permanent delete service foundation (Phase 4 / Task 4.10A).
 *
 * @internal
 */
final class ContentPermanentDeleteTest extends CIUnitTestCase
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
    private MenuService $menus;
    private CategoryService $categories;
    private TagService $tags;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pages      = Services::pageService(getShared: false);
        $this->posts      = Services::postService(getShared: false);
        $this->menus      = Services::menuService(getShared: false);
        $this->categories = Services::categoryService(getShared: false);
        $this->tags       = Services::tagService(getShared: false);
    }

    public function testPageNonTrashPermanentDeleteRejected(): void
    {
        $id = $this->createPage('Keep', 'keep-live');
        $errors = $this->pages->permanentlyDelete($id, $this->admin());
        $this->assertArrayHasKey('_status', $errors);
        $this->assertNotNull($this->pages->findById($id));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Page->value, $id, AuditEvent::PagePermanentlyDeleted));
    }

    public function testPageTrashPermanentDeleteAuthorizedAdmin(): void
    {
        $id = $this->createPage('Gone', 'gone-page');
        $this->assertSame([], $this->pages->trash($id, $this->userWith(['page.trash'])));

        $revBefore   = $this->countRevisions(RevisionResourceType::Page->value, $id);
        $auditBefore = $this->countAudits(RevisionResourceType::Page->value, $id);

        $this->assertSame([], $this->pages->permanentlyDelete($id, $this->admin()));
        $this->assertNull($this->pages->findById($id));
        $this->assertSame(0, db_connect()->table('page_translations')->where('page_id', $id)->countAllResults());
        $this->assertSame($revBefore, $this->countRevisions(RevisionResourceType::Page->value, $id));
        $this->assertGreaterThanOrEqual($auditBefore + 1, $this->countAudits(RevisionResourceType::Page->value, $id));
        $this->assertSame(1, $this->countAudits(RevisionResourceType::Page->value, $id, AuditEvent::PagePermanentlyDeleted));
    }

    public function testPageUnauthorizedPermanentDeleteRejected(): void
    {
        $id = $this->createPage('Auth', 'auth-page');
        $this->assertSame([], $this->pages->trash($id));
        $errors = $this->pages->permanentlyDelete($id, $this->userWith(['page.trash']));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertNotNull($this->pages->findById($id));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Page->value, $id, AuditEvent::PagePermanentlyDeleted));
    }

    public function testPageMissingPermanentDeleteRejected(): void
    {
        $errors = $this->pages->permanentlyDelete(99999, $this->admin());
        $this->assertArrayHasKey('_not_found', $errors);
    }

    public function testPageMenuDependencyBlocksPermanentDelete(): void
    {
        $id = $this->createPage('Linked', 'linked-page');
        $this->assertSame([], $this->menus->create(new MenuItemWriteDto(
            location: MenuLocation::Primary->value,
            label: 'Linked',
            targetType: MenuTargetType::Page->value,
            targetId: $id,
            externalUrl: '',
            displayOrder: 0,
            isActive: true,
        )));
        $this->assertSame([], $this->pages->trash($id));

        $errors = $this->pages->permanentlyDelete($id, $this->admin());
        $this->assertArrayHasKey('_dependency', $errors);
        $this->assertNotNull($this->pages->findById($id));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Page->value, $id, AuditEvent::PagePermanentlyDeleted));
    }

    public function testPageChildDependencyBlocksPermanentDelete(): void
    {
        $parentId = $this->createPage('Parent', 'parent-pd');
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: 'Child',
            slug: 'child-pd',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: $parentId,
        )));
        $childId = $this->pageIdBySlug('child-pd');

        $this->assertSame([], $this->pages->trash($childId));
        $this->assertSame([], $this->pages->trash($parentId));

        $errors = $this->pages->permanentlyDelete($parentId, $this->admin());
        $this->assertArrayHasKey('_dependency', $errors);
        $this->assertNotNull($this->pages->findById($parentId));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Page->value, $parentId, AuditEvent::PagePermanentlyDeleted));
    }

    public function testPageStaleLockVersionRejected(): void
    {
        $id = $this->createPage('Occ', 'occ-page');
        $this->assertSame([], $this->pages->trash($id));
        $page = $this->pages->findById($id);
        $this->assertNotNull($page);

        $errors = $this->pages->permanentlyDelete($id, $this->admin(), ((int) $page->lock_version) - 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertArrayHasKey('lock_version', $errors);
        $this->assertNotNull($this->pages->findById($id));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Page->value, $id, AuditEvent::PagePermanentlyDeleted));
    }

    public function testPageTrashRestoreStillWorks(): void
    {
        $id = $this->createPage('Restore', 'restore-page');
        $this->assertSame([], $this->pages->trash($id, $this->userWith(['page.trash'])));
        $this->assertSame([], $this->pages->restoreFromTrash($id, $this->userWith(['page.restore'])));
        $restored = $this->pages->findById($id);
        $this->assertNotNull($restored);
        $this->assertSame(PageStatus::Draft->value, $restored->status);
    }

    public function testPostNonTrashPermanentDeleteRejected(): void
    {
        $id = $this->createPost('Live', 'live-post');
        $errors = $this->posts->permanentlyDelete($id, $this->admin());
        $this->assertArrayHasKey('_status', $errors);
        $this->assertNotNull($this->posts->findById($id));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Post->value, $id, AuditEvent::PostPermanentlyDeleted));
    }

    public function testPostTrashPermanentDeleteRemovesOwnedRowsAndRetainsHistory(): void
    {
        $this->assertSame([], $this->categories->create(new CategoryWriteDto('News', 'news-pd')));
        $categoryId = (int) $this->categories->listActive()[0]->id;
        $this->assertSame([], $this->tags->create(new TagWriteDto('Featured', 'featured-pd')));
        $tagId = (int) $this->tags->listAll()[0]->id;

        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: 'With Tax',
            slug: 'with-tax',
            locale: 'id',
            manualAuthor: 'Author',
            categoryIds: [$categoryId],
            tagIds: [$tagId],
        )));
        $id = (int) $this->posts->listActive()[0]['post']->id;

        // Extra owned translation row (locale unique per post).
        db_connect()->table('post_translations')->insert([
            'post_id'         => $id,
            'locale'          => 'en',
            'title'           => 'With Tax EN',
            'slug'            => 'with-tax',
            'content_payload' => '{}',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->assertSame([], $this->posts->trash($id, $this->userWith(['post.trash'])));

        $revBefore   = $this->countRevisions(RevisionResourceType::Post->value, $id);
        $auditBefore = $this->countAudits(RevisionResourceType::Post->value, $id);
        $this->assertGreaterThan(0, db_connect()->table('post_categories')->where('post_id', $id)->countAllResults());
        $this->assertGreaterThan(0, db_connect()->table('post_tags')->where('post_id', $id)->countAllResults());
        $this->assertGreaterThan(0, db_connect()->table('post_translations')->where('post_id', $id)->countAllResults());

        $this->assertSame([], $this->posts->permanentlyDelete($id, $this->admin()));
        $this->assertNull($this->posts->findById($id));
        $this->assertSame(0, db_connect()->table('post_categories')->where('post_id', $id)->countAllResults());
        $this->assertSame(0, db_connect()->table('post_tags')->where('post_id', $id)->countAllResults());
        $this->assertSame(0, db_connect()->table('post_translations')->where('post_id', $id)->countAllResults());
        $this->assertSame($revBefore, $this->countRevisions(RevisionResourceType::Post->value, $id));
        $this->assertGreaterThanOrEqual($auditBefore + 1, $this->countAudits(RevisionResourceType::Post->value, $id));
        $this->assertSame(1, $this->countAudits(RevisionResourceType::Post->value, $id, AuditEvent::PostPermanentlyDeleted));
        // Category/tag masters remain.
        $this->assertNotNull($this->categories->findById($categoryId));
        $this->assertNotNull($this->tags->findById($tagId));
    }

    public function testPostUnauthorizedPermanentDeleteRejected(): void
    {
        $id = $this->createPost('Auth', 'auth-post');
        $this->assertSame([], $this->posts->trash($id));
        $errors = $this->posts->permanentlyDelete($id, $this->userWith(['post.trash']));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertNotNull($this->posts->findById($id));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Post->value, $id, AuditEvent::PostPermanentlyDeleted));
    }

    public function testPostMissingPermanentDeleteRejected(): void
    {
        $errors = $this->posts->permanentlyDelete(99999, $this->admin());
        $this->assertArrayHasKey('_not_found', $errors);
    }

    public function testPostStaleLockVersionRejected(): void
    {
        $id = $this->createPost('Occ', 'occ-post');
        $this->assertSame([], $this->posts->trash($id));
        $post = $this->posts->findById($id);
        $this->assertNotNull($post);

        $errors = $this->posts->permanentlyDelete($id, $this->admin(), ((int) $post->lock_version) - 1);
        $this->assertArrayHasKey('_conflict', $errors);
        $this->assertArrayHasKey('lock_version', $errors);
        $this->assertNotNull($this->posts->findById($id));
        $this->assertSame(0, $this->countAudits(RevisionResourceType::Post->value, $id, AuditEvent::PostPermanentlyDeleted));
    }

    public function testPostTrashRestoreStillWorks(): void
    {
        $id = $this->createPost('Restore', 'restore-post');
        $this->assertSame([], $this->posts->trash($id, $this->userWith(['post.trash'])));
        $this->assertSame([], $this->posts->restoreFromTrash($id, $this->userWith(['post.restore'])));
        $restored = $this->posts->findById($id);
        $this->assertNotNull($restored);
        $this->assertSame(PostStatus::Draft->value, $restored->status);
    }

    private function createPage(string $title, string $slug): int
    {
        $this->assertSame([], $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        )));

        return $this->pageIdBySlug($slug);
    }

    private function pageIdBySlug(string $slug): int
    {
        foreach ($this->pages->listActive() as $row) {
            if ($row['translation']?->slug === $slug) {
                return (int) $row['page']->id;
            }
        }

        $this->fail('Page slug not found: ' . $slug);
    }

    private function createPost(string $title, string $slug): int
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Author',
        )));

        return (int) $this->posts->listActive()[0]['post']->id;
    }

    private function admin(): User
    {
        return $this->userWith(['content.permanent_delete']);
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

    private function countRevisions(string $resourceType, int $resourceId): int
    {
        return db_connect()->table('revisions')
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->countAllResults();
    }

    private function countAudits(string $resourceType, int $resourceId, ?AuditEvent $event = null): int
    {
        $builder = db_connect()->table('audit_logs')
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId);
        if ($event !== null) {
            $builder->where('event', $event->value);
        }

        return $builder->countAllResults();
    }
}
