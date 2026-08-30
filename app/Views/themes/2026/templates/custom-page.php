<?php

declare(strict_types=1);

/**
 * Public custom-page Theme template — SMITE 2026 (ADR-002 / ADR-017).
 *
 * @var string               $title
 * @var string               $locale
 * @var string               $slug
 * @var array<string, mixed> $contentPayload
 * @var array<string, mixed> $contentMedia
 * @var string               $requestedLocale
 * @var bool                 $isFallback
 * @var mixed                $seo
 * @var string|null          $seoPartial
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
$heroAlt      = isset($heroImage['alt']) && is_string($heroImage['alt']) && $heroImage['alt'] !== ''
    ? $heroImage['alt']
    : ($heroTitle !== '' ? $heroTitle : $title);
$attachmentUrl = isset($attachment['url']) && is_string($attachment['url']) ? $attachment['url'] : '';
$attachmentLabel = isset($attachment['label']) && is_string($attachment['label']) && $attachment['label'] !== ''
    ? $attachment['label']
    : 'Download';

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
    'currentSlug' => $slug,
]) ?>
<main id="main" class="page-main">
    <div class="container">
        <?= view('themes/2026/_partials/locale_notice', [
            'requestedLocale' => $requestedLocale,
            'isFallback'      => $isFallback,
        ]) ?>

        <article class="page-article">
            <header class="page-hero">
                <h1 class="page-hero__title"><?= esc($title) ?></h1>
                <?php if ($heroTitle !== '') : ?>
                    <p class="page-hero__subtitle"><?= esc($heroTitle) ?></p>
                <?php endif; ?>
                <?php if ($heroDescription !== '') : ?>
                    <p class="page-hero__description"><?= esc($heroDescription) ?></p>
                <?php endif; ?>
                <?php if ($heroImageUrl !== '') : ?>
                    <figure class="page-hero__figure">
                        <img
                            class="page-hero__image"
                            src="<?= esc($heroImageUrl, 'attr') ?>"
                            alt="<?= esc($heroAlt, 'attr') ?>"
                            loading="lazy"
                            decoding="async"
                        >
                    </figure>
                <?php endif; ?>
            </header>

            <?php if ($body !== '') : ?>
                <div class="prose page-body">
                    <?= $body ?>
                </div>
            <?php endif; ?>

            <?php if ($attachmentUrl !== '') : ?>
                <p class="page-attachment">
                    <a class="btn btn--secondary" href="<?= esc($attachmentUrl, 'attr') ?>"><?= esc($attachmentLabel) ?></a>
                </p>
            <?php endif; ?>

            <?php if ($ctaUrl !== '') : ?>
                <p class="page-cta">
                    <a class="btn btn--primary" href="<?= esc($ctaUrl, 'attr') ?>">Learn more</a>
                </p>
            <?php endif; ?>

            <?php if ($videoUrl !== '') : ?>
                <p class="page-video">
                    <a href="<?= esc($videoUrl, 'attr') ?>" rel="noopener noreferrer">Watch video</a>
                </p>
            <?php endif; ?>

            <?php if ($slides !== []) : ?>
                <section class="hero-slides" aria-label="Slides">
                    <h2 class="hero-slides__title">Highlights</h2>
                    <ul class="hero-slides__list">
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
                                <li class="hero-slides__item">
                                    <?php if ($slideTitle !== '') : ?>
                                        <span class="hero-slides__label"><?= esc($slideTitle) ?></span>
                                    <?php endif; ?>
                                    <?php if ($slideUrl !== '') : ?>
                                        <a href="<?= esc($slideUrl, 'attr') ?>"><?= esc($slideTitle !== '' ? $slideTitle : $slideUrl) ?></a>
                                    <?php endif; ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </article>
    </div>
</main>
<?= view('themes/2026/_partials/site_footer', ['siteName' => $siteName]) ?>
</body>
</html>
