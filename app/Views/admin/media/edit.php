<?php

declare(strict_types=1);

/**
 * Media metadata edit (TH-008 polish).
 *
 * @var \App\Entities\MediaAsset $asset
 * @var string|null $imageUrl
 * @var array<string, string> $errors
 */
$activeNav = 'media';
$success   = session()->getFlashdata('success');
$fileSize  = (int) $asset->file_size;
$sizeLabel = $fileSize >= 1_048_576
    ? number_format($fileSize / 1_048_576, 1) . ' MB'
    : ($fileSize >= 1024
        ? number_format($fileSize / 1024, 1) . ' KB'
        : $fileSize . ' B');
$displayName = (string) ($asset->title ?: $asset->original_filename);
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Edit media') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Edit media') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Update metadata for this file. The stored file itself cannot be replaced here.') ?>
            </p>
        </div>
    </header>

    <div class="admin-form-toolbar">
        <div class="admin-form-toolbar__links">
            <a href="<?= esc(site_url('admin/media')) ?>"><?= esc('Back to Media') ?></a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => is_string($success) ? $success : null,
        'error'   => null,
        'errors'  => $errors,
    ]) ?>

    <section class="admin-form-section admin-media-detail">
        <h2 class="admin-form-section__title"><?= esc('File information') ?></h2>
        <div class="admin-media-detail__layout">
            <div class="admin-media-detail__preview">
                <?php if ($imageUrl !== null) : ?>
                    <img
                        class="admin-media-detail__image"
                        src="<?= esc($imageUrl, 'attr') ?>"
                        alt="<?= esc($asset->alt ?: $displayName, 'attr') ?>"
                    >
                <?php else : ?>
                    <div class="admin-media-thumb admin-media-thumb--document admin-media-thumb--large" aria-hidden="true">
                        <span class="admin-media-thumb__ext"><?= esc(strtoupper((string) $asset->extension)) ?></span>
                    </div>
                <?php endif; ?>
            </div>
            <dl class="admin-media-detail__meta">
                <div>
                    <dt><?= esc('Type') ?></dt>
                    <dd><?= view('admin/_partials/type_badge', ['type' => (string) $asset->type]) ?></dd>
                </div>
                <div>
                    <dt><?= esc('Original filename') ?></dt>
                    <dd><?= esc((string) $asset->original_filename) ?></dd>
                </div>
                <div>
                    <dt><?= esc('MIME type') ?></dt>
                    <dd><code class="admin-table__slug"><?= esc((string) $asset->mime_type) ?></code></dd>
                </div>
                <div>
                    <dt><?= esc('File size') ?></dt>
                    <dd><?= esc($sizeLabel) ?></dd>
                </div>
                <?php if ($asset->width !== null && $asset->height !== null) : ?>
                    <div>
                        <dt><?= esc('Dimensions') ?></dt>
                        <dd><?= esc((int) $asset->width . '×' . (int) $asset->height) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($imageUrl !== null) : ?>
                    <div>
                        <dt><?= esc('Public URL') ?></dt>
                        <dd><code class="admin-table__slug"><?= esc($imageUrl) ?></code></dd>
                    </div>
                <?php endif; ?>
                <?php if ($asset->type === 'DOCUMENT' && $asset->download_token) : ?>
                    <div>
                        <dt><?= esc('Download') ?></dt>
                        <dd>
                            <a href="<?= esc(site_url('download/document/' . $asset->download_token)) ?>">
                                <?= esc('Tokenized document link') ?>
                            </a>
                        </dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
    </section>

    <form class="admin-form" method="post" action="<?= esc(site_url('admin/media/' . $asset->id)) ?>">
        <?= csrf_field() ?>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('Metadata') ?></h2>
            <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="title"><?= esc('Title') ?></label>
                    <input type="text" id="title" name="title" maxlength="200" value="<?= esc((string) ($asset->title ?? ''), 'attr') ?>">
                </div>
                <div class="admin-form-field">
                    <label for="alt"><?= esc('Alt text') ?></label>
                    <input type="text" id="alt" name="alt" maxlength="255" value="<?= esc((string) ($asset->alt ?? ''), 'attr') ?>">
                </div>
                <div class="admin-form-field">
                    <label for="description"><?= esc('Description') ?></label>
                    <textarea id="description" name="description" rows="3"><?= esc((string) ($asset->description ?? '')) ?></textarea>
                </div>
            </div>
        </section>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit"><?= esc('Save') ?></button>
            <a class="admin-btn admin-btn--secondary" href="<?= esc(site_url('admin/media')) ?>"><?= esc('Cancel') ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
