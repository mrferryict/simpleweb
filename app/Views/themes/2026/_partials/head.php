<?php

declare(strict_types=1);

/**
 * Shared document head for SMITE 2026 (charset, viewport, stylesheet, title/SEO).
 *
 * @var string      $pageTitle
 * @var string|null $metaDescription
 * @var mixed       $seo
 * @var string|null $seoPartial
 */
$pageTitle       = isset($pageTitle) && is_string($pageTitle) ? $pageTitle : 'SMITE CMS';
$metaDescription = isset($metaDescription) && is_string($metaDescription) ? $metaDescription : '';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php if (isset($seo, $seoPartial) && is_string($seoPartial)) : ?>
    <?= view($seoPartial, ['seo' => $seo]) ?>
<?php else : ?>
<title><?= esc($pageTitle) ?></title>
    <?php if ($metaDescription !== '') : ?>
<meta name="description" content="<?= esc($metaDescription, 'attr') ?>">
    <?php endif; ?>
<?php endif; ?>
<link rel="stylesheet" href="<?= esc(base_url('themes/2026/css/app.css'), 'attr') ?>">
