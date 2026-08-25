<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Revision;
use CodeIgniter\Model;

/**
 * Persistence for revisions (ADR-019). Insert-only from application Services.
 */
class RevisionModel extends Model
{
    protected $table            = 'revisions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Revision::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'resource_type',
        'resource_id',
        'revision_number',
        'is_autosave',
        'snapshot',
        'created_by',
        'created_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
