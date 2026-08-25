<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Audit Trail HTTP access boundary (Phase 4 / Task 4.9E).
 *
 * @internal
 */
final class AuditAccessTest extends CIUnitTestCase
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

    public function testGetAdminAuditRequiresAuthentication(): void
    {
        $result = $this->get('admin/audit');
        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testHtmxUnauthenticatedAuditReturnsHxRedirect(): void
    {
        $result   = $this->withHeaders(['HX-Request' => 'true'])->get('admin/audit');
        $response = $result->response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/cp', $response->getHeaderLine('HX-Redirect'));
    }

    public function testAuditIndexViewEscapesAndOmitsMetadataAndSnapshots(): void
    {
        $html = view('admin/audit/index', [
            'rows' => [
                [
                    'id'            => 1,
                    'event'         => 'PAGE_PUBLISHED',
                    'actor_label'   => '<script>evil</script>',
                    'resource_type' => 'page',
                    'resource_id'   => '3',
                    'revision_id'   => '9',
                    'created_at'    => '2026-08-25 10:00:00',
                ],
            ],
        ]);

        $this->assertStringContainsString('PAGE_PUBLISHED', $html);
        $this->assertStringContainsString('page #3', $html);
        $this->assertStringContainsString('9', $html);
        $this->assertStringContainsString('&lt;script&gt;evil&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>evil</script>', $html);
        $this->assertStringNotContainsString('metadata', strtolower($html));
        $this->assertStringNotContainsString('snapshot', strtolower($html));
        $this->assertStringNotContainsString('schema_version', $html);
    }

    public function testAuditNavGuardedByAuditViewPermission(): void
    {
        $layout = file_get_contents(APPPATH . 'Views/admin/layouts/main.php');
        $this->assertNotFalse($layout);
        $this->assertStringContainsString("can('audit.view')", $layout);
        $this->assertStringContainsString('admin/audit', $layout);
    }
}
