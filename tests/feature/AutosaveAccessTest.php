<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Autosave HTTP boundary — auth/CSRF/session (Phase 4 / Task 4.9D).
 *
 * @internal
 */
final class AutosaveAccessTest extends CIUnitTestCase
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

    public function testPageAndPostAutosaveRequireAuthentication(): void
    {
        try {
            $page = $this->post('admin/pages/1/autosave', ['lock_version' => '1']);
            $this->assertTrue(in_array($page->response()->getStatusCode(), [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }

        try {
            $post = $this->post('admin/posts/1/autosave', ['lock_version' => '1']);
            $this->assertTrue(in_array($post->response()->getStatusCode(), [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testAutosaveRequiresCsrf(): void
    {
        try {
            $result = $this->withHeaders(['HX-Request' => 'true'])->post('admin/pages/1/autosave', [
                'title'        => 'X',
                'slug'         => 'x',
                'locale'       => 'id',
                'template_key' => 'custom-page',
                'lock_version' => '1',
            ]);
            $this->assertSame(403, $result->response()->getStatusCode());
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testHtmxUnauthenticatedAutosaveReturnsHxRedirect(): void
    {
        try {
            $result = $this->withHeaders(['HX-Request' => 'true'])->post('admin/pages/1/autosave', [
                'lock_version' => '1',
            ]);
            $response = $result->response();
            if ($response->getStatusCode() === 200) {
                $this->assertSame('/cp', $response->getHeaderLine('HX-Redirect'));
            } else {
                $this->assertTrue(in_array($response->getStatusCode(), [302, 303, 403], true));
            }
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testPageEditFormIncludesAutosaveHookWithoutTimer(): void
    {
        $html = view('admin/pages/form', [
            'mode'             => 'edit',
            'item'             => [
                'id'              => 7,
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'template_key'    => 'custom-page',
                'parent_id'       => null,
                'status'          => 'DRAFT',
                'lock_version'    => 2,
                'content_payload' => [],
            ],
            'parents'          => [],
            'locales'          => ['id', 'en'],
            'errors'           => [],
            'formAction'       => site_url('admin/pages/7'),
            'contentSchema'    => [],
            'contentPayload'   => [],
            'success'          => null,
            'flashError'       => null,
            'canPublish'       => false,
            'canUnpublish'     => false,
            'canViewRevisions' => false,
        ]);

        $this->assertMatchesRegularExpression('#admin.+pages.+7.+autosave#', $html);
        $this->assertStringContainsString('hx-post', $html);
        $this->assertStringContainsString('id="autosave-status"', $html);
        $this->assertStringContainsString('Save draft', $html);
        $this->assertStringNotContainsString('setInterval', $html);
        $this->assertStringNotContainsString('localStorage', $html);
    }

    public function testPostEditFormIncludesAutosaveHook(): void
    {
        $html = view('admin/posts/form', [
            'mode'                 => 'edit',
            'item'                 => [
                'id'              => 3,
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'manual_author'   => 'A',
                'status'          => 'DRAFT',
                'lock_version'    => 1,
                'category_ids'    => [],
                'tag_ids'         => [],
                'content_payload' => [],
            ],
            'locales'              => ['id', 'en'],
            'categories'           => [],
            'tags'                 => [],
            'errors'               => [],
            'formAction'           => site_url('admin/posts/3'),
            'contentSchema'        => [],
            'contentPayload'       => [],
            'success'              => null,
            'flashError'           => null,
            'canPublish'           => false,
            'canUnpublish'         => false,
            'canSubmitForReview'   => false,
            'canReviewPublish'     => false,
            'canReturnForRevision' => false,
            'canViewRevisions'     => false,
        ]);

        $this->assertMatchesRegularExpression('#admin.+posts.+3.+autosave#', $html);
        $this->assertStringContainsString('hx-include="#post-edit-form"', $html);
    }
}
