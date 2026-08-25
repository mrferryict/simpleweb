<?php
/**
 * Media upload form.
 *
 * @var array<string, string> $errors
 * @var array{title: string, alt: string, description: string} $item
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Upload media') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Upload media') ?></h1>
    <p><a href="<?= esc(site_url('admin/media')) ?>"><?= esc('Back to Media') ?></a></p>

    <?php if ($errors !== []) : ?>
        <ul role="alert">
            <?php foreach ($errors as $message) : ?>
                <li><?= esc((string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= esc(site_url('admin/media')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div>
            <label for="file"><?= esc('File') ?></label>
            <input type="file" id="file" name="file" required>
        </div>
        <div>
            <label for="title"><?= esc('Title') ?></label>
            <input type="text" id="title" name="title" maxlength="200" value="<?= esc($item['title'] ?? '', 'attr') ?>">
        </div>
        <div>
            <label for="alt"><?= esc('Alt text') ?></label>
            <input type="text" id="alt" name="alt" maxlength="255" value="<?= esc($item['alt'] ?? '', 'attr') ?>">
        </div>
        <div>
            <label for="description"><?= esc('Description') ?></label>
            <textarea id="description" name="description"><?= esc($item['description'] ?? '') ?></textarea>
        </div>
        <button type="submit"><?= esc('Upload') ?></button>
    </form>
<?= $this->endSection() ?>
