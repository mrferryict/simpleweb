<?php

declare(strict_types=1);

/**
 * Public custom-post template for the classic Theme.
 *
 * @var string               $title
 * @var string               $locale
 * @var string               $slug
 * @var array<string, mixed> $contentPayload
 * @var array<string, mixed> $contentMedia
 */
$payload = is_array($contentPayload ?? null) ? $contentPayload : [];
$body    = isset($payload['body']) && is_scalar($payload['body']) ? (string) $payload['body'] : '';
?>
<!DOCTYPE html>
<html lang="<?= esc($locale ?? 'id') ?>">
<head>
    <meta charset="utf-8">
    <?php if (isset($seo, $seoPartial) && is_string($seoPartial)) : ?>
        <?= view($seoPartial, ['seo' => $seo]) ?>
    <?php else : ?>
    <title><?= esc($title ?? '') ?></title>
    <?php endif; ?>
</head>
<body>
    <article>
        <h1><?= esc($title ?? '') ?></h1>
        <?= $body ?>
    </article>
</body>
</html>
