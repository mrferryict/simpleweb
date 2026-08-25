<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Admin\PostController;
use App\Dtos\PostWriteDto;
use App\Enums\PostStatus;
use App\Services\PostService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Auth;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\ControllerTestTrait;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Phase 7 / Task 7.2 — Post Admin SEO edit surface (ADR-024 §5 item 6).
 *
 * @internal
 */
final class PostAdminSeoTest extends CIUnitTestCase
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

    private PostService $posts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->posts = Services::postService(getShared: false);
    }

    protected function tearDown(): void
    {
        Services::resetSingle('auth');
        parent::tearDown();
    }

    public function testPostServicePersistsSeoFields(): void
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: 'SEO Post',
            slug: 'seo-post',
            locale: 'id',
            manualAuthor: 'Author',
            metaTitle: 'Stored Meta Title',
            metaDescription: 'Stored meta description',
            canonicalUrl: 'https://example.com/news/seo-post',
            ogImageId: 7,
        )));

        $translation = $this->posts->listActive()[0]['translation'];
        $this->assertSame('Stored Meta Title', $translation->meta_title);
        $this->assertSame('Stored meta description', $translation->meta_description);
        $this->assertSame('https://example.com/news/seo-post', $translation->canonical_url);
        $this->assertSame(7, (int) $translation->og_image_id);
    }

    public function testPostUpdateViaControllerPersistsSeoFields(): void
    {
        $admin = $this->adminPostActor();
        $id    = $this->createDraftPost($admin, 'Draft SEO', 'draft-seo', 1);

        $this->injectAuth($admin);
        $result = $this->postToController(PostController::class, 'update', [$id], [
            'title'            => 'Draft SEO',
            'slug'             => 'draft-seo',
            'locale'           => 'id',
            'manual_author'    => 'Author',
            'meta_title'       => 'Controller Meta Title',
            'meta_description' => 'Controller meta description',
            'canonical_url'    => 'https://example.com/news/draft-seo',
            'og_image_id'      => '12',
            'lock_version'     => '1',
            'content'          => ['body' => '<p>Body</p>'],
        ]);
        $result->assertRedirectTo('/admin/posts');

        $translation = $this->posts->findEditable($id, $admin)['translation'];
        $this->assertSame('Controller Meta Title', $translation->meta_title);
        $this->assertSame('Controller meta description', $translation->meta_description);
        $this->assertSame('https://example.com/news/draft-seo', $translation->canonical_url);
        $this->assertSame(12, (int) $translation->og_image_id);
    }

    public function testPostEditLoadsPersistedSeoFields(): void
    {
        $admin = $this->adminPostActor();
        $id    = $this->createDraftPost($admin, 'Loaded SEO', 'loaded-seo', 1);
        $this->assertSame([], $this->posts->update($id, new PostWriteDto(
            title: 'Loaded SEO',
            slug: 'loaded-seo',
            locale: 'id',
            manualAuthor: 'Author',
            metaTitle: 'Loaded Meta Title',
            metaDescription: 'Loaded meta description',
            canonicalUrl: 'https://example.com/news/loaded-seo',
            ogImageId: 99,
        ), $admin));

        $this->injectAuth($admin);
        $result = $this->getEdit($id);
        $result->assertOK();
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('name="meta_title"', $body);
        $this->assertStringContainsString('Loaded&#x20;Meta&#x20;Title', $body);
        $this->assertStringContainsString('Loaded meta description', $body);
        $this->assertStringContainsString('loaded-seo', $body);
        $this->assertStringContainsString('value="99"', $body);
    }

    public function testPostOwnershipBlocksSeoUpdateFromOtherContributor(): void
    {
        $owner = $this->actorWith(['post.create', 'post.edit_own'], 10);
        $other = $this->actorWith(['post.create', 'post.edit_own'], 99);
        $id    = $this->createDraftPost($owner, 'Owned SEO', 'owned-seo', 10);
        $this->assertSame(10, (int) $this->posts->findById($id)?->created_by);

        $errors = $this->posts->update($id, new PostWriteDto(
            title: 'Owned SEO',
            slug: 'owned-seo',
            locale: 'id',
            manualAuthor: 'Author',
            metaTitle: 'Hijacked Title',
            metaDescription: 'Hijacked description',
        ), $other);
        $this->assertArrayHasKey('_forbidden', $errors);

        $editable = $this->posts->findEditable($id);
        $this->assertNotNull($editable);
        $translation = $editable['translation'];
        $this->assertNull($translation->meta_title);
        $this->assertNull($translation->meta_description);
    }

    public function testPostEditDeniedForNonOwner(): void
    {
        $owner = $this->actorWith(['post.create', 'post.edit_own'], 10);
        $other = $this->actorWith(['post.create', 'post.edit_own'], 99);
        $id    = $this->createDraftPost($owner, 'Owned SEO', 'owned-seo', 10);

        $this->injectAuth($other);
        $result = $this->getEdit($id);
        $result->assertRedirectTo('/admin/posts');
    }

    public function testPostFormIncludesCsrfField(): void
    {
        $html = view('admin/posts/form', [
            'mode'           => 'create',
            'item'           => [
                'title'             => '',
                'slug'              => '',
                'locale'            => 'id',
                'manual_author'     => '',
                'category_ids'      => [],
                'tag_ids'           => [],
                'featured_image_id' => null,
                'content_payload'   => [],
                'meta_title'        => '',
                'meta_description'  => '',
                'canonical_url'     => '',
                'og_image_id'       => '',
            ],
            'locales'        => ['id', 'en'],
            'categories'     => [],
            'tags'           => [],
            'errors'         => [],
            'formAction'     => site_url('admin/posts'),
            'contentSchema'  => $this->posts->contentSchema(),
            'contentPayload' => [],
        ]);

        $this->assertStringContainsString('csrf_test_name', $html);
    }

    public function testPublicPostSeoResolutionUnchangedAfterAdminSeoUpdate(): void
    {
        $admin = $this->adminPostActor();
        $id    = $this->createDraftPost($admin, 'Public SEO', 'public-seo', 1);
        $this->assertSame([], $this->posts->update($id, new PostWriteDto(
            title: 'Public SEO',
            slug: 'public-seo',
            locale: 'id',
            manualAuthor: 'Author',
            metaTitle: 'Public Meta Title',
            metaDescription: 'Public meta description',
        ), $admin));
        db_connect()->table('posts')->where('id', $id)->update([
            'status' => PostStatus::Published->value,
        ]);

        $view = $this->posts->findPublishedForPublic('public-seo', 'id');
        $this->assertNotNull($view);
        $this->assertSame('Public Meta Title', $view->metaTitle);
        $this->assertSame('Public meta description', $view->metaDescription);

        $seo = Services::seoService(getShared: false)->forPostView($view);
        $this->assertStringContainsString('Public Meta Title', $seo->documentTitle);
        $this->assertSame('Public meta description', $seo->metaDescription);
    }

    /**
     * @param list<string> $permissions
     */
    private function actorWith(array $permissions, int $userId = 1): User
    {
        $user = $this->getMockBuilder(User::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['can'])
            ->getMock();
        $user->method('can')->willReturnCallback(
            static fn (string $p): bool => in_array($p, $permissions, true),
        );
        $user->id = $userId;

        return $user;
    }

    private function adminPostActor(): User
    {
        return $this->actorWith([
            'post.create',
            'post.edit_any',
            'post.publish',
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
        $auth->method('id')->willReturn((int) $user->id);

        Services::injectMock('auth', $auth);
    }

    private function createDraftPost(User $actor, string $title, string $slug, int $createdBy): int
    {
        $this->assertSame([], $this->posts->create(new PostWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            manualAuthor: 'Author',
            contentPayload: ['body' => '<p>Body</p>'],
            createdBy: $createdBy,
        ), $actor));

        return (int) $this->posts->listActive()[0]['post']->id;
    }

    private function getEdit(int $id): TestResponse
    {
        /** @var IncomingRequest $request */
        $request = service('incomingrequest', null, false);
        $request->setMethod('get');

        return $this->withUri('http://example.com/admin/posts/' . $id . '/edit')
            ->withRequest($request)
            ->controller(PostController::class)
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
