<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\CategoryWriteDto;
use App\Services\CategoryService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Category foundation tests (Phase 3 / Task 3.7).
 *
 * @internal
 */
final class CategoryServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Services::categoryService(getShared: false);
    }

    public function testCreateValidCategory(): void
    {
        $errors = $this->service->create(new CategoryWriteDto('News', 'news'));
        $this->assertSame([], $errors);
        $this->assertCount(1, $this->service->listActive());
        $this->assertTrue($this->service->listActive()[0]->is_active);
    }

    public function testDuplicateSlugIsRejected(): void
    {
        $this->assertSame([], $this->service->create(new CategoryWriteDto('A', 'news')));
        $errors = $this->service->create(new CategoryWriteDto('B', 'news'));
        $this->assertArrayHasKey('slug', $errors);
    }

    public function testUpdateCategory(): void
    {
        $this->assertSame([], $this->service->create(new CategoryWriteDto('A', 'a')));
        $id = $this->service->listAll()[0]->id;
        $errors = $this->service->update($id, new CategoryWriteDto('B', 'b'));
        $this->assertSame([], $errors);
        $this->assertSame('B', $this->service->findById($id)?->name);
        $this->assertSame('b', $this->service->findById($id)?->slug);
    }

    public function testDeactivateAndRestore(): void
    {
        $this->assertSame([], $this->service->create(new CategoryWriteDto('A', 'a')));
        $id = $this->service->listAll()[0]->id;
        $this->assertTrue($this->service->existsForMenuTarget($id));

        $this->assertSame([], $this->service->deactivate($id));
        $this->assertFalse($this->service->existsForMenuTarget($id));
        $this->assertCount(0, $this->service->listActive());

        $this->assertSame([], $this->service->restore($id));
        $this->assertTrue($this->service->existsForMenuTarget($id));
    }

    public function testMissingNameIsRejected(): void
    {
        $errors = $this->service->create(new CategoryWriteDto('', 'a'));
        $this->assertArrayHasKey('name', $errors);
    }
}
