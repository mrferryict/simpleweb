<?php

declare(strict_types=1);

/**
 * Password reset verification — presentation only.
 *
 * @var string|null           $error
 * @var array<string, string> $errors
 * @var string|null           $success
 * @var string                $token
 */
$errors = $errors ?? [];
$error  = $error ?? null;

$pageTitle = 'Set new password';
$skipLabel = 'Skip to set new password';
echo view('admin/auth/_partials/document_start', compact('pageTitle', 'skipLabel'));
echo view('admin/auth/_partials/card_header', [
    'cardTitle' => 'Set new password',
    'cardLead'  => 'Enter your reset token and choose a new password.',
]);
?>

            <?php if ($error !== null && $error !== '') : ?>
                <div class="admin-alert admin-alert--error" role="alert">
                    <?= esc($error) ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($success)) : ?>
                <div class="admin-alert admin-alert--success" role="status">
                    <?= esc((string) $success) ?>
                </div>
            <?php endif; ?>

            <form class="admin-auth-form" method="post" action="<?= site_url('cp/password-reset/verify') ?>">
                <?= csrf_field() ?>

                <div class="admin-form-field">
                    <label for="token"><?= esc('Token') ?></label>
                    <input
                        type="text"
                        id="token"
                        name="token"
                        value="<?= esc((string) $token, 'attr') ?>"
                        autocomplete="off"
                        required
                    >
                </div>

                <div class="admin-form-field">
                    <label for="password"><?= esc('New password') ?></label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="new-password"
                        required
                        value=""
                    >
                    <?php if (isset($errors['password'])) : ?>
                        <p class="admin-field-error" role="alert"><?= esc($errors['password']) ?></p>
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

<?= view('admin/auth/_partials/document_end') ?>
