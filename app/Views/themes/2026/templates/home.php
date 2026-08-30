<?php

declare(strict_types=1);

/**
 * SMITE 2026 public landing (GET /) — theme-owned starter presentation (ADR-017 / TH-003).
 *
 * @var string $siteName
 * @var string $siteDescription
 * @var string $cpUrl
 */
$siteName        = isset($siteName) && is_string($siteName) && $siteName !== '' ? $siteName : 'SMITE CMS';
$siteDescription = isset($siteDescription) && is_string($siteDescription) ? trim($siteDescription) : '';
$cpUrl           = isset($cpUrl) && is_string($cpUrl) ? $cpUrl : site_url('cp');

$heroLead = $siteDescription !== ''
    ? $siteDescription
    : 'A welcoming online presence for your organization. Publish pages, share updates, and manage content from the control panel.';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?= view('themes/2026/_partials/head', [
        'pageTitle'       => $siteName,
        'metaDescription' => $siteDescription !== '' ? $siteDescription : 'Official website powered by SMITE CMS.',
    ]) ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<?= view('themes/2026/_partials/site_header', [
    'siteName' => $siteName,
    'navMode'  => 'home',
]) ?>
<main id="main">
    <section class="hero section">
        <div class="container hero__inner">
            <h1 class="hero__title"><?= esc($siteName) ?></h1>
            <p class="hero__lead"><?= esc($heroLead) ?></p>
            <div class="hero__actions">
                <a class="btn btn--primary" href="#about">Learn more</a>
                <a class="btn btn--secondary" href="<?= esc($cpUrl, 'attr') ?>">Control Panel</a>
            </div>
        </div>
    </section>

    <section id="about" class="section section--muted">
        <div class="container">
            <h2 class="section__title">About Us</h2>
            <div class="about-grid">
                <div class="about-grid__intro">
                    <p>We serve our community with purpose, clarity, and care. This website is ready for your mission, programs, and public information.</p>
                    <p>Use the control panel to publish pages, share news, and keep visitors informed.</p>
                </div>
                <ul class="about-grid__values">
                    <li><strong>Mission</strong> — Communicate your purpose clearly.</li>
                    <li><strong>Service</strong> — Share programs and resources.</li>
                    <li><strong>Community</strong> — Stay connected with timely updates.</li>
                </ul>
            </div>
        </div>
    </section>

    <section id="highlights" class="section">
        <div class="container">
            <h2 class="section__title">What We Offer</h2>
            <div class="card-grid">
                <article class="card">
                    <h3 class="card__title">Clear Information</h3>
                    <p class="card__text">Organize essential information in readable pages visitors can trust.</p>
                </article>
                <article class="card">
                    <h3 class="card__title">Timely Updates</h3>
                    <p class="card__text">Publish announcements and news when your organization is ready.</p>
                </article>
                <article class="card">
                    <h3 class="card__title">Accessible Design</h3>
                    <p class="card__text">A responsive layout that works across phones, tablets, and desktops.</p>
                </article>
            </div>
        </div>
    </section>

    <section id="updates" class="section section--muted">
        <div class="container">
            <h2 class="section__title">Latest Updates</h2>
            <p class="section__intro">Starter placeholders — replace with published posts when your content is ready.</p>
            <div class="card-grid">
                <article class="card card--placeholder">
                    <p class="card__meta">Informational</p>
                    <h3 class="card__title">Getting Started</h3>
                    <p class="card__text">Your website is live. Sign in to the control panel to add pages and news.</p>
                </article>
                <article class="card card--placeholder">
                    <p class="card__meta">Informational</p>
                    <h3 class="card__title">Share Your Story</h3>
                    <p class="card__text">Introduce your organization, programs, and values with structured content.</p>
                </article>
                <article class="card card--placeholder">
                    <p class="card__meta">Informational</p>
                    <h3 class="card__title">Stay Connected</h3>
                    <p class="card__text">Publish updates to keep your community informed and engaged.</p>
                </article>
            </div>
        </div>
    </section>

    <section id="contact" class="section cta-section">
        <div class="container cta-section__inner">
            <h2 class="section__title">Get in Touch</h2>
            <p class="cta-section__text">Ready to build your public presence? Manage your site from the control panel and publish content when you are prepared.</p>
            <a class="btn btn--primary" href="<?= esc($cpUrl, 'attr') ?>">Open Control Panel</a>
        </div>
    </section>
</main>
<?= view('themes/2026/_partials/site_footer', ['siteName' => $siteName]) ?>
</body>
</html>
