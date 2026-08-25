<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\PageController;
use App\Controllers\Admin\PostController;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Page/Post Archive HTTP + edit-form visibility (Phase 4 / Task 4.11B / ADR-020).
 *
 * ControllerTestTrait bypasses route filters; Service authorization still applies.
 *
 * @internal
 */
final class ContentArchiveHttpBoundaryTest extends CIUnitTestCase
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

    public function testPageEditShowsArchiveWhenPublishedAndAuthorized(): void
    {
        $admin = $this->adminPageActor();
        $id    = $this->createPublishedPage($admin, 'Show Arch', 'show-arch-page');

        $this->injectAuth($admin);
        $published = $this->getEdit(PageController::class, $id);
        $published->assertOK();
        $body = (string) $published->response()->getBody();
        $this->assertStringContainsString('admin/pages/' . $id . '/archive', $body);
        $this->assertStringContainsString('Archive', $body);
        $this->assertStringNotContainsString('Unarchive', $body);
        $this->assertStringContainsString('name="lock_version"', $body);
    }

    public function testPageEditShowsPublishForArchivedWhenAuthorized(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPublishedPage($admin, 'Show Repub', 'show-repub-page');
        $this->assertSame([], $pages->archive($id, $admin));

        $this->injectAuth($admin);
        $archived = $this->getEdit(PageController::class, $id);
        $archived->assertOK();
        $archivedBody = (string) $archived->response()->getBody();
        $this->assertStringNotContainsString('admin/pages/' . $id . '/archive', $archivedBody);
        $this->assertStringContainsString('admin/pages/' . $id . '/publish', $archivedBody);
        $this->assertStringContainsString('Publish', $archivedBody);
        $this->assertStringNotContainsString('Unarchive', $archivedBody);
    }

    public function testPageEditHidesArchiveForDraft(): void
    {
        $admin = $this->adminPageActor();
        $id    = $this->createPage($admin, 'Draft Arch Hide', 'draft-arch-hide');
        $this->injectAuth($admin);
        $result = $this->getEdit(PageController::class, $id);
        $result->assertOK();
        $this->assertStringNotContainsString('/archive', (string) $result->response()->getBody());
    }

    public function testPageArchivePostSucceeds(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPublishedPage($admin, 'Http Arch', 'http-arch-page');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'archive', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages/' . $id . '/edit');
        $this->assertSame('Page archived.', session()->getFlashdata('success'));
        $this->assertSame(PageStatus::Archived->value, $pages->findById($id)?->status);
    }

    public function testUnauthorizedPageArchiveDenied(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPublishedPage($admin, 'No Http Arch', 'no-http-arch-page');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($this->actorWith(['page.edit', 'page.publish']));
        $result = $this->postToController(PageController::class, 'archive', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages');
        $this->assertNotNull(session()->getFlashdata('error'));
        $this->assertSame(PageStatus::Published->value, $pages->findById($id)?->status);
    }

    public function testStalePageArchiveReturnsHttp409(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPublishedPage($admin, 'Stale Http Arch', 'stale-http-arch-page');
        $lock  = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'archive', [$id], [
            'lock_version' => (string) ($lock - 1),
        ]);
        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame(PageStatus::Published->value, $pages->findById($id)?->status);
    }

    public function testPageRepublishFromArchiveViaPublish(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPublishedPage($admin, 'Http Repub', 'http-repub-page');
        $this->assertSame([], $pages->archive($id, $admin));
        $lock = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'publish', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/pages/' . $id . '/edit');
        $this->assertSame(PageStatus::Published->value, $pages->findById($id)?->status);
    }

    public function testStalePageRepublishReturnsHttp409(): void
    {
        $admin = $this->adminPageActor();
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPublishedPage($admin, 'Stale Http Repub', 'stale-http-repub-page');
        $this->assertSame([], $pages->archive($id, $admin));
        $lock = (int) $pages->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PageController::class, 'publish', [$id], [
            'lock_version' => (string) ($lock - 1),
        ]);
        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame(PageStatus::Archived->value, $pages->findById($id)?->status);
    }

    public function testPostEditShowsArchiveWhenPublishedAndAuthorized(): void
    {
        $admin = $this->adminPostActor();
        $id    = $this->createPublishedPost($admin, 'Show Arch Post', 'show-arch-post');

        $this->injectAuth($admin);
        $published = $this->getEdit(PostController::class, $id);
        $published->assertOK();
        $body = (string) $published->response()->getBody();
        $this->assertStringContainsString('admin/posts/' . $id . '/archive', $body);
        $this->assertStringContainsString('Archive', $body);
        $this->assertStringNotContainsString('Unarchive', $body);
    }

    public function testPostEditShowsPublishForArchivedWhenAuthorized(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createPublishedPost($admin, 'Show Repub Post', 'show-repub-post');
        $this->assertSame([], $posts->archive($id, $admin));

        $this->injectAuth($admin);
        $archived = $this->getEdit(PostController::class, $id);
        $archived->assertOK();
        $archivedBody = (string) $archived->response()->getBody();
        $this->assertStringNotContainsString('admin/posts/' . $id . '/archive', $archivedBody);
        $this->assertStringContainsString('admin/posts/' . $id . '/publish', $archivedBody);
        $this->assertStringNotContainsString('Unarchive', $archivedBody);
    }

    public function testPostArchivePostSucceeds(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createPublishedPost($admin, 'Http Arch Post', 'http-arch-post');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'archive', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/posts/' . $id . '/edit');
        $this->assertSame(PostStatus::Archived->value, $posts->findById($id)?->status);
    }

    public function testUnauthorizedPostArchiveDenied(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createPublishedPost($admin, 'No Http Arch Post', 'no-http-arch-post');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($this->actorWith(['post.edit_any', 'post.publish']));
        $result = $this->postToController(PostController::class, 'archive', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/posts');
        $this->assertSame(PostStatus::Published->value, $posts->findById($id)?->status);
    }

    public function testStalePostArchiveReturnsHttp409(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createPublishedPost($admin, 'Stale Http Arch Post', 'stale-http-arch-post');
        $lock  = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'archive', [$id], [
            'lock_version' => (string) ($lock - 1),
        ]);
        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame(PostStatus::Published->value, $posts->findById($id)?->status);
    }

    public function testPostRepublishFromArchiveViaPublish(): void
    {
        $admin = $this->adminPostActor();
        $posts = Services::postService(getShared: false);
        $id    = $this->createPublishedPost($admin, 'Http Repub Post', 'http-repub-post');
        $this->assertSame([], $posts->archive($id, $admin));
        $lock = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'publish', [$id], [
            'lock_version' => (string) $lock,
        ]);
        $result->assertRedirectTo('/admin/posts/' . $id . '/edit');
        $this->assertSame(PostStatus::Published->value, $posts->findById($id)?->status);
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
            'page.publish',
            'page.archive',
        ]);
    }

    private function adminPostActor(): User
    {
        return $this->actorWith([
            'post.create',
            'post.edit_any',
            'post.publish',
            'post.archive',
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
            contentPayload: ['body' => '<p>Hello</p>'],
        ), $actor));

        return (int) $pages->listActive()[0]['page']->id;
    }

    private function createPublishedPage(User $actor, string $title, string $slug): int
    {
        $pages = Services::pageService(getShared: false);
        $id    = $this->createPage($actor, $title, $slug);
        $this->assertSame([], $pages->publish($id, $actor));

        return $id;
    }

    private function createPublishedPost(User $actor, string $title, string $slug): int
    {
        $posts = Services::postService(getShared: false);
        $this->assertSame([], $posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Author',
            contentPayload: ['body' => '<p>Body</p>'],
            createdBy: 1,
        ), $actor));
        $id = (int) $posts->listActive($actor)[0]['post']->id;
        $this->assertSame([], $posts->publish($id, $actor));

        return $id;
    }

    private function getEdit(string $controllerClass, int $id): TestResponse
    {
        /** @var IncomingRequest $request */
        $request = service('incomingrequest', null, false);
        $request->setMethod('get');
        $segment = $controllerClass === PageController::class ? 'pages' : 'posts';

        return $this->withUri('http://example.com/admin/' . $segment . '/' . $id . '/edit')
            ->withRequest($request)
            ->controller($controllerClass)
            ->execute('edit', $id);
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
