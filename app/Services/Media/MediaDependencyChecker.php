<?php

declare(strict_types=1);

namespace App\Services\Media;

use CodeIgniter\Database\BaseConnection;

/**
 * Media permanent-delete dependency checks (ADR-007 / ADR-018 / DOC-06).
 *
 * Targeted queries only — does not load all Pages/Posts into memory.
 */
final class MediaDependencyChecker
{
    public function __construct(
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return list<string> Human-readable dependency labels; empty when safe to delete.
     */
    public function findDependencies(int $mediaId): array
    {
        if ($mediaId < 1) {
            return [];
        }

        $deps = [];

        $featured = $this->db->table('posts')
            ->select('id')
            ->where('featured_image_id', $mediaId)
            ->limit(5)
            ->get()
            ->getResultArray();

        foreach ($featured as $row) {
            $deps[] = 'Post #' . (int) $row['id'] . ' featured image';
        }

        $this->scanPayloadTable($deps, 'page_translations', 'page_id', 'Page', $mediaId);
        $this->scanPayloadTable($deps, 'post_translations', 'post_id', 'Post', $mediaId);

        return $deps;
    }

    public function isReferenced(int $mediaId): bool
    {
        return $this->findDependencies($mediaId) !== [];
    }

    /**
     * @param list<string> $deps
     */
    private function scanPayloadTable(
        array &$deps,
        string $table,
        string $ownerColumn,
        string $label,
        int $mediaId,
    ): void {
        // Candidates: bare integer media_id ("hero_image":42) or object {"media_id":42}.
        $needles = [
            ':' . $mediaId,
            ':' . $mediaId . ',',
            ':' . $mediaId . '}',
            '"media_id":' . $mediaId,
            '"media_id": ' . $mediaId,
        ];

        $builder = $this->db->table($table)->select($ownerColumn . ', content_payload, id')->groupStart();
        foreach ($needles as $i => $needle) {
            if ($i === 0) {
                $builder->like('content_payload', $needle);
            } else {
                $builder->orLike('content_payload', $needle);
            }
        }
        $rows = $builder->groupEnd()->limit(50)->get()->getResultArray();

        foreach ($rows as $row) {
            $payload = $this->decode((string) ($row['content_payload'] ?? ''));
            if ($this->payloadContainsMediaId($payload, $mediaId)) {
                $deps[] = $label . ' #' . (int) $row[$ownerColumn] . ' content';
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $json): array
    {
        if ($json === '' || $json === '{}') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<mixed> $payload
     */
    private function payloadContainsMediaId(array $payload, int $mediaId): bool
    {
        foreach ($payload as $key => $value) {
            if (is_int($value) && $value === $mediaId) {
                return true;
            }
            if (is_string($value) && ctype_digit($value) && (int) $value === $mediaId) {
                return true;
            }

            if (is_array($value)) {
                if (array_key_exists('media_id', $value)) {
                    $id = $value['media_id'];
                    if (is_int($id) && $id === $mediaId) {
                        return true;
                    }
                    if (is_string($id) && ctype_digit($id) && (int) $id === $mediaId) {
                        return true;
                    }
                }

                if ($this->payloadContainsMediaId($value, $mediaId)) {
                    return true;
                }
            }

            unset($key);
        }

        return false;
    }
}
