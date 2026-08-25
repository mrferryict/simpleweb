<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Historical public URL redirect (DOC-07 §19 / ADR-024).
 *
 * @property int         $id
 * @property string      $source_path
 * @property string      $target_path
 * @property int         $http_code
 * @property string      $resource_type
 * @property int         $resource_id
 * @property string      $locale
 * @property bool        $active
 * @property string|null $created_at
 */
class UrlRedirect extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
    ];

    protected $casts = [
        'id'          => 'integer',
        'http_code'   => 'integer',
        'resource_id' => 'integer',
        'active'      => 'boolean',
    ];
}
