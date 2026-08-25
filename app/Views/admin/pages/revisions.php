<?php

/**
 * Page editorial revision history (ADR-019 / Task 4.9C).
 *
 * @var int    $pageId
 * @var string $pageTitle
 * @var list<array{id: int, revision_number: int, is_autosave: bool, created_at: string, actor_label: string}> $revisions
 * @var bool   $canRestore
 * @var int    $lockVersion
 * @var string|null $success
 * @var string|null $flashError
 */
$pageId      = (int) ($pageId ?? 0);
$pageTitle   = (string) ($pageTitle ?? 'Page');
$revisions   = is_array($revisions ?? null) ? $revisions : [];
$canRestore  = ! empty($canRestore);
$lockVersion = isset($lockVersion) ? (int) $lockVersion : 1;
$success     = $success ?? null;
$flashError  = $flashError ?? null;
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Page revisions') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Revisions: ' . $pageTitle) ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/pages/' . $pageId . '/edit')) ?>"><?= esc('Back to edit') ?></a>
        ·
        <a href="<?= esc(site_url('admin/pages')) ?>"><?= esc('Pages') ?></a>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <?php if (! empty($flashError)) : ?>
        <p role="alert"><?= esc((string) $flashError) ?></p>
    <?php endif; ?>

    <?= $this->include('admin/_partials/revision_history', [
        'revisions'      => $revisions,
        'canRestore'     => $canRestore,
        'restoreBaseUrl' => site_url('admin/pages/' . $pageId . '/revisions'),
        'lockVersion'    => $lockVersion,
    ]) ?>
<?= $this->endSection() ?>
