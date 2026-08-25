<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PostTranslation;
use CodeIgniter\Model;

/**
 * Persistence for post_translations (ADR-013).
 */
class PostTranslationModel extends Model
{
    protected $table            = 'post_translations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = PostTranslation::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'post_id',
        'locale',
        'title',
        'slug',
        'content_payload',
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_image_id',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findByPostAndLocale(int $postId, string $locale): ?PostTranslation
    {
        /** @var PostTranslation|null $row */
        $row = $this->where('post_id', $postId)
            ->where('locale', $locale)
            ->first();

        return $row instanceof PostTranslation ? $row : null;
    }

    public function findBySlugAndLocale(string $slug, string $locale, ?int $exceptPostId = null): ?PostTranslation
    {
        $builder = $this->where('slug', $slug)->where('locale', $locale);
        if ($exceptPostId !== null) {
            $builder->where('post_id !=', $exceptPostId);
        }

        /** @var PostTranslation|null $row */
        $row = $builder->first();

        return $row instanceof PostTranslation ? $row : null;
    }
}
