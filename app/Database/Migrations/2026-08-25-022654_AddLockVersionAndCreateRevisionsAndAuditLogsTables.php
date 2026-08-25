<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ADR-019: lock_version on pages/posts + revisions + audit_logs (Phase 4 / Task 4.9B).
 */
class AddLockVersionAndCreateRevisionsAndAuditLogsTables extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('pages', [
            'lock_version' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 1,
                'after'      => 'template_key',
            ],
        ]);

        $this->forge->addColumn('posts', [
            'lock_version' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 1,
                'after'      => 'created_by',
            ],
        ]);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'resource_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
            ],
            'resource_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'revision_number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'is_autosave' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 0,
            ],
            'snapshot' => [
                'type' => 'LONGTEXT',
                'null' => false,
            ],
            'created_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['resource_type', 'resource_id', 'revision_number'], 'revisions_resource_rev_unique');
        $this->forge->addKey(['resource_type', 'resource_id', 'created_at'], false, false, 'revisions_resource_idx');
        $this->forge->addKey(['resource_type', 'resource_id', 'is_autosave', 'created_at'], false, false, 'revisions_resource_autosave_idx');
        $this->forge->addKey('created_by', false, false, 'revisions_created_by_idx');
        $this->forge->createTable('revisions', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'actor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'event' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'resource_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
                'default'    => null,
            ],
            'resource_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'revision_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
            'metadata' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['event', 'created_at']);
        $this->forge->addKey(['resource_type', 'resource_id', 'created_at']);
        $this->forge->addKey(['actor_id', 'created_at']);
        $this->forge->addKey('revision_id');
        $this->forge->createTable('audit_logs', true);

        if ($this->db->DBDriver === 'MySQLi') {
            $users     = $this->db->prefixTable('users');
            $revisions = $this->db->prefixTable('revisions');
            $audits    = $this->db->prefixTable('audit_logs');

            $this->db->query(
                "ALTER TABLE {$revisions}
                 ADD CONSTRAINT revisions_created_by_foreign
                 FOREIGN KEY (created_by) REFERENCES {$users} (id)
                 ON DELETE SET NULL ON UPDATE RESTRICT",
            );
            $this->db->query(
                "ALTER TABLE {$audits}
                 ADD CONSTRAINT audit_logs_actor_id_foreign
                 FOREIGN KEY (actor_id) REFERENCES {$users} (id)
                 ON DELETE SET NULL ON UPDATE RESTRICT",
            );
            $this->db->query(
                "ALTER TABLE {$audits}
                 ADD CONSTRAINT audit_logs_revision_id_foreign
                 FOREIGN KEY (revision_id) REFERENCES {$revisions} (id)
                 ON DELETE SET NULL ON UPDATE RESTRICT",
            );
        }
    }

    public function down(): void
    {
        if ($this->db->DBDriver === 'MySQLi') {
            $revisions = $this->db->prefixTable('revisions');
            $audits    = $this->db->prefixTable('audit_logs');
            @$this->db->query("ALTER TABLE {$audits} DROP FOREIGN KEY audit_logs_revision_id_foreign");
            @$this->db->query("ALTER TABLE {$audits} DROP FOREIGN KEY audit_logs_actor_id_foreign");
            @$this->db->query("ALTER TABLE {$revisions} DROP FOREIGN KEY revisions_created_by_foreign");
        }

        $this->forge->dropTable('audit_logs', true);
        $this->forge->dropTable('revisions', true);

        // SQLite test refresh cannot reliably drop columns; tables are rebuilt on migrate.
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        if ($this->db->fieldExists('lock_version', 'posts')) {
            $this->forge->dropColumn('posts', 'lock_version');
        }
        if ($this->db->fieldExists('lock_version', 'pages')) {
            $this->forge->dropColumn('pages', 'lock_version');
        }
    }
}
