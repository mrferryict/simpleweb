<?php

declare(strict_types=1);

/**
 * Public custom-post Theme template (ADR-015 / ADR-016).
 *
 * @var string $title
 * @var string $manualAuthor
 * @var string $locale
 * @var string $slug
 * @var string $body Sanitized RICH_TEXT HTML (ADR-014) — render as HTML, not esc()'d text.
 * @var string $requestedLocale
 * @var bool   $isFallback
 */
?>
<!DOCTYPE html>
<html lang="<?= esc($locale, 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (isset($seo, $seoPartial) && is_string($seoPartial)) : ?>
        <?= view($seoPartial, ['seo' => $seo]) ?>
    <?php else : ?>
    <title><?= esc($title) ?></title>
    <?php endif; ?>
</head>
<body>
    <article>
        <header>
            <h1><?= esc($title) ?></h1>
            <?php if ($manualAuthor !== '') : ?>
                <p><?= esc($manualAuthor) ?></p>
            <?php endif; ?>
        </header>
        <div class="post-body">
            <?= $body ?>
        </div>
    </article>
</body>
</html>
