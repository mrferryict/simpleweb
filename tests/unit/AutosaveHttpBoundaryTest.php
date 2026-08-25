<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\PageController;
use App\Controllers\Admin\PostController;
use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
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
 * Autosave HTMX controller boundary (Phase 4 / Task 4.9D / ADR-019).
 *
 * @internal
 */
final class AutosaveHttpBoundaryTest extends CIUnitTestCase
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

    public function testPageAutosaveCreatesAutosaveRevisionWithoutMutatingLive(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create']);
        $pages  = Services::pageService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Live Page',
            slug: 'live-page-as',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'Live'],
        ), $editor));
        $id = (int) $pages->listActive()[0]['page']->id;

        $beforeLock   = (int) $pages->findById($id)?->lock_version;
        $beforeStatus = (string) $pages->findById($id)?->status;
        $editorialBefore = count(Services::revisionService(getShared: false)
            ->listEditorial(RevisionResourceType::Page, $id));
        $auditBefore = db_connect()->table('audit_logs')
            ->where('resource_type', 'page')
            ->where('resource_id', $id)
            ->countAllResults();

        $this->injectAuth($editor);
        $result = $this->postToController(
            PageController::class,
            'autosave',
            [$id],
            [
                'title'        => 'Draft Title',
                'slug'         => 'live-page-as',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'lock_version' => (string) $beforeLock,
                'content'      => ['hero_title' => 'Drafty'],
            ],
            ['HX-Request' => 'true'],
        );

        $this->assertSame(200, $result->response()->getStatusCode());
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('Draft saved', $body);
        $this->assertStringContainsString('data-autosave-state="success"', $body);

        $page = $pages->findById($id);
        $this->assertNotNull($page);
        $this->assertSame($beforeLock, (int) $page->lock_version);
        $this->assertSame($beforeStatus, (string) $page->status);
        $this->assertSame(PageStatus::Draft->value, (string) $page->status);

        $payload = (string) $pages->listActive()[0]['translation']->content_payload;
        $this->assertStringContainsString('Live', $payload);
        $this->assertStringNotContainsString('Drafty', $payload);

        $this->assertSame(
            $editorialBefore,
            count(Services::revisionService(getShared: false)->listEditorial(RevisionResourceType::Page, $id)),
        );
        $this->assertSame(
            $auditBefore,
            db_connect()->table('audit_logs')
                ->where('resource_type', 'page')
                ->where('resource_id', $id)
                ->countAllResults(),
        );

        $autosave = Services::revisionService(getShared: false)
            ->findLatestAutosave(RevisionResourceType::Page, $id);
        $this->assertNotNull($autosave);
        $this->assertSame(1, (int) $autosave->is_autosave);
        $snap = $autosave->decodedSnapshot();
        $this->assertIsArray($snap);
        $this->assertSame('Drafty', $snap['translations']['id']['content_payload']['hero_title'] ?? null);
    }

    public function testPostAutosaveCreatesAutosaveWithoutPublishing(): void
    {
        $editor = $this->actorWith(['post.create', 'post.edit_any', 'post.edit_own']);
        $posts  = Services::postService(getShared: false);

        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Live Post',
            slug: 'live-post-as',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>Live</p>'],
            createdBy: 1,
        ), $editor));
        $id = (int) $posts->listActive($editor)[0]['post']->id;
        $beforeLock = (int) $posts->findById($id)?->lock_version;

        $this->injectAuth($editor);
        $result = $this->postToController(
            PostController::class,
            'autosave',
            [$id],
            [
                'title'         => 'Draft Post',
                'slug'          => 'live-post-as',
                'locale'        => 'id',
                'manual_author' => 'A',
                'lock_version'  => (string) $beforeLock,
                'content'       => ['body' => '<p>Drafty</p>'],
            ],
            ['HX-Request' => 'true'],
        );

        $this->assertSame(200, $result->response()->getStatusCode());

        $post = $posts->findById($id);
        $this->assertNotNull($post);
        $this->assertSame($beforeLock, (int) $post->lock_version);
        $this->assertSame(PostStatus::Draft->value, (string) $post->status);

        $payload = (string) $posts->listActive($editor)[0]['translation']->content_payload;
        $this->assertStringContainsString('Live', $payload);
        $this->assertStringNotContainsString('Drafty', $payload);

        $autosave = Services::revisionService(getShared: false)
            ->findLatestAutosave(RevisionResourceType::Post, $id);
        $this->assertNotNull($autosave);
        $this->assertSame(1, (int) $autosave->is_autosave);
    }

    public function testPostAutosaveForbiddenForOtherUsersPost(): void
    {
        $owner = $this->actorWith(['post.create', 'post.edit_own'], 10);
        $other = $this->actorWith(['post.create', 'post.edit_own'], 99);
        $posts = Services::postService(getShared: false);

        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Owned',
            slug: 'owned-as',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>x</p>'],
            createdBy: 10,
        ), $owner));
        $created = $posts->findById(1) ?? $posts->listActive()[0]['post'] ?? null;
        // Prefer direct lookup of the only non-trash post.
        $all = $posts->listActive();
        $this->assertNotSame([], $all);
        $id = (int) $all[0]['post']->id;
        $this->assertSame(10, (int) $all[0]['post']->created_by);

        $this->injectAuth($other);
        $result = $this->postToController(
            PostController::class,
            'autosave',
            [$id],
            [
                'title'         => 'Owned',
                'slug'          => 'owned-as',
                'locale'        => 'id',
                'manual_author' => 'A',
                'lock_version'  => '1',
                'content'       => ['body' => '<p>steal</p>'],
            ],
            ['HX-Request' => 'true'],
        );

        $this->assertSame(403, $result->response()->getStatusCode());
        $this->assertNull(
            Services::revisionService(getShared: false)->findLatestAutosave(RevisionResourceType::Post, $id),
        );
    }

    public function testPageAutosaveStaleLockVersionReturns409(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create']);
        $pages  = Services::pageService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Occ As',
            slug: 'occ-as',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'A'],
        ), $editor));
        $id = (int) $pages->listActive()[0]['page']->id;
        $this->assertSame([], $pages->update($id, new PageWriteDto(
            title: 'Occ As',
            slug: 'occ-as',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'B'],
        ), $editor, 1));

        $lock = (int) $pages->findById($id)?->lock_version;
        $this->injectAuth($editor);
        $result = $this->postToController(
            PageController::class,
            'autosave',
            [$id],
            [
                'title'        => 'Occ As',
                'slug'         => 'occ-as',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'lock_version' => '1',
                'content'      => ['hero_title' => 'Stale'],
            ],
            ['HX-Request' => 'true'],
        );

        $this->assertSame(409, $result->response()->getStatusCode());
        $this->assertStringContainsString('data-autosave-state="conflict"', (string) $result->response()->getBody());
        $this->assertSame($lock, (int) $pages->findById($id)?->lock_version);
        $this->assertNull(
            Services::revisionService(getShared: false)->findLatestAutosave(RevisionResourceType::Page, $id),
        );
    }

    public function testPageAutosaveSanitizesRichTextInSnapshot(): void
    {
        $editor = $this->actorWith(['page.edit', 'page.create']);
        $pages  = Services::pageService(getShared: false);

        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Sanitize As',
            slug: 'sanitize-as',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['body' => '<p>Safe</p>'],
        ), $editor));
        $id = (int) $pages->listActive()[0]['page']->id;

        $this->injectAuth($editor);
        $result = $this->postToController(
            PageController::class,
            'autosave',
            [$id],
            [
                'title'        => 'Sanitize As',
                'slug'         => 'sanitize-as',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'lock_version' => '1',
                'content'      => ['body' => '<p>Ok</p><script>alert(1)</script>'],
            ],
            ['HX-Request' => 'true'],
        );

        $this->assertSame(200, $result->response()->getStatusCode());
        $autosave = Services::revisionService(getShared: false)
            ->findLatestAutosave(RevisionResourceType::Page, $id);
        $this->assertNotNull($autosave);
        $snap = $autosave->decodedSnapshot();
        $this->assertIsArray($snap);
        $body = (string) ($snap['translations']['id']['content_payload']['body'] ?? '');
        $this->assertStringNotContainsString('<script>', $body);
        $this->assertStringContainsString('Ok', $body);
    }

    public function testAutosaveSharesRevisionNumberSequence(): void
    {
        $editor = $this->actorWith(['post.create', 'post.edit_any']);
        $posts  = Services::postService(getShared: false);

        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Seq',
            slug: 'seq-as',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>a</p>'],
            createdBy: 1,
        ), $editor));
        $id = (int) $posts->listActive($editor)[0]['post']->id;

        // create = editorial #1
        $this->assertSame([], $posts->autosave($id, new PostWriteDto(
            title: 'Seq',
            slug: 'seq-as',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>b</p>'],
            createdBy: 1,
        ), $editor, 1));

        $autosave = Services::revisionService(getShared: false)
            ->findLatestAutosave(RevisionResourceType::Post, $id);
        $this->assertNotNull($autosave);
        $this->assertSame(2, (int) $autosave->revision_number);
        $this->assertSame(1, count(Services::revisionService(getShared: false)
            ->listEditorial(RevisionResourceType::Post, $id)));
    }

    public function testPageAutosaveForbiddenWithoutEditPermission(): void
    {
        $creator = $this->actorWith(['page.edit', 'page.create']);
        $pages   = Services::pageService(getShared: false);
        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'No Edit',
            slug: 'no-edit-as',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['hero_title' => 'A'],
        ), $creator));
        $id = (int) $pages->listActive()[0]['page']->id;

        $denied = $this->actorWith(['page.create']);
        $this->injectAuth($denied);
        $result = $this->postToController(
            PageController::class,
            'autosave',
            [$id],
            [
                'title'        => 'No Edit',
                'slug'         => 'no-edit-as',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'lock_version' => '1',
                'content'      => ['hero_title' => 'B'],
            ],
            ['HX-Request' => 'true'],
        );

        $this->assertSame(403, $result->response()->getStatusCode());
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
