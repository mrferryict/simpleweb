<?php

declare(strict_types=1);

/**
 * Password reset request — presentation only.
 *
 * @var string|null $message Opaque success-style message.
 * @var string|null $error   Throttle or error message.
 */
$pageTitle = 'Password reset';
$skipLabel = 'Skip to password reset';
echo view('admin/auth/_partials/document_start', compact('pageTitle', 'skipLabel'));
echo view('admin/auth/_partials/card_header', [
    'cardTitle' => 'Password reset',
    'cardLead'  => 'Enter your account email to request a password reset.',
]);
?>

            <?php if (! empty($error)) : ?>
                <div class="admin-alert admin-alert--error" role="alert">
                    <?= esc((string) $error) ?>
                </div>
            <?php endif; ?>

            <?php if (! empty($message)) : ?>
                <div class="admin-alert admin-alert--info" role="status">
                    <?= esc((string) $message) ?>
                </div>
            <?php endif; ?>

            <form class="admin-auth-form" method="post" action="<?= site_url('cp/password-reset') ?>">
                <?= csrf_field() ?>

                <div class="admin-form-field">
                    <label for="email"><?= esc('Email') ?></label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        autocomplete="email"
                        required
                        value=""
                    >
                </div>

                <div class="admin-auth-form__actions">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--block">
                        <?= esc('Request reset') ?>
                    </button>
                </div>
            </form>

<?= view('admin/auth/_partials/document_end') ?>
