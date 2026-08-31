<?php

declare(strict_types=1);

/**
 * Shared authentication card header (TH-024 / TH-025).
 *
 * @var string      $cardTitle Page heading inside the card.
 * @var string|null $cardLead  Optional supporting text.
 */
$cardLead = $cardLead ?? null;
$siteSettings = service('settingService')->getSiteSettings();
$siteName     = $siteSettings->siteName !== '' ? $siteSettings->siteName : 'SMITE CMS';
?>
            <header class="admin-auth-card__header">
                <span class="admin-auth-card__accent" aria-hidden="true"></span>
                <p class="admin-auth-card__brand"><?= esc($siteName) ?></p>
                <h1 class="admin-auth-card__title"><?= esc($cardTitle) ?></h1>
                <?php if ($cardLead !== null && $cardLead !== '') : ?>
                    <p class="admin-auth-card__lead"><?= esc($cardLead) ?></p>
                <?php endif; ?>
            </header>
