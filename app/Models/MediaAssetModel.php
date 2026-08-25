<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\MediaAsset;
use CodeIgniter\Model;

/**
 * Persistence for media_assets (ADR-018).
 */
class MediaAssetModel extends Model
{
    protected $table            = 'media_assets';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = MediaAsset::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'type',
        'title',
        'description',
        'alt',
        'original_filename',
        'storage_key',
        'mime_type',
        'extension',
        'file_size',
        'width',
        'height',
        'download_token',
        'status',
        'uploaded_by',
        'deleted_at',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findByDownloadToken(string $token): ?MediaAsset
    {
        /** @var MediaAsset|null $row */
        $row = $this->where('download_token', $token)->first();

        return $row instanceof MediaAsset ? $row : null;
    }

    public function findByStorageKey(string $storageKey): ?MediaAsset
    {
        /** @var MediaAsset|null $row */
        $row = $this->where('storage_key', $storageKey)->first();

        return $row instanceof MediaAsset ? $row : null;
    }
}
