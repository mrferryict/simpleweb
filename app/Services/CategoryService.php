<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\CategoryWriteDto;
use App\Entities\Category;
use App\Models\CategoryModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Validation\ValidationInterface;

/**
 * Category foundation (REQ-CAT-001–003 / Phase 3 Task 3.7).
 *
 * Flat categories only. Deactivate/restore via is_active (not Post/Page Trash).
 * Permanent removal with replacement strategy is deferred (REQ-CAT-003).
 */
class CategoryService
{
    private const NAME_MAX = 200;
    private const SLUG_MAX = 200;

    /** @var list<string> */
    private const RESERVED_SLUGS = [
        'cp',
        'admin',
        'logout',
        'download',
        'sitemap.xml',
        'robots.txt',
        'en',
        'id',
    ];

    public function __construct(
        private readonly CategoryModel $categoryModel,
        private readonly ValidationInterface $validation,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return list<Category>
     */
    public function listAll(): array
    {
        /** @var list<Category> $rows */
        $rows = $this->categoryModel->orderBy('name', 'ASC')->findAll();

        return $rows;
    }

    /**
     * Active categories for Post assignment / menu targets.
     *
     * @return list<Category>
     */
    public function listActive(): array
    {
        /** @var list<Category> $rows */
        $rows = $this->categoryModel
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $rows;
    }

    public function findById(int $id): ?Category
    {
        if ($id < 1) {
            return null;
        }

        /** @var Category|null $row */
        $row = $this->categoryModel->find($id);

        return $row instanceof Category ? $row : null;
    }

    /**
     * Menu POST_CATEGORY: category must exist and be active.
     * (Deactivated categories are treated like non-public targets; docs do not
     * define trash for categories — is_active is the REQ-CAT-002 contract.)
     */
    public function existsForMenuTarget(int $id): bool
    {
        $category = $this->findById($id);

        return $category !== null && $category->is_active === true;
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function create(CategoryWriteDto $dto): array
    {
        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, null);
        if ($errors !== []) {
            return $errors;
        }

        $id = $this->categoryModel->insert([
            'name'      => $normalized['name'],
            'slug'      => $normalized['slug'],
            'is_active' => $normalized['is_active'] ? 1 : 0,
        ], true);

        if (! is_int($id) && ! is_numeric($id)) {
            return ['_persist' => 'Unable to create Category.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function update(int $id, CategoryWriteDto $dto): array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return ['_not_found' => 'Category not found.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, $id);
        if ($errors !== []) {
            return $errors;
        }

        $this->categoryModel->update($id, [
            'name'      => $normalized['name'],
            'slug'      => $normalized['slug'],
            'is_active' => $normalized['is_active'] ? 1 : 0,
        ]);

        return [];
    }

    /**
     * REQ-CAT-002 deactivate.
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function deactivate(int $id): array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return ['_not_found' => 'Category not found.'];
        }

        $this->categoryModel->update($id, ['is_active' => 0]);

        return [];
    }

    /**
     * REQ-CAT-002 restore (reactivate).
     *
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function restore(int $id): array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return ['_not_found' => 'Category not found.'];
        }

        $this->categoryModel->update($id, ['is_active' => 1]);

        return [];
    }

    public function countPostReferences(int $categoryId): int
    {
        return $this->db->table('post_categories')
            ->where('category_id', $categoryId)
            ->countAllResults();
    }

    /**
     * @return array{name: string, slug: string, is_active: bool}
     */
    private function normalize(CategoryWriteDto $dto): array
    {
        return [
            'name'      => trim($dto->name),
            'slug'      => $this->normalizeSlug($dto->slug),
            'is_active' => $dto->isActive,
        ];
    }

    /**
     * @param array{name: string, slug: string, is_active: bool} $data
     *
     * @return array<string, string>
     */
    private function validate(array $data, ?int $exceptId): array
    {
        $this->validation->reset();
        $this->validation->setRules([
            'name' => [
                'label' => 'Name',
                'rules' => 'required|max_length[' . self::NAME_MAX . ']',
            ],
            'slug' => [
                'label' => 'Slug',
                'rules' => 'required|max_length[' . self::SLUG_MAX . ']',
            ],
        ]);

        if (! $this->validation->run([
            'name' => $data['name'],
            'slug' => $data['slug'],
        ])) {
            /** @var array<string, string> $errors */
            $errors = $this->validation->getErrors();

            return $errors;
        }

        if ($data['slug'] === '' || in_array($data['slug'], self::RESERVED_SLUGS, true)) {
            return ['slug' => 'The Slug field is invalid or reserved.'];
        }

        if ($this->categoryModel->findBySlug($data['slug'], $exceptId) !== null) {
            return ['slug' => 'The Slug field must be unique among Categories.'];
        }

        return [];
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = str_replace([' ', '_'], '-', $slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '', $slug) ?? '';
        $slug = preg_replace('/-+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
