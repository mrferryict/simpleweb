<?php

declare(strict_types=1);

/**
 * Control Panel login (ADR-001 / REQ-AUTH-005).
 *
 * Presentation only — no credential processing.
 *
 * @var string|null $error Optional generic authentication/validation message.
 */
$pageTitle = 'Control Panel Login';
$skipLabel = 'Skip to sign in';
echo view('admin/auth/_partials/document_start', compact('pageTitle', 'skipLabel'));
echo view('admin/auth/_partials/card_header', [
    'cardTitle' => 'Control Panel',
    'cardLead'  => 'Sign in to manage your website content.',
]);
?>

            <?php if (! empty($error)) : ?>
                <div class="admin-alert admin-alert--error" role="alert">
                    <?= esc((string) $error) ?>
                </div>
            <?php endif; ?>

            <form class="admin-auth-form" method="post" action="<?= site_url('cp') ?>">
                <?= csrf_field() ?>

                <div class="admin-form-field">
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

                <div class="admin-form-field">
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

                <div class="admin-auth-form__actions">
                    <button type="submit" class="admin-btn admin-btn--primary admin-btn--block">
                        <?= esc('Sign in') ?>
                    </button>
                </div>
            </form>

<?= view('admin/auth/_partials/document_end') ?>
