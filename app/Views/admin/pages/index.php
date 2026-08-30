<?php

declare(strict_types=1);

/**
 * Page list + Trash (Phase 2 / Task 2.5 + Phase 4 / Task 4.10 / TH-007 polish).
 *
 * @var list<array{page: \App\Entities\Page, translation: \App\Entities\PageTranslation|null}> $rows
 * @var bool $isTrash
 * @var string|null $success
 * @var string|null $error
 * @var bool $canTrash
 * @var bool $canRestore
 * @var bool $canPermanentDelete
 */
$activeNav           = 'pages';
$isTrash             = ! empty($isTrash);
$canTrash            = ! empty($canTrash);
$canRestore          = ! empty($canRestore);
$canPermanentDelete  = ! empty($canPermanentDelete);
$permanentDeleteMsg  = 'Permanent Delete: this Page will be removed. Revision and audit history are kept. Media files are not deleted. This cannot be undone.';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Pages
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc($isTrash ? 'Pages — Trash' : 'Pages') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc($isTrash
                    ? 'Recover trashed pages or permanently remove them when authorized.'
                    : 'Manage website pages, slugs, templates, and publication status.') ?>
            </p>
        </div>
    </header>

    <div class="admin-toolbar" aria-label="<?= esc('Page list actions') ?>">
        <div class="admin-toolbar__group">
            <span class="admin-toolbar__label"><?= esc('View') ?></span>
            <a
                class="admin-btn admin-btn--<?= $isTrash ? 'secondary' : 'primary' ?> admin-btn--small"
                href="<?= esc(site_url('admin/pages')) ?>"
                <?= ! $isTrash ? ' aria-current="page"' : '' ?>
            ><?= esc('Active') ?></a>
            <a
                class="admin-btn admin-btn--<?= $isTrash ? 'primary' : 'secondary' ?> admin-btn--small"
                href="<?= esc(site_url('admin/pages?status=TRASH')) ?>"
                <?= $isTrash ? ' aria-current="page"' : '' ?>
            ><?= esc('Trash') ?></a>
        </div>
        <?php if (! $isTrash) : ?>
            <div class="admin-toolbar__group">
                <a class="admin-btn admin-btn--primary admin-btn--small" href="<?= esc(site_url('admin/pages/new')) ?>">
                    <?= esc('Create Page') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success ?? null,
        'error'   => $error ?? null,
        'errors'  => [],
    ]) ?>

    <?php if ($rows === []) : ?>
        <div class="admin-empty-state">
            <h2 class="admin-empty-state__title">
                <?= esc($isTrash ? 'No pages in Trash' : 'No pages yet') ?>
            </h2>
            <p class="admin-empty-state__text">
                <?= esc($isTrash
                    ? 'Trashed pages appear here until they are recovered or permanently removed when authorized.'
                    : 'Create your first page to start building your website.') ?>
            </p>
            <?php if (! $isTrash) : ?>
                <a class="admin-btn admin-btn--primary" href="<?= esc(site_url('admin/pages/new')) ?>">
                    <?= esc('Create Page') ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col"><?= esc('ID') ?></th>
                        <th scope="col"><?= esc('Title') ?></th>
                        <th scope="col"><?= esc('Slug') ?></th>
                        <th scope="col"><?= esc('Locale') ?></th>
                        <th scope="col"><?= esc('Status') ?></th>
                        <th scope="col"><?= esc('Template') ?></th>
                        <th scope="col"><?= esc('Updated') ?></th>
                        <th scope="col" class="admin-table__actions"><?= esc('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $page        = $row['page'];
                        $translation = $row['translation'];
                        $lockVersion = (int) $page->lock_version;
                        $isRowTrash  = $page->status === \App\Enums\PageStatus::Trash->value;
                        $updatedAt   = is_string($page->updated_at ?? null) ? trim($page->updated_at) : '';
                        ?>
                        <tr>
                            <td><?= esc((string) $page->id) ?></td>
                            <td>
                                <span class="admin-table__primary"><?= esc($translation?->title ?? '—') ?></span>
                            </td>
                            <td><span class="admin-table__slug"><?= esc($translation?->slug ?? '—') ?></span></td>
                            <td><?= esc($translation?->locale ?? '—') ?></td>
                            <td><?= view('admin/_partials/status_badge', ['status' => (string) $page->status]) ?></td>
                            <td><?= esc($page->template_key) ?></td>
                            <td class="admin-table__date"><?= esc($updatedAt !== '' ? $updatedAt : '—') ?></td>
                            <td class="admin-table__actions">
                                <div class="admin-actions">
                                    <?php if ($isRowTrash) : ?>
                                        <?php if ($canRestore) : ?>
                                            <form
                                                class="admin-actions__form"
                                                method="post"
                                                action="<?= esc(site_url('admin/pages/' . $page->id . '/restore')) ?>"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                                                <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Restore') ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($canPermanentDelete) : ?>
                                            <form
                                                class="admin-actions__form"
                                                method="post"
                                                action="<?= esc(site_url('admin/pages/' . $page->id . '/permanent-delete')) ?>"
                                                data-confirm="<?= esc($permanentDeleteMsg, 'attr') ?>"
                                                onsubmit="return confirm(this.getAttribute('data-confirm'));"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                                                <button class="admin-btn admin-btn--danger admin-btn--small" type="submit"><?= esc('Permanent Delete') ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <a
                                            class="admin-btn admin-btn--secondary admin-btn--small"
                                            href="<?= esc(site_url('admin/pages/' . $page->id . '/edit')) ?>"
                                        ><?= esc('Edit') ?></a>
                                        <?php if ($canTrash) : ?>
                                            <form
                                                class="admin-actions__form"
                                                method="post"
                                                action="<?= esc(site_url('admin/pages/' . $page->id . '/delete')) ?>"
                                            >
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                                                <button class="admin-btn admin-btn--danger admin-btn--small" type="submit"><?= esc('Trash') ?></button>
                                            </form>
                                        <?php endif; ?>
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
