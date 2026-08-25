<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\MenuItemWriteDto;
use App\Enums\MenuLocation;
use App\Enums\MenuTargetType;
use App\Services\MenuService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * @internal
 */
final class MenuServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private MenuService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Services::menuService(getShared: false);
    }

    public function testPrimaryLocationIsAccepted(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Home', 'https://example.com/', 1));
        $this->assertSame([], $errors);
        $items = $this->service->listByLocation(MenuLocation::Primary->value);
        $this->assertCount(1, $items);
        $this->assertSame('PRIMARY', $items[0]->location);
    }

    public function testFooterLocationIsAccepted(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Footer->value, 'Contact', 'https://example.com/contact', 0));
        $this->assertSame([], $errors);
        $this->assertCount(1, $this->service->listByLocation(MenuLocation::Footer->value));
    }

    public function testUnknownLocationIsRejected(): void
    {
        $errors = $this->service->create($this->externalDto('SIDEBAR', 'X', 'https://example.com/', 0));
        $this->assertArrayHasKey('location', $errors);
        $this->assertSame([], $this->service->listByLocation('SIDEBAR'));
    }

    public function testRequiredLabelValidation(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, '', 'https://example.com/', 0));
        $this->assertArrayHasKey('label', $errors);
    }

    public function testInvalidOrderIsRejected(): void
    {
        $errors = $this->service->create(new MenuItemWriteDto(
            location: MenuLocation::Primary->value,
            label: 'X',
            targetType: MenuTargetType::ExternalUrl->value,
            targetId: null,
            externalUrl: 'https://example.com/',
            displayOrder: -1,
            isActive: true,
        ));
        $this->assertArrayHasKey('display_order', $errors);
    }

    public function testItemsListedDeterministicallyByLocationAndOrder(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'B', 'https://example.com/b', 20)));
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'A', 'https://example.com/a', 10)));
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Footer->value, 'F', 'https://example.com/f', 1)));

        $primary = $this->service->listByLocation(MenuLocation::Primary->value);
        $this->assertSame(['A', 'B'], array_map(static fn ($i) => $i->label, $primary));
        $this->assertCount(1, $this->service->listByLocation(MenuLocation::Footer->value));
    }

    public function testActiveStatePersists(): void
    {
        $this->assertSame([], $this->service->create(new MenuItemWriteDto(
            location: MenuLocation::Primary->value,
            label: 'Inactive',
            targetType: MenuTargetType::ExternalUrl->value,
            targetId: null,
            externalUrl: 'https://example.com/x',
            displayOrder: 0,
            isActive: false,
        )));
        $item = $this->service->listByLocation(MenuLocation::Primary->value)[0];
        $this->assertFalse($item->is_active);
    }

    public function testCreateSucceedsForValidInput(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, 'About', 'https://example.com/about', 5));
        $this->assertSame([], $errors);
    }

    public function testUpdateSucceedsForValidInput(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Old', 'https://example.com/old', 1)));
        $id = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;

        $errors = $this->service->update($id, $this->externalDto(MenuLocation::Footer->value, 'New', 'https://example.com/new', 3, false));
        $this->assertSame([], $errors);

        $updated = $this->service->findById($id);
        $this->assertNotNull($updated);
        $this->assertSame('FOOTER', $updated->location);
        $this->assertSame('New', $updated->label);
        $this->assertSame('https://example.com/new', $updated->destination);
        $this->assertSame(3, $updated->display_order);
        $this->assertFalse($updated->is_active);
    }

    public function testDeleteRemovesItem(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Temp', 'https://example.com/t', 0)));
        $id = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;
        $this->assertSame([], $this->service->delete($id));
        $this->assertNull($this->service->findById($id));
        $this->assertArrayHasKey('_not_found', $this->service->delete($id));
    }

    public function testInvalidMutationDoesNotPersist(): void
    {
        $before = $this->service->listAllGrouped();
        $errors = $this->service->create($this->externalDto('BAD', '', 'https://example.com/', -5));
        $this->assertNotSame([], $errors);
        $after = $this->service->listAllGrouped();
        $this->assertCount(count($before['PRIMARY']), $after['PRIMARY']);
        $this->assertCount(count($before['FOOTER']), $after['FOOTER']);
    }

    public function testEditorMatrixLacksMenuManagePermission(): void
    {
        $matrix = config('AuthGroups')->matrix;
        $this->assertContains('menu.*', $matrix['admin']);
        $this->assertFalse(in_array('menu.manage', $matrix['editor'], true));
        $this->assertFalse(in_array('menu.*', $matrix['editor'], true));
    }

    public function testTopLevelItemCanBeCreated(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Top', 'https://example.com/', 0));
        $this->assertSame([], $errors);
        $item = $this->service->listByLocation(MenuLocation::Primary->value)[0];
        $this->assertNull($item->parent_id);
    }

    public function testValidLevel2ChildCanBeCreated(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Parent', 'https://example.com/p', 0)));
        $parentId = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;

        $errors = $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'Child',
            'https://example.com/c',
            1,
            true,
            $parentId,
        ));
        $this->assertSame([], $errors);

        $flat = $this->service->listByLocation(MenuLocation::Primary->value);
        $this->assertSame(['Parent', 'Child'], array_map(static fn ($i) => $i->label, $flat));
        $this->assertSame($parentId, $flat[1]->parent_id);
    }

    public function testNonExistentParentIsRejected(): void
    {
        $errors = $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'X',
            'https://example.com/',
            0,
            true,
            99999,
        ));
        $this->assertArrayHasKey('parent_id', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testSelfParentIsRejected(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Self', 'https://example.com/', 0)));
        $id = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;

        $errors = $this->service->update($id, $this->externalDto(
            MenuLocation::Primary->value,
            'Self',
            'https://example.com/',
            0,
            true,
            $id,
        ));
        $this->assertArrayHasKey('parent_id', $errors);
        $this->assertNull($this->service->findById($id)?->parent_id);
    }

    public function testChildCannotBecomeAParent(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'P', 'https://example.com/p', 0)));
        $parentId = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;
        $this->assertSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'C',
            'https://example.com/c',
            1,
            true,
            $parentId,
        )));
        $childId = $this->service->listByLocation(MenuLocation::Primary->value)[1]->id;

        $errors = $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'Grand',
            'https://example.com/g',
            2,
            true,
            $childId,
        ));
        $this->assertArrayHasKey('parent_id', $errors);
        $this->assertCount(2, $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testLevel3HierarchyIsRejected(): void
    {
        $this->testChildCannotBecomeAParent();
    }

    public function testParentFromAnotherLocationIsRejected(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Footer->value, 'FooterParent', 'https://example.com/f', 0)));
        $footerParentId = $this->service->listByLocation(MenuLocation::Footer->value)[0]->id;

        $errors = $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'BadChild',
            'https://example.com/x',
            0,
            true,
            $footerParentId,
        ));
        $this->assertArrayHasKey('parent_id', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testUpdatingChildToAnotherValidParentWorks(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'P1', 'https://example.com/1', 0)));
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'P2', 'https://example.com/2', 1)));
        $p1 = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;
        $p2 = $this->service->listByLocation(MenuLocation::Primary->value)[1]->id;
        $this->assertSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'C',
            'https://example.com/c',
            0,
            true,
            $p1,
        )));
        $childId = array_values(array_filter(
            $this->service->listByLocation(MenuLocation::Primary->value),
            static fn ($i) => $i->label === 'C',
        ))[0]->id;

        $errors = $this->service->update($childId, $this->externalDto(
            MenuLocation::Primary->value,
            'C',
            'https://example.com/c',
            0,
            true,
            $p2,
        ));
        $this->assertSame([], $errors);
        $this->assertSame($p2, $this->service->findById($childId)?->parent_id);
    }

    public function testUpdatingCannotCreateCycle(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'P', 'https://example.com/p', 0)));
        $parentId = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;
        $this->assertSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'C',
            'https://example.com/c',
            1,
            true,
            $parentId,
        )));
        $childId = $this->service->listByLocation(MenuLocation::Primary->value)[1]->id;

        $errors = $this->service->update($parentId, $this->externalDto(
            MenuLocation::Primary->value,
            'P',
            'https://example.com/p',
            0,
            true,
            $childId,
        ));
        $this->assertNotSame([], $errors);
        $this->assertNull($this->service->findById($parentId)?->parent_id);
    }

    public function testParentDeletionWithChildrenIsRejected(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'P', 'https://example.com/p', 0)));
        $parentId = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;
        $this->assertSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'C',
            'https://example.com/c',
            1,
            true,
            $parentId,
        )));

        $errors = $this->service->delete($parentId);
        $this->assertArrayHasKey('parent_id', $errors);
        $this->assertNotNull($this->service->findById($parentId));
        $this->assertCount(2, $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testParentAndChildOrderingIsDeterministic(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'P-B', 'https://example.com/pb', 20)));
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'P-A', 'https://example.com/pa', 10)));
        $pA = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;
        $pB = $this->service->listByLocation(MenuLocation::Primary->value)[1]->id;

        $this->assertSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'C-B',
            'https://example.com/cb',
            20,
            true,
            $pA,
        )));
        $this->assertSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'C-A',
            'https://example.com/ca',
            10,
            true,
            $pA,
        )));

        $labels = array_map(
            static fn ($i) => $i->label,
            $this->service->listByLocation(MenuLocation::Primary->value),
        );
        $this->assertSame(['P-A', 'C-A', 'C-B', 'P-B'], $labels);
        unset($pB);
    }

    public function testInvalidHierarchyInputIsNotPersisted(): void
    {
        $before = count($this->service->listByLocation(MenuLocation::Primary->value));
        $this->assertNotSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'X',
            'https://example.com/',
            0,
            true,
            123456,
        )));
        $this->assertCount($before, $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testExistingExternalUrlRecordsRemainValidShape(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Legacy', 'https://example.com/legacy', 0)));
        $item = $this->service->listByLocation(MenuLocation::Primary->value)[0];
        $this->assertSame(MenuTargetType::ExternalUrl->value, $item->target_type);
        $this->assertNull($item->target_id);
        $this->assertSame('https://example.com/legacy', $item->destination);
    }

    public function testPageDestinationPersistsDeferredId(): void
    {
        $pageService = Services::pageService(getShared: false);
        $this->assertSame([], $pageService->create(new \App\Dtos\PageWriteDto(
            title: 'About Page',
            slug: 'about-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        )));
        $pageId = $pageService->listActive()[0]['page']->id;

        $errors = $this->service->create($this->pageDto(MenuLocation::Primary->value, 'About', $pageId, 0));
        $this->assertSame([], $errors);
        $item = $this->service->listByLocation(MenuLocation::Primary->value)[0];
        $this->assertSame(MenuTargetType::Page->value, $item->target_type);
        $this->assertSame($pageId, $item->target_id);
        $this->assertSame('', $item->destination);
    }

    public function testPageDestinationRejectsMissingPage(): void
    {
        $errors = $this->service->create($this->pageDto(MenuLocation::Primary->value, 'Missing', 99999, 0));
        $this->assertArrayHasKey('target_id', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testPostCategoryDestinationPersistsValidActiveId(): void
    {
        /** @var \App\Services\CategoryService $categories */
        $categories = service('categoryService');
        $this->assertSame([], $categories->create(new \App\Dtos\CategoryWriteDto('News', 'news')));
        $categoryId = $categories->listActive()[0]->id;

        $errors = $this->service->create($this->categoryDto(MenuLocation::Primary->value, 'News', $categoryId, 0));
        $this->assertSame([], $errors);
        $item = $this->service->listByLocation(MenuLocation::Primary->value)[0];
        $this->assertSame(MenuTargetType::PostCategory->value, $item->target_type);
        $this->assertSame($categoryId, $item->target_id);
        $this->assertSame('', $item->destination);
    }

    public function testPostCategoryDestinationRejectsMissingCategory(): void
    {
        $errors = $this->service->create($this->categoryDto(MenuLocation::Primary->value, 'Missing', 99999, 0));
        $this->assertArrayHasKey('target_id', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testPostCategoryDestinationRejectsInactiveCategory(): void
    {
        /** @var \App\Services\CategoryService $categories */
        $categories = service('categoryService');
        $this->assertSame([], $categories->create(new \App\Dtos\CategoryWriteDto('Old', 'old')));
        $categoryId = $categories->listAll()[0]->id;
        $this->assertSame([], $categories->deactivate($categoryId));

        $errors = $this->service->create($this->categoryDto(MenuLocation::Primary->value, 'Old', $categoryId, 0));
        $this->assertArrayHasKey('target_id', $errors);
    }

    public function testExternalUrlDestinationPersists(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Docs', 'https://example.com/docs', 0));
        $this->assertSame([], $errors);
        $item = $this->service->listByLocation(MenuLocation::Primary->value)[0];
        $this->assertSame(MenuTargetType::ExternalUrl->value, $item->target_type);
        $this->assertSame('https://example.com/docs', $item->destination);
    }

    public function testJavascriptUrlIsRejected(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Bad', 'javascript:alert(1)', 0));
        $this->assertArrayHasKey('destination', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testDataUrlIsRejected(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Bad', 'data:text/html,hi', 0));
        $this->assertArrayHasKey('destination', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testVbscriptUrlIsRejected(): void
    {
        $errors = $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Bad', 'vbscript:msgbox(1)', 0));
        $this->assertArrayHasKey('destination', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testMixedPageAndExternalUrlIsRejected(): void
    {
        $errors = $this->service->create(new MenuItemWriteDto(
            location: MenuLocation::Primary->value,
            label: 'Mixed',
            targetType: MenuTargetType::Page->value,
            targetId: 1,
            externalUrl: 'https://example.com/',
            displayOrder: 0,
            isActive: true,
        ));
        $this->assertArrayHasKey('destination', $errors);
        $this->assertSame([], $this->service->listByLocation(MenuLocation::Primary->value));
    }

    public function testMissingPageTargetIdIsRejected(): void
    {
        $errors = $this->service->create(new MenuItemWriteDto(
            location: MenuLocation::Primary->value,
            label: 'Page',
            targetType: MenuTargetType::Page->value,
            targetId: null,
            externalUrl: '',
            displayOrder: 0,
            isActive: true,
        ));
        $this->assertArrayHasKey('target_id', $errors);
    }

    public function testMissingExternalUrlIsRejected(): void
    {
        $errors = $this->service->create(new MenuItemWriteDto(
            location: MenuLocation::Primary->value,
            label: 'Ext',
            targetType: MenuTargetType::ExternalUrl->value,
            targetId: null,
            externalUrl: '',
            displayOrder: 0,
            isActive: true,
        ));
        $this->assertArrayHasKey('destination', $errors);
    }

    public function testEditingDestinationTypeWorks(): void
    {
        $this->assertSame([], $this->service->create($this->externalDto(MenuLocation::Primary->value, 'Switch', 'https://example.com/a', 0)));
        $id = $this->service->listByLocation(MenuLocation::Primary->value)[0]->id;

        $pageService = Services::pageService(getShared: false);
        $this->assertSame([], $pageService->create(new \App\Dtos\PageWriteDto(
            title: 'Switch Page',
            slug: 'switch-page',
            locale: 'id',
            templateKey: 'custom-page',
            parentId: null,
        )));
        $pageId = $pageService->listActive()[0]['page']->id;

        $errors = $this->service->update($id, $this->pageDto(MenuLocation::Primary->value, 'Switch', $pageId, 0));
        $this->assertSame([], $errors);
        $updated = $this->service->findById($id);
        $this->assertSame(MenuTargetType::Page->value, $updated?->target_type);
        $this->assertSame($pageId, $updated?->target_id);
        $this->assertSame('', $updated?->destination);
    }

    public function testInvalidDestinationIsNotPersisted(): void
    {
        $before = count($this->service->listByLocation(MenuLocation::Primary->value));
        $this->assertNotSame([], $this->service->create($this->externalDto(
            MenuLocation::Primary->value,
            'Nope',
            'javascript:void(0)',
            0,
        )));
        $this->assertCount($before, $this->service->listByLocation(MenuLocation::Primary->value));
    }

    private function externalDto(
        string $location,
        string $label,
        string $url,
        int $order,
        bool $active = true,
        ?int $parentId = null,
    ): MenuItemWriteDto {
        return new MenuItemWriteDto(
            location: $location,
            label: $label,
            targetType: MenuTargetType::ExternalUrl->value,
            targetId: null,
            externalUrl: $url,
            displayOrder: $order,
            isActive: $active,
            parentId: $parentId,
        );
    }

    private function pageDto(
        string $location,
        string $label,
        int $pageId,
        int $order,
        bool $active = true,
        ?int $parentId = null,
    ): MenuItemWriteDto {
        return new MenuItemWriteDto(
            location: $location,
            label: $label,
            targetType: MenuTargetType::Page->value,
            targetId: $pageId,
            externalUrl: '',
            displayOrder: $order,
            isActive: $active,
            parentId: $parentId,
        );
    }

    private function categoryDto(
        string $location,
        string $label,
        int $categoryId,
        int $order,
        bool $active = true,
        ?int $parentId = null,
    ): MenuItemWriteDto {
        return new MenuItemWriteDto(
            location: $location,
            label: $label,
            targetType: MenuTargetType::PostCategory->value,
            targetId: $categoryId,
            externalUrl: '',
            displayOrder: $order,
            isActive: $active,
            parentId: $parentId,
        );
    }
}
