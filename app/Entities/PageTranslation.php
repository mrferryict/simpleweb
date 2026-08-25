<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Localized Page translation (DOC-07 / DOC-08).
 *
 * @property int         $id
 * @property int         $page_id
 * @property string      $locale
 * @property string      $title
 * @property string      $slug
 * @property string      $content_payload
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $canonical_url
 * @property int|null    $og_image_id
 * @property string|null $created_at
 * @property string|null $updated_at
 */
class PageTranslation extends Entity
{
    protected $datamap = [];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id'      => 'integer',
        'page_id' => 'integer',
        'og_image_id' => '?integer',
    ];
}
