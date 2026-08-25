<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HTTP boundary checks for Post foundation (Phase 3 / Task 3.7).
 *
 * @internal
 */
final class PostAccessTest extends CIUnitTestCase
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

    public function testGetAdminPostsRequiresAuthentication(): void
    {
        $result = $this->get('admin/posts');
        $result->assertRedirect();
        $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303], true));
    }

    public function testHtmxGetAdminPostsUnauthenticatedReturnsHxRedirect(): void
    {
        $result   = $this->withHeaders(['HX-Request' => 'true'])->get('admin/posts');
        $response = $result->response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/cp', $response->getHeaderLine('HX-Redirect'));
        $this->assertSame('', $response->getBody());
    }

    public function testPostAdminPostsRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/posts', [
                'title'         => 'X',
                'slug'          => 'x',
                'locale'        => 'id',
                'manual_author' => 'A',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testHtmxPostAdminPostsWithoutCsrfIsRejected(): void
    {
        try {
            $result = $this->withHeaders([
                'HX-Request' => 'true',
            ])->post('admin/posts', [
                'title'         => 'X',
                'slug'          => 'x',
                'locale'        => 'id',
                'manual_author' => 'A',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testHtmxPostAdminPostsWithInvalidCsrfIsRejected(): void
    {
        try {
            $result = $this->withHeaders([
                'HX-Request'   => 'true',
                'X-CSRF-TOKEN' => 'definitely-not-a-valid-token',
            ])->post('admin/posts', [
                'title'         => 'X',
                'slug'          => 'x',
                'locale'        => 'id',
                'manual_author' => 'A',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testGetAdminCategoriesRequiresAuthentication(): void
    {
        $result = $this->get('admin/categories');
        $result->assertRedirect();
    }

    public function testGetAdminTagsRequiresAuthentication(): void
    {
        $result = $this->get('admin/tags');
        $result->assertRedirect();
    }
}
