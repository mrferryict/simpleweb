<?php

declare(strict_types=1);

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Themes and Audit admin presentation polish (TH-010).
 *
 * @internal
 */
final class AdminThemesAuditUiTest extends CIUnitTestCase
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

    public function testThemeListEmptyStateRenders(): void
    {
        $html = view('admin/themes/index', [
            'themes'       => [],
            'previewPages' => [],
            'success'      => null,
            'error'        => null,
        ]);

        $this->assertStringContainsString('admin-empty-state', $html);
        $this->assertStringContainsString('No enabled themes available', $html);
        $this->assertStringContainsString('admin-page-header', $html);
    }

    public function testThemeListRendersActiveStateAndActions(): void
    {
        $html = view('admin/themes/index', [
            'themes' => [
                [
                    'id'        => '2026',
                    'name'      => 'Theme 2026',
                    'version'   => '1.0.0',
                    'author'    => 'SMITE',
                    'state'     => 'ACTIVE',
                    'is_active' => true,
                ],
                [
                    'id'        => 'classic',
                    'name'      => 'Classic',
                    'version'   => '1.0.0',
                    'author'    => 'SMITE',
                    'state'     => 'ENABLED',
                    'is_active' => false,
                ],
            ],
            'previewPages' => [
                ['id' => 4, 'title' => 'Home'],
            ],
            'success'      => null,
            'error'        => null,
        ]);

        $this->assertStringContainsString('admin-table', $html);
        $this->assertStringContainsString('status-badge--published', $html);
        $this->assertStringContainsString('>Active</span>', $html);
        $this->assertStringContainsString('Currently active', $html);
        $this->assertStringContainsString('admin/themes/classic/activate', $html);
        $this->assertStringContainsString('>Activate</button>', $html);
        $this->assertStringContainsString('name="csrf_', $html);
        $this->assertStringContainsString('Preview: Home', $html);
        $this->assertStringContainsString('admin/preview/theme/2026/page/4', $html);
        $this->assertStringContainsString('admin/preview/theme/classic/page/4', $html);
    }

    public function testAuditListEmptyStateRenders(): void
    {
        $html = view('admin/audit/index', ['rows' => []]);

        $this->assertStringContainsString('admin-empty-state', $html);
        $this->assertStringContainsString('No audit events yet', $html);
        $this->assertStringContainsString('Audit Trail', $html);
    }

    public function testAuditListRendersEventRows(): void
    {
        $html = view('admin/audit/index', [
            'rows' => [
                [
                    'id'            => 12,
                    'event'         => 'PAGE_PUBLISHED',
                    'actor_label'   => 'Editor One',
                    'resource_type' => 'page',
                    'resource_id'   => '7',
                    'revision_id'   => '22',
                    'created_at'    => '2026-08-30 12:00:00',
                ],
            ],
        ]);

        $this->assertStringContainsString('admin-table--audit', $html);
        $this->assertStringContainsString('admin-audit__event', $html);
        $this->assertStringContainsString('PAGE_PUBLISHED', $html);
        $this->assertStringContainsString('page #7', $html);
        $this->assertStringContainsString('Editor One', $html);
        $this->assertStringContainsString('22', $html);
        $this->assertStringContainsString('2026-08-30 12:00:00', $html);
        $this->assertStringContainsString('<th scope="col">', $html);
    }

    public function testAuditListEscapesActorLabel(): void
    {
        $html = view('admin/audit/index', [
            'rows' => [
                [
                    'id'            => 1,
                    'event'         => 'LOGIN_SUCCESS',
                    'actor_label'   => '<script>evil</script>',
                    'resource_type' => '—',
                    'resource_id'   => '—',
                    'revision_id'   => '—',
                    'created_at'    => '2026-08-25 10:00:00',
                ],
            ],
        ]);

        $this->assertStringContainsString('&lt;script&gt;evil&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>evil</script>', $html);
        $this->assertStringNotContainsString('metadata', strtolower($html));
        $this->assertStringNotContainsString('snapshot', strtolower($html));
    }
}
