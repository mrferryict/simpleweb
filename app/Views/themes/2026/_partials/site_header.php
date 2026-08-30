<?php

declare(strict_types=1);

/**
 * Site header shell for SMITE 2026.
 *
 * @var string      $siteName
 * @var string      $navMode
 * @var string|null $currentSlug
 */
$siteName = isset($siteName) && is_string($siteName) && $siteName !== '' ? $siteName : 'SMITE CMS';
$navMode  = isset($navMode) && is_string($navMode) ? $navMode : 'site';
?>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-brand" href="<?= esc(site_url('/'), 'attr') ?>"><?= esc($siteName) ?></a>
        <?= view('themes/2026/_partials/site_nav', [
            'navMode'     => $navMode,
            'currentSlug' => $currentSlug ?? '',
        ]) ?>
    </div>
</header>
