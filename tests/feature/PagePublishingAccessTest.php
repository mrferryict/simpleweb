<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Admin Page publish/unpublish HTTP boundary (Phase 4 / Task 4.3).
 *
 * @internal
 */
final class PagePublishingAccessTest extends CIUnitTestCase
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

    public function testPublishRequiresCsrfOrAuthBoundary(): void
    {
        try {
            $result = $this->post('admin/pages/1/publish', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testUnpublishRequiresCsrfOrAuthBoundary(): void
    {
        try {
            $result = $this->post('admin/pages/1/unpublish', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testFormShowsPublishOnDraftOnly(): void
    {
        $html = view('admin/pages/form', $this->formVars(
            status: 'DRAFT',
            canPublish: true,
            canUnpublish: false,
        ));
        $this->assertStringContainsString('admin/pages/1/publish', $html);
        $this->assertStringContainsString('Publish', $html);
        $this->assertStringNotContainsString('/unpublish', $html);
    }

    public function testFormShowsUnpublishOnPublishedOnly(): void
    {
        $html = view('admin/pages/form', $this->formVars(
            status: 'PUBLISHED',
            canPublish: false,
            canUnpublish: true,
        ));
        $this->assertStringContainsString('admin/pages/1/unpublish', $html);
        $this->assertStringContainsString('Unpublish', $html);
        $this->assertStringNotContainsString('admin/pages/1/publish"', $html);
    }

    public function testAdminPagesRouteRemainsProtected(): void
    {
        $result = $this->get('admin/pages');
        $result->assertRedirect();
    }

    /**
     * @return array<string, mixed>
     */
    private function formVars(string $status, bool $canPublish, bool $canUnpublish): array
    {
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
        ];
    }
}
