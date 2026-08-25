<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\PageWriteDto;
use App\Enums\PageStatus;
use App\Services\PageService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Public Page lookup (Phase 4 / Task 4.4 / ADR-017).
 *
 * @internal
 */
final class PublicPageLookupTest extends CIUnitTestCase
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

    public function testPublishedPrimaryPageResolves(): void
    {
        $this->createPublished('About', 'about-us', 'id', ['body' => '<p>Hello</p>', 'hero_title' => 'Hero']);
        $dto = $this->pages->findPublishedForPublic('about-us', 'id');

        $this->assertNotNull($dto);
        $this->assertSame('About', $dto->title);
        $this->assertSame('about-us', $dto->slug);
        $this->assertSame('id', $dto->locale);
        $this->assertSame('<p>Hello</p>', $dto->contentPayload['body'] ?? null);
        $this->assertSame('Hero', $dto->contentPayload['hero_title'] ?? null);
        $this->assertSame('id', $dto->requestedLocale);
        $this->assertFalse($dto->isFallback);
        $this->assertSame('custom-page', $dto->templateKey);
    }

    public function testDraftDoesNotResolve(): void
    {
        $this->assertSame([], $this->pages->create($this->dto('Draft', 'draft-page', 'id')));
        $this->assertNull($this->pages->findPublishedForPublic('draft-page', 'id'));
        $this->assertNull($this->pages->findPublishedForPublic('draft-page', 'en'));
    }

    public function testUnpublishedDoesNotResolve(): void
    {
        $this->createWithStatus('Unpub', 'unpub-page', PageStatus::Unpublished);
        $this->assertNull($this->pages->findPublishedForPublic('unpub-page', 'id'));
    }

    public function testArchivedDoesNotResolve(): void
    {
        $this->createWithStatus('Arch', 'arch-page', PageStatus::Archived);
        $this->assertNull($this->pages->findPublishedForPublic('arch-page', 'id'));
    }

    public function testTrashDoesNotResolve(): void
    {
        $this->createWithStatus('Trash', 'trash-page', PageStatus::Trash);
        $this->assertNull($this->pages->findPublishedForPublic('trash-page', 'id'));
    }

    public function testMissingSlugReturnsNull(): void
    {
        $this->assertNull($this->pages->findPublishedForPublic('no-such-page', 'id'));
        $this->assertNull($this->pages->findPublishedForPublic('no-such-page', 'en'));
    }

    public function testPublishedSecondaryTranslationResolves(): void
    {
        $id = $this->createPublished('ID Title', 'shared-page', 'id', ['body' => '<p>ID</p>']);
        $this->insertTranslation($id, 'en', 'EN Title', 'shared-page', ['body' => '<p>EN</p>']);

        $dto = $this->pages->findPublishedForPublic('shared-page', 'en');
        $this->assertNotNull($dto);
        $this->assertSame('EN Title', $dto->title);
        $this->assertSame('<p>EN</p>', $dto->contentPayload['body'] ?? null);
        $this->assertSame('en', $dto->locale);
        $this->assertFalse($dto->isFallback);
    }

    public function testMissingSecondaryFallsBackToPublishedPrimary(): void
    {
        $this->createPublished('Primary Only', 'fallback-page', 'id', ['body' => '<p>Primary</p>']);

        $dto = $this->pages->findPublishedForPublic('fallback-page', 'en');
        $this->assertNotNull($dto);
        $this->assertTrue($dto->isFallback);
        $this->assertSame('Primary Only', $dto->title);
        $this->assertSame('id', $dto->locale);
        $this->assertSame('en', $dto->requestedLocale);
        $this->assertSame('<p>Primary</p>', $dto->contentPayload['body'] ?? null);
    }

    public function testSecondaryDoesNotFallBackToNonPublishedPrimary(): void
    {
        $this->assertSame([], $this->pages->create($this->dto('Draft Primary', 'no-fb-page', 'id')));
        $this->assertNull($this->pages->findPublishedForPublic('no-fb-page', 'en'));
    }

    public function testMissingPrimaryTranslationDoesNotResolve(): void
    {
        $id = $this->createPublished('EN Setup', 'en-only-page', 'id', []);
        db_connect()->table('page_translations')->where('page_id', $id)->delete();
        db_connect()->table('page_translations')->insert([
            'page_id'         => $id,
            'locale'          => 'en',
            'title'           => 'EN Only',
            'slug'            => 'en-only-page',
            'content_payload' => '{}',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);

        $this->assertNull($this->pages->findPublishedForPublic('en-only-page', 'id'));
    }

    public function testReservedSlugNewsNeverResolvesAsPage(): void
    {
        $this->assertNull($this->pages->findPublishedForPublic('news', 'id'));
        $this->assertNull($this->pages->findPublishedForPublic('news', 'en'));
    }

    public function testHierarchyDoesNotComposeNestedPublicPath(): void
    {
        $parentId = $this->createPublished('Parent', 'parent-page', 'id', []);
        $errors = $this->pages->create(new PageWriteDto(
            title: 'Child',
            slug: 'child-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: $parentId,
            contentPayload: [],
        ));
        $this->assertSame([], $errors);
        $childId = (int) db_connect()->table('page_translations')
            ->where('slug', 'child-page')
            ->where('locale', 'id')
            ->get()
            ->getRowArray()['page_id'];
        db_connect()->table('pages')->where('id', $childId)->update([
            'status' => PageStatus::Published->value,
        ]);

        $this->assertNotNull($this->pages->findPublishedForPublic('child-page', 'id'));
        $this->assertNull($this->pages->findPublishedForPublic('parent-page/child-page', 'id'));
    }

    public function testTemplateKeyComesFromStoredPageNotRequest(): void
    {
        $this->createPublished('T', 'tpl-page', 'id', []);
        $dto = $this->pages->findPublishedForPublic('tpl-page', 'id');
        $this->assertNotNull($dto);
        $this->assertSame('custom-page', $dto->templateKey);
    }

    public function testUnavailableTemplateKeyStillReturnsDtoForControllerTo404(): void
    {
        $id = $this->createPublished('Bad Tpl', 'bad-tpl', 'id', []);
        db_connect()->table('pages')->where('id', $id)->update([
            'template_key' => 'missing-template',
        ]);

        $dto = $this->pages->findPublishedForPublic('bad-tpl', 'id');
        $this->assertNotNull($dto);
        $this->assertSame('missing-template', $dto->templateKey);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createPublished(string $title, string $slug, string $locale, array $payload): int
    {
        $errors = $this->pages->create(new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: $payload,
        ));
        $this->assertSame([], $errors);
        $id = $this->pages->listActive()[0]['page']->id;
        db_connect()->table('pages')->where('id', $id)->update([
            'status' => PageStatus::Published->value,
        ]);

        return $id;
    }

    private function createWithStatus(string $title, string $slug, PageStatus $status): int
    {
        $this->assertSame([], $this->pages->create($this->dto($title, $slug, 'id')));
        $id = $this->pages->listActive()[0]['page']->id;
        db_connect()->table('pages')->where('id', $id)->update([
            'status'     => $status->value,
            'deleted_at' => $status === PageStatus::Trash ? date('Y-m-d H:i:s') : null,
        ]);

        return $id;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertTranslation(int $pageId, string $locale, string $title, string $slug, array $payload): void
    {
        db_connect()->table('page_translations')->insert([
            'page_id'         => $pageId,
            'locale'          => $locale,
            'title'           => $title,
            'slug'            => $slug,
            'content_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    private function dto(string $title, string $slug, string $locale): PageWriteDto
    {
        return new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [],
        );
    }
}
