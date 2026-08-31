<?php

declare(strict_types=1);

namespace App\Services\Security;

use CodeIgniter\Email\Email;
use Config\Email as EmailConfig;
use Throwable;

/**
 * SMTP delivery via CodeIgniter Email (V2-004).
 *
 * Never logs credentials, tokens, or recipient addresses on failure.
 */
final class SmtpPasswordResetEmailTransport implements PasswordResetEmailTransportInterface
{
    public function __construct(
        private readonly EmailConfig $config,
    ) {
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $email = new Email($this->config);
        $email->setFrom(
            $this->config->fromEmail !== '' ? $this->config->fromEmail : 'noreply@localhost',
            $this->config->fromName !== '' ? $this->config->fromName : 'SMITE CMS',
        );
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMailType('html');
        $email->setMessage($htmlBody);
        $email->setAltMessage($textBody);

        try {
            return $email->send(false);
        } catch (Throwable) {
            log_message('error', 'Password reset email SMTP delivery failed.');

            return false;
        }
    }
}
