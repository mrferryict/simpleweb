<?php
/**
 * Control Panel login fragment (ADR-001 / REQ-AUTH-005).
 *
 * Presentation only — no credential processing.
 *
 * @var string|null $error Optional generic authentication/validation message.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc('Control Panel Login') ?></title>
</head>
<body>
    <main>
        <h1><?= esc('Control Panel Login') ?></h1>

        <?php if (! empty($error)) : ?>
            <p role="alert"><?= esc((string) $error) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= site_url('cp') ?>">
            <?= csrf_field() ?>

            <div>
                <label for="username"><?= esc('Username') ?></label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                    value="<?= esc(old('username') ?? '', 'attr') ?>"
                >
            </div>

            <div>
                <label for="password"><?= esc('Password') ?></label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    value=""
                >
            </div>

            <div>
                <button type="submit"><?= esc('Sign in') ?></button>
            </div>
        </form>
    </main>
</body>
</html>
