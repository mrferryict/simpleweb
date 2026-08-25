<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * MediaAsset entity (ADR-018).
 *
 * @property int         $id
 * @property string      $type
 * @property string|null $title
 * @property string|null $description
 * @property string|null $alt
 * @property string      $original_filename
 * @property string      $storage_key
 * @property string      $mime_type
 * @property string      $extension
 * @property int         $file_size
 * @property int|null    $width
 * @property int|null    $height
 * @property string|null $download_token
 * @property string      $status
 * @property int|null    $uploaded_by
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $deleted_at
 */
class MediaAsset extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $casts = [
        'id'          => 'integer',
        'file_size'   => 'integer',
        'width'       => '?integer',
        'height'      => '?integer',
        'uploaded_by' => '?integer',
    ];
}
