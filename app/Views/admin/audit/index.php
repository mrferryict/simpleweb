<?php

declare(strict_types=1);

/**
 * Admin Audit Trail list (ADR-019 / Task 4.9E / TH-010 polish). Read-only.
 *
 * @var list<array{
 *     id: int,
 *     event: string,
 *     actor_label: string,
 *     resource_type: string,
 *     resource_id: string,
 *     revision_id: string,
 *     created_at: string
 * }> $rows
 */
$activeNav = 'audit';
$rows      = is_array($rows ?? null) ? $rows : [];
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Audit Trail') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Audit Trail') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Append-only operational history for compliance review. Events are listed newest first.') ?>
            </p>
        </div>
    </header>

    <?php if ($rows === []) : ?>
        <div class="admin-empty-state">
            <h2 class="admin-empty-state__title"><?= esc('No audit events yet') ?></h2>
            <p class="admin-empty-state__text">
                <?= esc('Administrative activity will appear here when events are recorded.') ?>
            </p>
        </div>
    <?php else : ?>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--audit">
                <caption class="admin-table__caption"><?= esc('Recent audit events') ?></caption>
                <thead>
                    <tr>
                        <th scope="col"><?= esc('When') ?></th>
                        <th scope="col"><?= esc('Event') ?></th>
                        <th scope="col"><?= esc('Actor') ?></th>
                        <th scope="col"><?= esc('Resource') ?></th>
                        <th scope="col"><?= esc('Revision') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        if (! is_array($row)) {
                            continue;
                        }
                        $resourceType = (string) ($row['resource_type'] ?? '—');
                        $resourceId   = (string) ($row['resource_id'] ?? '—');
                        $resourceLabel = $resourceType;
                        if ($resourceId !== '—' && $resourceType !== '—') {
                            $resourceLabel = $resourceType . ' #' . $resourceId;
                        } elseif ($resourceId !== '—') {
                            $resourceLabel = '#' . $resourceId;
                        }
                        ?>
                        <tr>
                            <td class="admin-table__date admin-audit__when"><?= esc((string) ($row['created_at'] ?? '')) ?></td>
                            <td>
                                <code class="admin-audit__event"><?= esc((string) ($row['event'] ?? '')) ?></code>
                            </td>
                            <td><?= esc((string) ($row['actor_label'] ?? '')) ?></td>
                            <td><span class="admin-audit__resource"><?= esc($resourceLabel) ?></span></td>
                            <td class="admin-table__date"><?= esc((string) ($row['revision_id'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>
