<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Admin Post review workflow HTTP boundary (Phase 4 / Task 4.2).
 *
 * @internal
 */
final class PostReviewAccessTest extends CIUnitTestCase
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

    public function testSubmitReviewRequiresCsrfOrAuthBoundary(): void
    {
        try {
            $result = $this->post('admin/posts/1/submit-review', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testReviewPublishRequiresCsrfOrAuthBoundary(): void
    {
        try {
            $result = $this->post('admin/posts/1/review-publish', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testReturnRevisionRequiresCsrfOrAuthBoundary(): void
    {
        try {
            $result = $this->post('admin/posts/1/return-revision', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testFormShowsSubmitForReviewOnDraftOnly(): void
    {
        $html = view('admin/posts/form', $this->formVars(
            status: 'DRAFT',
            canSubmitForReview: true,
        ));
        $this->assertStringContainsString('admin/posts/1/submit-review', $html);
        $this->assertStringContainsString('Submit for Review', $html);
        $this->assertStringNotContainsString('review-publish', $html);
    }

    public function testFormShowsReviewActionsOnPendingReviewOnly(): void
    {
        $html = view('admin/posts/form', $this->formVars(
            status: 'PENDING_REVIEW',
            canReviewPublish: true,
            canReturnForRevision: true,
        ));
        $this->assertStringContainsString('admin/posts/1/review-publish', $html);
        $this->assertStringContainsString('admin/posts/1/return-revision', $html);
        $this->assertStringContainsString('Return for Revision', $html);
        $this->assertStringNotContainsString('submit-review', $html);
        // Direct Task 4.1 publish path must not appear for PENDING_REVIEW.
        $this->assertStringNotContainsString('admin/posts/1/publish"', $html);
    }

    public function testPendingReviewDoesNotExposeDirectPublishForContributorFlags(): void
    {
        $html = view('admin/posts/form', $this->formVars(
            status: 'PENDING_REVIEW',
            canPublish: false,
            canSubmitForReview: false,
            canReviewPublish: false,
            canReturnForRevision: false,
        ));
        $this->assertStringNotContainsString('/publish', $html);
        $this->assertStringNotContainsString('/review-publish', $html);
        $this->assertStringNotContainsString('/submit-review', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function formVars(
        string $status,
        bool $canPublish = false,
        bool $canUnpublish = false,
        bool $canSubmitForReview = false,
        bool $canReviewPublish = false,
        bool $canReturnForRevision = false,
    ): array {
        return [
            'mode'                 => 'edit',
            'item'                 => [
                'id'              => 1,
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'manual_author'   => 'A',
                'status'          => $status,
                'category_ids'    => [],
                'tag_ids'         => [],
                'content_payload' => [],
            ],
            'locales'              => ['id', 'en'],
            'categories'           => [],
            'tags'                 => [],
            'errors'               => [],
            'formAction'           => site_url('admin/posts/1'),
            'contentSchema'        => [],
            'contentPayload'       => [],
            'success'              => null,
            'flashError'           => null,
            'canPublish'           => $canPublish,
            'canUnpublish'         => $canUnpublish,
            'canSubmitForReview'   => $canSubmitForReview,
            'canReviewPublish'     => $canReviewPublish,
            'canReturnForRevision' => $canReturnForRevision,
        ];
    }
}
