<?php

declare(strict_types=1);

/**
 * Category active/inactive indicator (TH-008).
 *
 * @var bool $isActive
 */
$active = ! empty($isActive);
$label  = $active ? 'Active' : 'Inactive';
$modifier = $active ? 'active' : 'inactive';
?>
<span class="status-badge status-badge--<?= esc($modifier, 'attr') ?>"><?= esc($label) ?></span>
