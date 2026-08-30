<?php

declare(strict_types=1);

/**
 * Shared Control Panel layout (Phase 1 + Phase 3 Tasks 3.5–3.6 / TH-006 shell).
 *
 * Presentation chrome only — authentication/authorization remain on filters.
 * Child views extend this layout and fill the `content` section.
 *
 * Optional sections: `title` (plain text; escaped here).
 * Optional view data: `activeNav` (navigation highlight key).
 *
 * Frontend assets: pinned vendored static files under /assets/admin/ (ADR-010).
 * CSRF for HTMX: meta token + htmx-csrf.js (DOC-03 §11 / .cursorrules §4.5).
 */
$username = auth()->user()?->username;
$username = is_string($username) ? $username : null;
$pageTitle = trim($this->renderSection('title'));
if ($pageTitle === '') {
    $pageTitle = 'SMITE CMS Control Panel';
}
$activeNav = isset($activeNav) && is_string($activeNav) ? $activeNav : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle) ?></title>
    <meta name="csrf-token" content="<?= esc(csrf_hash()) ?>">
    <meta name="csrf-header" content="<?= esc(config('Security')->headerName) ?>">
    <meta name="csrf-param" content="<?= esc(csrf_token()) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/admin-shell.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/admin-content.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/quill.snow.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/quill-editor.css')) ?>">
</head>
<body class="admin-shell">
    <a class="admin-skip-link" href="#main"><?= esc('Skip to main content') ?></a>

    <div class="admin-app">
        <header class="admin-header">
            <div class="admin-header__brand-group">
                <p class="admin-header__brand"><?= esc('SMITE CMS') ?></p>
                <p class="admin-header__subtitle"><?= esc('Control Panel') ?></p>
            </div>

            <details class="admin-nav-toggle">
                <summary class="admin-nav-toggle__summary"><?= esc('Menu') ?></summary>
                <div class="admin-nav--mobile">
                    <?= view('admin/_partials/navigation', ['activeNav' => $activeNav]) ?>
                </div>
            </details>

            <div class="admin-header__account" aria-label="<?= esc('Control Panel account') ?>">
                <?php if ($username !== null && $username !== '') : ?>
                    <span class="admin-header__user"><?= esc('Signed in as ' . $username) ?></span>
                <?php endif; ?>
                <a class="admin-header__logout" href="<?= esc(site_url('logout')) ?>"><?= esc('Log out') ?></a>
            </div>
        </header>

        <div class="admin-layout">
            <aside class="admin-sidebar admin-sidebar--desktop" aria-label="<?= esc('Control Panel navigation') ?>">
                <?= view('admin/_partials/navigation', ['activeNav' => $activeNav]) ?>
            </aside>

            <main id="main" class="admin-main">
                <div class="admin-main__inner">
                    <?= $this->renderSection('content') ?>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= esc(base_url('assets/admin/js/htmx.min.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/htmx-csrf.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/quill.min.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/quill-editor.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/media-picker.js')) ?>"></script>
    <script defer src="<?= esc(base_url('assets/admin/js/alpine.min.js')) ?>"></script>
</body>
</html>
