<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\AuditLog;
use CodeIgniter\Model;

/**
 * Persistence for audit_logs (ADR-019). Append-only from application Services.
 */
class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = AuditLog::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'actor_id',
        'event',
        'resource_type',
        'resource_id',
        'revision_id',
        'metadata',
        'created_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = false;
}
