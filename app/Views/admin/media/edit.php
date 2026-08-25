<?php
/**
 * Media metadata edit.
 *
 * @var \App\Entities\MediaAsset $asset
 * @var string|null $imageUrl
 * @var array<string, string> $errors
 */
$success = session()->getFlashdata('success');
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Edit media') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Edit media') ?></h1>
    <p><a href="<?= esc(site_url('admin/media')) ?>"><?= esc('Back to Media') ?></a></p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>

    <?php if ($errors !== []) : ?>
        <ul role="alert">
            <?php foreach ($errors as $message) : ?>
                <li><?= esc((string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p><?= esc('Type: ' . $asset->type) ?></p>
    <p><?= esc('Original filename: ' . $asset->original_filename) ?></p>
    <p><?= esc('MIME: ' . $asset->mime_type) ?></p>
    <?php if ($imageUrl !== null) : ?>
        <p><?= esc('Public URL:') ?> <code><?= esc($imageUrl) ?></code></p>
    <?php endif; ?>

    <form method="post" action="<?= esc(site_url('admin/media/' . $asset->id)) ?>">
        <?= csrf_field() ?>
        <div>
            <label for="title"><?= esc('Title') ?></label>
            <input type="text" id="title" name="title" maxlength="200" value="<?= esc((string) ($asset->title ?? ''), 'attr') ?>">
        </div>
        <div>
            <label for="alt"><?= esc('Alt text') ?></label>
            <input type="text" id="alt" name="alt" maxlength="255" value="<?= esc((string) ($asset->alt ?? ''), 'attr') ?>">
        </div>
        <div>
            <label for="description"><?= esc('Description') ?></label>
            <textarea id="description" name="description"><?= esc((string) ($asset->description ?? '')) ?></textarea>
        </div>
        <button type="submit"><?= esc('Save') ?></button>
    </form>
<?= $this->endSection() ?>
