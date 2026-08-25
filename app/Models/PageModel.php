<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Page;
use CodeIgniter\Model;

/**
 * Persistence for pages (ADR-013).
 */
class PageModel extends Model
{
    protected $table            = 'pages';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Page::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'parent_id',
        'status',
        'template_key',
        'lock_version',
        'deleted_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function countChildren(int $parentId): int
    {
        return $this->where('parent_id', $parentId)
            ->where('status !=', 'TRASH')
            ->countAllResults();
    }
}
