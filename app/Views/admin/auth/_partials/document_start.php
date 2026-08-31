<?php

declare(strict_types=1);

/**
 * Shared authentication document head and page opener (TH-024 / TH-025).
 *
 * @var string      $pageTitle  Document title (escaped in output).
 * @var string|null $skipLabel  Skip-link label; defaults to "Skip to main content".
 */
$skipLabel = isset($skipLabel) && is_string($skipLabel) && $skipLabel !== ''
    ? $skipLabel
    : 'Skip to main content';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/admin-shell.css'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/admin-content.css'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/admin/css/auth.css'), 'attr') ?>">
</head>
<body class="admin-auth">
    <a class="admin-skip-link" href="#auth-main"><?= esc($skipLabel) ?></a>

    <div class="admin-auth-page">
        <main id="auth-main" class="admin-auth-card">
