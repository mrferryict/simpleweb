<?php

declare(strict_types=1);

/**
 * Media asset type indicator (TH-008). Not an editorial status.
 *
 * @var string $type Raw type token (e.g. IMAGE, DOCUMENT).
 */
$rawType = strtoupper(trim((string) ($type ?? '')));

$labels = [
    'IMAGE'    => 'Image',
    'DOCUMENT' => 'Document',
];

$modifiers = [
    'IMAGE'    => 'image',
    'DOCUMENT' => 'document',
];

$label    = $labels[$rawType] ?? $rawType;
$modifier = $modifiers[$rawType] ?? 'default';
?>
<span class="type-badge type-badge--<?= esc($modifier, 'attr') ?>"><?= esc($label) ?></span>
