<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Phase 7 / Task 7.1B — SEO columns on translation tables + url_redirects (ADR-024 / DOC-07).
 */
class AddLocalizationSeoAndUrlRedirects extends Migration
{
    public function up(): void
    {
        $seoFields = [
            'meta_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'after'      => 'content_payload',
            ],
            'meta_description' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
            ],
            'canonical_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
            ],
            'og_image_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ];

        foreach (['page_translations', 'post_translations'] as $table) {
            $this->forge->addColumn($table, $seoFields);
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'source_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => false,
            ],
            'target_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => false,
            ],
            'http_code' => [
                'type'       => 'SMALLINT',
                'constraint' => 3,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 301,
            ],
            'resource_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
            ],
            'resource_id' => [
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
            'active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 1,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('source_path');
        $this->forge->addKey(['active', 'source_path']);
        $this->forge->addKey(['resource_type', 'resource_id']);
        $this->forge->createTable('url_redirects', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('url_redirects', true);

        if ($this->db->DBDriver === 'SQLite3') {
            // SQLite cannot drop columns reliably; older migration downs recreate tables.
            return;
        }

        foreach (['page_translations', 'post_translations'] as $table) {
            $this->forge->dropColumn($table, 'meta_title');
            $this->forge->dropColumn($table, 'meta_description');
            $this->forge->dropColumn($table, 'canonical_url');
            $this->forge->dropColumn($table, 'og_image_id');
        }
    }
}
