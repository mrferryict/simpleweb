<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Category;
use CodeIgniter\Model;

/**
 * Persistence for categories (ADR-013 / REQ-CAT-*).
 */
class CategoryModel extends Model
{
    protected $table            = 'categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Category::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'name',
        'slug',
        'is_active',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findBySlug(string $slug, ?int $exceptId = null): ?Category
    {
        $builder = $this->where('slug', $slug);
        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        /** @var Category|null $row */
        $row = $builder->first();

        return $row instanceof Category ? $row : null;
    }
}
