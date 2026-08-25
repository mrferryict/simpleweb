<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Immutable revision row (ADR-019).
 *
 * @property int         $id
 * @property string      $resource_type
 * @property int         $resource_id
 * @property int         $revision_number
 * @property int         $is_autosave
 * @property string      $snapshot
 * @property int|null    $created_by
 * @property string      $created_at
 */
class Revision extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
    ];

    protected $casts = [
        'id'              => 'integer',
        'resource_id'     => 'integer',
        'revision_number' => 'integer',
        'is_autosave'     => 'integer',
        'created_by'      => '?integer',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public function decodedSnapshot(): ?array
    {
        $decoded = json_decode((string) $this->snapshot, true);

        return is_array($decoded) ? $decoded : null;
    }
}
