<?php
/**
 * Shared Control Panel layout (Phase 1 + Phase 3 Tasks 3.5–3.6).
 *
 * Presentation chrome only — authentication/authorization remain on filters.
 * Child views extend this layout and fill the `content` section.
 *
 * Optional section: `title` (plain text; escaped here).
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
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/quill.snow.css')) ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/quill-editor.css')) ?>">
</head>
<body>
    <header>
        <p><?= esc('SMITE CMS Control Panel') ?></p>
        <nav aria-label="<?= esc('Control Panel account') ?>">
            <?php if ($username !== null && $username !== '') : ?>
                <span><?= esc('Signed in as ' . $username) ?></span>
            <?php endif; ?>
            <a href="<?= esc(site_url('logout')) ?>"><?= esc('Log out') ?></a>
        </nav>
    </header>

    <nav aria-label="<?= esc('Control Panel primary') ?>">
        <ul>
            <li><a href="<?= esc(site_url('admin/pages')) ?>"><?= esc('Pages') ?></a></li>
            <li><a href="<?= esc(site_url('admin/posts')) ?>"><?= esc('Posts') ?></a></li>
            <li><a href="<?= esc(site_url('admin/categories')) ?>"><?= esc('Categories') ?></a></li>
            <li><a href="<?= esc(site_url('admin/tags')) ?>"><?= esc('Tags') ?></a></li>
            <li><a href="<?= esc(site_url('admin/media')) ?>"><?= esc('Media') ?></a></li>
            <li><a href="<?= esc(site_url('admin/menus')) ?>"><?= esc('Menus') ?></a></li>
            <li><a href="<?= esc(site_url('admin/settings')) ?>"><?= esc('Settings') ?></a></li>
            <?php if (auth()->user()?->can('theme.activate')) : ?>
                <li><a href="<?= esc(site_url('admin/themes')) ?>"><?= esc('Themes') ?></a></li>
            <?php endif; ?>
            <?php if (auth()->user()?->can('audit.view')) : ?>
                <li><a href="<?= esc(site_url('admin/audit')) ?>"><?= esc('Audit') ?></a></li>
            <?php endif; ?>
        </ul>
    </nav>

    <main>
        <?= $this->renderSection('content') ?>
    </main>

    <script src="<?= esc(base_url('assets/admin/js/htmx.min.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/htmx-csrf.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/quill.min.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/quill-editor.js')) ?>"></script>
    <script src="<?= esc(base_url('assets/admin/js/media-picker.js')) ?>"></script>
    <script defer src="<?= esc(base_url('assets/admin/js/alpine.min.js')) ?>"></script>
</body>
</html>
