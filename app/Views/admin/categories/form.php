<?php
/**
 * Category create/edit form.
 *
 * @var string $mode
 * @var array{id?: int, name: string, slug: string, is_active: bool} $item
 * @var array<string, string> $errors
 * @var string $formAction
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc($mode === 'edit' ? 'Edit category' : 'New category') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc($mode === 'edit' ? 'Edit category' : 'New category') ?></h1>
    <p><a href="<?= esc(site_url('admin/categories')) ?>"><?= esc('Back to Categories') ?></a></p>

    <?php if ($errors !== []) : ?>
        <ul role="alert">
            <?php foreach ($errors as $message) : ?>
                <li><?= esc((string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= esc($formAction) ?>">
        <?= csrf_field() ?>

        <div>
            <label for="name"><?= esc('Name') ?></label>
            <input
                type="text"
                id="name"
                name="name"
                required
                maxlength="200"
                value="<?= esc((string) ($item['name'] ?? ''), 'attr') ?>"
            >
            <?php if (isset($errors['name'])) : ?>
                <p><?= esc($errors['name']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="slug"><?= esc('Slug') ?></label>
            <input
                type="text"
                id="slug"
                name="slug"
                required
                maxlength="200"
                value="<?= esc((string) ($item['slug'] ?? ''), 'attr') ?>"
            >
            <?php if (isset($errors['slug'])) : ?>
                <p><?= esc($errors['slug']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label>
                <input
                    type="checkbox"
                    name="is_active"
                    value="1"
                    <?= ! empty($item['is_active']) ? 'checked' : '' ?>
                >
                <?= esc('Active') ?>
            </label>
        </div>

        <div>
            <button type="submit"><?= esc($mode === 'edit' ? 'Update category' : 'Create category') ?></button>
        </div>
    </form>
<?= $this->endSection() ?>
