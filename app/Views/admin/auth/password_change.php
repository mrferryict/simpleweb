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

$pageTitle = 'Change your password';
$skipLabel = 'Skip to password change';
echo view('admin/auth/_partials/document_start', compact('pageTitle', 'skipLabel'));
echo view('admin/auth/_partials/card_header', [
    'cardTitle' => 'Change your password',
    'cardLead'  => 'You must set a new password before you can use the Control Panel.',
]);
?>

            <?php if ($error !== null && $error !== '') : ?>
                <div class="admin-alert admin-alert--error" role="alert">
                    <?= esc($error) ?>
                </div>
            <?php endif; ?>

            <form class="admin-auth-form" method="post" action="<?= site_url('cp/password-change') ?>">
                <?= csrf_field() ?>

                <div class="admin-form-field">
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
                        <p class="admin-field-error" role="alert"><?= esc($errors['password']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
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
                        <p class="admin-field-error" role="alert"><?= esc($errors['password_new']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
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
                        <p class="admin-field-error" role="alert"><?= esc($errors['password_confirm']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-auth-form__actions">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--block">
                        <?= esc('Update password') ?>
                    </button>
                </div>
            </form>

            <footer class="admin-auth-card__footer">
                <a href="<?= esc(site_url('logout'), 'attr') ?>"><?= esc('Sign out') ?></a>
            </footer>

<?= view('admin/auth/_partials/document_end') ?>
