<?php
/**
 * Menu items index with two-level hierarchy and destination summary.
 *
 * @var array{
 *     PRIMARY: list<array{item: \App\Entities\MenuItem, children: list<\App\Entities\MenuItem>}>,
 *     FOOTER: list<array{item: \App\Entities\MenuItem, children: list<\App\Entities\MenuItem>}>
 * } $grouped
 * @var string|null $success
 * @var string|null $error
 */

use App\Enums\MenuTargetType;

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
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Menus
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Menus') ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/settings')) ?>"><?= esc('Back to Site Settings') ?></a>
        |
        <a href="<?= esc(site_url('admin/menus/new')) ?>"><?= esc('Add menu item') ?></a>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <?php if (! empty($error)) : ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>

    <?php foreach (['PRIMARY' => 'Primary', 'FOOTER' => 'Footer'] as $locationKey => $locationTitle) : ?>
        <section>
            <h2><?= esc($locationTitle) ?></h2>
            <?php $nodes = $grouped[$locationKey] ?? []; ?>
            <?php if ($nodes === []) : ?>
                <p><?= esc('No items in this location.') ?></p>
            <?php else : ?>
                <ul>
                    <?php foreach ($nodes as $node) : ?>
                        <?php $item = $node['item']; ?>
                        <li>
                            <strong><?= esc($item->label) ?></strong>
                            <?= esc('—') ?>
                            <?= esc(MenuTargetType::tryFromString((string) $item->target_type)?->label() ?? (string) $item->target_type) ?>
                            <?= esc('—') ?>
                            <?= esc($formatDestination($item)) ?>
                            <?= esc('—') ?>
                            <?= esc('order ' . $item->display_order) ?>
                            <?= esc('—') ?>
                            <?= esc($item->is_active ? 'Active' : 'Inactive') ?>
                            —
                            <a href="<?= esc(site_url('admin/menus/' . $item->id . '/edit')) ?>"><?= esc('Edit') ?></a>
                            <form
                                method="post"
                                action="<?= esc(site_url('admin/menus/' . $item->id . '/delete')) ?>"
                                style="display:inline"
                            >
                                <?= csrf_field() ?>
                                <button type="submit"><?= esc('Delete') ?></button>
                            </form>

                            <?php if ($node['children'] !== []) : ?>
                                <ul>
                                    <?php foreach ($node['children'] as $child) : ?>
                                        <li>
                                            <span><?= esc('Child: ' . $child->label) ?></span>
                                            <?= esc('—') ?>
                                            <?= esc(MenuTargetType::tryFromString((string) $child->target_type)?->label() ?? (string) $child->target_type) ?>
                                            <?= esc('—') ?>
                                            <?= esc($formatDestination($child)) ?>
                                            <?= esc('—') ?>
                                            <?= esc('order ' . $child->display_order) ?>
                                            <?= esc('—') ?>
                                            <?= esc($child->is_active ? 'Active' : 'Inactive') ?>
                                            —
                                            <a href="<?= esc(site_url('admin/menus/' . $child->id . '/edit')) ?>"><?= esc('Edit') ?></a>
                                            <form
                                                method="post"
                                                action="<?= esc(site_url('admin/menus/' . $child->id . '/delete')) ?>"
                                                style="display:inline"
                                            >
                                                <?= csrf_field() ?>
                                                <button type="submit"><?= esc('Delete') ?></button>
                                            </form>
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
<?= $this->endSection() ?>
