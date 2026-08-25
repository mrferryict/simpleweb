<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Menu destination types (DOC-01 REQ-MENU-003).
 *
 * Existing free-form `destination` values are treated as EXTERNAL_URL.
 * Page / Post Category use deferred numeric target_id (modules not yet present).
 */
class AddMenuTargetTypeColumns extends Migration
{
    public function up(): void
    {
        $table = $this->db->prefixTable('menu_items');

        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query(
                "ALTER TABLE {$table} ADD COLUMN target_type VARCHAR(32) NOT NULL DEFAULT 'EXTERNAL_URL'",
            );
            $this->db->query("ALTER TABLE {$table} ADD COLUMN target_id INTEGER NULL");
            $this->db->query("CREATE INDEX IF NOT EXISTS menu_items_target_type ON {$table} (target_type)");
            $this->db->query("CREATE INDEX IF NOT EXISTS menu_items_target_id ON {$table} (target_id)");

            return;
        }

        $this->forge->addColumn('menu_items', [
            'target_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'default'    => 'EXTERNAL_URL',
                'after'      => 'label',
            ],
            'target_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'target_type',
            ],
        ]);

        $this->db->query("CREATE INDEX menu_items_target_type ON {$table} (target_type)");
        $this->db->query("CREATE INDEX menu_items_target_id ON {$table} (target_id)");

        // Preserve existing rows: destination strings remain EXTERNAL_URL (column default).
        $this->db->query(
            "UPDATE {$table} SET target_type = 'EXTERNAL_URL' WHERE target_type IS NULL OR target_type = ''",
        );
    }

    public function down(): void
    {
        $table = $this->db->prefixTable('menu_items');

        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('DROP INDEX IF EXISTS menu_items_target_type');
            $this->db->query('DROP INDEX IF EXISTS menu_items_target_id');
            $this->forge->dropColumn('menu_items', 'target_id');
            $this->forge->dropColumn('menu_items', 'target_type');

            return;
        }

        $this->db->query("ALTER TABLE {$table} DROP INDEX menu_items_target_type");
        $this->db->query("ALTER TABLE {$table} DROP INDEX menu_items_target_id");
        $this->forge->dropColumn('menu_items', ['target_id', 'target_type']);
    }
}
