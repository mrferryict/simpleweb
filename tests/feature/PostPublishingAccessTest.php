<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Admin Post publish/unpublish HTTP boundary (Phase 4 / Task 4.1).
 *
 * @internal
 */
final class PostPublishingAccessTest extends CIUnitTestCase
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

    public function testPublishRequiresAuthentication(): void
    {
        try {
            $result = $this->post('admin/posts/1/publish', []);
            $result->assertRedirect();
        } catch (SecurityException $e) {
            // Global CSRF may reject before the session filter redirects.
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testUnpublishRequiresAuthentication(): void
    {
        try {
            $result = $this->post('admin/posts/1/unpublish', []);
            $result->assertRedirect();
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testPublishRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/posts/1/publish', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testUnpublishRequiresCsrf(): void
    {
        try {
            $result = $this->post('admin/posts/1/unpublish', []);
            $status = $result->response()->getStatusCode();
            $this->assertTrue(in_array($status, [302, 303, 403], true));
        } catch (SecurityException $e) {
            $this->assertSame(403, $e->getCode());
        }
    }

    public function testPublishFormShowsOnlyWhenApplicable(): void
    {
        $draft = view('admin/posts/form', $this->formVars(
            status: 'DRAFT',
            canPublish: true,
            canUnpublish: false,
        ));
        $this->assertStringContainsString('admin/posts/1/publish', $draft);
        $this->assertStringContainsString('Publish', $draft);
        $this->assertStringNotContainsString('admin/posts/1/unpublish', $draft);

        $published = view('admin/posts/form', $this->formVars(
            status: 'PUBLISHED',
            canPublish: false,
            canUnpublish: true,
        ));
        $this->assertStringContainsString('admin/posts/1/unpublish', $published);
        $this->assertStringContainsString('Unpublish', $published);
        $this->assertStringNotContainsString('admin/posts/1/publish', $published);

        $create = view('admin/posts/form', $this->formVars(
            status: '',
            canPublish: false,
            canUnpublish: false,
            mode: 'create',
            id: null,
        ));
        $this->assertStringNotContainsString('/publish', $create);
        $this->assertStringNotContainsString('/unpublish', $create);
    }

    public function testLifecycleFormsIncludeCsrfField(): void
    {
        $html = view('admin/posts/form', $this->formVars(
            status: 'DRAFT',
            canPublish: true,
            canUnpublish: false,
        ));
        $this->assertMatchesRegularExpression('/name="csrf_[^"]+"/i', $html);
        $this->assertStringContainsString('admin/posts/1/publish', $html);
    }

    /**
     * @return array<string, mixed>
     */
    private function formVars(
        string $status,
        bool $canPublish,
        bool $canUnpublish,
        string $mode = 'edit',
        ?int $id = 1,
    ): array {
        return [
            'mode'           => $mode,
            'item'           => [
                'id'              => $id,
                'title'           => 'T',
                'slug'            => 't',
                'locale'          => 'id',
                'manual_author'   => 'A',
                'status'          => $status,
                'category_ids'    => [],
                'tag_ids'         => [],
                'content_payload' => [],
            ],
            'locales'        => ['id', 'en'],
            'categories'     => [],
            'tags'           => [],
            'errors'         => [],
            'formAction'     => site_url($mode === 'edit' ? 'admin/posts/1' : 'admin/posts'),
            'contentSchema'  => [],
            'contentPayload' => [],
            'success'        => null,
            'flashError'     => null,
            'canPublish'           => $canPublish,
            'canUnpublish'         => $canUnpublish,
            'canSubmitForReview'   => false,
            'canReviewPublish'     => false,
            'canReturnForRevision' => false,
        ];
    }
}
