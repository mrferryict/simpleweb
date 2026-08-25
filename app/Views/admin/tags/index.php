<?php
/**
 * Tag list (Phase 3 / Task 3.7).
 *
 * @var list<\App\Entities\Tag> $rows
 * @var string|null $success
 * @var string|null $error
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Tags
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Tags') ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/tags/new')) ?>"><?= esc('Add tag') ?></a>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <?php if (! empty($error)) : ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>

    <?php if ($rows === []) : ?>
        <p><?= esc('No tags yet.') ?></p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th><?= esc('ID') ?></th>
                    <th><?= esc('Name') ?></th>
                    <th><?= esc('Slug') ?></th>
                    <th><?= esc('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $tag) : ?>
                    <tr>
                        <td><?= esc((string) $tag->id) ?></td>
                        <td><?= esc($tag->name) ?></td>
                        <td><?= esc($tag->slug) ?></td>
                        <td>
                            <a href="<?= esc(site_url('admin/tags/' . $tag->id . '/edit')) ?>"><?= esc('Edit') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?= $this->endSection() ?>
