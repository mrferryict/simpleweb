<?php

declare(strict_types=1);

/**
 * Single menu item row in the admin menu tree (TH-009).
 *
 * @var \App\Entities\MenuItem $item
 * @var callable $formatDestination
 * @var bool $isChild
 */

use App\Enums\MenuTargetType;

$isChild = ! empty($isChild);
$targetLabel = MenuTargetType::tryFromString((string) $item->target_type)?->label() ?? (string) $item->target_type;
?>
<div class="admin-menu-tree__row">
    <div class="admin-menu-tree__content">
        <span class="admin-menu-tree__label">
            <?php if ($isChild) : ?>
                <span class="admin-menu-tree__child-prefix"><?= esc('Child:') ?></span>
            <?php endif; ?>
            <?= esc($item->label) ?>
        </span>
        <span class="admin-menu-tree__meta">
            <?= esc($targetLabel) ?>
            <span aria-hidden="true">·</span>
            <?= esc($formatDestination($item)) ?>
            <span aria-hidden="true">·</span>
            <?= esc('Order ' . $item->display_order) ?>
        </span>
    </div>
    <div class="admin-menu-tree__status">
        <?= view('admin/_partials/active_badge', ['isActive' => $item->is_active]) ?>
    </div>
    <div class="admin-menu-tree__actions">
        <div class="admin-actions">
            <a
                class="admin-btn admin-btn--secondary admin-btn--small"
                href="<?= esc(site_url('admin/menus/' . $item->id . '/edit')) ?>"
            ><?= esc('Edit') ?></a>
            <form
                class="admin-actions__form"
                method="post"
                action="<?= esc(site_url('admin/menus/' . $item->id . '/delete')) ?>"
            >
                <?= csrf_field() ?>
                <button class="admin-btn admin-btn--danger admin-btn--small" type="submit"><?= esc('Delete') ?></button>
            </form>
        </div>
    </div>
</div>
