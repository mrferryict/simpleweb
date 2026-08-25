<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Menu item domain object (no DB/HTTP logic).
 *
 * @property int         $id
 * @property string      $location
 * @property int|null    $parent_id
 * @property string      $label
 * @property string      $target_type
 * @property int|null    $target_id
 * @property string      $destination External URL storage when target_type is EXTERNAL_URL.
 * @property int         $display_order
 * @property bool        $is_active
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class MenuItem extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'            => 'integer',
        'parent_id'     => '?integer',
        'target_id'     => '?integer',
        'display_order' => 'integer',
        'is_active'     => 'boolean',
    ];
}
