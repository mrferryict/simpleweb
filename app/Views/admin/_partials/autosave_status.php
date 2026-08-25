<?php

/**
 * HTMX autosave status fragment (ADR-019 / Task 4.9D).
 * Presentation only — no snapshot JSON, no internal paths.
 *
 * @var string      $state success|conflict|error|forbidden|not_found
 * @var string|null $message
 * @var int|null    $revisionNumber
 * @var int|null    $lockVersion
 * @var string|null $savedAt
 * @var array<string, string> $errors
 */
$state           = (string) ($state ?? 'error');
$message         = isset($message) && is_string($message) ? $message : null;
$revisionNumber  = isset($revisionNumber) && is_numeric($revisionNumber) ? (int) $revisionNumber : null;
$lockVersion     = isset($lockVersion) && is_numeric($lockVersion) ? (int) $lockVersion : null;
$savedAt         = isset($savedAt) && is_string($savedAt) ? $savedAt : null;
$errors          = is_array($errors ?? null) ? $errors : [];
?>
<div class="autosave-status" data-autosave-state="<?= esc($state, 'attr') ?>" role="status">
    <?php if ($state === 'success') : ?>
        <p>
            <?= esc('Draft saved') ?>
            <?php if ($revisionNumber !== null) : ?>
                <?= esc('(revision #' . $revisionNumber . ')') ?>
            <?php endif; ?>
            <?php if ($savedAt !== null && $savedAt !== '') : ?>
                <?= esc('at ' . $savedAt) ?>
            <?php endif; ?>
            <?php if ($lockVersion !== null) : ?>
                <span data-lock-version="<?= esc((string) $lockVersion, 'attr') ?>">
                    <?= esc('· lock ' . $lockVersion) ?>
                </span>
            <?php endif; ?>
        </p>
    <?php elseif ($state === 'conflict') : ?>
        <p role="alert">
            <?= esc($message ?? 'The content was modified by another session.') ?>
            <?php if ($lockVersion !== null) : ?>
                <?= esc('(current version: ' . $lockVersion . ')') ?>
            <?php endif; ?>
        </p>
    <?php else : ?>
        <p role="alert"><?= esc($message ?? 'Autosave failed.') ?></p>
        <?php if ($errors !== []) : ?>
            <ul>
                <?php foreach ($errors as $errorMessage) : ?>
                    <li><?= esc((string) $errorMessage) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>
