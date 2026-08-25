<?php

declare(strict_types=1);

namespace App\Services\Revision;

use App\Entities\Page;
use App\Entities\PageTranslation;
use App\Entities\Post;
use App\Entities\PostTranslation;
use App\Entities\Revision;
use App\Enums\AuditEvent;
use App\Enums\RevisionResourceType;
use App\Models\PageModel;
use App\Models\PageTranslationModel;
use App\Models\PostModel;
use App\Models\PostTranslationModel;
use App\Models\RevisionModel;
use App\Services\Audit\AuditService;
use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Revision persistence foundation (ADR-019).
 *
 * Insert-only; no generic CRUD. Snapshot JSON schema_version = 1.
 */
class RevisionService
{
    public function __construct(
        private readonly RevisionModel $revisionModel,
        private readonly PostModel $postModel,
        private readonly PostTranslationModel $postTranslationModel,
        private readonly PageModel $pageModel,
        private readonly PageTranslationModel $pageTranslationModel,
        private readonly AuditService $auditService,
        private readonly BaseConnection $db,
    ) {
    }

    public function findById(int $id): ?Revision
    {
        if ($id < 1) {
            return null;
        }

        $row = $this->revisionModel->find($id);

        return $row instanceof Revision ? $row : null;
    }

    /**
     * Editorial history only (is_autosave = 0).
     *
     * @return list<Revision>
     */
    public function listEditorial(RevisionResourceType $type, int $resourceId): array
    {
        /** @var list<Revision> $rows */
        $rows = $this->revisionModel
            ->where('resource_type', $type->value)
            ->where('resource_id', $resourceId)
            ->where('is_autosave', 0)
            ->orderBy('revision_number', 'DESC')
            ->findAll();

        return $rows;
    }

    /**
     * Presentation rows for Control Panel history (no snapshot JSON).
     *
     * @return list<array{
     *     id: int,
     *     revision_number: int,
     *     is_autosave: bool,
     *     created_at: string,
     *     actor_label: string
     * }>
     */
    public function listEditorialHistory(RevisionResourceType $type, int $resourceId): array
    {
        $rows = $this->listEditorial($type, $resourceId);
        if ($rows === []) {
            return [];
        }

        $actorIds = [];
        foreach ($rows as $row) {
            if ($row->created_by !== null && (int) $row->created_by > 0) {
                $actorIds[] = (int) $row->created_by;
            }
        }
        $actorIds = array_values(array_unique($actorIds));

        $usernames = [];
        if ($actorIds !== []) {
            $userRows = $this->db->table('users')
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
            $actorId = $row->created_by !== null ? (int) $row->created_by : 0;
            $label   = 'System';
            if ($actorId > 0) {
                $name = trim($usernames[$actorId] ?? '');
                $label = $name !== '' ? $name : ('User #' . $actorId);
            }

            $created = $row->created_at;
            $createdStr = is_object($created) && method_exists($created, 'format')
                ? $created->format('Y-m-d H:i:s')
                : (string) $created;

            $out[] = [
                'id'              => (int) $row->id,
                'revision_number' => (int) $row->revision_number,
                'is_autosave'     => ((int) $row->is_autosave) === 1,
                'created_at'      => $createdStr,
                'actor_label'     => $label,
            ];
        }

        return $out;
    }

    /**
     * Latest autosave revision for a resource (is_autosave = 1), if any.
     */
    public function findLatestAutosave(RevisionResourceType $type, int $resourceId): ?Revision
    {
        $row = $this->revisionModel
            ->where('resource_type', $type->value)
            ->where('resource_id', $resourceId)
            ->where('is_autosave', 1)
            ->orderBy('revision_number', 'DESC')
            ->first();

        return $row instanceof Revision ? $row : null;
    }

