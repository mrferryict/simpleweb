<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Page relational entity (identity / hierarchy / status / template).
 *
 * @property int         $id
 * @property int|null    $parent_id
 * @property string      $status
 * @property string      $template_key
 * @property int         $lock_version
 * @property string|null $deleted_at
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Page extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'           => 'integer',
        'parent_id'    => '?integer',
        'lock_version' => 'integer',
    ];
}
