<?php

declare(strict_types=1);

/**
 * Public custom-post Theme template — SMITE 2026 (ADR-015 / ADR-016).
 *
 * @var string      $title
 * @var string      $manualAuthor
 * @var string      $locale
 * @var string      $slug
 * @var string      $body Sanitized RICH_TEXT HTML (ADR-014)
 * @var string      $requestedLocale
 * @var bool        $isFallback
 * @var mixed       $seo
 * @var string|null $seoPartial
 */
$settings = service('settingService')->getSiteSettings();
$siteName = $settings->siteName !== '' ? $settings->siteName : 'SMITE CMS';
?>
<!DOCTYPE html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
    <?= view('themes/2026/_partials/head', [
        'pageTitle'  => $title,
        'seo'        => $seo ?? null,
        'seoPartial' => $seoPartial ?? null,
    ]) ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to main content</a>
<?= view('themes/2026/_partials/site_header', [
    'siteName'    => $siteName,
    'navMode'     => 'site',
    'currentSlug' => '',
]) ?>
<main id="main" class="page-main">
    <div class="container">
        <?= view('themes/2026/_partials/locale_notice', [
            'requestedLocale' => $requestedLocale,
            'isFallback'      => $isFallback,
        ]) ?>

        <article class="post-article">
            <header class="post-header">
                <h1 class="post-header__title"><?= esc($title) ?></h1>
                <?php if ($manualAuthor !== '') : ?>
                    <p class="post-header__meta">By <?= esc($manualAuthor) ?></p>
                <?php endif; ?>
            </header>
            <div class="prose post-body">
                <?= $body ?>
            </div>
        </article>
    </div>
</main>
<?= view('themes/2026/_partials/site_footer', ['siteName' => $siteName]) ?>
</body>
</html>
