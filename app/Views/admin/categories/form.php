<?php

declare(strict_types=1);

/**
 * Category create/edit form (TH-008 polish).
 *
 * @var string $mode
 * @var array{id?: int, name: string, slug: string, is_active: bool} $item
 * @var array<string, string> $errors
 * @var string $formAction
 */
$activeNav = 'categories';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc($mode === 'edit' ? 'Edit category' : 'New category') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc($mode === 'edit' ? 'Edit category' : 'New category') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Categories group posts for navigation and filtering.') ?>
            </p>
        </div>
    </header>

    <div class="admin-form-toolbar">
        <div class="admin-form-toolbar__links">
            <a href="<?= esc(site_url('admin/categories')) ?>"><?= esc('Back to Categories') ?></a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => null,
        'error'   => null,
        'errors'  => $errors,
    ]) ?>

    <form class="admin-form" method="post" action="<?= esc($formAction) ?>">
        <?= csrf_field() ?>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('Category details') ?></h2>
            <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="name">
                        <?= esc('Name') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        required
                        maxlength="200"
                        value="<?= esc((string) ($item['name'] ?? ''), 'attr') ?>"
                    >
                    <?php if (isset($errors['name'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="slug">
                        <?= esc('Slug') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        required
                        maxlength="200"
                        value="<?= esc((string) ($item['slug'] ?? ''), 'attr') ?>"
                    >
                    <?php if (isset($errors['slug'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['slug']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label>
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= ! empty($item['is_active']) ? 'checked' : '' ?>
                        >
                        <?= esc('Active') ?>
                    </label>
                    <p class="admin-form-hint"><?= esc('Inactive categories cannot be assigned to new posts.') ?></p>
                </div>
            </div>
        </section>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit">
                <?= esc($mode === 'edit' ? 'Update category' : 'Create category') ?>
            </button>
            <a class="admin-btn admin-btn--secondary" href="<?= esc(site_url('admin/categories')) ?>"><?= esc('Cancel') ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
