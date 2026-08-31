<?php

declare(strict_types=1);

/**
 * Staff user list (V2-003 / ADR-027 P0-1).
 *
 * @var list<array{
 *     id: int,
 *     username: string,
 *     email_display: string,
 *     group: string,
 *     group_label: string,
 *     is_active: bool,
 *     is_admin: bool,
 *     can_edit: bool,
 *     can_deactivate: bool,
 *     created_at: string,
 *     updated_at: string
 * }> $rows
 * @var array{multiple_admins: bool, admin_count: int, message: string|null} $invariant
 * @var string|null $success
 * @var string|null $error
 */
$activeNav = 'users';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Users
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Users') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Manage Editor and Contributor staff accounts. The Admin account is protected by the single-Admin rule.') ?>
            </p>
        </div>
    </header>

    <div class="admin-toolbar" aria-label="<?= esc('User list actions') ?>">
        <div class="admin-toolbar__group">
            <a class="admin-btn admin-btn--primary admin-btn--small" href="<?= esc(site_url('admin/users/new')) ?>">
                <?= esc('Create User') ?>
            </a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success ?? null,
        'error'   => $error ?? null,
        'errors'  => [],
    ]) ?>

    <?php if (! empty($invariant['multiple_admins']) && ! empty($invariant['message'])) : ?>
        <div class="admin-alert admin-alert--info" role="alert">
            <p><?= esc((string) $invariant['message']) ?></p>
        </div>
    <?php endif; ?>

    <?php if ($rows === []) : ?>
        <div class="admin-empty-state">
            <h2 class="admin-empty-state__title"><?= esc('No users yet') ?></h2>
            <p class="admin-empty-state__text">
                <?= esc('Create a staff account to get started.') ?>
            </p>
            <a class="admin-btn admin-btn--primary" href="<?= esc(site_url('admin/users/new')) ?>">
                <?= esc('Create User') ?>
            </a>
        </div>
    <?php else : ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col"><?= esc('Username') ?></th>
                        <th scope="col"><?= esc('Email') ?></th>
                        <th scope="col"><?= esc('Role') ?></th>
                        <th scope="col"><?= esc('Status') ?></th>
                        <th scope="col"><?= esc('Updated') ?></th>
                        <th scope="col" class="admin-table__actions"><?= esc('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td>
                                <span class="admin-table__primary"><?= esc($row['username']) ?></span>
                                <?php if ($row['is_admin']) : ?>
                                    <span class="status-badge status-badge--default"><?= esc('Admin') ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($row['email_display']) ?></td>
                            <td><?= esc($row['group_label']) ?></td>
                            <td><?= view('admin/_partials/active_badge', ['isActive' => $row['is_active']]) ?></td>
                            <td><?= esc($row['updated_at']) ?></td>
                            <td class="admin-table__actions">
                                <div class="admin-actions">
                                    <a
                                        class="admin-btn admin-btn--secondary admin-btn--small"
                                        href="<?= esc(site_url('admin/users/' . $row['id'] . '/edit')) ?>"
                                    ><?= esc('Edit') ?></a>
                                    <?php if ($row['is_active'] && $row['can_deactivate']) : ?>
                                        <form
                                            class="admin-actions__form"
                                            method="post"
                                            action="<?= esc(site_url('admin/users/' . $row['id'] . '/deactivate')) ?>"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="admin-btn admin-btn--danger admin-btn--small" type="submit"><?= esc('Deactivate') ?></button>
                                        </form>
                                    <?php elseif (! $row['is_active']) : ?>
                                        <form
                                            class="admin-actions__form"
                                            method="post"
                                            action="<?= esc(site_url('admin/users/' . $row['id'] . '/activate')) ?>"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Activate') ?></button>
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
