<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc('Password reset') ?></title>
</head>
<body>
<main>
    <h1><?= esc('Password reset') ?></h1>
    <?php if (! empty($error)): ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>
    <?php if (! empty($message)): ?>
        <p><?= esc((string) $message) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= site_url('cp/password-reset') ?>">
        <?= csrf_field() ?>
        <div>
            <label for="email"><?= esc('Email') ?></label>
            <input type="email" id="email" name="email" autocomplete="email" required>
        </div>
        <button type="submit"><?= esc('Request reset') ?></button>
    </form>
</main>
</body>
</html>
