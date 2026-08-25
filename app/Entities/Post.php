<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Post relational entity (identity / status / author / featured media ref).
 *
 * @property int         $id
 * @property string      $status
 * @property string      $manual_author
 * @property int|null    $featured_image_id
 * @property int|null    $created_by
 * @property int         $lock_version
 * @property string|null $deleted_at
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class Post extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'deleted_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'                => 'integer',
        'featured_image_id' => '?integer',
        'created_by'        => '?integer',
        'lock_version'      => 'integer',
    ];
}
