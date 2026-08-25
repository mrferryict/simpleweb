<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Enums\PageStatus;
use App\Services\PageService;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Page publishing foundation (Phase 4 / Task 4.3 / DOC-04 §20).
 *
 * @internal
 */
final class PagePublishingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private PageService $pages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pages = Services::pageService(getShared: false);
    }

    public function testDraftToPublishedSucceeds(): void
    {
        $id = $this->createDraft('About', 'about-pub');
        $this->assertSame([], $this->pages->publish($id));
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
    }

    public function testUnpublishedToPublishedSucceeds(): void
    {
        $id = $this->createDraft('Again', 'again-pub');
        $this->assertSame([], $this->pages->publish($id));
        $this->assertSame([], $this->pages->unpublish($id));
        $this->assertSame([], $this->pages->publish($id));
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
    }

    public function testPublishedToUnpublishedSucceeds(): void
    {
        $id = $this->createDraft('Live', 'live-pub');
        $this->assertSame([], $this->pages->publish($id));
        $payloadBefore = (string) $this->pages->findEditable($id)['translation']->content_payload;
        $this->assertSame([], $this->pages->unpublish($id));
        $this->assertSame(PageStatus::Unpublished->value, $this->pages->findById($id)?->status);
        $this->assertSame(
            $payloadBefore,
            (string) $this->pages->findEditable($id)['translation']->content_payload,
        );
    }

    public function testInvalidPublishTransitionIsRejected(): void
    {
        $id = $this->createDraft('Already Live', 'already-live-page');
        $this->assertSame([], $this->pages->publish($id));
        $errors = $this->pages->publish($id);
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
    }

    public function testArchivedToPublishedSucceeds(): void
    {
        $id = $this->createDraft('Archived Pub', 'archived-pub-page');
        $this->assertSame([], $this->pages->publish($id));
        $this->assertSame([], $this->pages->archive($id));
        $this->assertSame([], $this->pages->publish($id));
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
    }

    public function testInvalidUnpublishTransitionIsRejected(): void
    {
        $id = $this->createDraft('Draft Only', 'draft-only');
        $errors = $this->pages->unpublish($id);
        $this->assertArrayHasKey('_status', $errors);
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($id)?->status);
    }

    public function testPublishPermissionBoundary(): void
    {
        $id = $this->createDraft('Denied', 'denied-page');
        $errors = $this->pages->publish($id, $this->userWithout('page.publish'));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PageStatus::Draft->value, $this->pages->findById($id)?->status);
    }

    public function testUnpublishPermissionBoundary(): void
    {
        $id = $this->createDraft('Denied U', 'denied-unpub-page');
        $this->assertSame([], $this->pages->publish($id));
        $errors = $this->pages->unpublish($id, $this->userWithout('page.unpublish'));
        $this->assertArrayHasKey('_forbidden', $errors);
        $this->assertSame(PageStatus::Published->value, $this->pages->findById($id)?->status);
    }

    public function testAdminWildcardCanPublish(): void
    {
        $id = $this->createDraft('Admin Pub', 'admin-pub');
        $admin = $this->userWith(['page.publish', 'page.unpublish']);
        $this->assertSame([], $this->pages->publish($id, $admin));
        $this->assertSame([], $this->pages->unpublish($id, $admin));
        $this->assertSame(PageStatus::Unpublished->value, $this->pages->findById($id)?->status);
    }

    public function testPublishRequiresPrimaryLocaleTranslation(): void
    {
        $id = $this->createDraft('EN Setup', 'en-setup');
        db_connect()->table('page_translations')->where('page_id', $id)->delete();
        db_connect()->table('page_translations')->insert([
            'page_id'         => $id,
            'locale'          => 'en',
            'title'           => 'EN Only',
            'slug'            => 'en-setup',
            'content_payload' => '{}',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
        $errors = $this->pages->publish($id);
        $this->assertArrayHasKey('_locale', $errors);
    }

    public function testTrashCannotBePublished(): void
    {
        $id = $this->createDraft('Trash Me', 'trash-me');
        $this->assertSame([], $this->pages->trash($id));
        $errors = $this->pages->publish($id);
        $this->assertArrayHasKey('_not_found', $errors);
    }

    public function testExistsForMenuTargetStillAllowsPublishedAndDraft(): void
    {
        $id = $this->createDraft('Menu Page', 'menu-page');
        $this->assertTrue($this->pages->existsForMenuTarget($id));
        $this->assertSame([], $this->pages->publish($id));
        $this->assertTrue($this->pages->existsForMenuTarget($id));
        $this->assertSame([], $this->pages->unpublish($id));
        $this->assertTrue($this->pages->existsForMenuTarget($id));
    }

    private function createDraft(string $title, string $slug): int
    {
        $errors = $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['body' => '<p>Hello</p>'],
        ));
        $this->assertSame([], $errors);

        return $this->pages->listActive()[0]['page']->id;
    }

    /**
     * @param list<string> $permissions
     */
    private function userWith(array $permissions): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => in_array($permission, $permissions, true),
        );

        return $user;
    }

    private function userWithout(string $denied): User
    {
        $user = $this->createMock(User::class);
        $user->method('can')->willReturnCallback(
            static fn (string $permission): bool => $permission !== $denied,
        );

        return $user;
    }
}
