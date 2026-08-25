<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * SMITE CMS V1 authorization baseline:
 * - Groups: docs/08-Technical-Architecture.md §27; CONTEXT.md; docs/03-Authorization-Security.md §4
 * - Permissions: docs/03-Authorization-Security.md §5 (recommended permission families)
 * - Matrix: derived from docs/03-Authorization-Security.md §4.1–4.3 and AUTHZ-001–007
 */

namespace Config;

use CodeIgniter\Shield\Config\AuthGroups as ShieldAuthGroups;

class AuthGroups extends ShieldAuthGroups
{
    /**
     * --------------------------------------------------------------------
     * Default Group
     * --------------------------------------------------------------------
     * Least-privileged application group for newly created staff accounts.
     * Public self-registration is not a V1 Control Panel path.
     */
    public string $defaultGroup = 'contributor';

    /**
     * --------------------------------------------------------------------
     * Groups
     * --------------------------------------------------------------------
     * Shield group keys (docs/08 §27): admin, editor, contributor.
     *
     * @var array<string, array<string, string>>
     */
    public array $groups = [
        'admin' => [
            'title'       => 'Admin',
            'description' => 'Single account with full system authority over users, settings, content, media, theme activation, and audit.',
        ],
        'editor' => [
            'title'       => 'Editor',
            'description' => 'May manage Pages and Posts within schema boundaries, publish Posts, review Contributor submissions, and manage categories, tags, and permitted media.',
        ],
        'contributor' => [
            'title'       => 'Contributor',
            'description' => 'May create Draft Posts, edit own Posts, submit Posts for review, and manage permitted own media. Cannot publish.',
        ],
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions
     * --------------------------------------------------------------------
     * Exact names from docs/03-Authorization-Security.md §5.
     */
    public array $permissions = [
        'site.manage' => 'Can manage Site Settings',
        'menu.manage' => 'Can manage the site Menu structure',

        'page.create'    => 'Can create Pages',
        'page.edit'      => 'Can edit Pages',
        'page.publish'   => 'Can publish Pages',
        'page.unpublish' => 'Can unpublish Pages',
        'page.archive'   => 'Can archive Pages',
        'page.restore'   => 'Can restore Page revisions / trash',
        'page.trash'     => 'Can move Pages to Trash',

        'post.create'        => 'Can create Posts',
        'post.edit_own'      => 'Can edit own Posts',
        'post.edit_any'      => 'Can edit any Post',
        'post.submit_review' => 'Can submit Posts for review',
        'post.review'        => 'Can review Contributor Post submissions',
        'post.publish'       => 'Can publish Posts',
        'post.unpublish'     => 'Can unpublish Posts',
        'post.archive'       => 'Can archive Posts',
        'post.restore'       => 'Can restore Post revisions / trash',
        'post.trash'         => 'Can move Posts to Trash',

        'category.manage' => 'Can manage Categories',
        'tag.manage'      => 'Can manage Tags',

        'media.upload'  => 'Can upload Media',
        'media.edit'    => 'Can edit Media',
        'media.delete'  => 'Can delete Media',
        'media.restore' => 'Can restore Media from Trash',

        'user.manage' => 'Can manage user accounts',

        'theme.preview'  => 'Can preview an ENABLED Theme without activating it',
        'theme.activate' => 'Can activate an ENABLED Theme',

        'audit.view' => 'Can view the Audit Trail',

        'content.permanent_delete' => 'Can permanently delete content',
    ];

    /**
     * --------------------------------------------------------------------
     * Permissions Matrix
     * --------------------------------------------------------------------
     * Admin: full authority (DOC-03 §4.1 / AUTHZ-003) via Shield family wildcards
     * over the documented permission families only.
     *
     * Editor / Contributor: capabilities from DOC-03 §4.2–4.3; ownership
     * enforcement remains a later Service-layer concern (AUTHZ-001/002).
     */
    public array $matrix = [
        'admin' => [
            'site.*',
            'menu.*',
            'page.*',
            'post.*',
            'category.*',
            'tag.*',
            'media.*',
            'user.*',
            'theme.*',
            'audit.*',
            'content.*',
        ],
        'editor' => [
            'page.create',
            'page.edit',
            'page.restore',
            'post.create',
            'post.edit_any',
            'post.review',
            'post.publish',
            'post.unpublish',
            'post.archive',
            'post.restore',
            'category.manage',
            'tag.manage',
            'media.upload',
            'media.edit',
            'media.delete',
            'media.restore',
        ],
        'contributor' => [
            'post.create',
            'post.edit_own',
            'post.submit_review',
            'media.upload',
            'media.edit',
            'media.delete',
            'media.restore',
        ],
    ];
}
