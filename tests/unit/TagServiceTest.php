<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dtos\TagWriteDto;
use App\Services\TagService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Services;

/**
 * Tag foundation tests (Phase 3 / Task 3.7).
 *
 * @internal
 */
final class TagServiceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = 'App';
    protected $migrate   = true;
    protected $refresh   = true;

    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Services::tagService(getShared: false);
    }

    public function testCreateValidTag(): void
    {
        $errors = $this->service->create(new TagWriteDto('Featured', 'featured'));
        $this->assertSame([], $errors);
        $this->assertCount(1, $this->service->listAll());
    }

    public function testDuplicateSlugIsRejected(): void
    {
        $this->assertSame([], $this->service->create(new TagWriteDto('A', 'feat')));
        $errors = $this->service->create(new TagWriteDto('B', 'feat'));
        $this->assertArrayHasKey('slug', $errors);
    }

    public function testUpdateTag(): void
    {
        $this->assertSame([], $this->service->create(new TagWriteDto('A', 'a')));
        $id = $this->service->listAll()[0]->id;
        $errors = $this->service->update($id, new TagWriteDto('B', 'b'));
        $this->assertSame([], $errors);
        $this->assertSame('B', $this->service->findById($id)?->name);
    }

    public function testMissingNameIsRejected(): void
    {
        $errors = $this->service->create(new TagWriteDto('', 'a'));
        $this->assertArrayHasKey('name', $errors);
    }
}
