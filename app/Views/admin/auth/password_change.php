<?php

declare(strict_types=1);

/**
 * Mandatory password change (force_reset) — presentation only.
 *
 * @var array<string, string> $errors
 * @var string|null           $error
 */
$errors = $errors ?? [];
$error  = $error ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc('Change your password') ?></title>
</head>
<body>
<main>
    <h1><?= esc('Change your password') ?></h1>
    <p><?= esc('You must set a new password before you can use the Control Panel.') ?></p>

    <?php if ($error !== null && $error !== '') : ?>
        <p role="alert"><?= esc($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= site_url('cp/password-change') ?>">
        <?= csrf_field() ?>

        <div>
            <label for="password"><?= esc('Current password') ?></label>
            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
                value=""
            >
            <?php if (isset($errors['password'])) : ?>
                <p role="alert"><?= esc($errors['password']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="password_new"><?= esc('New password') ?></label>
            <input
                type="password"
                id="password_new"
                name="password_new"
                autocomplete="new-password"
                required
                value=""
            >
            <?php if (isset($errors['password_new'])) : ?>
                <p role="alert"><?= esc($errors['password_new']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="password_confirm"><?= esc('Confirm new password') ?></label>
            <input
                type="password"
                id="password_confirm"
                name="password_confirm"
                autocomplete="new-password"
                required
                value=""
            >
            <?php if (isset($errors['password_confirm'])) : ?>
                <p role="alert"><?= esc($errors['password_confirm']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <button type="submit"><?= esc('Update password') ?></button>
        </div>
    </form>

    <p><a href="<?= esc(site_url('logout'), 'attr') ?>"><?= esc('Sign out') ?></a></p>
</main>
</body>
</html>
