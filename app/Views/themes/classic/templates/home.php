<?php

declare(strict_types=1);

/**
 * Classic theme first-run landing (GET /).
 *
 * @var string $siteName
 * @var string $siteDescription
 * @var string $cpUrl
 */
$siteName        = isset($siteName) && is_string($siteName) && $siteName !== '' ? $siteName : 'SMITE CMS';
$siteDescription = isset($siteDescription) && is_string($siteDescription) ? $siteDescription : '';
$cpUrl           = isset($cpUrl) && is_string($cpUrl) ? $cpUrl : '/cp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($siteName) ?></title>
    <?php if ($siteDescription !== ''): ?>
        <meta name="description" content="<?= esc($siteDescription) ?>">
    <?php endif; ?>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: system-ui, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }
        main { max-width: 36rem; padding: 2rem 1.25rem; text-align: center; }
        h1 { margin: 0 0 0.75rem; font-size: 2rem; }
        p { margin: 0 0 1rem; color: #374151; line-height: 1.5; }
        a { color: #065f46; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <h1><?= esc($siteName) ?></h1>
    <p>Website is ready.</p>
    <p>Log in to <a href="<?= esc($cpUrl) ?>">/cp</a> to configure the site.</p>
</main>
</body>
</html>
