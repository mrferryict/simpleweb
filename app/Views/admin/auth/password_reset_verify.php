<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc('Set new password') ?></title>
</head>
<body>
<main>
    <h1><?= esc('Set new password') ?></h1>
    <?php if (! empty($error)): ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>
    <?php if (! empty($success)): ?>
        <p><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= site_url('cp/password-reset/verify') ?>">
        <?= csrf_field() ?>
        <div>
            <label for="token"><?= esc('Token') ?></label>
            <input type="text" id="token" name="token" value="<?= esc((string) $token, 'attr') ?>" autocomplete="off" required>
        </div>
        <div>
            <label for="password"><?= esc('New password') ?></label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
        </div>
        <button type="submit"><?= esc('Update password') ?></button>
    </form>
</main>
</body>
</html>
