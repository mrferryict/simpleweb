<?php
/**
 * Control Panel landing placeholder (Phase 1 / Tasks 1.17–1.20).
 *
 * Uses the shared admin layout. No dashboard widgets.
 *
 * @var string|null $username Authenticated username from Shield (optional).
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
SMITE CMS Control Panel
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('SMITE CMS Control Panel') ?></h1>

    <?php if ($username !== null && $username !== '') : ?>
        <p><?= esc('Welcome, ' . $username) ?></p>
    <?php else : ?>
        <p><?= esc('Welcome') ?></p>
    <?php endif; ?>

    <p><?= esc('Placeholder dashboard content. Modules will be added in later tasks.') ?></p>
<?= $this->endSection() ?>
