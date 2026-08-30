<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Category;
use App\Entities\MediaAsset;
use App\Entities\Tag;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Categories, Tags, and Media admin presentation polish (TH-008).
 *
 * @internal
 */
final class AdminTaxonomyMediaUiTest extends CIUnitTestCase
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

    public function testCategoryListEmptyStateRendersCreateAction(): void
    {
        $html = view('admin/categories/index', [
            'rows'    => [],
            'success' => null,
            'error'   => null,
        ]);

        $this->assertStringContainsString('admin-empty-state', $html);
        $this->assertStringContainsString('No categories yet', $html);
        $this->assertStringContainsString('Create Category', $html);
        $this->assertStringContainsString('admin/categories/new', $html);
        $this->assertStringContainsString('admin-toolbar', $html);
    }

    public function testCategoryListRendersActiveBadgeAndActions(): void
    {
        $html = view('admin/categories/index', [
            'rows' => [
                new Category([
                    'id'        => 2,
                    'name'      => 'News',
                    'slug'      => 'news',
                    'is_active' => true,
                ]),
            ],
            'success' => null,
            'error'   => null,
        ]);

        $this->assertStringContainsString('admin-table', $html);
        $this->assertStringContainsString('status-badge--active', $html);
        $this->assertStringContainsString('>Active</span>', $html);
        $this->assertStringContainsString('admin/categories/2/edit', $html);
        $this->assertStringContainsString('Deactivate', $html);
        $this->assertStringContainsString('admin/categories/2/deactivate', $html);
        $this->assertStringContainsString('name="csrf_', $html);
    }

    public function testInactiveCategoryShowsRestoreAction(): void
    {
        $html = view('admin/categories/index', [
            'rows' => [
                new Category([
                    'id'        => 4,
                    'name'      => 'Archive',
                    'slug'      => 'archive',
                    'is_active' => false,
                ]),
            ],
            'success' => null,
            'error'   => null,
        ]);

        $this->assertStringContainsString('status-badge--inactive', $html);
        $this->assertStringContainsString('>Restore</button>', $html);
        $this->assertStringContainsString('admin/categories/4/restore', $html);
        $this->assertStringNotContainsString('Deactivate', $html);
    }

    public function testCategoryFormUsesAdminSections(): void
    {
        $html = view('admin/categories/form', [
            'mode'       => 'create',
            'item'       => ['name' => '', 'slug' => '', 'is_active' => true],
            'errors'     => [],
            'formAction' => site_url('admin/categories'),
        ]);

        $this->assertStringContainsString('admin-form-section', $html);
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="slug"', $html);
        $this->assertStringContainsString('name="is_active"', $html);
        $this->assertStringContainsString('Create category', $html);
        $this->assertStringContainsString('csrf_', $html);
    }

    public function testTagListEmptyStateRendersCreateAction(): void
    {
        $html = view('admin/tags/index', [
            'rows'    => [],
            'success' => null,
            'error'   => null,
        ]);

        $this->assertStringContainsString('No tags yet', $html);
        $this->assertStringContainsString('Create Tag', $html);
        $this->assertStringContainsString('admin/tags/new', $html);
    }

    public function testTagListRendersEditAction(): void
    {
        $html = view('admin/tags/index', [
            'rows' => [
                new Tag([
                    'id'   => 7,
                    'name' => 'Featured',
                    'slug' => 'featured',
                ]),
            ],
            'success' => null,
            'error'   => null,
        ]);

        $this->assertStringContainsString('admin-table', $html);
        $this->assertStringContainsString('Featured', $html);
        $this->assertStringContainsString('admin/tags/7/edit', $html);
    }

    public function testTagFormUsesAdminSections(): void
    {
        $html = view('admin/tags/form', [
            'mode'       => 'edit',
            'item'       => ['id' => 1, 'name' => 'News', 'slug' => 'news'],
            'errors'     => [],
            'formAction' => site_url('admin/tags/1'),
        ]);

        $this->assertStringContainsString('admin-form-section', $html);
        $this->assertStringContainsString('value="News"', $html);
        $this->assertStringContainsString('Update tag', $html);
    }

    public function testMediaListEmptyStateRendersUploadAction(): void
    {
        $html = view('admin/media/index', [
            'status'             => 'ACTIVE',
            'rows'               => [],
            'success'            => null,
            'error'              => null,
            'canPermanentDelete' => false,
        ]);

        $this->assertStringContainsString('No media uploaded yet', $html);
        $this->assertStringContainsString('Upload Media', $html);
        $this->assertStringContainsString('admin/media/upload', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
    }

    public function testMediaListRendersImageThumbnailAndActions(): void
    {
        $html = view('admin/media/index', [
            'status'             => 'ACTIVE',
            'rows'               => [[
                'asset' => new MediaAsset([
                    'id'                => 9,
                    'type'              => 'IMAGE',
                    'title'             => 'Hero',
                    'original_filename' => 'hero.png',
                    'mime_type'         => 'image/png',
                    'extension'         => 'png',
                    'file_size'         => 2048,
                    'width'             => 800,
                    'height'            => 600,
                    'status'            => 'ACTIVE',
                ]),
                'imageUrl' => '/uploads/images/hero.png',
            ]],
            'success'            => null,
            'error'              => null,
            'canPermanentDelete' => false,
        ]);

        $this->assertStringContainsString('admin-media-thumb', $html);
        $this->assertStringContainsString('type-badge--image', $html);
        $this->assertStringContainsString('>Image</span>', $html);
        $this->assertStringContainsString('2.0 KB', $html);
        $this->assertStringContainsString('800×600', $html);
        $this->assertStringContainsString('admin/media/9/edit', $html);
        $this->assertStringContainsString('>View</a>', $html);
        $this->assertStringContainsString('admin/media/9/trash', $html);
    }

    public function testMediaTrashHidesPermanentDeleteWhenUnauthorized(): void
    {
        $html = view('admin/media/index', [
            'status'             => 'TRASH',
            'rows'               => [[
                'asset' => new MediaAsset([
                    'id'                => 3,
                    'type'              => 'DOCUMENT',
                    'original_filename' => 'file.pdf',
                    'mime_type'         => 'application/pdf',
                    'extension'         => 'pdf',
                    'file_size'         => 512,
                    'download_token'    => 'abc123',
                    'status'            => 'TRASH',
                ]),
                'imageUrl' => null,
            ]],
            'success'            => null,
            'error'              => null,
            'canPermanentDelete' => false,
        ]);

        $this->assertStringContainsString('type-badge--document', $html);
        $this->assertStringContainsString('>Restore</button>', $html);
        $this->assertStringNotContainsString('Delete permanently', $html);
        $this->assertStringNotContainsString('/delete', $html);
    }

    public function testMediaTrashShowsPermanentDeleteWhenAuthorized(): void
    {
        $html = view('admin/media/index', [
            'status'             => 'TRASH',
            'rows'               => [[
                'asset' => new MediaAsset([
                    'id'                => 3,
                    'type'              => 'DOCUMENT',
                    'original_filename' => 'file.pdf',
                    'mime_type'         => 'application/pdf',
                    'extension'         => 'pdf',
                    'file_size'         => 512,
                    'status'            => 'TRASH',
                ]),
                'imageUrl' => null,
            ]],
            'success'            => null,
            'error'              => null,
            'canPermanentDelete' => true,
        ]);

        $this->assertStringContainsString('Delete permanently', $html);
        $this->assertStringContainsString('admin/media/3/delete', $html);
    }

    public function testMediaUploadFormPreservesMultipartAndCsrf(): void
    {
        $html = view('admin/media/upload', [
            'errors' => [],
            'item'   => ['title' => '', 'alt' => '', 'description' => ''],
        ]);

        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertStringContainsString('type="file"', $html);
        $this->assertStringContainsString('name="file"', $html);
        $this->assertStringContainsString('action="' . site_url('admin/media') . '"', $html);
        $this->assertStringContainsString('csrf_', $html);
    }

    public function testMediaEditFormPreservesMetadataFields(): void
    {
        $html = view('admin/media/edit', [
            'asset' => new MediaAsset([
                'id'                => 5,
                'type'              => 'IMAGE',
                'title'             => 'Logo',
                'alt'               => 'Site logo',
                'description'       => 'Header logo',
                'original_filename' => 'logo.png',
                'mime_type'         => 'image/png',
                'extension'         => 'png',
                'file_size'         => 1024,
                'width'             => 200,
                'height'            => 80,
                'status'            => 'ACTIVE',
            ]),
            'imageUrl' => '/uploads/images/logo.png',
            'errors'   => [],
        ]);

        $this->assertStringContainsString('admin-media-detail', $html);
        $this->assertStringContainsString('name="title"', $html);
        $this->assertStringContainsString('value="Logo"', $html);
        $this->assertStringContainsString('Site&#x20;logo', $html);
        $this->assertStringContainsString('Header logo', $html);
        $this->assertStringContainsString('admin/media/5', $html);
    }
}
