<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * ADR-008 / ADR-026 — User email PII columns (ciphertext + lookup HMAC).
 *
 * Requires Shield `users` table. SQLite PHPUnit suites that omit Shield
 * migrations skip this change (no `users` table) so App schema tests stay green.
 */
class AddUserEmailPiiColumns extends Migration
{
    public function up(): void
    {
        if (! $this->db->tableExists('users')) {
            return;
        }

        if (! $this->db->fieldExists('email_ciphertext', 'users')) {
            $fields = [
                'email_ciphertext' => [
                    'type' => 'TEXT',
                    'null' => true,
                    'default' => null,
                ],
                'email_lookup_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                    'default'    => null,
                ],
            ];

            if ($this->db->DBDriver === 'MySQLi') {
                $fields['email_ciphertext']['after'] = 'username';
                $fields['email_lookup_hash']['after'] = 'email_ciphertext';
            }

            $this->forge->addColumn('users', $fields);
        }

        $table = $this->db->prefixTable('users');
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query(
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_users_email_lookup_hash ON '
                . $table . ' (email_lookup_hash)',
            );

            return;
        }

        $indexes = $this->db->getIndexData('users');
        if (! array_key_exists('uq_users_email_lookup_hash', $indexes)) {
            $this->db->query(
                'CREATE UNIQUE INDEX uq_users_email_lookup_hash ON '
                . $table . ' (email_lookup_hash)',
            );
        }
    }

    public function down(): void
    {
        if (! $this->db->tableExists('users')) {
            return;
        }

        $table = $this->db->prefixTable('users');

        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('DROP INDEX IF EXISTS uq_users_email_lookup_hash');
        } else {
            $indexes = $this->db->getIndexData('users');
            if (array_key_exists('uq_users_email_lookup_hash', $indexes)) {
                $this->db->query('DROP INDEX uq_users_email_lookup_hash ON ' . $table);
            }
        }

        $drop = [];
        if ($this->db->fieldExists('email_ciphertext', 'users')) {
            $drop[] = 'email_ciphertext';
        }
        if ($this->db->fieldExists('email_lookup_hash', 'users')) {
            $drop[] = 'email_lookup_hash';
        }
        if ($drop !== [] && $this->db->DBDriver !== 'SQLite3') {
            $this->forge->dropColumn('users', $drop);
        }
    }
}
