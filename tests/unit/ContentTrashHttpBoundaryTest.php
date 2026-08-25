<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\PageController;
use App\Controllers\Admin\PostController;
use App\Dtos\MenuItemWriteDto;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\MenuLocation;
use App\Enums\MenuTargetType;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\RevisionResourceType;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Page/Post trash restore + permanent-delete HTTP boundary (Phase 4 / Task 4.10).
 *
 * ControllerTestTrait bypasses route filters; Service authorization still applies.
 *
 * @internal
 */
final class ContentTrashHttpBoundaryTest extends CIUnitTestCase
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

    protected function tearDown(): void
    {
        Services::resetSingle('auth');
        parent::tearDown();
    }

    public function testPageTrashListShowsRestoreAndPermanentDelete(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createAndTrashPage($admin, 'Trash List', 'trash-list');

        $this->injectAuth($admin);
        $result = $this->getIndex(PageController::class, ['status' => 'TRASH']);
        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Restore', $body);
        $this->assertStringContainsString('Permanent Delete', $body);
        $this->assertStringContainsString('admin/pages/' . $id . '/restore', $body);
        $this->assertStringContainsString('admin/pages/' . $id . '/permanent-delete', $body);
    }

    public function testPageRestorePostReturnsDraft(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createAndTrashPage($admin, 'Restore Me', 'restore-me');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'restore', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages/' . $id . '/edit');
        $this->assertSame('Page restored.', session()->getFlashdata('success'));

        $restored = $pages->findById($id);
        $this->assertNotNull($restored);
        $this->assertSame(PageStatus::Draft->value, $restored->status);
    }

    public function testPagePermanentDeletePostSucceeds(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createAndTrashPage($admin, 'Gone', 'gone-ui');
        $lock  = (int) $pages->findById($id)?->lock_version;
        $revBefore = db_connect()->table('revisions')
            ->where('resource_type', RevisionResourceType::Page->value)
            ->where('resource_id', $id)
            ->countAllResults();

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'permanentDelete', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages?status=TRASH');
        $this->assertSame('Page permanently deleted.', session()->getFlashdata('success'));
        $this->assertNull($pages->findById($id));
        $this->assertSame($revBefore, db_connect()->table('revisions')
            ->where('resource_type', RevisionResourceType::Page->value)
            ->where('resource_id', $id)
            ->countAllResults());
    }

    public function testUnauthorizedPageRestoreDenied(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createAndTrashPage($admin, 'No Restore', 'no-restore');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $denied = $this->actorWith(['page.edit']);
        $this->injectAuth($denied);
        $result = $this->postToController(PageController::class, 'restore', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages');
        $this->assertNotNull(session()->getFlashdata('error'));
        $this->assertSame(PageStatus::Trash->value, $pages->findById($id)?->status);
    }

    public function testUnauthorizedPagePermanentDeleteDenied(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createAndTrashPage($admin, 'No Delete', 'no-delete');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $denied = $this->actorWith(['page.restore', 'page.trash']);
        $this->injectAuth($denied);
        $result = $this->postToController(PageController::class, 'permanentDelete', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages');
        $error = strtolower((string) session()->getFlashdata('error'));
        $this->assertNotSame('', $error);
        $this->assertStringNotContainsString('select ', $error);
        $this->assertStringNotContainsString('sql', $error);
        $this->assertSame(PageStatus::Trash->value, $pages->findById($id)?->status);
    }

    public function testDependencyBlockedPagePermanentDeleteRemainsTrash(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPage($admin, 'Linked', 'linked-ui');
        $menus = Services::menuService(getShared: false);
        $this->assertSame([], $menus->create(new MenuItemWriteDto(
            location: MenuLocation::Primary->value,
            label: 'Linked',
            targetType: MenuTargetType::Page->value,
            targetId: $id,
            externalUrl: '',
            displayOrder: 0,
            isActive: true,
        )));
        $this->assertSame([], $pages->trash($id, $admin));
        $lock = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'permanentDelete', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages?status=TRASH');
        $error = (string) session()->getFlashdata('error');
        $this->assertNotSame('', $error);
        $this->assertStringContainsString('dependencies', strtolower($error));
        $this->assertStringNotContainsString('SELECT', $error);
        $this->assertStringNotContainsString('menu_items', $error);
        $this->assertSame(PageStatus::Trash->value, $pages->findById($id)?->status);
        $this->assertSame(0, db_connect()->table('audit_logs')
            ->where('resource_id', $id)
            ->where('event', 'PAGE_PERMANENTLY_DELETED')
            ->countAllResults());
    }

    public function testStalePageRestoreReturnsHttp409(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createAndTrashPage($admin, 'Stale Restore', 'stale-restore');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'restore', [$id], [
            'lock_version' => (string) ($lock - 1),
        ]);
        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame(PageStatus::Trash->value, $pages->findById($id)?->status);
    }

    public function testStalePagePermanentDeleteReturnsHttp409(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createAndTrashPage($admin, 'Stale Delete', 'stale-delete');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'permanentDelete', [$id], [
            'lock_version' => (string) ($lock - 1),
        ]);
        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame(PageStatus::Trash->value, $pages->findById($id)?->status);
    }

    public function testPostTrashListShowsRestoreAndPermanentDelete(): void
    {
        $admin = $this->adminPostActor();
        $id    = $this->createAndTrashPost($admin, 'Trash List Post', 'trash-list-post');

        $this->injectAuth($admin);
        $result = $this->getIndex(PostController::class, ['status' => 'TRASH']);
        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Restore', $body);
        $this->assertStringContainsString('Permanent Delete', $body);
        $this->assertStringContainsString('admin/posts/' . $id . '/restore', $body);
        $this->assertStringContainsString('admin/posts/' . $id . '/permanent-delete', $body);
    }

    public function testPostRestorePostReturnsDraft(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createAndTrashPost($admin, 'Restore Post', 'restore-post-ui');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'restore', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/posts/' . $id . '/edit');
        $this->assertSame(PostStatus::Draft->value, $posts->findById($id)?->status);
    }

    public function testPostPermanentDeletePostSucceeds(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createAndTrashPost($admin, 'Gone Post', 'gone-post-ui');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'permanentDelete', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/posts?status=TRASH');
        $this->assertNull($posts->findById($id));
    }

    public function testUnauthorizedPostRestoreDenied(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createAndTrashPost($admin, 'No Restore Post', 'no-restore-post');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($this->actorWith(['post.create']));
        $result = $this->postToController(PostController::class, 'restore', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/posts');
        $this->assertSame(PostStatus::Trash->value, $posts->findById($id)?->status);
    }

    public function testUnauthorizedPostPermanentDeleteDenied(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createAndTrashPost($admin, 'No Delete Post', 'no-delete-post');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($this->actorWith(['post.restore']));
        $result = $this->postToController(PostController::class, 'permanentDelete', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/posts');
        $this->assertSame(PostStatus::Trash->value, $posts->findById($id)?->status);
    }

    public function testStalePostRestoreReturnsHttp409(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createAndTrashPost($admin, 'Stale Post Restore', 'stale-post-restore');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'restore', [$id], [
            'lock_version' => (string) ($lock - 1),
        ]);
        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame(PostStatus::Trash->value, $posts->findById($id)?->status);
    }

    public function testStalePostPermanentDeleteReturnsHttp409(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createAndTrashPost($admin, 'Stale Post Delete', 'stale-post-delete');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'permanentDelete', [$id], [
            'lock_version' => (string) ($lock - 1),
        ]);
        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame(PostStatus::Trash->value, $posts->findById($id)?->status);
    }

    /**
     * @param list<string> $permissions
     */
    private function actorWith(array $permissions): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $p): bool => in_array($p, $permissions, true),
        );
        $user->id = 1;

        return $user;
    }

    private function adminPageActor(): User
    {
        return $this->actorWith([
            'page.create',
            'page.edit',
            'page.trash',
            'page.restore',
            'content.permanent_delete',
        ]);
    }

    private function adminPostActor(): User
    {
        return $this->actorWith([
            'post.create',
            'post.edit_any',
            'post.trash',
            'post.restore',
            'content.permanent_delete',
        ]);
    }

    private function injectAuth(User $user): void
    {
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['user', 'id', 'setAuthenticator'])
            ->getMock();
        $auth->method('setAuthenticator')->willReturnSelf();
        $auth->method('user')->willReturn($user);
        $auth->method('id')->willReturn(1);

        Services::injectMock('auth', $auth);
    }

    private function createPage(User $actor, string $title, string $slug): int
    {
        $pages = Services::pageService(getShared: false);
        $this->assertSame([], $pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        ), $actor));

        return (int) $pages->listActive()[0]['page']->id;
    }

    private function createAndTrashPage(User $actor, string $title, string $slug): int
    {
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPage($actor, $title, $slug);
        $this->assertSame([], $pages->trash($id, $actor));

        return $id;
    }

    private function createAndTrashPost(User $actor, string $title, string $slug): int
    {
        $posts = Services::postService(getShared: false);
        $this->assertSame([], $posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Author',
            createdBy: 1,
        ), $actor));
        $id = (int) $posts->listActive($actor)[0]['post']->id;
        $this->assertSame([], $posts->trash($id, $actor));

        return $id;
    }

    /**
     * @param array<string, string> $query
     */
    private function getIndex(string $controllerClass, array $query): TestResponse
    {
        /** @var IncomingRequest $request */
        $request = service('incomingrequest', null, false);
        $request->setGlobal('get', $query);
        $request->setMethod('get');

        $uri = 'http://example.com/admin/' . ($controllerClass === PageController::class ? 'pages' : 'posts');
        if ($query !== []) {
            $uri .= '?' . http_build_query($query);
        }

        return $this->withUri($uri)
            ->withRequest($request)
            ->controller($controllerClass)
            ->execute('index');
    }

    /**
     * @param list<int>            $params
     * @param array<string, mixed> $post
     */
    private function postToController(
        string $controllerClass,
        string $method,
        array $params,
        array $post,
    ): TestResponse {
        /** @var IncomingRequest $request */
        $request = service('incomingrequest', null, false);
        $request->setGlobal('post', $post);
        $request->setMethod('post');

        return $this->withRequest($request)
            ->controller($controllerClass)
            ->execute($method, ...$params);
    }
}
