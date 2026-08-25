<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc('Admin recovery') ?></title>
</head>
<body>
<main>
    <h1><?= esc('Admin recovery') ?></h1>
    <?php if (! empty($error)): ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>
    <?php if (! empty($success)): ?>
        <p><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= site_url('cp/admin-recovery') ?>">
        <?= csrf_field() ?>
        <div>
            <label for="skey"><?= esc('Recovery secret') ?></label>
            <input type="password" id="skey" name="skey" autocomplete="off" required>
        </div>
        <div>
            <label for="username"><?= esc('Username') ?></label>
            <input type="text" id="username" name="username" autocomplete="username" required>
        </div>
        <div>
            <label for="password"><?= esc('New password') ?></label>
            <input type="password" id="password" name="password" autocomplete="new-password" required>
        </div>
        <button type="submit"><?= esc('Recover') ?></button>
    </form>
</main>
</body>
</html>
