<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Services\SettingService;
use Uri\Rfc3986\Uri;

/**
 * Password-reset email delivery (V2-004 / ADR-027 P0-2).
 *
 * Token generation/storage remains in PasswordResetController.
 */
final class PasswordResetEmailService
{
    public function __construct(
        private readonly SettingService $settingService,
        private readonly PasswordResetEmailTransportInterface $transport,
    ) {
    }

    /**
     * Send the reset link to the recipient. Returns false on delivery failure without
     * exposing SMTP details to callers.
     */
    #[\NoDiscard]
    public function sendResetEmail(string $recipientEmail, string $token, int $tokenTtlSeconds): bool
    {
        if ($recipientEmail === '' || $token === '') {
            return false;
        }

        $resetUrl = $this->buildResetUrl($token);
        $siteName = $this->resolveSiteName();
        $ttlLabel = $this->formatTtlLabel($tokenTtlSeconds);

        $subject = 'Reset your password — ' . $siteName;

        $htmlBody = view('emails/password_reset_html', [
            'siteName' => $siteName,
            'resetUrl' => $resetUrl,
            'ttlLabel' => $ttlLabel,
        ]);

        $textBody = view('emails/password_reset_text', [
            'siteName' => $siteName,
            'resetUrl' => $resetUrl,
            'ttlLabel' => $ttlLabel,
        ]);

        $sent = $this->transport->send($recipientEmail, $subject, $htmlBody, $textBody);
        if (! $sent) {
            log_message('error', 'Password reset email could not be delivered.');
        }

        return $sent;
    }

    public function buildResetUrl(string $token): string
    {
        /** @var \Config\App $appConfig */
        $appConfig = config('App');
        $base      = rtrim($appConfig->baseURL, '/');

        $uri = new Uri($base . '/cp/password-reset/verify');

        return $uri->withQuery('token=' . rawurlencode($token))->toString();
    }

    private function resolveSiteName(): string
    {
        $name = trim($this->settingService->getSiteSettings()->siteName);

        return $name !== '' ? $name : 'SMITE CMS';
    }

    private function formatTtlLabel(int $seconds): string
    {
        if ($seconds >= 3600 && $seconds % 3600 === 0) {
            $hours = (int) ($seconds / 3600);

            return $hours === 1 ? '1 hour' : $hours . ' hours';
        }

        if ($seconds >= 60 && $seconds % 60 === 0) {
            $minutes = (int) ($seconds / 60);

            return $minutes === 1 ? '1 minute' : $minutes . ' minutes';
        }

        return $seconds === 1 ? '1 second' : $seconds . ' seconds';
    }
}
