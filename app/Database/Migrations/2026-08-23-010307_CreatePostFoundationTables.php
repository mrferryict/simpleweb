<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Post foundation tables (Phase 3 / Task 3.7).
 *
 * Sources:
 * - posts + post_translations (DOC-08 §16 / §19)
 * - manual_author (REQ-POST-006); featured_image_id nullable (DOC-08 §17 / REQ-POST-009)
 * - created_by for edit_own ownership (DOC-03 AUTHZ-001)
 * - soft trash: status + deleted_at (DOC-02 §32 / REQ-POST-013)
 * - categories / tags flat (REQ-CAT-001 / REQ-TAG-001); category is_active for deactivate/restore (REQ-CAT-002)
 * - post_categories / post_tags many-to-many (DOC-02 §10)
 *
 * No FK to media_assets (table not in foundation). No post template_key (not documented).
 * INT PKs match existing pages/menu migrations (DOC-02 §31 BIGINT deferred for consistency).
 */
class CreatePostFoundationTables extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'default'    => 'DRAFT',
            ],
            'manual_author' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
                'default'    => '',
            ],
            'featured_image_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('created_by');
        $this->forge->createTable('posts', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'post_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'locale' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'content_payload' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['post_id', 'locale'], false, true);
        $this->forge->addKey(['locale', 'slug'], false, true);
        $this->forge->createTable('post_translations', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('slug', false, true);
        $this->forge->addKey('is_active');
        $this->forge->createTable('categories', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
                'null'       => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('slug', false, true);
        $this->forge->createTable('tags', true);

        $this->forge->addField([
            'post_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'category_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
        $this->forge->addKey(['post_id', 'category_id'], true);
        $this->forge->addKey('category_id');
        $this->forge->createTable('post_categories', true);

        $this->forge->addField([
            'post_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'tag_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
        ]);
        $this->forge->addKey(['post_id', 'tag_id'], true);
        $this->forge->addKey('tag_id');
        $this->forge->createTable('post_tags', true);

        if ($this->db->DBDriver === 'MySQLi') {
            $posts         = $this->db->prefixTable('posts');
            $translations  = $this->db->prefixTable('post_translations');
            $categories    = $this->db->prefixTable('categories');
            $tags          = $this->db->prefixTable('tags');
            $postCategories = $this->db->prefixTable('post_categories');
            $postTags      = $this->db->prefixTable('post_tags');

            $this->db->query(
                "ALTER TABLE {$translations}
                 ADD CONSTRAINT post_translations_post_id_foreign
                 FOREIGN KEY (post_id) REFERENCES {$posts} (id)
                 ON DELETE CASCADE ON UPDATE RESTRICT",
            );
            $this->db->query(
                "ALTER TABLE {$postCategories}
                 ADD CONSTRAINT post_categories_post_id_foreign
                 FOREIGN KEY (post_id) REFERENCES {$posts} (id)
                 ON DELETE CASCADE ON UPDATE RESTRICT,
                 ADD CONSTRAINT post_categories_category_id_foreign
                 FOREIGN KEY (category_id) REFERENCES {$categories} (id)
                 ON DELETE RESTRICT ON UPDATE RESTRICT",
            );
            $this->db->query(
                "ALTER TABLE {$postTags}
                 ADD CONSTRAINT post_tags_post_id_foreign
                 FOREIGN KEY (post_id) REFERENCES {$posts} (id)
                 ON DELETE CASCADE ON UPDATE RESTRICT,
                 ADD CONSTRAINT post_tags_tag_id_foreign
                 FOREIGN KEY (tag_id) REFERENCES {$tags} (id)
                 ON DELETE RESTRICT ON UPDATE RESTRICT",
            );
        }
    }

    public function down(): void
    {
        $posts          = $this->db->prefixTable('posts');
        $translations   = $this->db->prefixTable('post_translations');
        $postCategories = $this->db->prefixTable('post_categories');
        $postTags       = $this->db->prefixTable('post_tags');

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query("ALTER TABLE {$postTags} DROP FOREIGN KEY post_tags_tag_id_foreign");
            $this->db->query("ALTER TABLE {$postTags} DROP FOREIGN KEY post_tags_post_id_foreign");
            $this->db->query("ALTER TABLE {$postCategories} DROP FOREIGN KEY post_categories_category_id_foreign");
            $this->db->query("ALTER TABLE {$postCategories} DROP FOREIGN KEY post_categories_post_id_foreign");
            $this->db->query("ALTER TABLE {$translations} DROP FOREIGN KEY post_translations_post_id_foreign");
        }

        $this->forge->dropTable('post_tags', true);
        $this->forge->dropTable('post_categories', true);
        $this->forge->dropTable('tags', true);
        $this->forge->dropTable('categories', true);
        $this->forge->dropTable('post_translations', true);
        $this->forge->dropTable('posts', true);
    }
}
