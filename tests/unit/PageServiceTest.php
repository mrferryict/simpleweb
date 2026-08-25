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
 * @internal
 */
final class PageServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private PageService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Services::pageService(getShared: false);
    }

    public function testCreateValidPage(): void
    {
        $errors = $this->service->create($this->dto('About', 'about', 'id'));
        $this->assertSame([], $errors);

        $rows = $this->service->listActive();
        $this->assertCount(1, $rows);
        $this->assertSame(PageStatus::Draft->value, $rows[0]['page']->status);
        $this->assertSame('custom-page', $rows[0]['page']->template_key);
        $this->assertSame('About', $rows[0]['translation']?->title);
        $this->assertSame('about', $rows[0]['translation']?->slug);
        $this->assertSame('id', $rows[0]['translation']?->locale);
        $this->assertSame('{}', $rows[0]['translation']?->content_payload);
    }

    public function testMissingTitleIsRejected(): void
    {
        $errors = $this->service->create($this->dto('', 'about', 'id'));
        $this->assertArrayHasKey('title', $errors);
        $this->assertSame([], $this->service->listActive());
    }

    public function testInvalidSlugIsRejected(): void
    {
        $errors = $this->service->create($this->dto('Bad', 'Bad Slug!!', 'id'));
        // normalize strips invalid chars → may become "bad-slug" which is valid,
        // so use reserved / empty after normalize
        $errors = $this->service->create($this->dto('Bad', '!!!', 'id'));
        $this->assertArrayHasKey('slug', $errors);
    }

    public function testReservedSlugIsRejected(): void
    {
        $errors = $this->service->create($this->dto('Admin', 'admin', 'id'));
        $this->assertArrayHasKey('slug', $errors);
    }

    public function testDuplicateSlugSameLocaleIsRejected(): void
    {
        $this->assertSame([], $this->service->create($this->dto('One', 'same-slug', 'id')));
        $errors = $this->service->create($this->dto('Two', 'same-slug', 'id'));
        $this->assertArrayHasKey('slug', $errors);
        $this->assertCount(1, $this->service->listActive());
    }

    public function testSameSlugDifferentLocaleIsAllowed(): void
    {
        $this->assertSame([], $this->service->create($this->dto('ID', 'about', 'id')));
        $this->assertSame([], $this->service->create($this->dto('EN', 'about', 'en')));
        $this->assertCount(2, $this->service->listActive());
    }

    public function testInvalidLocaleIsRejected(): void
    {
        $errors = $this->service->create($this->dto('X', 'x', 'fr'));
        $this->assertArrayHasKey('locale', $errors);
    }

    public function testUpdateValidPage(): void
    {
        $this->assertSame([], $this->service->create($this->dto('Old', 'old', 'id')));
        $id = $this->service->listActive()[0]['page']->id;

        $errors = $this->service->update($id, $this->dto('New', 'new-slug', 'en'));
        $this->assertSame([], $errors);

        $editable = $this->service->findEditable($id);
        $this->assertSame('New', $editable['translation']->title);
        $this->assertSame('new-slug', $editable['translation']->slug);
        $this->assertSame('en', $editable['translation']->locale);
    }

    public function testInvalidUpdateIsRejected(): void
    {
        $this->assertSame([], $this->service->create($this->dto('Keep', 'keep', 'id')));
        $id = $this->service->listActive()[0]['page']->id;

        $errors = $this->service->update($id, $this->dto('', 'keep', 'id'));
        $this->assertArrayHasKey('title', $errors);
        $this->assertSame('Keep', $this->service->findEditable($id)['translation']->title);
    }

    public function testTrashSoftDeletes(): void
    {
        $this->assertSame([], $this->service->create($this->dto('Gone', 'gone', 'id')));
        $id = $this->service->listActive()[0]['page']->id;

        $this->assertSame([], $this->service->trash($id));
        $this->assertSame([], $this->service->listActive());
        $page = $this->service->findById($id);
        $this->assertNotNull($page);
        $this->assertSame(PageStatus::Trash->value, $page->status);
        $this->assertFalse($this->service->existsForMenuTarget($id));
    }

    public function testPageIdentifierIsPositiveInteger(): void
    {
        $this->assertSame([], $this->service->create($this->dto('Id', 'id-page', 'id')));
        $id = $this->service->listActive()[0]['page']->id;
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testHierarchyMaxTwoLevels(): void
    {
        $this->assertSame([], $this->service->create($this->dto('Parent', 'parent', 'id')));
        $parentId = $this->service->listActive()[0]['page']->id;
        $this->assertSame([], $this->service->create(new PageWriteDto(
            title: 'Child',
            slug: 'child',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: $parentId,
        )));
        $childId = array_values(array_filter(
            $this->service->listActive(),
            static fn (array $row): bool => $row['translation']?->slug === 'child',
        ))[0]['page']->id;

        $errors = $this->service->create(new PageWriteDto(
            title: 'Grand',
            slug: 'grand',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: $childId,
        ));
        $this->assertArrayHasKey('parent_id', $errors);
    }

    public function testInvalidContentPayloadIsRejectedOnCreate(): void
    {
        $errors = $this->service->create(new PageWriteDto(
            title: 'Has Extra',
            slug: 'has-extra',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: ['unexpected' => 'value'],
        ));
        $this->assertArrayHasKey('unexpected', $errors);
        $this->assertSame([], $this->service->listActive());
    }

    public function testEmptyContentPayloadRemainsValidForDraftCreate(): void
    {
        $errors = $this->service->create($this->dto('Empty Payload', 'empty-payload', 'id'));
        $this->assertSame([], $errors);
        $this->assertSame('{}', $this->service->listActive()[0]['translation']?->content_payload);
    }

    public function testValidSchemaPayloadIsPersistedOnCreate(): void
    {
        $errors = $this->service->create(new PageWriteDto(
            title: 'With Content',
            slug: 'with-content',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [
                'hero_title' => ' Hello ',
                'cta_url'    => 'https://example.com/page',
            ],
        ));
        $this->assertSame([], $errors);
        $payload = json_decode(
            (string) $this->service->listActive()[0]['translation']?->content_payload,
            true,
        );
        $this->assertIsArray($payload);
        $this->assertSame('Hello', $payload['hero_title']);
        $this->assertSame('https://example.com/page', $payload['cta_url']);
    }

    public function testUpdatePreservesLegacyPayloadKeysAbsentFromSchema(): void
    {
        $this->assertSame([], $this->service->create($this->dto('Legacy', 'legacy-page', 'id')));
        $id = $this->service->listActive()[0]['page']->id;

        // Simulate legacy payload written before the current theme schema existed.
        $db = db_connect();
        $db->table('page_translations')
            ->where('page_id', $id)
            ->where('locale', 'id')
            ->update([
                'content_payload' => json_encode([
                    'legacy_widget' => 'keep-me',
                    'hero_title'    => 'Old Title',
                ], JSON_THROW_ON_ERROR),
            ]);

        $errors = $this->service->update($id, new PageWriteDto(
            title: 'Legacy',
            slug: 'legacy-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
            contentPayload: [
                'hero_title' => 'New Title',
            ],
        ));
        $this->assertSame([], $errors);

        $editable = $this->service->findEditable($id);
        $payload  = json_decode((string) $editable['translation']->content_payload, true);
        $this->assertIsArray($payload);
        $this->assertSame('New Title', $payload['hero_title']);
        $this->assertSame('keep-me', $payload['legacy_widget']);
    }

    public function testUnknownTemplateKeyIsRejected(): void
    {
        $errors = $this->service->create(new PageWriteDto(
            title: 'Bad Template',
            slug: 'bad-template',
            locale: 'id',
            templateKey: 'not-real',
            parentId: null,
        ));
        $this->assertArrayHasKey('template_key', $errors);
        $this->assertSame([], $this->service->listActive());
    }

    private function dto(string $title, string $slug, string $locale): PageWriteDto
    {
        return new PageWriteDto(
            title: $title,
            slug: $slug,
            locale: $locale,
            templateKey: 'custom-page',
            parentId: null,
        );
    }
}
