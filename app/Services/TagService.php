<?php

declare(strict_types=1);

namespace App\Services;

use App\Dtos\TagWriteDto;
use App\Entities\Tag;
use App\Models\TagModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Validation\ValidationInterface;

/**
 * Tag foundation (REQ-TAG-001–003 / Phase 3 Task 3.7).
 *
 * Flat tags. Optional on Posts. No deactivate lifecycle documented.
 */
class TagService
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
        private readonly TagModel $tagModel,
        private readonly ValidationInterface $validation,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return list<Tag>
     */
    public function listAll(): array
    {
        /** @var list<Tag> $rows */
        $rows = $this->tagModel->orderBy('name', 'ASC')->findAll();

        return $rows;
    }

    public function findById(int $id): ?Tag
    {
        if ($id < 1) {
            return null;
        }

        /** @var Tag|null $row */
        $row = $this->tagModel->find($id);

        return $row instanceof Tag ? $row : null;
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function create(TagWriteDto $dto): array
    {
        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, null);
        if ($errors !== []) {
            return $errors;
        }

        $id = $this->tagModel->insert([
            'name' => $normalized['name'],
            'slug' => $normalized['slug'],
        ], true);

        if (! is_int($id) && ! is_numeric($id)) {
            return ['_persist' => 'Unable to create Tag.'];
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    #[\NoDiscard]
    public function update(int $id, TagWriteDto $dto): array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return ['_not_found' => 'Tag not found.'];
        }

        $normalized = $this->normalize($dto);
        $errors     = $this->validate($normalized, $id);
        if ($errors !== []) {
            return $errors;
        }

        $this->tagModel->update($id, [
            'name' => $normalized['name'],
            'slug' => $normalized['slug'],
        ]);

        return [];
    }

    public function countPostReferences(int $tagId): int
    {
        return $this->db->table('post_tags')
            ->where('tag_id', $tagId)
            ->countAllResults();
    }

    /**
     * @return array{name: string, slug: string}
     */
    private function normalize(TagWriteDto $dto): array
    {
        return [
            'name' => trim($dto->name),
            'slug' => $this->normalizeSlug($dto->slug),
        ];
    }

    /**
     * @param array{name: string, slug: string} $data
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

        if ($this->tagModel->findBySlug($data['slug'], $exceptId) !== null) {
            return ['slug' => 'The Slug field must be unique among Tags.'];
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
