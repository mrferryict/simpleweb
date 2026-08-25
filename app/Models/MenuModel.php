<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\MenuItem;
use CodeIgniter\Model;

/**
 * Persistence for menu_items (ADR-013).
 */
class MenuModel extends Model
{
    protected $table            = 'menu_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = MenuItem::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'location',
        'parent_id',
        'label',
        'target_type',
        'target_id',
        'destination',
        'display_order',
        'is_active',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Flat persistence order for a location (display_order, id).
     * Hierarchy assembly belongs in MenuService.
     *
     * @return list<MenuItem>
     */
    public function findByLocationOrdered(string $location): array
    {
        /** @var list<MenuItem> $items */
        $items = $this->where('location', $location)
            ->orderBy('display_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return $items;
    }

    /**
     * Top-level items (parent_id IS NULL) for a location, ordered.
     *
     * @return list<MenuItem>
     */
    public function findTopLevelByLocationOrdered(string $location): array
    {
        /** @var list<MenuItem> $items */
        $items = $this->where('location', $location)
            ->where('parent_id', null)
            ->orderBy('display_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return $items;
    }

    public function countChildren(int $parentId): int
    {
        return $this->where('parent_id', $parentId)->countAllResults();
    }
}
