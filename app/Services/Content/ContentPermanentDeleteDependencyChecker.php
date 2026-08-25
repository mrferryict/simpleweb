<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\MenuTargetType;
use App\Enums\PageStatus;
use CodeIgniter\Database\BaseConnection;

/**
 * Blocking dependency checks before Page/Post permanent delete (DOC-04 §19).
 *
 * Classification used by this checker:
 * - BLOCKING: child Pages (parent_id); Menu items targeting PAGE
 * - OWNED (cleaned by Service, not blockers): translations, post_categories, post_tags
 * - INDEPENDENT (not blockers): Media / featured_image_id / content media_id
 *
 * Menu does not target Post (REQ-MENU-003: PAGE | POST_CATEGORY | EXTERNAL_URL).
 */
final class ContentPermanentDeleteDependencyChecker
{
    public function __construct(
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * @return list<string> Human-readable labels; empty when safe to permanently delete.
     */
    public function findPageDependencies(int $pageId): array
    {
        if ($pageId < 1) {
            return [];
        }

        $deps = [];

        // Child Pages (any status) block permanent delete of the parent.
        $allChildren = $this->db->table('pages')
            ->select('id, status')
            ->where('parent_id', $pageId)
            ->limit(10)
            ->get()
            ->getResultArray();

        foreach ($allChildren as $row) {
            $status = (string) ($row['status'] ?? '');
            $label  = $status === PageStatus::Trash->value
                ? 'Child Page #' . (int) $row['id'] . ' (TRASH)'
                : 'Child Page #' . (int) $row['id'];
            $deps[] = $label;
        }

        $menuRows = $this->db->table('menu_items')
            ->select('id')
            ->where('target_type', MenuTargetType::Page->value)
            ->where('target_id', $pageId)
            ->limit(10)
            ->get()
            ->getResultArray();

        foreach ($menuRows as $row) {
            $deps[] = 'Menu item #' . (int) $row['id'];
        }

        return $deps;
    }

    /**
     * @return list<string>
     */
    public function findPostDependencies(int $postId): array
    {
        if ($postId < 1) {
            return [];
        }

        // V1 Menu has no POST target type (REQ-MENU-003). Owned pivots/translations
        // are cleaned transactionally by PostService — not blockers.
        return [];
    }
}
