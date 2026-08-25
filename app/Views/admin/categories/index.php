<?php
/**
 * Category list (Phase 3 / Task 3.7).
 *
 * @var list<\App\Entities\Category> $rows
 * @var string|null $success
 * @var string|null $error
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Categories
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Categories') ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/categories/new')) ?>"><?= esc('Add category') ?></a>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <?php if (! empty($error)) : ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>

    <?php if ($rows === []) : ?>
        <p><?= esc('No categories yet.') ?></p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th><?= esc('ID') ?></th>
                    <th><?= esc('Name') ?></th>
                    <th><?= esc('Slug') ?></th>
                    <th><?= esc('Active') ?></th>
                    <th><?= esc('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $category) : ?>
                    <tr>
                        <td><?= esc((string) $category->id) ?></td>
                        <td><?= esc($category->name) ?></td>
                        <td><?= esc($category->slug) ?></td>
                        <td><?= esc($category->is_active ? 'Yes' : 'No') ?></td>
                        <td>
                            <a href="<?= esc(site_url('admin/categories/' . $category->id . '/edit')) ?>"><?= esc('Edit') ?></a>
                            <?php if ($category->is_active) : ?>
                                <form
                                    method="post"
                                    action="<?= esc(site_url('admin/categories/' . $category->id . '/deactivate')) ?>"
                                    style="display:inline"
                                >
                                    <?= csrf_field() ?>
                                    <button type="submit"><?= esc('Deactivate') ?></button>
                                </form>
                            <?php else : ?>
                                <form
                                    method="post"
                                    action="<?= esc(site_url('admin/categories/' . $category->id . '/restore')) ?>"
                                    style="display:inline"
                                >
                                    <?= csrf_field() ?>
                                    <button type="submit"><?= esc('Restore') ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?= $this->endSection() ?>
