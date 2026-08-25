<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\PageTranslation;
use CodeIgniter\Model;

/**
 * Persistence for page_translations (ADR-013).
 */
class PageTranslationModel extends Model
{
    protected $table            = 'page_translations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = PageTranslation::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'page_id',
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

    public function findByPageAndLocale(int $pageId, string $locale): ?PageTranslation
    {
        /** @var PageTranslation|null $row */
        $row = $this->where('page_id', $pageId)
            ->where('locale', $locale)
            ->first();

        return $row instanceof PageTranslation ? $row : null;
    }

    public function findBySlugAndLocale(string $slug, string $locale, ?int $exceptPageId = null): ?PageTranslation
    {
        $builder = $this->where('slug', $slug)->where('locale', $locale);
        if ($exceptPageId !== null) {
            $builder->where('page_id !=', $exceptPageId);
        }

        /** @var PageTranslation|null $row */
        $row = $builder->first();

        return $row instanceof PageTranslation ? $row : null;
    }
}
