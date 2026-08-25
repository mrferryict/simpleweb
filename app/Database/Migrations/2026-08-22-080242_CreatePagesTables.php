<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Page foundation tables (DOC-02 / DOC-08 hybrid model).
 *
 * pages = relational identity/hierarchy/status/template
 * page_translations = locale title/slug/content_payload
 *
 * Soft-trash via status=TRASH + deleted_at (DOC-02 §23 / REQ-PAGE-012).
 * ContentSchemaValidator / revisions / OCC deferred (Phase 3–4).
 */
class CreatePagesTables extends Migration
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
            'parent_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'default'    => 'DRAFT',
            ],
            'template_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
                'default'    => 'custom-page',
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
        $this->forge->addKey('parent_id');
        $this->forge->addKey('status');
        $this->forge->createTable('pages', true);

        $pages = $this->db->prefixTable('pages');
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                "ALTER TABLE {$pages}
                 ADD CONSTRAINT pages_parent_id_foreign
                 FOREIGN KEY (parent_id) REFERENCES {$pages} (id)
                 ON DELETE RESTRICT ON UPDATE RESTRICT",
            );
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'page_id' => [
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
        $this->forge->addKey(['page_id', 'locale'], false, true);
        $this->forge->addKey(['locale', 'slug'], false, true);
        $this->forge->createTable('page_translations', true);

        $translations = $this->db->prefixTable('page_translations');
        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query(
                "ALTER TABLE {$translations}
                 ADD CONSTRAINT page_translations_page_id_foreign
                 FOREIGN KEY (page_id) REFERENCES {$pages} (id)
                 ON DELETE CASCADE ON UPDATE RESTRICT",
            );
        }
    }

    public function down(): void
    {
        $pages        = $this->db->prefixTable('pages');
        $translations = $this->db->prefixTable('page_translations');

        if ($this->db->DBDriver === 'MySQLi') {
            $this->db->query("ALTER TABLE {$translations} DROP FOREIGN KEY page_translations_page_id_foreign");
            $this->db->query("ALTER TABLE {$pages} DROP FOREIGN KEY pages_parent_id_foreign");
        }

        $this->forge->dropTable('page_translations', true);
        $this->forge->dropTable('pages', true);
    }
}
