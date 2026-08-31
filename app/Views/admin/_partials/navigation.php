<?php

declare(strict_types=1);

/**
 * Control Panel primary navigation (shared desktop sidebar + mobile menu).
 *
 * @var string $activeNav Current section key for aria-current (optional).
 */
$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : '';
$user      = auth()->user();

$items = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'url' => site_url('admin'), 'show' => true],
    ['key' => 'pages', 'label' => 'Pages', 'url' => site_url('admin/pages'), 'show' => true],
    ['key' => 'posts', 'label' => 'Posts', 'url' => site_url('admin/posts'), 'show' => true],
    ['key' => 'categories', 'label' => 'Categories', 'url' => site_url('admin/categories'), 'show' => true],
    ['key' => 'tags', 'label' => 'Tags', 'url' => site_url('admin/tags'), 'show' => true],
    ['key' => 'media', 'label' => 'Media', 'url' => site_url('admin/media'), 'show' => true],
    ['key' => 'menus', 'label' => 'Menus', 'url' => site_url('admin/menus'), 'show' => true],
    ['key' => 'settings', 'label' => 'Settings', 'url' => site_url('admin/settings'), 'show' => true],
    ['key' => 'users', 'label' => 'Users', 'url' => site_url('admin/users'), 'show' => $user?->can('user.manage') === true],
    ['key' => 'themes', 'label' => 'Themes', 'url' => site_url('admin/themes'), 'show' => $user?->can('theme.activate') === true],
    ['key' => 'audit', 'label' => 'Audit', 'url' => site_url('admin/audit'), 'show' => $user?->can('audit.view') === true],
];
?>
<nav class="admin-nav" aria-label="<?= esc('Control Panel primary') ?>">
    <ul class="admin-nav__list">
        <?php foreach ($items as $item) : ?>
            <?php if (! $item['show']) {
                continue;
            } ?>
            <li class="admin-nav__item">
                <a
                    class="admin-nav__link"
                    href="<?= esc($item['url'], 'attr') ?>"
                    <?= $activeNav === $item['key'] ? ' aria-current="page"' : '' ?>
                ><?= esc($item['label']) ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
