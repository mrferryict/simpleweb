<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * scheduled_actions row (ADR-021).
 *
 * @property int         $id
 * @property string      $target_type
 * @property int         $target_id
 * @property string      $action_type
 * @property string      $execute_at
 * @property string      $status
 * @property string|null $claimed_at
 * @property string|null $lease_until
 * @property string|null $processed_at
 * @property string|null $result_code
 * @property string|null $result_message
 * @property int         $attempts
 * @property string|null $last_error
 * @property string|null $failed_at
 * @property int|null    $created_by
 * @property int|null    $pending_guard
 * @property string      $created_at
 * @property string      $updated_at
 */
class ScheduledAction extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'            => 'integer',
        'target_id'     => 'integer',
        'attempts'      => 'integer',
        'created_by'    => '?integer',
        'pending_guard' => '?integer',
    ];
}
