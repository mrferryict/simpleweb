<?php

declare(strict_types=1);

/**
 * Category list (Phase 3 / Task 3.7 / TH-008 polish).
 *
 * @var list<\App\Entities\Category> $rows
 * @var string|null $success
 * @var string|null $error
 */
$activeNav = 'categories';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Categories
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Categories') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Organize posts with categories. Inactive categories stay in the list but cannot be assigned to new posts.') ?>
            </p>
        </div>
    </header>

    <div class="admin-toolbar" aria-label="<?= esc('Category list actions') ?>">
        <div class="admin-toolbar__group">
            <a class="admin-btn admin-btn--primary admin-btn--small" href="<?= esc(site_url('admin/categories/new')) ?>">
                <?= esc('Create Category') ?>
            </a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success ?? null,
        'error'   => $error ?? null,
        'errors'  => [],
    ]) ?>

    <?php if ($rows === []) : ?>
        <div class="admin-empty-state">
            <h2 class="admin-empty-state__title"><?= esc('No categories yet') ?></h2>
            <p class="admin-empty-state__text">
                <?= esc('Create a category to organize your posts.') ?>
            </p>
            <a class="admin-btn admin-btn--primary" href="<?= esc(site_url('admin/categories/new')) ?>">
                <?= esc('Create Category') ?>
            </a>
        </div>
    <?php else : ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col"><?= esc('ID') ?></th>
                        <th scope="col"><?= esc('Name') ?></th>
                        <th scope="col"><?= esc('Slug') ?></th>
                        <th scope="col"><?= esc('Status') ?></th>
                        <th scope="col" class="admin-table__actions"><?= esc('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $category) : ?>
                        <tr>
                            <td><?= esc((string) $category->id) ?></td>
                            <td>
                                <span class="admin-table__primary"><?= esc($category->name) ?></span>
                            </td>
                            <td><span class="admin-table__slug"><?= esc($category->slug) ?></span></td>
                            <td><?= view('admin/_partials/active_badge', ['isActive' => $category->is_active]) ?></td>
                            <td class="admin-table__actions">
                                <div class="admin-actions">
                                    <a
                                        class="admin-btn admin-btn--secondary admin-btn--small"
                                        href="<?= esc(site_url('admin/categories/' . $category->id . '/edit')) ?>"
                                    ><?= esc('Edit') ?></a>
                                    <?php if ($category->is_active) : ?>
                                        <form
                                            class="admin-actions__form"
                                            method="post"
                                            action="<?= esc(site_url('admin/categories/' . $category->id . '/deactivate')) ?>"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="admin-btn admin-btn--danger admin-btn--small" type="submit"><?= esc('Deactivate') ?></button>
                                        </form>
                                    <?php else : ?>
                                        <form
                                            class="admin-actions__form"
                                            method="post"
                                            action="<?= esc(site_url('admin/categories/' . $category->id . '/restore')) ?>"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Restore') ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>