    /**
     * Capture live resource state into an editorial revision and matching audit event.
     * Caller must already be inside a DB transaction with the parent row locked.
     */
    public function recordEditorialFromLive(
        RevisionResourceType $type,
        int $resourceId,
        AuditEvent $event,
        ?int $actorId,
        ?array $metadata = null,
    ): Revision {
        $snapshot = match ($type) {
            RevisionResourceType::Post => $this->buildPostSnapshot($resourceId),
            RevisionResourceType::Page => $this->buildPageSnapshot($resourceId),
        };

        $revision = $this->insertRevision($type, $resourceId, $snapshot, false, $actorId);
        (void) $this->auditService->append(
            $event,
            $actorId,
            $type->value,
            $resourceId,
            (int) $revision->id,
            $metadata,
        );

        return $revision;
    }

    /**
     * Autosave snapshot (is_autosave = 1). No audit. Does not mutate live rows.
     *
     * @param array<string, mixed> $snapshot Already schema_version=1 shaped snapshot
     */
    public function recordAutosave(
        RevisionResourceType $type,
        int $resourceId,
        array $snapshot,
        ?int $actorId,
    ): Revision {
        return $this->insertRevision($type, $resourceId, $snapshot, true, $actorId);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPostSnapshot(int $postId): array
    {
        $post = $this->postModel->find($postId);
        if (! $post instanceof Post) {
            throw new RuntimeException('Post not found for snapshot.');
        }

        /** @var list<PostTranslation> $translations */
        $translations = $this->postTranslationModel
            ->where('post_id', $postId)
            ->orderBy('locale', 'ASC')
            ->findAll();

        $translationMap = [];
        foreach ($translations as $row) {
            $locale = strtolower(trim((string) $row->locale));
            if ($locale !== 'id' && $locale !== 'en') {
                continue;
            }
            $payload = json_decode((string) $row->content_payload, true);
            if (! is_array($payload)) {
                $payload = [];
            }
            $translationMap[$locale] = [
                'title'           => (string) $row->title,
                'slug'            => (string) $row->slug,
                'content_payload' => $payload,
            ];
        }

        $categoryIds = $this->orderedIntIds(
            $this->db->table('post_categories')
                ->select('category_id')
                ->where('post_id', $postId)
                ->orderBy('category_id', 'ASC')
                ->get()
                ->getResultArray(),
            'category_id',
        );
        $tagIds = $this->orderedIntIds(
            $this->db->table('post_tags')
                ->select('tag_id')
                ->where('post_id', $postId)
                ->orderBy('tag_id', 'ASC')
                ->get()
                ->getResultArray(),
            'tag_id',
        );

        $featured = $post->featured_image_id;
        $featuredId = is_numeric($featured) && (int) $featured > 0 ? (int) $featured : null;

        return [
            'schema_version'    => 1,
            'resource_type'     => RevisionResourceType::Post->value,
            'resource_id'       => $postId,
            'captured_at'       => $this->nowIso(),
            'status'            => (string) $post->status,
            'manual_author'     => (string) $post->manual_author,
            'featured_image_id' => $featuredId,
            'category_ids'      => $categoryIds,
            'tag_ids'           => $tagIds,
            'translations'      => $translationMap,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPageSnapshot(int $pageId): array
    {
        $page = $this->pageModel->find($pageId);
        if (! $page instanceof Page) {
            throw new RuntimeException('Page not found for snapshot.');
        }

        /** @var list<PageTranslation> $translations */
        $translations = $this->pageTranslationModel
            ->where('page_id', $pageId)
            ->orderBy('locale', 'ASC')
            ->findAll();

        $translationMap = [];
        foreach ($translations as $row) {
            $locale = strtolower(trim((string) $row->locale));
            if ($locale !== 'id' && $locale !== 'en') {
                continue;
            }
            $payload = json_decode((string) $row->content_payload, true);
            if (! is_array($payload)) {
                $payload = [];
            }
            $translationMap[$locale] = [
                'title'           => (string) $row->title,
                'slug'            => (string) $row->slug,
                'content_payload' => $payload,
            ];
        }

        $parent = $page->parent_id;

        return [
            'schema_version' => 1,
            'resource_type'  => RevisionResourceType::Page->value,
            'resource_id'    => $pageId,
            'captured_at'    => $this->nowIso(),
            'status'         => (string) $page->status,
            'template_key'   => (string) $page->template_key,
            'parent_id'      => is_numeric($parent) && (int) $parent > 0 ? (int) $parent : null,
            'translations'   => $translationMap,
        ];
    }

    /**
     * Next revision_number under lock (caller must hold parent FOR UPDATE).
     */
    public function nextRevisionNumber(RevisionResourceType $type, int $resourceId): int
    {
        $row = $this->db->table('revisions')
            ->select('revision_number')
            ->where('resource_type', $type->value)
            ->where('resource_id', $resourceId)
            ->orderBy('revision_number', 'DESC')
            ->limit(1)
            ->get()
            ->getRowArray();

        if ($row === null || ! isset($row['revision_number'])) {
            return 1;
        }

        return ((int) $row['revision_number']) + 1;
    }

    /**
     * Conditional OCC bump. Returns false on stale version.
     */
    public function bumpLockVersion(string $table, int $id, int $expectedLockVersion): bool
    {
        if ($table !== 'pages' && $table !== 'posts') {
            throw new RuntimeException('Invalid OCC table.');
        }

        $this->db->table($table)
            ->set('lock_version', 'lock_version + 1', false)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('id', $id)
            ->where('lock_version', $expectedLockVersion)
            ->update();

        return $this->db->affectedRows() === 1;
    }

    /**
     * Lock parent row for update inside an open transaction.
     *
     * @return array<string, mixed>|null
     */
    public function lockParentRow(string $table, int $id): ?array
    {
        if ($table !== 'pages' && $table !== 'posts') {
            throw new RuntimeException('Invalid lock table.');
        }

        // SQLite (PHPUnit) does not support SELECT … FOR UPDATE the same way as MariaDB.
        if ($this->db->DBDriver === 'SQLite3') {
            $row = $this->db->table($table)->where('id', $id)->get()->getRowArray();

            return is_array($row) ? $row : null;
        }

        $result = $this->db->query(
            'SELECT * FROM ' . $this->db->prefixTable($table) . ' WHERE id = ? FOR UPDATE',
            [$id],
        );
        if ($result === false) {
            return null;
        }

        $row = $result->getRowArray();

        return is_array($row) ? $row : null;
    }

    public function currentLockVersion(string $table, int $id): ?int
    {
        $row = $this->db->table($table)->select('lock_version')->where('id', $id)->get()->getRowArray();
        if ($row === null || ! isset($row['lock_version'])) {
            return null;
        }

        return (int) $row['lock_version'];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function insertRevision(
        RevisionResourceType $type,
        int $resourceId,
        array $snapshot,
        bool $isAutosave,
        ?int $actorId,
    ): Revision {
        $number = $this->nextRevisionNumber($type, $resourceId);
        $json   = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $id = $this->revisionModel->insert([
            'resource_type'   => $type->value,
            'resource_id'     => $resourceId,
            'revision_number' => $number,
            'is_autosave'     => $isAutosave ? 1 : 0,
            'snapshot'        => $json,
            'created_by'      => $actorId !== null && $actorId > 0 ? $actorId : null,
            'created_at'      => date('Y-m-d H:i:s'),
        ], true);

        if (! is_numeric($id)) {
            throw new RuntimeException('Unable to create revision.');
        }

        $row = $this->revisionModel->find((int) $id);
        if (! $row instanceof Revision) {
            throw new RuntimeException('Unable to load revision.');
        }

        return $row;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<int>
     */
    private function orderedIntIds(array $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! isset($row[$key]) || ! is_numeric($row[$key])) {
                continue;
            }
            $id = (int) $row[$key];
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }

    private function nowIso(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get())))
            ->format(DateTimeImmutable::ATOM);
    }
}
