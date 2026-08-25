<?php

declare(strict_types=1);

namespace App\Services\Security;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Persist/lookup user email via PiiCipherService only (ADR-008).
 */
final class UserEmailService
{
    public function __construct(
        private readonly PiiCipherService $cipher,
        private readonly BaseConnection $db,
    ) {
    }

    /**
     * Store encrypted email + lookup hash for a user. Never logs plaintext.
     */
    public function setEmail(int $userId, string $email): void
    {
        if ($userId < 1) {
            throw new RuntimeException('Invalid user id.');
        }

        $normalized = $this->cipher->normalize($email);
        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email.');
        }

        $hash = $this->cipher->getLookupHash($normalized);
        $existing = $this->db->table('users')
            ->select('id')
            ->where('email_lookup_hash', $hash)
            ->where('id !=', $userId)
            ->get()
            ->getRowArray();
        if ($existing !== null) {
            throw new RuntimeException('Email is already in use.');
        }

        $this->db->table('users')->where('id', $userId)->update([
            'email_ciphertext'  => $this->cipher->encrypt($normalized),
            'email_lookup_hash' => $hash,
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    public function findUserIdByEmail(string $email): ?int
    {
        $hash = $this->cipher->getLookupHash($email);
        $row  = $this->db->table('users')
            ->select('id')
            ->where('email_lookup_hash', $hash)
            ->get()
            ->getRowArray();

        return $row !== null ? (int) $row['id'] : null;
    }

    /**
     * Decrypt email for authorized use only (SMTP / profile). Never log result.
     */
    public function getDecryptedEmail(int $userId): ?string
    {
        $row = $this->db->table('users')
            ->select('email_ciphertext')
            ->where('id', $userId)
            ->get()
            ->getRowArray();

        if ($row === null || ! isset($row['email_ciphertext']) || ! is_string($row['email_ciphertext']) || $row['email_ciphertext'] === '') {
            return null;
        }

        return $this->cipher->decrypt($row['email_ciphertext']);
    }
}
