<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Append-only audit log row (ADR-019).
 *
 * @property int         $id
 * @property int|null    $actor_id
 * @property string      $event
 * @property string|null $resource_type
 * @property int|null    $resource_id
 * @property int|null    $revision_id
 * @property string|null $metadata
 * @property string      $created_at
 */
class AuditLog extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
    ];

    protected $casts = [
        'id'          => 'integer',
        'actor_id'    => '?integer',
        'resource_id' => '?integer',
        'revision_id' => '?integer',
    ];
}
