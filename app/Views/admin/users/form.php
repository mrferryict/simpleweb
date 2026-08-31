<?php

declare(strict_types=1);

/**
 * Staff user create/edit form (V2-003 / ADR-027 P0-1).
 *
 * @var string $mode
 * @var array{
 *     id?: int,
 *     username: string,
 *     email: string,
 *     group: string,
 *     is_active: bool
 * } $item
 * @var array<string, string> $assignableGroups
 * @var array<string, string> $errors
 * @var string $formAction
 * @var bool $showPassword
 * @var bool $canChangeGroup
 * @var bool $canChangeActive
 * @var bool $isAdmin
 */
$activeNav = 'users';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc($mode === 'edit' ? 'Edit user' : 'New user') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc($mode === 'edit' ? 'Edit user' : 'New user') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc($mode === 'edit'
                    ? 'Update account details. Password changes use the dedicated password-change flow after login.'
                    : 'Create an Editor or Contributor account. The user will be prompted to change their password on first login.') ?>
            </p>
        </div>
    </header>

    <div class="admin-form-toolbar">
        <div class="admin-form-toolbar__links">
            <a href="<?= esc(site_url('admin/users')) ?>"><?= esc('Back to Users') ?></a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => null,
        'error'   => $errors['_persist'] ?? $errors['_invariant'] ?? null,
        'errors'  => $errors,
    ]) ?>

    <form class="admin-form" method="post" action="<?= esc($formAction) ?>">
        <?= csrf_field() ?>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('Account details') ?></h2>
            <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="username">
                        <?= esc('Username') ?>
                        <?php if ($mode === 'create') : ?>
                            <span class="admin-required" aria-hidden="true">*</span>
                        <?php endif; ?>
                    </label>
                    <?php if ($mode === 'edit') : ?>
                        <input
                            type="text"
                            id="username"
                            value="<?= esc((string) ($item['username'] ?? ''), 'attr') ?>"
                            readonly
                            disabled
                        >
                        <p class="admin-form-hint"><?= esc('Username cannot be changed.') ?></p>
                    <?php else : ?>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            required
                            maxlength="30"
                            autocomplete="off"
                            value="<?= esc((string) ($item['username'] ?? ''), 'attr') ?>"
                        >
                        <?php if (isset($errors['username'])) : ?>
                            <p class="admin-field-error"><?= esc($errors['username']) ?></p>
                        <?php endif; ?>
                        <p class="admin-form-hint"><?= esc('Lowercase letters, numbers, and dots (3–30 characters).') ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="email">
                        <?= esc('Email') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autocomplete="off"
                        value="<?= esc((string) ($item['email'] ?? ''), 'attr') ?>"
                    >
                    <?php if (isset($errors['email'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['email']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="group">
                        <?= esc('Role') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <?php if ($isAdmin) : ?>
                        <input
                            type="text"
                            id="group"
                            value="<?= esc('Admin') ?>"
                            readonly
                            disabled
                        >
                        <p class="admin-form-hint"><?= esc('The Admin role cannot be assigned or changed through this form.') ?></p>
                    <?php elseif ($canChangeGroup) : ?>
                        <select id="group" name="group" required>
                            <?php foreach ($assignableGroups as $groupKey => $groupLabel) : ?>
                                <option
                                    value="<?= esc($groupKey, 'attr') ?>"
                                    <?= ($item['group'] ?? '') === $groupKey ? 'selected' : '' ?>
                                ><?= esc($groupLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['group'])) : ?>
                            <p class="admin-field-error"><?= esc($errors['group']) ?></p>
                        <?php endif; ?>
                    <?php else : ?>
                        <input
                            type="text"
                            id="group"
                            value="<?= esc((string) ($assignableGroups[$item['group']] ?? $item['group'] ?? '')) ?>"
                            readonly
                            disabled
                        >
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label>
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= ! empty($item['is_active']) ? 'checked' : '' ?>
                            <?= ! $canChangeActive ? 'disabled' : '' ?>
                        >
                        <?= esc('Active') ?>
                    </label>
                    <?php if (! $canChangeActive) : ?>
                        <input type="hidden" name="is_active" value="<?= ! empty($item['is_active']) ? '1' : '0' ?>">
                        <p class="admin-form-hint"><?= esc('The only Admin account cannot be deactivated.') ?></p>
                    <?php else : ?>
                        <p class="admin-form-hint"><?= esc('Inactive accounts cannot sign in.') ?></p>
                    <?php endif; ?>
                    <?php if (isset($errors['is_active'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['is_active']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($showPassword) : ?>
            <section class="admin-form-section">
                <h2 class="admin-form-section__title"><?= esc('Initial password') ?></h2>
                <div class="admin-form-section__grid">
                    <div class="admin-form-field">
                        <label for="password">
                            <?= esc('Password') ?>
                            <span class="admin-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                        >
                        <?php if (isset($errors['password'])) : ?>
                            <p class="admin-field-error"><?= esc($errors['password']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-form-field">
                        <label for="password_confirm">
                            <?= esc('Confirm password') ?>
                            <span class="admin-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
                            required
                            autocomplete="new-password"
                        >
                        <?php if (isset($errors['password_confirm'])) : ?>
                            <p class="admin-field-error"><?= esc($errors['password_confirm']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit">
                <?= esc($mode === 'edit' ? 'Update user' : 'Create user') ?>
            </button>
            <a class="admin-btn admin-btn--secondary" href="<?= esc(site_url('admin/users')) ?>"><?= esc('Cancel') ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
