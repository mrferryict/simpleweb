<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Two-level Menu hierarchy (DOC-01 REQ-MENU-002).
 *
 * parent_id NULL = level 1; non-null = level 2 under a top-level item.
 * MySQL FK RESTRICT mirrors Service rejection of deleting parents with children.
 * Raw SQL uses prefixTable() so PHPUnit SQLite (DBPrefix db_) stays consistent.
 */
class AddParentIdToMenuItems extends Migration
{
    public function up(): void
    {
        $table = $this->db->prefixTable('menu_items');

        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query("ALTER TABLE {$table} ADD COLUMN parent_id INTEGER NULL");
            $this->db->query("CREATE INDEX IF NOT EXISTS menu_items_parent_id ON {$table} (parent_id)");

            return;
        }

        $this->forge->addColumn('menu_items', [
            'parent_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'location',
            ],
        ]);

        $this->db->query("CREATE INDEX menu_items_parent_id ON {$table} (parent_id)");
        $this->db->query(
            "ALTER TABLE {$table}
             ADD CONSTRAINT menu_items_parent_id_foreign
             FOREIGN KEY (parent_id) REFERENCES {$table} (id)
             ON DELETE RESTRICT ON UPDATE RESTRICT",
        );
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('menu_items');

        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('DROP INDEX IF EXISTS menu_items_parent_id');
            $this->forge->dropColumn('menu_items', 'parent_id');

            return;
        }

        $this->db->query("ALTER TABLE {$table} DROP FOREIGN KEY menu_items_parent_id_foreign");
        $this->db->query("ALTER TABLE {$table} DROP INDEX menu_items_parent_id");
        $this->forge->dropColumn('menu_items', 'parent_id');
    }
}
