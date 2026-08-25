<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Page;
use App\Entities\PageTranslation;
use App\Entities\Post;
use App\Entities\PostTranslation;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Trash list action visibility (Phase 4 / Task 4.10).
 *
 * @internal
 */
final class ContentTrashUiTest extends CIUnitTestCase
{
    public function testTrashPageShowsRestoreAndPermanentDeleteWhenAuthorized(): void
    {
        $html = $this->pageIndexHtml(
            status: PageStatus::Trash->value,
            isTrash: true,
            canRestore: true,
            canPermanentDelete: true,
        );

        $this->assertStringContainsString('Restore', $html);
        $this->assertStringContainsString('Permanent Delete', $html);
        $this->assertStringContainsString('admin/pages/12/restore', $html);
        $this->assertStringContainsString('admin/pages/12/permanent-delete', $html);
        $this->assertStringContainsString('name="lock_version"', $html);
        $this->assertStringContainsString('value="7"', $html);
        $this->assertMatchesRegularExpression('/name="csrf_[^"]+"/i', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('data-confirm=', $html);
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Revision and audit history are kept', $decoded);
        $this->assertStringContainsString('Media files are not deleted', $decoded);
        $this->assertStringNotContainsString('Edit', $html);
    }

    public function testActivePageDoesNotShowPermanentDelete(): void
    {
        $html = $this->pageIndexHtml(
            status: PageStatus::Draft->value,
            isTrash: false,
            canRestore: true,
            canPermanentDelete: true,
            canTrash: true,
        );

        $this->assertStringNotContainsString('Permanent Delete', $html);
        $this->assertStringNotContainsString('permanent-delete', $html);
        $this->assertStringContainsString('Edit', $html);
        $this->assertStringContainsString('Trash', $html);
        $this->assertStringContainsString('name="lock_version"', $html);
    }

    public function testUnauthorizedPageTrashActionsAreHidden(): void
    {
        $html = $this->pageIndexHtml(
            status: PageStatus::Trash->value,
            isTrash: true,
            canRestore: false,
            canPermanentDelete: false,
        );

        $this->assertStringNotContainsString('Restore', $html);
        $this->assertStringNotContainsString('Permanent Delete', $html);
        $this->assertStringNotContainsString('/restore', $html);
        $this->assertStringNotContainsString('permanent-delete', $html);
    }

    public function testTrashPostShowsRestoreAndPermanentDeleteWhenAuthorized(): void
    {
        $html = $this->postIndexHtml(
            status: PostStatus::Trash->value,
            isTrash: true,
            canRestore: true,
            canPermanentDelete: true,
        );

        $this->assertStringContainsString('Restore', $html);
        $this->assertStringContainsString('Permanent Delete', $html);
        $this->assertStringContainsString('admin/posts/8/restore', $html);
        $this->assertStringContainsString('admin/posts/8/permanent-delete', $html);
        $this->assertStringContainsString('name="lock_version"', $html);
        $this->assertStringContainsString('value="3"', $html);
        $this->assertMatchesRegularExpression('/name="csrf_[^"]+"/i', $html);
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('Revision and audit history are kept', $decoded);
    }

    public function testActivePostDoesNotShowPermanentDelete(): void
    {
        $html = $this->postIndexHtml(
            status: PostStatus::Published->value,
            isTrash: false,
            canRestore: true,
            canPermanentDelete: true,
            canTrash: true,
        );

        $this->assertStringNotContainsString('Permanent Delete', $html);
        $this->assertStringNotContainsString('permanent-delete', $html);
        $this->assertStringContainsString('Edit', $html);
        $this->assertStringContainsString('Trash', $html);
    }

    public function testUnauthorizedPostTrashActionsAreHidden(): void
    {
        $html = $this->postIndexHtml(
            status: PostStatus::Trash->value,
            isTrash: true,
            canRestore: false,
            canPermanentDelete: false,
        );

        $this->assertStringNotContainsString('>Restore<', $html);
        $this->assertStringNotContainsString('Permanent Delete', $html);
        $this->assertStringNotContainsString('/restore', $html);
        $this->assertStringNotContainsString('permanent-delete', $html);
    }

    private function pageIndexHtml(
        string $status,
        bool $isTrash,
        bool $canRestore,
        bool $canPermanentDelete,
        bool $canTrash = false,
    ): string {
        $page = new Page([
            'id'           => 12,
            'parent_id'    => null,
            'status'       => $status,
            'template_key' => 'custom-page',
            'lock_version' => 7,
        ]);
        $translation = new PageTranslation([
            'id'     => 1,
            'page_id'=> 12,
            'locale' => 'id',
            'title'  => 'About',
            'slug'   => 'about',
        ]);

        return view('admin/pages/index', [
            'rows'               => [['page' => $page, 'translation' => $translation]],
            'isTrash'            => $isTrash,
            'success'            => null,
            'error'              => null,
            'canTrash'           => $canTrash,
            'canRestore'         => $canRestore,
            'canPermanentDelete' => $canPermanentDelete,
        ]);
    }

    private function postIndexHtml(
        string $status,
        bool $isTrash,
        bool $canRestore,
        bool $canPermanentDelete,
        bool $canTrash = false,
    ): string {
        $post = new Post([
            'id'           => 8,
            'status'       => $status,
            'manual_author'=> 'A',
            'lock_version' => 3,
        ]);
        $translation = new PostTranslation([
            'id'      => 1,
            'post_id' => 8,
            'locale'  => 'id',
            'title'   => 'News',
            'slug'    => 'news-item',
        ]);

        return view('admin/posts/index', [
            'rows'               => [[
                'post'         => $post,
                'translation'  => $translation,
                'category_ids' => [],
                'tag_ids'      => [],
            ]],
            'isTrash'            => $isTrash,
            'success'            => null,
            'error'              => null,
            'canTrash'           => $canTrash,
            'canRestore'         => $canRestore,
            'canPermanentDelete' => $canPermanentDelete,
        ]);
    }
}
