<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\ScheduledAction;
use CodeIgniter\Model;

/**
 * Persistence for scheduled_actions (ADR-021).
 */
class ScheduledActionModel extends Model
{
    protected $table            = 'scheduled_actions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ScheduledAction::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'target_type',
        'target_id',
        'action_type',
        'execute_at',
        'status',
        'claimed_at',
        'lease_until',
        'processed_at',
        'result_code',
        'result_message',
        'attempts',
        'last_error',
        'failed_at',
        'created_by',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
