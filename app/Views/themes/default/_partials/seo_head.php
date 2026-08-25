<?php

declare(strict_types=1);

/**
 * Theme SEO head partial (ADR-024 §14).
 *
 * @var \App\Dtos\PublicSeoViewDto $seo
 */
?>
<title><?= esc($seo->documentTitle) ?></title>
<?php if ($seo->metaDescription !== '') : ?>
<meta name="description" content="<?= esc($seo->metaDescription, 'attr') ?>">
<?php endif; ?>
<link rel="canonical" href="<?= esc($seo->canonicalUrl, 'attr') ?>">
<?php foreach ($seo->hreflangAlternates as $alternate) : ?>
<link rel="alternate" hreflang="<?= esc($alternate['hreflang'], 'attr') ?>" href="<?= esc($alternate['href'], 'attr') ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= esc($seo->xDefaultUrl, 'attr') ?>">
<?php if ($seo->ogImageUrl !== null && $seo->ogImageUrl !== '') : ?>
<meta property="og:image" content="<?= esc($seo->ogImageUrl, 'attr') ?>">
<?php endif; ?>
