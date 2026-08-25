<?php

declare(strict_types=1);

/**
 * Public custom-page Theme template (ADR-002 / ADR-017 / Task 4.7).
 *
 * @var string               $title
 * @var string               $locale
 * @var string               $slug
 * @var array<string, mixed> $contentPayload Persist-sanitized payload (ADR-014); media_id remains int
 * @var array<string, mixed> $contentMedia   Parallel ACTIVE media presentation (Task 4.7)
 * @var string               $requestedLocale
 * @var bool                 $isFallback
 */
$payload = is_array($contentPayload ?? null) ? $contentPayload : [];
$media   = is_array($contentMedia ?? null) ? $contentMedia : [];

$heroTitle       = isset($payload['hero_title']) && is_scalar($payload['hero_title'])
    ? (string) $payload['hero_title']
    : '';
$heroDescription = isset($payload['hero_description']) && is_scalar($payload['hero_description'])
    ? (string) $payload['hero_description']
    : '';
$body            = isset($payload['body']) && is_scalar($payload['body'])
    ? (string) $payload['body']
    : '';
$ctaUrl          = isset($payload['cta_url']) && is_scalar($payload['cta_url'])
    ? (string) $payload['cta_url']
    : '';
$videoUrl        = isset($payload['video_url']) && is_scalar($payload['video_url'])
    ? (string) $payload['video_url']
    : '';
$slides          = isset($payload['hero_slides']) && is_array($payload['hero_slides'])
    ? $payload['hero_slides']
    : [];

$heroImage = isset($media['hero_image']) && is_array($media['hero_image'])
    ? $media['hero_image']
    : null;
$attachment = isset($media['attachment']) && is_array($media['attachment'])
    ? $media['attachment']
    : null;

$heroImageUrl = isset($heroImage['url']) && is_string($heroImage['url']) ? $heroImage['url'] : '';
$attachmentUrl = isset($attachment['url']) && is_string($attachment['url']) ? $attachment['url'] : '';
$attachmentLabel = isset($attachment['label']) && is_string($attachment['label']) && $attachment['label'] !== ''
    ? $attachment['label']
    : 'Download';
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
            <?php if ($heroTitle !== '') : ?>
                <p class="hero-title"><?= esc($heroTitle) ?></p>
            <?php endif; ?>
            <?php if ($heroDescription !== '') : ?>
                <p class="hero-description"><?= esc($heroDescription) ?></p>
            <?php endif; ?>
            <?php if ($heroImageUrl !== '') : ?>
                <p class="hero-image">
                    <img src="<?= esc($heroImageUrl, 'attr') ?>" alt="">
                </p>
            <?php endif; ?>
        </header>

        <?php if ($body !== '') : ?>
            <div class="page-body">
                <?= $body ?>
            </div>
        <?php endif; ?>

        <?php if ($attachmentUrl !== '') : ?>
            <p class="attachment">
                <a href="<?= esc($attachmentUrl, 'attr') ?>"><?= esc($attachmentLabel) ?></a>
            </p>
        <?php endif; ?>

        <?php if ($ctaUrl !== '') : ?>
            <p><a href="<?= esc($ctaUrl, 'attr') ?>"><?= esc($ctaUrl) ?></a></p>
        <?php endif; ?>

        <?php if ($videoUrl !== '') : ?>
            <p class="video-url"><?= esc($videoUrl) ?></p>
        <?php endif; ?>

        <?php if ($slides !== []) : ?>
            <ul class="hero-slides">
                <?php foreach ($slides as $slide) : ?>
                    <?php if (! is_array($slide)) :
                        continue;
                    endif; ?>
                    <?php
                    $slideTitle = isset($slide['title']) && is_scalar($slide['title'])
                        ? (string) $slide['title']
                        : '';
                    $slideUrl = isset($slide['url']) && is_scalar($slide['url'])
                        ? (string) $slide['url']
                        : '';
                    ?>
                    <?php if ($slideTitle !== '' || $slideUrl !== '') : ?>
                        <li>
                            <?php if ($slideTitle !== '') : ?>
                                <span><?= esc($slideTitle) ?></span>
                            <?php endif; ?>
                            <?php if ($slideUrl !== '') : ?>
                                <a href="<?= esc($slideUrl, 'attr') ?>"><?= esc($slideUrl) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
</body>
</html>
