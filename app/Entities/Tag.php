<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Flat Tag (REQ-TAG-001).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Tag extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}
