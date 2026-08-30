<?php

declare(strict_types=1);

/**
 * Developer-controlled static navigation (TH-003).
 *
 * @var string      $navMode   home|site
 * @var string|null $currentSlug
 */
$navMode          = isset($navMode) && is_string($navMode) ? $navMode : 'site';
$currentSlug      = isset($currentSlug) && is_string($currentSlug) ? $currentSlug : '';
$homeUrl          = site_url('/');
$newsLandingUrl   = site_url('berita');
$newsLandingSlug  = 'berita';
?>
<nav class="site-nav" aria-label="Primary">
    <details class="site-nav__toggle">
        <summary class="site-nav__summary">Menu</summary>
        <ul class="site-nav__list">
            <?php if ($navMode === 'home') : ?>
                <li><a href="<?= esc($homeUrl, 'attr') ?>" aria-current="page">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#highlights">Highlights</a></li>
                <li><a href="#updates">Updates</a></li>
                <li><a href="#contact">Contact</a></li>
            <?php else : ?>
                <li>
                    <a href="<?= esc($homeUrl, 'attr') ?>"<?= $currentSlug === '' ? ' aria-current="page"' : '' ?>>Home</a>
                </li>
                <li>
                    <a href="<?= esc(site_url('about'), 'attr') ?>"<?= $currentSlug === 'about' ? ' aria-current="page"' : '' ?>>About</a>
                </li>
                <li>
                    <a href="<?= esc($newsLandingUrl, 'attr') ?>"<?= $currentSlug === $newsLandingSlug ? ' aria-current="page"' : '' ?>>News</a>
                </li>
                <li>
                    <a href="<?= esc(site_url('contact'), 'attr') ?>"<?= $currentSlug === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a>
                </li>
            <?php endif; ?>
        </ul>
    </details>
    <ul class="site-nav__list site-nav__list--desktop">
        <?php if ($navMode === 'home') : ?>
            <li><a href="<?= esc($homeUrl, 'attr') ?>" aria-current="page">Home</a></li>
            <li><a href="#about">About</a></li>
            <li><a href="#highlights">Highlights</a></li>
            <li><a href="#updates">Updates</a></li>
            <li><a href="#contact">Contact</a></li>
        <?php else : ?>
            <li>
                <a href="<?= esc($homeUrl, 'attr') ?>"<?= $currentSlug === '' ? ' aria-current="page"' : '' ?>>Home</a>
            </li>
            <li>
                <a href="<?= esc(site_url('about'), 'attr') ?>"<?= $currentSlug === 'about' ? ' aria-current="page"' : '' ?>>About</a>
            </li>
            <li>
                <a href="<?= esc($newsLandingUrl, 'attr') ?>"<?= $currentSlug === $newsLandingSlug ? ' aria-current="page"' : '' ?>>News</a>
            </li>
            <li>
                <a href="<?= esc(site_url('contact'), 'attr') ?>"<?= $currentSlug === 'contact' ? ' aria-current="page"' : '' ?>>Contact</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
