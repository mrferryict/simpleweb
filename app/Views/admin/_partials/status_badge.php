<?php

declare(strict_types=1);

/**
 * Editorial status badge for Pages and Posts (TH-007).
 *
 * @var string $status Raw status token from entity (e.g. DRAFT, PUBLISHED).
 */
$rawStatus = strtoupper(trim((string) ($status ?? '')));

$labels = [
    'DRAFT'          => 'Draft',
    'PENDING_REVIEW' => 'In Review',
    'PUBLISHED'      => 'Published',
    'UNPUBLISHED'    => 'Unpublished',
    'ARCHIVED'       => 'Archived',
    'TRASH'          => 'Trash',
];

$modifiers = [
    'DRAFT'          => 'draft',
    'PENDING_REVIEW' => 'review',
    'PUBLISHED'      => 'published',
    'UNPUBLISHED'    => 'unpublished',
    'ARCHIVED'       => 'archived',
    'TRASH'          => 'trash',
];

$label    = $labels[$rawStatus] ?? $rawStatus;
$modifier = $modifiers[$rawStatus] ?? 'default';
?>
<span class="status-badge status-badge--<?= esc($modifier, 'attr') ?>"><?= esc($label) ?></span>
