<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Flat Category (REQ-CAT-001). Deactivate/restore via is_active (REQ-CAT-002).
 *
 * @property int         $id
 * @property string      $name
 * @property string      $slug
 * @property bool        $is_active
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Category extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'        => 'integer',
        'is_active' => 'boolean',
    ];
}
