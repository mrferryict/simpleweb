<?php

declare(strict_types=1);

/**
 * Control Panel dashboard (TH-006).
 *
 * Module shortcuts only — no fabricated statistics or activity feeds.
 *
 * @var string|null $username Authenticated username from Shield (optional).
 */
$user = auth()->user();
$authUsername = $user?->username;
$displayName = is_string($authUsername) && $authUsername !== ''
    ? $authUsername
    : (isset($username) && is_string($username) && $username !== '' ? $username : 'there');

$can = static fn (string $permission): bool => $user?->can($permission) === true;

$quickActions = [
    [
        'label' => 'Create Page',
        'url'   => site_url('admin/pages/new'),
        'show'  => $can('page.create'),
        'style' => 'primary',
    ],
    [
        'label' => 'Create Post',
        'url'   => site_url('admin/posts/new'),
        'show'  => $can('post.create'),
        'style' => 'primary',
    ],
    [
        'label' => 'Open Media',
        'url'   => site_url('admin/media'),
        'show'  => $can('media.upload'),
        'style' => 'secondary',
    ],
    [
        'label' => 'Open Settings',
        'url'   => site_url('admin/settings'),
        'show'  => $can('site.manage'),
        'style' => 'secondary',
    ],
];

$sections = [
    [
        'title' => 'Content',
        'cards' => [
            [
                'title'       => 'Pages',
                'description' => 'Create and manage website pages.',
                'action'      => 'Open Pages',
                'url'         => site_url('admin/pages'),
                'show'        => $can('page.edit'),
                'icon'        => 'pages',
            ],
            [
                'title'       => 'Posts',
                'description' => 'Create and manage news and articles.',
                'action'      => 'Open Posts',
                'url'         => site_url('admin/posts'),
                'show'        => $can('post.create'),
                'icon'        => 'posts',
            ],
        ],
    ],
    [
        'title' => 'Media',
        'cards' => [
            [
                'title'       => 'Media',
                'description' => 'Manage images and documents.',
                'action'      => 'Open Media',
                'url'         => site_url('admin/media'),
                'show'        => $can('media.upload'),
                'icon'        => 'media',
            ],
        ],
    ],
    [
        'title' => 'Site',
        'cards' => [
            [
                'title'       => 'Menus',
                'description' => 'Manage site navigation.',
                'action'      => 'Open Menus',
                'url'         => site_url('admin/menus'),
                'show'        => $can('menu.manage'),
                'icon'        => 'menus',
            ],
        ],
    ],
    [
        'title' => 'Configuration',
        'cards' => [
            [
                'title'       => 'Settings',
                'description' => 'Configure site settings.',
                'action'      => 'Open Settings',
                'url'         => site_url('admin/settings'),
                'show'        => $can('site.manage'),
                'icon'        => 'settings',
            ],
        ],
    ],
    [
        'title' => 'Appearance',
        'cards' => [
            [
                'title'       => 'Themes',
                'description' => 'Manage active theme and previews.',
                'action'      => 'Open Themes',
                'url'         => site_url('admin/themes'),
                'show'        => $can('theme.activate'),
                'icon'        => 'themes',
            ],
        ],
    ],
    [
        'title' => 'Security',
        'cards' => [
            [
                'title'       => 'Audit',
                'description' => 'Review system activity.',
                'action'      => 'Open Audit',
                'url'         => site_url('admin/audit'),
                'show'        => $can('audit.view'),
                'icon'        => 'audit',
            ],
        ],
    ],
];

