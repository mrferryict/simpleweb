<?php

declare(strict_types=1);

/**
 * Media upload form (TH-008 polish).
 *
 * @var array<string, string> $errors
 * @var array{title: string, alt: string, description: string} $item
 */
$activeNav = 'media';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Upload media') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Upload media') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Upload an image or document. Files are validated for type and size before storage.') ?>
            </p>
        </div>
    </header>

    <div class="admin-form-toolbar">
        <div class="admin-form-toolbar__links">
            <a href="<?= esc(site_url('admin/media')) ?>"><?= esc('Back to Media') ?></a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => null,
        'error'   => null,
        'errors'  => $errors,
    ]) ?>

    <form class="admin-form" method="post" action="<?= esc(site_url('admin/media')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('File') ?></h2>
            <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="file">
                        <?= esc('Choose file') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input class="admin-form-field__file" type="file" id="file" name="file" required>
                    <?php if (isset($errors['file'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['file']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('Metadata (optional)') ?></h2>
            <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="title"><?= esc('Title') ?></label>
                    <input type="text" id="title" name="title" maxlength="200" value="<?= esc($item['title'] ?? '', 'attr') ?>">
                </div>
                <div class="admin-form-field">
                    <label for="alt"><?= esc('Alt text') ?></label>
                    <input type="text" id="alt" name="alt" maxlength="255" value="<?= esc($item['alt'] ?? '', 'attr') ?>">
                    <p class="admin-form-hint"><?= esc('Describe images for accessibility when used in content.') ?></p>
                </div>
                <div class="admin-form-field">
                    <label for="description"><?= esc('Description') ?></label>
                    <textarea id="description" name="description" rows="3"><?= esc($item['description'] ?? '') ?></textarea>
                </div>
            </div>
        </section>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit"><?= esc('Upload') ?></button>
            <a class="admin-btn admin-btn--secondary" href="<?= esc(site_url('admin/media')) ?>"><?= esc('Cancel') ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
