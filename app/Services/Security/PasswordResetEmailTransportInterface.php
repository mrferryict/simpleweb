<?php

declare(strict_types=1);

namespace App\Services\Security;

/**
 * Transport boundary for password-reset email delivery (V2-004 / ADR-027 P0-2).
 */
interface PasswordResetEmailTransportInterface
{
    public function send(string $to, string $subject, string $htmlBody, string $textBody): bool;
}