$iconSvg = static function (string $icon): string {
    return match ($icon) {
        'pages' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 2h9l5 5v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1zm8 1.5V8h4.5L14 3.5zM7 4v16h10V9h-5V4H7z"/></svg>',
        'posts' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16a1 1 0 0 1 1 1v3H3V5a1 1 0 0 1 1-1zm-1 7h18v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-9zm3 2v2h8v-2H6zm0 4v2h5v-2H6z"/></svg>',
        'media' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5zm2 0v10.2l3.6-3.6a1 1 0 0 1 1.4 0L15.2 18H18V5H6zm12 15v-4.8l-4.3-4.3-5.7 5.7H18zM9 8a2 2 0 1 0 0.001 4.001A2 2 0 0 0 9 8z"/></svg>',
        'menus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16v2H4V6zm0 5h16v2H4v-2zm0 5h16v2H4v-2z"/></svg>',
        'settings' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8.25a3.75 3.75 0 1 0 0 7.5 3.75 3.75 0 0 0 0-7.5zM4.5 12a1.5 1.5 0 0 1 1.07-1.44l1.2-.35-.35-1.2A1.5 1.5 0 0 1 8.7 6.8l1.2.35.35-1.2A1.5 1.5 0 0 1 12 4.5a1.5 1.5 0 0 1 1.75 1.45l.35 1.2 1.2-.35a1.5 1.5 0 0 1 1.95 1.01l.35 1.2 1.2.35A1.5 1.5 0 0 1 19.5 12a1.5 1.5 0 0 1-1.07 1.44l-1.2.35.35 1.2a1.5 1.5 0 0 1-1.95 1.01l-1.2-.35-.35 1.2A1.5 1.5 0 0 1 12 19.5a1.5 1.5 0 0 1-1.75-1.45l-.35-1.2-1.2.35a1.5 1.5 0 0 1-1.95-1.01l-.35-1.2-1.2-.35A1.5 1.5 0 0 1 4.5 12z"/></svg>',
        'themes' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a9 9 0 1 0 0 18c.55 0 1-.45 1-1v-1.1c0-.55.45-1 1-1 1.1 0 2-.9 2-2 0-.55.45-1 1-1h1.6c2.2 0 4-1.8 4-4 0-4.9-4-8.9-9.6-8.9z"/></svg>',
        'audit' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 0 0-7 7v3.1L3.3 16.8A1 1 0 0 0 4.2 18h15.6a1 1 0 0 0 .9-1.5L19 12.1V9a7 7 0 0 0-7-7zm0 2a5 5 0 0 1 5 5v3.5l1.2 2H5.8l1.2-2V9a5 5 0 0 1 5-5zm-1 14h2v2h-2v-2z"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4V4zm2 2v12h12V6H6z"/></svg>',
    };
};
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
SMITE CMS Control Panel
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <section class="admin-dashboard__welcome" aria-labelledby="dashboard-welcome-title">
        <h1 id="dashboard-welcome-title" class="admin-dashboard__welcome-title">
            <?= esc('Welcome back, ' . $displayName) ?>
        </h1>
        <p class="admin-dashboard__welcome-text">
            <?= esc('Manage your website content, media, navigation, and settings from the SMITE CMS Control Panel.') ?>
        </p>
    </section>

    <?php
    $visibleQuickActions = array_values(array_filter(
        $quickActions,
        static fn (array $action): bool => $action['show'],
    ));
    ?>
    <?php if ($visibleQuickActions !== []) : ?>
        <div class="admin-dashboard__quick-actions" aria-label="<?= esc('Quick actions') ?>">
            <?php foreach ($visibleQuickActions as $action) : ?>
                <a
                    class="admin-btn admin-btn--<?= esc($action['style'], 'attr') ?>"
                    href="<?= esc($action['url'], 'attr') ?>"
                ><?= esc($action['label']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($sections as $section) : ?>
        <?php
        $visibleCards = array_values(array_filter(
            $section['cards'],
            static fn (array $card): bool => $card['show'],
        ));
        if ($visibleCards === []) {
            continue;
        }
        ?>
        <section class="admin-dashboard__section" aria-labelledby="dashboard-section-<?= esc($section['title'], 'attr') ?>">
            <h2 id="dashboard-section-<?= esc($section['title'], 'attr') ?>" class="admin-dashboard__section-title">
                <?= esc($section['title']) ?>
            </h2>
            <div class="admin-card-grid">
                <?php foreach ($visibleCards as $card) : ?>
                    <article class="admin-card">
                        <div class="admin-card__head">
                            <span class="admin-card__icon"><?= $iconSvg($card['icon']) ?></span>
                            <h3 class="admin-card__title"><?= esc($card['title']) ?></h3>
                        </div>
                        <p class="admin-card__text"><?= esc($card['description']) ?></p>
                        <a class="admin-card__action" href="<?= esc($card['url'], 'attr') ?>">
                            <?= esc($card['action']) ?> <span aria-hidden="true">→</span>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
<?= $this->endSection() ?>
