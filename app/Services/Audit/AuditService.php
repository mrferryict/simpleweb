<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Entities\AuditLog;
use App\Enums\AuditEvent;
use App\Models\AuditLogModel;
use RuntimeException;

/**
 * Append-only audit trail (ADR-019 §11).
 */
class AuditService
{
    public function __construct(
        private readonly AuditLogModel $model,
    ) {
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    #[\NoDiscard]
    public function append(
        AuditEvent $event,
        ?int $actorId,
        ?string $resourceType,
        ?int $resourceId,
        ?int $revisionId = null,
        ?array $metadata = null,
    ): AuditLog {
        $metaJson = null;
        if ($metadata !== null) {
            $metaJson = json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        $id = $this->model->insert([
            'actor_id'      => $actorId !== null && $actorId > 0 ? $actorId : null,
            'event'         => $event->value,
            'resource_type' => $resourceType,
            'resource_id'   => $resourceId !== null && $resourceId > 0 ? $resourceId : null,
            'revision_id'   => $revisionId !== null && $revisionId > 0 ? $revisionId : null,
            'metadata'      => $metaJson,
            'created_at'    => date('Y-m-d H:i:s'),
        ], true);

        if (! is_numeric($id)) {
            throw new RuntimeException('Unable to append audit log.');
        }

        $row = $this->model->find((int) $id);
        if (! $row instanceof AuditLog) {
            throw new RuntimeException('Unable to load audit log.');
        }

        return $row;
    }

    /**
     * Read-only Control Panel list (ADR-019 / AUTHZ-007). Newest first.
     * Does not expose metadata or snapshot contents.
     *
     * @return list<array{
     *     id: int,
     *     event: string,
     *     actor_label: string,
     *     resource_type: string,
     *     resource_id: string,
     *     revision_id: string,
     *     created_at: string
     * }>
     */
    public function listRecentForAdmin(int $limit = 100): array
    {
        $limit = max(1, min($limit, 100));

        /** @var list<AuditLog> $rows */
        $rows = $this->model
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($limit);

        if ($rows === []) {
            return [];
        }

        $actorIds = [];
        foreach ($rows as $row) {
            if ($row->actor_id !== null && (int) $row->actor_id > 0) {
                $actorIds[] = (int) $row->actor_id;
            }
        }
        $actorIds = array_values(array_unique($actorIds));

        $usernames = [];
        if ($actorIds !== [] && db_connect()->tableExists('users')) {
            $userRows = db_connect()->table('users')
                ->select('id, username')
                ->whereIn('id', $actorIds)
                ->get()
                ->getResultArray();
            foreach ($userRows as $userRow) {
                $usernames[(int) $userRow['id']] = (string) ($userRow['username'] ?? '');
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $actorId = $row->actor_id !== null ? (int) $row->actor_id : 0;
            $label   = 'System';
            if ($actorId > 0) {
                $name  = trim($usernames[$actorId] ?? '');
                $label = $name !== '' ? $name : ('User #' . $actorId);
            }

            $created = $row->created_at;
            $createdStr = is_object($created) && method_exists($created, 'format')
                ? $created->format('Y-m-d H:i:s')
                : (string) $created;

            $out[] = [
                'id'            => (int) $row->id,
                'event'         => (string) $row->event,
                'actor_label'   => $label,
                'resource_type' => $row->resource_type !== null && $row->resource_type !== ''
                    ? (string) $row->resource_type
                    : '—',
                'resource_id'   => $row->resource_id !== null && (int) $row->resource_id > 0
                    ? (string) (int) $row->resource_id
                    : '—',
                'revision_id'   => $row->revision_id !== null && (int) $row->revision_id > 0
                    ? (string) (int) $row->revision_id
                    : '—',
                'created_at'    => $createdStr,
            ];
        }

        return $out;
    }
}
