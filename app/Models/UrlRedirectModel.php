<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\UrlRedirect;
use CodeIgniter\Model;

/**
 * Persistence for url_redirects (ADR-024 / DOC-07 §19).
 */
class UrlRedirectModel extends Model
{
    protected $table            = 'url_redirects';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = UrlRedirect::class;
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;

    /** @var list<string> */
    protected $allowedFields = [
        'source_path',
        'target_path',
        'http_code',
        'resource_type',
        'resource_id',
        'locale',
        'active',
    ];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    public function findActiveBySourcePath(string $sourcePath): ?UrlRedirect
    {
        /** @var UrlRedirect|null $row */
        $row = $this->where('source_path', $sourcePath)
            ->where('active', 1)
            ->first();

        return $row instanceof UrlRedirect ? $row : null;
    }

    /**
     * @return list<UrlRedirect>
     */
    public function findActiveByTargetPath(string $targetPath): array
    {
        /** @var list<UrlRedirect> $rows */
        $rows = $this->where('target_path', $targetPath)
            ->where('active', 1)
            ->findAll();

        return $rows;
    }

    public function isActiveSourceReserved(string $sourcePath, ?int $exceptId = null): bool
    {
        $builder = $this->where('source_path', $sourcePath)->where('active', 1);
        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * @return list<string>
     */
    public function listActiveSourcePaths(): array
    {
        $rows = $this->select('source_path')
            ->where('active', 1)
            ->findAll();

        $paths = [];
        foreach ($rows as $row) {
            if ($row instanceof UrlRedirect) {
                $paths[] = (string) $row->source_path;
            }
        }

        return $paths;
    }
}
