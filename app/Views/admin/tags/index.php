<?php

declare(strict_types=1);

/**
 * Tag list (Phase 3 / Task 3.7 / TH-008 polish).
 *
 * @var list<\App\Entities\Tag> $rows
 * @var string|null $success
 * @var string|null $error
 */
$activeNav = 'tags';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Tags
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Tags') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Tags provide flexible labels for posts. Use them alongside categories for finer organization.') ?>
            </p>
        </div>
    </header>

    <div class="admin-toolbar" aria-label="<?= esc('Tag list actions') ?>">
        <div class="admin-toolbar__group">
            <a class="admin-btn admin-btn--primary admin-btn--small" href="<?= esc(site_url('admin/tags/new')) ?>">
                <?= esc('Create Tag') ?>
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
            <h2 class="admin-empty-state__title"><?= esc('No tags yet') ?></h2>
            <p class="admin-empty-state__text">
                <?= esc('Create a tag to help organize your posts.') ?>
            </p>
            <a class="admin-btn admin-btn--primary" href="<?= esc(site_url('admin/tags/new')) ?>">
                <?= esc('Create Tag') ?>
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
                        <th scope="col" class="admin-table__actions"><?= esc('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $tag) : ?>
                        <tr>
                            <td><?= esc((string) $tag->id) ?></td>
                            <td>
                                <span class="admin-table__primary"><?= esc($tag->name) ?></span>
                            </td>
                            <td><span class="admin-table__slug"><?= esc($tag->slug) ?></span></td>
                            <td class="admin-table__actions">
                                <div class="admin-actions">
                                    <a
                                        class="admin-btn admin-btn--secondary admin-btn--small"
                                        href="<?= esc(site_url('admin/tags/' . $tag->id . '/edit')) ?>"
                                    ><?= esc('Edit') ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>
