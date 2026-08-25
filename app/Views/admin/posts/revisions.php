<?php

/**
 * Post editorial revision history (ADR-019 / Task 4.9C).
 *
 * @var int    $postId
 * @var string $postTitle
 * @var list<array{id: int, revision_number: int, is_autosave: bool, created_at: string, actor_label: string}> $revisions
 * @var bool   $canRestore
 * @var int    $lockVersion
 * @var string|null $success
 * @var string|null $flashError
 */
$postId      = (int) ($postId ?? 0);
$postTitle   = (string) ($postTitle ?? 'Post');
$revisions   = is_array($revisions ?? null) ? $revisions : [];
$canRestore  = ! empty($canRestore);
$lockVersion = isset($lockVersion) ? (int) $lockVersion : 1;
$success     = $success ?? null;
$flashError  = $flashError ?? null;
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Post revisions') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Revisions: ' . $postTitle) ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/posts/' . $postId . '/edit')) ?>"><?= esc('Back to edit') ?></a>
        ·
        <a href="<?= esc(site_url('admin/posts')) ?>"><?= esc('Posts') ?></a>
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
        'restoreBaseUrl' => site_url('admin/posts/' . $postId . '/revisions'),
        'lockVersion'    => $lockVersion,
    ]) ?>
<?= $this->endSection() ?>
