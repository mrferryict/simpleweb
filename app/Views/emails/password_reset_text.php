<?php

declare(strict_types=1);

/**
 * Password reset email — plain text body (V2-004).
 *
 * @var string $siteName
 * @var string $resetUrl
 * @var string $ttlLabel
 */
?>
Hello,

We received a request to reset the password for your account on <?= esc($siteName) ?>.

Use the link below to choose a new password. This link expires in <?= esc($ttlLabel) ?>.

<?= esc($resetUrl) ?>


If you did not request a password reset, you can ignore this email. Your password will not change.
