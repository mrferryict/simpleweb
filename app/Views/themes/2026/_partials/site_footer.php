<?php

declare(strict_types=1);

/**
 * Site footer for SMITE 2026.
 *
 * @var string $siteName
 */
$siteName = isset($siteName) && is_string($siteName) && $siteName !== '' ? $siteName : 'SMITE CMS';
$year     = date('Y');
?>
<footer class="site-footer">
    <div class="container site-footer__inner">
        <p class="site-footer__brand"><?= esc($siteName) ?></p>
        <p class="site-footer__copy">&copy; <?= esc($year) ?> <?= esc($siteName) ?>. All rights reserved.</p>
    </div>
</footer>
