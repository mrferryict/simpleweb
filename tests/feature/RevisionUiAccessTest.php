<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Revision history routes + form lock_version presentation (Phase 4 / Task 4.9C).
 *
 * @internal
 */
final class RevisionUiAccessTest extends CIUnitTestCase
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

    public function testPageAndPostRevisionRoutesRequireAuthentication(): void
    {
        $this->get('admin/pages/1/revisions')->assertRedirect();
        $this->get('admin/posts/1/revisions')->assertRedirect();
    }

    public function testPageAndPostRestoreRequireCsrf(): void
    {
        try {
            $result = $this->post('admin/pages/1/revisions/1/restore', ['lock_version' => '1']);
            $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }

        try {
            $result = $this->post('admin/posts/1/revisions/1/restore', ['lock_version' => '1']);
            $this->assertTrue(in_array($result->response()->getStatusCode(), [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testHtmxUnauthenticatedRevisionHistoryReturnsHxRedirect(): void
    {
        $result   = $this->withHeaders(['HX-Request' => 'true'])->get('admin/pages/1/revisions');
        $response = $result->response();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/cp', $response->getHeaderLine('HX-Redirect'));
    }

    public function testPageEditFormRendersHiddenLockVersion(): void
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
                'lock_version'    => 4,
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
            'canViewRevisions' => true,
        ]);

        $this->assertMatchesRegularExpression(
            '/name="lock_version"[^>]*value="4"|value="4"[^>]*name="lock_version"/',
            $html,
        );
        $this->assertStringContainsString('admin/pages/7/revisions', $html);
    }

    public function testPostEditFormRendersHiddenLockVersion(): void
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
                'lock_version'    => 9,
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
            'canViewRevisions'     => true,
        ]);

        $this->assertMatchesRegularExpression(
            '/name="lock_version"[^>]*value="9"|value="9"[^>]*name="lock_version"/',
            $html,
        );
        $this->assertStringContainsString('admin/posts/3/revisions', $html);
    }

    public function testRevisionHistoryPartialShowsRestoreWithCsrfAndLockVersion(): void
    {
        $html = view('admin/_partials/revision_history', [
            'revisions' => [
                [
                    'id'              => 11,
                    'revision_number' => 2,
                    'is_autosave'     => false,
                    'created_at'      => '2026-08-25 10:00:00',
                    'actor_label'     => 'editor1',
                ],
            ],
            'canRestore'     => true,
            'restoreBaseUrl' => site_url('admin/pages/1/revisions'),
            'lockVersion'    => 3,
        ]);

        $this->assertStringContainsString('#2', $html);
        $this->assertStringContainsString('Manual', $html);
        $this->assertStringContainsString('editor1', $html);
        $this->assertMatchesRegularExpression('#admin.+pages.+1.+revisions.+11.+restore#', $html);
        $this->assertStringContainsString('name="lock_version"', $html);
        $this->assertStringContainsString('value="3"', $html);
        $this->assertMatchesRegularExpression('/name="csrf_[^"]+"/i', $html);
    }

    public function testContributorCannotSeePostRevisionLinkOnForm(): void
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
            'canSubmitForReview'   => true,
            'canReviewPublish'     => false,
            'canReturnForRevision' => false,
            'canViewRevisions'     => false,
        ]);

        $this->assertStringNotContainsString('/revisions', $html);
    }
}
