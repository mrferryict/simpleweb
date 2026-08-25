<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Tag;
use CodeIgniter\Model;

/**
 * Persistence for tags (ADR-013 / REQ-TAG-*).
 */
class TagModel extends Model
{
    protected $table            = 'tags';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Tag::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'name',
        'slug',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findBySlug(string $slug, ?int $exceptId = null): ?Tag
    {
        $builder = $this->where('slug', $slug);
        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        /** @var Tag|null $row */
        $row = $builder->first();

        return $row instanceof Tag ? $row : null;
    }
}
