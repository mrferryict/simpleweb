<?php

declare(strict_types=1);

/**
 * Control Panel flash / validation messages (TH-007).
 *
 * @var string|null $success
 * @var string|null $error
 * @var array<string, string>|list<string> $errors
 */
$success = isset($success) && is_string($success) && $success !== '' ? $success : null;
$error   = isset($error) && is_string($error) && $error !== '' ? $error : null;
$errors  = is_array($errors ?? null) ? $errors : [];
?>
<?php if ($success !== null) : ?>
    <div class="admin-alert admin-alert--success" role="status"><?= esc($success) ?></div>
<?php endif; ?>
<?php if ($error !== null) : ?>
    <div class="admin-alert admin-alert--error" role="alert"><?= esc($error) ?></div>
<?php endif; ?>
<?php if ($errors !== []) : ?>
    <div class="admin-alert admin-alert--error" role="alert">
        <p><?= esc('Please correct the following:') ?></p>
        <ul>
            <?php foreach ($errors as $field => $message) : ?>
                <li><?= esc(is_string($message) ? $message : (string) $field) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
