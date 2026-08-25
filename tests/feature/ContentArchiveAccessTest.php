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
 * Archive HTTP access boundaries (Phase 4 / Task 4.11B / ADR-020).
 *
 * @internal
 */
final class ContentArchiveAccessTest extends CIUnitTestCase
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

    public function testArchiveRoutesRequireAuthentication(): void
    {
        $this->assertPostRejectedWithoutCsrf('admin/pages/1/archive', ['lock_version' => '1']);
        $this->assertPostRejectedWithoutCsrf('admin/posts/1/archive', ['lock_version' => '1']);
    }

    public function testPageArchiveRequiresCsrf(): void
    {
        $this->assertPostRejectedWithoutCsrf('admin/pages/1/archive', ['lock_version' => '1']);
    }

    public function testPostArchiveRequiresCsrf(): void
    {
        $this->assertPostRejectedWithoutCsrf('admin/posts/1/archive', ['lock_version' => '1']);
    }

    public function testArchiveRoutesDeclarePermissionFilters(): void
    {
        $routes = (string) file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertStringContainsString("permission:page.archive", $routes);
        $this->assertStringContainsString("permission:post.archive", $routes);
        $this->assertStringNotContainsString('unarchive', $routes);
        $this->assertStringNotContainsString('/republish', $routes);
    }

    public function testGetPageArchiveDoesNotMutate(): void
    {
        $pages = Services::pageService(getShared: false);
        $this->assertSame([], $pages->create(new PageWriteDto(
            title: 'Keep Pub',
            slug: 'keep-pub-get',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['body' => '<p>Hello</p>'],
        )));
        $id = (int) $pages->listActive()[0]['page']->id;
        $this->assertSame([], $pages->publish($id));

        try {
            $result = $this->get('admin/pages/' . $id . '/archive');
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 404, 405], true));
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->assertSame(PageStatus::Published->value, $pages->findById($id)?->status);
    }

    public function testGetPostArchiveDoesNotMutate(): void
    {
        $posts = Services::postService(getShared: false);
        $this->assertSame([], $posts->create(new PostWriteDto(
            title: 'Keep Post Pub',
            slug: 'keep-post-pub-get',
            locale: 'id',
            manualAuthor: 'A',
            contentPayload: ['body' => '<p>Body</p>'],
        )));
        $id = (int) $posts->listActive()[0]['post']->id;
        $this->assertSame([], $posts->publish($id));

        try {
            $result = $this->get('admin/posts/' . $id . '/archive');
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 404, 405], true));
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }

        $this->assertSame(PostStatus::Published->value, $posts->findById($id)?->status);
    }

    public function testUnarchiveRoutesDoNotExist(): void
    {
        foreach ([
            'admin/pages/1/unarchive',
            'admin/posts/1/unarchive',
            'admin/pages/1/republish',
            'admin/posts/1/republish',
        ] as $path) {
            try {
                $get = $this->get($path);
                $this->assertTrue(in_array($get->response()->getStatusCode(), [302, 303, 404, 405], true));
            } catch (PageNotFoundException $e) {
                $this->assertSame(404, $e->getCode());
            }

            try {
                $post = $this->post($path, ['lock_version' => '1']);
                $this->assertTrue(in_array($post->response()->getStatusCode(), [302, 303, 403, 404, 405], true));
            } catch (PageNotFoundException $e) {
                $this->assertSame(404, $e->getCode());
            } catch (SecurityException $e) {
                $this->assertSame(403, $e->getCode());
            }
        }
    }

    public function testPageFormArchiveVisibility(): void
    {
        $published = view('admin/pages/form', $this->pageFormVars(
            status: 'PUBLISHED',
            canPublish: false,
            canUnpublish: true,
            canArchive: true,
        ));
        $this->assertStringContainsString('admin/pages/1/archive', $published);
        $this->assertStringContainsString('Archive', $published);
        $this->assertStringContainsString('name="lock_version"', $published);
        $this->assertMatchesRegularExpression('/name="csrf_[^"]+"/i', $published);
        $this->assertStringNotContainsString('Unarchive', $published);

        foreach (['DRAFT', 'PENDING_REVIEW', 'UNPUBLISHED', 'ARCHIVED', 'TRASH'] as $status) {
            $html = view('admin/pages/form', $this->pageFormVars(
                status: $status,
                canPublish: $status === 'ARCHIVED',
                canUnpublish: false,
                canArchive: false,
            ));
            $this->assertStringNotContainsString('admin/pages/1/archive', $html, $status);
            $this->assertStringNotContainsString('Unarchive', $html, $status);
        }

        $archived = view('admin/pages/form', $this->pageFormVars(
            status: 'ARCHIVED',
            canPublish: true,
            canUnpublish: false,
            canArchive: false,
        ));
        $this->assertStringContainsString('admin/pages/1/publish', $archived);
        $this->assertStringContainsString('Publish', $archived);
        $this->assertStringNotContainsString('/archive', $archived);
    }

    public function testPostFormArchiveVisibility(): void
    {
        $published = view('admin/posts/form', $this->postFormVars(
            status: 'PUBLISHED',
            canPublish: false,
            canUnpublish: true,
            canArchive: true,
        ));
        $this->assertStringContainsString('admin/posts/1/archive', $published);
        $this->assertStringContainsString('Archive', $published);
        $this->assertStringNotContainsString('Unarchive', $published);

        foreach (['DRAFT', 'PENDING_REVIEW', 'UNPUBLISHED', 'ARCHIVED', 'TRASH'] as $status) {
            $html = view('admin/posts/form', $this->postFormVars(
                status: $status,
                canPublish: $status === 'ARCHIVED',
                canUnpublish: false,
                canArchive: false,
            ));
            $this->assertStringNotContainsString('admin/posts/1/archive', $html, $status);
        }

        $archived = view('admin/posts/form', $this->postFormVars(
            status: 'ARCHIVED',
            canPublish: true,
            canUnpublish: false,
            canArchive: false,
        ));
        $this->assertStringContainsString('admin/posts/1/publish', $archived);
        $this->assertStringNotContainsString('/archive', $archived);
        $this->assertStringNotContainsString('Unarchive', $archived);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageFormVars(
        string $status,
        bool $canPublish,
        bool $canUnpublish,
        bool $canArchive,
    ): array {
        return [
            'mode'           => 'edit',
            'item'           => [
                'id'              => 1,
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'template_key'    => 'custom-page',
                'parent_id'       => null,
                'status'          => $status,
                'lock_version'    => 3,
                'content_payload' => [],
            ],
            'parents'        => [],
            'locales'        => ['id', 'en'],
            'errors'         => [],
            'formAction'     => site_url('admin/pages/1'),
            'contentSchema'  => [],
            'contentPayload' => [],
            'success'        => null,
            'flashError'     => null,
            'canPublish'     => $canPublish,
            'canUnpublish'   => $canUnpublish,
            'canArchive'     => $canArchive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postFormVars(
        string $status,
        bool $canPublish,
        bool $canUnpublish,
        bool $canArchive,
    ): array {
        return [
            'mode'                 => 'edit',
            'item'                 => [
                'id'              => 1,
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'manual_author'   => 'A',
                'status'          => $status,
                'lock_version'    => 3,
                'category_ids'    => [],
                'tag_ids'         => [],
                'content_payload' => [],
            ],
            'locales'              => ['id', 'en'],
            'categories'           => [],
            'tags'                 => [],
            'errors'               => [],
            'formAction'           => site_url('admin/posts/1'),
            'contentSchema'        => [],
            'contentPayload'       => [],
            'success'              => null,
            'flashError'           => null,
            'canPublish'           => $canPublish,
            'canUnpublish'         => $canUnpublish,
            'canArchive'           => $canArchive,
            'canSubmitForReview'   => false,
            'canReviewPublish'     => false,
            'canReturnForRevision' => false,
        ];
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
