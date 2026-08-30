<?php

declare(strict_types=1);

/**
 * Subtle locale fallback notice (ADR-024).
 *
 * @var string $requestedLocale
 * @var bool   $isFallback
 */
if (! isset($isFallback) || $isFallback !== true) {
    return;
}

$requestedLocale = isset($requestedLocale) && is_string($requestedLocale) ? $requestedLocale : 'en';
$message         = $requestedLocale === 'en'
    ? 'This content is shown in the primary site language.'
    : 'Konten ini ditampilkan dalam bahasa utama situs.';
?>
<div class="locale-notice" role="status">
    <p><?= esc($message) ?></p>
</div>
