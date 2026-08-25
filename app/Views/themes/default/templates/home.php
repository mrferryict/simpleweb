<?php

declare(strict_types=1);

/**
 * First-run / empty-site public landing (GET /).
 * Not backed by a Page row — administrators replace this by publishing real content.
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
        :root { color-scheme: light; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: Georgia, "Times New Roman", serif;
            background: linear-gradient(160deg, #f7f4ef 0%, #e8eef2 55%, #dfe8e3 100%);
            color: #1c2430;
        }
        main {
            max-width: 36rem;
            padding: 2.5rem 1.5rem;
            text-align: center;
        }
        h1 {
            margin: 0 0 0.75rem;
            font-size: clamp(2rem, 5vw, 2.75rem);
            letter-spacing: 0.02em;
            font-weight: 700;
        }
        p {
            margin: 0 0 1.5rem;
            font-size: 1.125rem;
            line-height: 1.55;
            color: #3a4656;
        }
        a {
            color: #1a5f4a;
            font-weight: 600;
            text-decoration-thickness: 1px;
            text-underline-offset: 0.2em;
        }
        a:hover { color: #0f3d31; }
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
