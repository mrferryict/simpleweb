<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Security\PasswordResetEmailTransportInterface;

/**
 * In-memory password-reset mail capture for PHPUnit (V2-004).
 */
final class CapturingPasswordResetEmailTransport implements PasswordResetEmailTransportInterface
{
    /** @var list<array{to: string, subject: string, html: string, text: string}> */
    private static array $messages = [];

    private bool $shouldSucceed;

    public function __construct(bool $shouldSucceed = true)
    {
        $this->shouldSucceed = $shouldSucceed;
    }

    public function send(string $to, string $subject, string $htmlBody, string $textBody): bool
    {
        if (! $this->shouldSucceed) {
            return false;
        }

        self::$messages[] = [
            'to'      => $to,
            'subject' => $subject,
            'html'    => $htmlBody,
            'text'    => $textBody,
        ];

        return true;
    }

    /**
     * @return list<array{to: string, subject: string, html: string, text: string}>
     */
    public static function messages(): array
    {
        return self::$messages;
    }

    public static function last(): ?array
    {
        if (self::$messages === []) {
            return null;
        }

        return self::$messages[array_key_last(self::$messages)];
    }

    public static function reset(): void
    {
        self::$messages = [];
    }
}
