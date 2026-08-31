<?php

declare(strict_types=1);

/**
 * Password reset email — HTML body (V2-004).
 *
 * @var string $siteName
 * @var string $resetUrl
 * @var string $ttlLabel
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= esc('Password reset') ?></title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.5; color: #1a1a1a;">
    <p><?= esc('Hello,') ?></p>
    <p>
        <?= esc('We received a request to reset the password for your account on') ?>
        <strong><?= esc($siteName) ?></strong>.
    </p>
    <p><?= esc('Use the link below to choose a new password. This link expires in') ?>
        <?= esc($ttlLabel) ?>.</p>
    <p>
        <a href="<?= esc($resetUrl, 'attr') ?>" style="color: #1d4ed8;"><?= esc('Reset your password') ?></a>
    </p>
    <p style="font-size: 14px; color: #4b5563;">
        <?= esc('If the button does not work, copy and paste this URL into your browser:') ?><br>
        <?= esc($resetUrl) ?>
    </p>
    <p style="font-size: 14px; color: #4b5563;">
        <?= esc('If you did not request a password reset, you can ignore this email. Your password will not change.') ?>
    </p>
</body>
</html>
