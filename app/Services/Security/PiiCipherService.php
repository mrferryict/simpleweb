<?php

declare(strict_types=1);

namespace App\Services\Security;

use Config\EmailPii;
use RuntimeException;

/**
 * Sodium email PII cipher + HMAC lookup (ADR-008).
 *
 * Never log decrypted email or key material.
 */
final class PiiCipherService
{
    private const KEY_BYTES   = 32;
    private const NONCE_BYTES = 24;

    private readonly string $encryptionKey;
    private readonly string $lookupHmacKey;

    public function __construct(EmailPii $config)
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('ext-sodium is required for email PII protection.');
        }

        $this->encryptionKey = $this->decodeKey($config->encryptionKeyHex, 'EMAIL_ENCRYPTION_KEY');
        $this->lookupHmacKey = $this->decodeKey($config->lookupHmacKeyHex, 'EMAIL_LOOKUP_HMAC_KEY');

        if ($config->encryptionKeyHex === $config->lookupHmacKeyHex) {
            throw new RuntimeException('EMAIL_ENCRYPTION_KEY and EMAIL_LOOKUP_HMAC_KEY must differ.');
        }
    }

    /**
     * Authenticated encrypt normalized email → URL-safe Base64(nonce||ciphertext).
     */
    public function encrypt(string $email): string
    {
        $normalized = $this->normalize($email);
        $nonce      = random_bytes(self::NONCE_BYTES);
        $cipher     = sodium_crypto_secretbox($normalized, $nonce, $this->encryptionKey);

        return rtrim(strtr(base64_encode($nonce . $cipher), '+/', '-_'), '=');
    }

    /**
     * Decrypt stored ciphertext to normalized email string.
     */
    public function decrypt(string $ciphertext): string
    {
        $raw = base64_decode(strtr($ciphertext, '-_', '+/'), true);
        if ($raw === false || strlen($raw) <= self::NONCE_BYTES) {
            throw new RuntimeException('Invalid email ciphertext.');
        }

        $nonce = substr($raw, 0, self::NONCE_BYTES);
        $box   = substr($raw, self::NONCE_BYTES);
        $plain = sodium_crypto_secretbox_open($box, $nonce, $this->encryptionKey);
        if ($plain === false) {
            throw new RuntimeException('Unable to decrypt email ciphertext.');
        }

        return $plain;
    }

    /**
     * Deterministic HMAC-SHA256 hex lookup hash of normalized email.
     */
    public function getLookupHash(string $email): string
    {
        return hash_hmac('sha256', $this->normalize($email), $this->lookupHmacKey);
    }

    public function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    private function decodeKey(string $hex, string $label): string
    {
        if ($hex === '' || ! preg_match('/^[0-9a-fA-F]{64}$/', $hex)) {
            throw new RuntimeException($label . ' must be a 64-character hexadecimal string.');
        }

        $binary = hex2bin($hex);
        if ($binary === false || strlen($binary) !== self::KEY_BYTES) {
            throw new RuntimeException($label . ' is not a valid 32-byte key.');
        }

        return $binary;
    }
}
