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
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Pages/Posts admin presentation polish (TH-007).
 *
 * @internal
 */
final class AdminContentUiTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    /**
     * @var list<string>
     */
    protected $namespace = [
        'CodeIgniter\Shield',
        'CodeIgniter\Settings',
        'App',
    ];

    protected $migrate = true;
    protected $refresh = true;
    public function testPageListEmptyStateRendersCreateAction(): void
    {
        $html = view('admin/pages/index', [
            'rows'               => [],
            'isTrash'            => false,
            'success'            => null,
            'error'              => null,
            'canTrash'           => true,
            'canRestore'         => true,
            'canPermanentDelete' => true,
        ]);

        $this->assertStringContainsString('admin-content.css', $html);
        $this->assertStringContainsString('admin-empty-state', $html);
        $this->assertStringContainsString('No pages yet', $html);
        $this->assertStringContainsString('Create Page', $html);
        $this->assertStringContainsString('admin/pages/new', $html);
        $this->assertStringNotContainsString('Placeholder dashboard content', $html);
    }

    public function testPageListRendersStatusBadge(): void
    {
        $html = view('admin/pages/index', [
            'rows' => [[
                'page' => new Page([
                    'id'           => 3,
                    'status'       => PageStatus::Published->value,
                    'template_key' => 'custom-page',
                    'lock_version' => 1,
                    'updated_at'   => '2026-08-30 10:00:00',
                ]),
                'translation' => new PageTranslation([
                    'title'  => 'About',
                    'slug'   => 'about',
                    'locale' => 'id',
                ]),
            ]],
            'isTrash'            => false,
            'success'            => null,
            'error'              => null,
            'canTrash'           => true,
            'canRestore'         => true,
            'canPermanentDelete' => true,
        ]);

        $this->assertStringContainsString('status-badge--published', $html);
        $this->assertStringContainsString('>Published</span>', $html);
        $this->assertStringContainsString('admin-table', $html);
        $this->assertStringContainsString('admin/pages/3/edit', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function testPostListEmptyStateRendersCreateAction(): void
    {
        $html = view('admin/posts/index', [
            'rows'               => [],
            'isTrash'            => false,
            'success'            => null,
            'error'              => null,
            'canTrash'           => true,
            'canRestore'         => true,
            'canPermanentDelete' => true,
        ]);

        $this->assertStringContainsString('No posts yet', $html);
        $this->assertStringContainsString('Create Post', $html);
        $this->assertStringContainsString('admin/posts/new', $html);
    }

    public function testPostListRendersPendingReviewBadge(): void
    {
        $html = view('admin/posts/index', [
            'rows' => [[
                'post' => new Post([
                    'id'            => 5,
                    'status'        => PostStatus::PendingReview->value,
                    'manual_author' => 'Editor',
                    'lock_version'  => 2,
                    'updated_at'    => '2026-08-30 11:00:00',
                ]),
                'translation' => new PostTranslation([
                    'title'  => 'Welcome',
                    'slug'   => 'welcome',
                    'locale' => 'id',
                ]),
                'category_ids' => [],
                'tag_ids'      => [],
            ]],
            'isTrash'            => false,
            'success'            => null,
            'error'              => null,
            'canTrash'           => true,
            'canRestore'         => true,
            'canPermanentDelete' => true,
        ]);

        $this->assertStringContainsString('status-badge--review', $html);
        $this->assertStringContainsString('>In Review</span>', $html);
        $this->assertStringContainsString('admin/posts/5/edit', $html);
    }

    public function testPageCreateFormUsesAdminSectionsAndPreservesAutosaveHooks(): void
    {
        $html = view('admin/pages/form', [
            'mode'            => 'edit',
            'item'            => [
                'id'           => 7,
                'title'        => 'About',
                'slug'         => 'about',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'parent_id'    => null,
                'status'       => PageStatus::Draft->value,
                'lock_version' => 4,
            ],
            'parents'         => [],
            'locales'         => ['id'],
            'errors'          => [],
            'formAction'      => site_url('admin/pages/7'),
            'contentSchema'   => [],
            'contentPayload'  => [],
            'success'         => null,
            'flashError'      => null,
            'canPublish'      => true,
            'canUnpublish'    => false,
            'canArchive'      => false,
            'canViewRevisions'=> true,
        ]);

        $this->assertStringContainsString('admin-form-section', $html);
        $this->assertStringContainsString('admin-autosave', $html);
        $this->assertStringContainsString('hx-post', $html);
        $this->assertStringContainsString('admin/pages/7/autosave', $html);
        $this->assertStringContainsString('name="lock_version"', $html);
        $this->assertStringContainsString('admin/pages/7/publish', $html);
        $this->assertStringContainsString('status-badge--draft', $html);
    }

    public function testPostCreateFormPreservesQuillAndTaxonomyFields(): void
    {
        $html = view('admin/posts/form', [
            'mode'                 => 'create',
            'item'                 => [
                'title'         => '',
                'slug'          => '',
                'locale'        => 'id',
                'manual_author' => '',
                'category_ids'  => [],
                'tag_ids'       => [],
            ],
            'locales'              => ['id'],
            'categories'           => [],
            'tags'                 => [],
            'errors'               => [],
            'formAction'           => site_url('admin/posts'),
            'contentSchema'        => ['body' => ['type' => 'RICH_TEXT', 'label' => 'Body', 'required' => true]],
            'contentPayload'       => [],
            'success'              => null,
            'flashError'           => null,
            'canPublish'           => false,
            'canUnpublish'         => false,
            'canArchive'           => false,
            'canSubmitForReview'   => false,
            'canReviewPublish'     => false,
            'canReturnForRevision' => false,
            'canViewRevisions'     => false,
        ]);

        $this->assertStringContainsString('admin-form-section', $html);
        $this->assertStringContainsString('name="category_ids[]"', $html);
        $this->assertStringContainsString('name="tag_ids[]"', $html);
        $this->assertStringContainsString('data-rich-text="quill"', $html);
        $this->assertStringContainsString('name="content[body]"', $html);
        $this->assertStringContainsString('Create post', $html);
    }
}
