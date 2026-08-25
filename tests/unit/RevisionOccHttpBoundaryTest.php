<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\PageController;
use App\Controllers\Admin\PostController;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
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
 * OCC HTTP 409 + revision history/restore controller boundary (Phase 4 / Task 4.9C).
 *
 * Uses ControllerTestTrait (no route filters) because the SQLite test DB does not
 * migrate Shield auth tables required for FeatureTestTrait login.
 *
 * @internal
 */
final class RevisionOccHttpBoundaryTest extends CIUnitTestCase
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

    public function testStalePageUpdateReturnsHttp409WithoutMutation(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create', 'page.restore']);
        $pages  = Services::pageService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Occ Page',
            slug: 'occ-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'A'],
        ), $editor));

        $id = (int) $pages->listActive()[0]['page']->id;
        $this->assertSame([], $pages->update($id, new PageWriteDto(
            title: 'Occ Page',
            slug: 'occ-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'B'],
        ), $editor, 1));

        $beforeLock  = (int) $pages->findById($id)?->lock_version;
        $revBefore   = count(Services::revisionService(getShared: false)
            ->listEditorial(RevisionResourceType::Page, $id));
        $auditBefore = db_connect()->table('audit_logs')
            ->where('resource_type', 'page')
            ->where('resource_id', $id)
            ->countAllResults();

        $this->injectAuth($editor);
        $result = $this->postToController(
            PageController::class,
            'update',
            [$id],
            [
                'title'        => 'Occ Page',
                'slug'         => 'occ-page',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'lock_version' => '1',
                'content'      => ['hero_title' => 'Stale'],
            ],
        );

        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame($beforeLock, (int) $pages->findById($id)?->lock_version);
        $this->assertSame(
            $revBefore,
            count(Services::revisionService(getShared: false)->listEditorial(RevisionResourceType::Page, $id)),
        );
        $this->assertSame(
            $auditBefore,
            db_connect()->table('audit_logs')
                ->where('resource_type', 'page')
                ->where('resource_id', $id)
                ->countAllResults(),
        );

        $payload = (string) $pages->listActive()[0]['translation']->content_payload;
        $this->assertStringContainsString('B', $payload);
        $this->assertStringNotContainsString('Stale', $payload);
    }

    public function testStalePostUpdateReturnsHttp409WithoutMutation(): void
    {
        $editor = $this->actorWith([
            'post.create',
            'post.edit_any',
            'post.edit_own',
            'post.restore',
        ]);
        $posts = Services::postService(getShared: false);

        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Occ Post',
            slug: 'occ-post',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>One</p>'],
            createdBy: 1,
        ), $editor));

        $id = (int) $posts->listActive($editor)[0]['post']->id;
        $this->assertSame([], $posts->update($id, new PostWriteDto(
            title: 'Occ Post',
            slug: 'occ-post',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>Two</p>'],
            createdBy: 1,
        ), $editor, 1));

        $beforeLock  = (int) $posts->findById($id)?->lock_version;
        $revBefore   = count(Services::revisionService(getShared: false)
            ->listEditorial(RevisionResourceType::Post, $id));
        $auditBefore = db_connect()->table('audit_logs')
            ->where('resource_type', 'post')
            ->where('resource_id', $id)
            ->countAllResults();

        $this->injectAuth($editor);
        $result = $this->postToController(
            PostController::class,
            'update',
            [$id],
            [
                'title'         => 'Occ Post',
                'slug'          => 'occ-post',
                'locale'        => 'id',
                'manual_author' => 'A',
                'lock_version'  => '1',
                'content'       => ['body' => '<p>Stale</p>'],
            ],
        );

        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame($beforeLock, (int) $posts->findById($id)?->lock_version);
        $this->assertSame(
            $revBefore,
            count(Services::revisionService(getShared: false)->listEditorial(RevisionResourceType::Post, $id)),
        );
        $this->assertSame(
            $auditBefore,
            db_connect()->table('audit_logs')
                ->where('resource_type', 'post')
                ->where('resource_id', $id)
                ->countAllResults(),
        );
    }

    public function testHtmxStalePageUpdateKeeps409Status(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create']);
        $pages  = Services::pageService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Htmx Occ',
            slug: 'htmx-occ',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'A'],
        ), $editor));
        $id = (int) $pages->listActive()[0]['page']->id;
        $this->assertSame([], $pages->update($id, new PageWriteDto(
            title: 'Htmx Occ',
            slug: 'htmx-occ',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'B'],
        ), $editor, 1));

        $this->injectAuth($editor);
        $result = $this->postToController(
            PageController::class,
            'update',
            [$id],
            [
                'title'        => 'Htmx Occ',
                'slug'         => 'htmx-occ',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'lock_version' => '1',
                'content'      => ['hero_title' => 'Stale'],
            ],
            ['HX-Request' => 'true'],
        );

        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertSame('', $result->response()->getHeaderLine('HX-Redirect'));
    }

    public function testPageRevisionHistoryAndRestoreAuthorized(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create', 'page.restore', 'page.publish']);
        $pages  = Services::pageService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Hist Page',
            slug: 'hist-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'One'],
        ), $editor));
        $id = (int) $pages->listActive()[0]['page']->id;
        $this->assertSame([], $pages->update($id, new PageWriteDto(
            title: 'Hist Page',
            slug: 'hist-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'Two'],
        ), $editor, 1));

        $history = Services::revisionService(getShared: false)
            ->listEditorialHistory(RevisionResourceType::Page, $id);
        $this->assertGreaterThanOrEqual(2, count($history));
        $this->assertFalse($history[0]['is_autosave']);

        $this->injectAuth($editor);
        $list = $this->withUri('http://example.com/admin/pages/' . $id . '/revisions')
            ->controller(PageController::class)
            ->execute('revisions', $id);
        $list->assertOK();
        $body = (string) $list->response()->getBody();
        $this->assertStringContainsString('Revisions:', $body);
        $this->assertStringContainsString('Manual', $body);
        $this->assertStringContainsString('Restore', $body);

        $sourceId = $history[count($history) - 1]['id'];
        $lock     = (int) $pages->findById($id)?->lock_version;

        $restore = $this->postToController(
            PageController::class,
            'restoreRevision',
            [$id, $sourceId],
            ['lock_version' => (string) $lock],
        );
        $restore->assertRedirectTo('/admin/pages/' . $id . '/edit');

        $payload = (string) $pages->listActive()[0]['translation']->content_payload;
        $this->assertStringContainsString('One', $payload);
    }

    public function testPostRevisionHistoryShowsRestoreForEditor(): void
    {
        $editor = $this->actorWith(['post.create', 'post.edit_any', 'post.restore']);
        $posts  = Services::postService(getShared: false);

        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Hist Post',
            slug: 'hist-post',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>One</p>'],
            createdBy: 1,
        ), $editor));
        $id = (int) $posts->listActive($editor)[0]['post']->id;

        $this->injectAuth($editor);
        $list = $this->withUri('http://example.com/admin/posts/' . $id . '/revisions')
            ->controller(PostController::class)
            ->execute('revisions', $id);
        $list->assertOK();
        $this->assertStringContainsString('Revisions:', (string) $list->response()->getBody());
        $this->assertStringContainsString('Restore', (string) $list->response()->getBody());
    }

    public function testWrongResourceRevisionRestoreRejected(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create', 'page.restore', 'post.create', 'post.edit_any']);
        $pages  = Services::pageService(getShared: false);
        $posts  = Services::postService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'P1',
            slug: 'p1-wrong',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'A'],
        ), $editor));
        $pageId = (int) $pages->listActive()[0]['page']->id;

        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Po1',
            slug: 'po1-wrong',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>x</p>'],
            createdBy: 1,
        ), $editor));
        $postId = (int) $posts->listActive($editor)[0]['post']->id;

        $postRev = Services::revisionService(getShared: false)
            ->listEditorial(RevisionResourceType::Post, $postId)[0];

        $this->injectAuth($editor);
        $result = $this->postToController(
            PageController::class,
            'restoreRevision',
            [$pageId, (int) $postRev->id],
            ['lock_version' => (string) (int) $pages->findById($pageId)?->lock_version],
        );
        $result->assertRedirect();
        $this->assertStringContainsString(
            'revision',
            strtolower((string) session()->getFlashdata('error')),
        );
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

    /**
     * @param list<int>             $params
     * @param array<string, mixed>  $post
     * @param array<string, string> $headers
     */
    private function postToController(
        string $controllerClass,
        string $method,
        array $params,
        array $post,
        array $headers = [],
    ): TestResponse {
        /** @var IncomingRequest $request */
        $request = service('incomingrequest', null, false);
        $request->setGlobal('post', $post);
        $request->setMethod('post');
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }

        return $this->withRequest($request)
            ->controller($controllerClass)
            ->execute($method, ...$params);
    }
}
