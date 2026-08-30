<?php

declare(strict_types=1);

/**
 * ENABLED Themes list and activation (Phase 6 / Task 6.1B / ADR-022 / TH-010 polish).
 *
 * @var list<array{
 *     id: string,
 *     name: string,
 *     version: string,
 *     author: string,
 *     state: string,
 *     is_active: bool
 * }> $themes
 * @var list<array{id: int, title: string}> $previewPages
 * @var string|null $success
 * @var string|null $error
 */
$activeNav = 'themes';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Themes
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Themes') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Manage the themes available to this CMS installation. Only ENABLED themes are listed; activate one to serve the public site.') ?>
            </p>
        </div>
    </header>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success ?? null,
        'error'   => $error ?? null,
        'errors'  => [],
    ]) ?>

    <?php if ($themes === []) : ?>
        <div class="admin-empty-state">
            <h2 class="admin-empty-state__title"><?= esc('No enabled themes available') ?></h2>
            <p class="admin-empty-state__text">
                <?= esc('ENABLED themes configured for this installation will appear here when they pass validation.') ?>
            </p>
        </div>
    <?php else : ?>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--themes">
                <caption class="admin-table__caption"><?= esc('Enabled themes available for activation') ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?= esc('Theme') ?></th>
                        <th scope="col"><?= esc('Version') ?></th>
                        <th scope="col"><?= esc('Author') ?></th>
                        <th scope="col"><?= esc('State') ?></th>
                        <th scope="col" class="admin-table__actions"><?= esc('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($themes as $theme) : ?>
                        <?php
                        $isActive = ! empty($theme['is_active']);
                        $state    = strtoupper((string) ($theme['state'] ?? ''));
                        ?>
                        <tr<?= $isActive ? ' class="admin-theme-row--active"' : '' ?>>
                            <td>
                                <span class="admin-table__primary"><?= esc($theme['name']) ?></span>
                                <span class="admin-table__meta admin-table__slug"><?= esc($theme['id']) ?></span>
                            </td>
                            <td class="admin-table__date"><?= esc($theme['version']) ?></td>
                            <td><?= esc($theme['author']) ?></td>
                            <td>
                                <?php if ($isActive) : ?>
                                    <span class="status-badge status-badge--published"><?= esc('Active') ?></span>
                                <?php else : ?>
                                    <span class="status-badge status-badge--default"><?= esc($state !== '' ? $state : 'Enabled') ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="admin-table__actions">
                                <div class="admin-actions">
                                    <?php if ($isActive) : ?>
                                        <span class="admin-theme-status" aria-current="true"><?= esc('Currently active') ?></span>
                                    <?php else : ?>
                                        <form
                                            class="admin-actions__form"
                                            method="post"
                                            action="<?= esc(site_url('admin/themes/' . $theme['id'] . '/activate')) ?>"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="admin-btn admin-btn--primary admin-btn--small" type="submit"><?= esc('Activate') ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($previewPages !== []) : ?>
                                        <?php foreach ($previewPages as $previewPage) : ?>
                                            <a
                                                class="admin-btn admin-btn--secondary admin-btn--small"
                                                href="<?= esc(site_url(
                                                    'admin/preview/theme/' . $theme['id'] . '/page/' . $previewPage['id'],
                                                )) ?>"
                                            ><?= esc('Preview: ' . $previewPage['title']) ?></a>
                                        <?php endforeach; ?>
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
