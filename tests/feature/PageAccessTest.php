<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HTTP boundary checks for Page foundation (Phase 2 / Task 2.5).
 *
 * @internal
 */
final class PageAccessTest extends CIUnitTestCase
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

    public function testGetAdminPagesRequiresAuthentication(): void
    {
        $result = $this->get('admin/pages');
        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testPostAdminPagesRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/pages', [
                'title'        => 'X',
                'slug'         => 'x',
                'locale'       => 'id',
                'template_key' => 'custom-page',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testHtmxGetAdminPagesUnauthenticatedReturnsHxRedirect(): void
    {
        $result = $this->withHeaders(['HX-Request' => 'true'])->get('admin/pages');
        $response = $result->response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/cp', $response->getHeaderLine('HX-Redirect'));
        $this->assertSame('', $response->getBody());
    }

    public function testHtmxPostAdminPagesWithoutCsrfIsRejected(): void
    {
        try {
            $result = $this->withHeaders([
                'HX-Request' => 'true',
            ])->post('admin/pages', [
                'title'        => 'X',
                'slug'         => 'x',
                'locale'       => 'id',
                'template_key' => 'custom-page',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testHtmxPostAdminPagesWithInvalidCsrfIsRejected(): void
    {
        try {
            $result = $this->withHeaders([
                'HX-Request'    => 'true',
                'X-CSRF-TOKEN'  => 'definitely-not-a-valid-token',
            ])->post('admin/pages', [
                'title'        => 'X',
                'slug'         => 'x',
                'locale'       => 'id',
                'template_key' => 'custom-page',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }
}
