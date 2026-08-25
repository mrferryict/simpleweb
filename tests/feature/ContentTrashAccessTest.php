<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Dtos\PageWriteDto;
use App\Dtos\PostWriteDto;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Trash restore / permanent-delete HTTP access boundaries (Phase 4 / Task 4.10).
 *
 * @internal
 */
final class ContentTrashAccessTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;

    public function testTrashRoutesRequireAuthentication(): void
    {
        $this->get('admin/pages?status=TRASH')->assertRedirect();
        $this->get('admin/posts?status=TRASH')->assertRedirect();
    }

    public function testPageRestoreRequiresCsrf(): void
    {
        $this->assertPostRejectedWithoutCsrf('admin/pages/1/restore', ['lock_version' => '1']);
    }

    public function testPagePermanentDeleteRequiresCsrf(): void
    {
        $this->assertPostRejectedWithoutCsrf('admin/pages/1/permanent-delete', ['lock_version' => '1']);
    }

    public function testPostRestoreRequiresCsrf(): void
    {
        $this->assertPostRejectedWithoutCsrf('admin/posts/1/restore', ['lock_version' => '1']);
    }

    public function testPostPermanentDeleteRequiresCsrf(): void
    {
        $this->assertPostRejectedWithoutCsrf('admin/posts/1/permanent-delete', ['lock_version' => '1']);
    }

    public function testGetPagePermanentDeleteDoesNotMutate(): void
    {
        $pages = Services::pageService(getShared: false);
        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Keep',
            slug: 'keep-get',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        )));
        $id = (int) $pages->listActive()[0]['page']->id;
        $this->assertSame([], $pages->trash($id));

        try {
            $result = $this->get('admin/pages/' . $id . '/permanent-delete');
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 404, 405], true));
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->assertSame(PageStatus::Trash->value, $pages->findById($id)?->status);
    }

    public function testGetPostPermanentDeleteDoesNotMutate(): void
    {
        $posts = Services::postService(getShared: false);
        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Keep Post',
            slug: 'keep-post-get',
            locale: 'id',
            manualAuthor: 'A',
        )));
        $id = (int) $posts->listActive()[0]['post']->id;
        $this->assertSame([], $posts->trash($id));

        try {
            $result = $this->get('admin/posts/' . $id . '/permanent-delete');
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 404, 405], true));
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->assertSame(PostStatus::Trash->value, $posts->findById($id)?->status);
    }

    public function testHtmxUnauthenticatedTrashListReturnsHxRedirect(): void
    {
        $result   = $this->withHeaders(['HX-Request' => 'true'])->get('admin/pages?status=TRASH');
        $response = $result->response();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/cp', $response->getHeaderLine('HX-Redirect'));
    }

    /**
     * @param array<string, string> $body
     */
    private function assertPostRejectedWithoutCsrf(string $path, array $body): void
    {
        try {
            $result = $this->post($path, $body);
            $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }
}
