<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * ADR-008 email PII secrets (EMAIL_ENCRYPTION_KEY / EMAIL_LOOKUP_HMAC_KEY).
 */
class EmailPii extends BaseConfig
{
    /**
     * 64-char hex → 32-byte sodium key (env EMAIL_ENCRYPTION_KEY).
     */
    public string $encryptionKeyHex = '';

    /**
     * 64-char hex → 32-byte HMAC key (env EMAIL_LOOKUP_HMAC_KEY).
     */
    public string $lookupHmacKeyHex = '';

    public function __construct()
    {
        parent::__construct();

        $enc = env('EMAIL_ENCRYPTION_KEY', $this->encryptionKeyHex);
        $hmac = env('EMAIL_LOOKUP_HMAC_KEY', $this->lookupHmacKeyHex);
        $this->encryptionKeyHex = is_string($enc) ? trim($enc) : '';
        $this->lookupHmacKeyHex = is_string($hmac) ? trim($hmac) : '';
    }
}
