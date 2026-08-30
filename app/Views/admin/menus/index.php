<?php

declare(strict_types=1);

/**
 * Menu items index with two-level hierarchy (Phase 2 / Task 2.4 / TH-009 polish).
 *
 * @var array{
 *     PRIMARY: list<array{item: \App\Entities\MenuItem, children: list<\App\Entities\MenuItem>}>,
 *     FOOTER: list<array{item: \App\Entities\MenuItem, children: list<\App\Entities\MenuItem>}>
 * } $grouped
 * @var string|null $success
 * @var string|null $error
 */

use App\Enums\MenuTargetType;

$activeNav = 'menus';

$formatDestination = static function ($item): string {
    $type = MenuTargetType::tryFromString((string) $item->target_type);
    if ($type === null) {
        return (string) $item->target_type;
    }

    return match ($type) {
        MenuTargetType::Page => 'Page #' . (string) $item->target_id,
        MenuTargetType::PostCategory => 'Post Category #' . (string) $item->target_id,
        MenuTargetType::ExternalUrl => (string) $item->destination,
    };
};

$hasAnyItems = false;
foreach (['PRIMARY', 'FOOTER'] as $locationKey) {
    if (($grouped[$locationKey] ?? []) !== []) {
        $hasAnyItems = true;
        break;
    }
}
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Menus
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Menus') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Manage navigation items for the Primary and Footer menu locations. Items support one level of nesting.') ?>
            </p>
        </div>
    </header>

    <div class="admin-toolbar" aria-label="<?= esc('Menu management actions') ?>">
        <div class="admin-toolbar__group">
            <a class="admin-btn admin-btn--secondary admin-btn--small" href="<?= esc(site_url('admin/settings')) ?>">
                <?= esc('Site Settings') ?>
            </a>
            <a class="admin-btn admin-btn--primary admin-btn--small" href="<?= esc(site_url('admin/menus/new')) ?>">
                <?= esc('Add menu item') ?>
            </a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success ?? null,
        'error'   => $error ?? null,
        'errors'  => [],
    ]) ?>

    <?php if (! $hasAnyItems) : ?>
        <div class="admin-empty-state">
            <h2 class="admin-empty-state__title"><?= esc('No menu items yet') ?></h2>
            <p class="admin-empty-state__text">
                <?= esc('Create a menu item to organize navigation for your website.') ?>
            </p>
            <a class="admin-btn admin-btn--primary" href="<?= esc(site_url('admin/menus/new')) ?>">
                <?= esc('Add menu item') ?>
            </a>
        </div>
    <?php else : ?>
        <?php foreach (['PRIMARY' => 'Primary', 'FOOTER' => 'Footer'] as $locationKey => $locationTitle) : ?>
            <?php $nodes = $grouped[$locationKey] ?? []; ?>
            <section class="admin-form-section admin-menu-location" aria-labelledby="menu-location-<?= esc(strtolower($locationKey), 'attr') ?>">
                <h2 id="menu-location-<?= esc(strtolower($locationKey), 'attr') ?>" class="admin-form-section__title">
                    <?= esc($locationTitle) ?>
                </h2>

                <?php if ($nodes === []) : ?>
                    <p class="admin-form-hint"><?= esc('No items in this location.') ?></p>
                <?php else : ?>
                    <ul class="admin-menu-tree">
                        <?php foreach ($nodes as $node) : ?>
                            <?php $item = $node['item']; ?>
                            <li class="admin-menu-tree__item">
                                <?= view('admin/menus/_partials/tree_item', [
                                    'item'              => $item,
                                    'formatDestination' => $formatDestination,
                                    'isChild'           => false,
                                ]) ?>

                                <?php if ($node['children'] !== []) : ?>
                                    <ul class="admin-menu-tree__children">
                                        <?php foreach ($node['children'] as $child) : ?>
                                            <li class="admin-menu-tree__item admin-menu-tree__item--child">
                                                <?= view('admin/menus/_partials/tree_item', [
                                                    'item'              => $child,
                                                    'formatDestination' => $formatDestination,
                                                    'isChild'           => true,
                                                ]) ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
<?= $this->endSection() ?>
