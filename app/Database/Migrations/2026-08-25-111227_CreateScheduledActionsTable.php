<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ADR-021: scheduled_actions (Phase 4 / Task 4.12C).
 *
 * MariaDB DDL matches ADR-021 §5 including generated pending_guard.
 * SQLite uses the same uniqueness strategy (generated CASE + unique index)
 * so PHPUnit's in-memory driver can exercise the contract.
 */
class CreateScheduledActionsTable extends Migration
{
    public function up(): void
    {
        if ($this->db->DBDriver === 'SQLite3') {
            $this->upSqlite();

            return;
        }

        $this->upMariaDb();
    }

    public function down(): void
    {
        $this->forge->dropTable('scheduled_actions', true);
    }

    private function upMariaDb(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'target_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
            ],
            'target_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'action_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 16,
                'null'       => false,
            ],
            'execute_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => false,
                'default'    => 'PENDING',
            ],
            'claimed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'lease_until' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'processed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'result_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'default'    => null,
            ],
            'result_message' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 0,
            ],
            'last_error' => [
                'type' => 'TEXT',
                'null' => true,
                'default' => null,
            ],
            'failed_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
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
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['status', 'execute_at'], false, false, 'idx_due');
        $this->forge->addKey(['target_type', 'target_id'], false, false, 'idx_target');
        $this->forge->addKey('created_by', false, false, 'idx_created_by');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', 'SET NULL', 'fk_scheduled_actions_created_by');
        $this->forge->createTable('scheduled_actions', true);

        $table = $this->db->prefixTable('scheduled_actions');
        $this->db->query(
            'ALTER TABLE `' . $table . '` ADD COLUMN `pending_guard` TINYINT '
            . "GENERATED ALWAYS AS (IF(`status` = 'PENDING', 1, NULL)) STORED",
        );
        $this->db->query(
            'ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uq_scheduled_pending` '
            . '(`target_type`, `target_id`, `action_type`, `execute_at`, `pending_guard`)',
        );
    }

    private function upSqlite(): void
    {
        $table = $this->db->prefixTable('scheduled_actions');
        $this->db->query(
            'CREATE TABLE `' . $table . '` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                `target_type` VARCHAR(16) NOT NULL,
                `target_id` INTEGER NOT NULL,
                `action_type` VARCHAR(16) NOT NULL,
                `execute_at` DATETIME NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
                `claimed_at` DATETIME DEFAULT NULL,
                `lease_until` DATETIME DEFAULT NULL,
                `processed_at` DATETIME DEFAULT NULL,
                `result_code` VARCHAR(50) DEFAULT NULL,
                `result_message` TEXT DEFAULT NULL,
                `attempts` INTEGER NOT NULL DEFAULT 0,
                `last_error` TEXT DEFAULT NULL,
                `failed_at` DATETIME DEFAULT NULL,
                `created_by` INTEGER DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                `pending_guard` INTEGER GENERATED ALWAYS AS (
                    CASE WHEN `status` = \'PENDING\' THEN 1 ELSE NULL END
                ) STORED
            )',
        );
        $this->db->query(
            'CREATE UNIQUE INDEX `uq_scheduled_pending` ON `' . $table . '` '
            . '(`target_type`, `target_id`, `action_type`, `execute_at`, `pending_guard`)',
        );
        $this->db->query(
            'CREATE INDEX `idx_due` ON `' . $table . '` (`status`, `execute_at`)',
        );
        $this->db->query(
            'CREATE INDEX `idx_target` ON `' . $table . '` (`target_type`, `target_id`)',
        );
        $this->db->query(
            'CREATE INDEX `idx_created_by` ON `' . $table . '` (`created_by`)',
        );
    }
}
