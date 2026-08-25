<?php

/**
 * Admin Audit Trail list (ADR-019 / Task 4.9E). Read-only.
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
$rows = is_array($rows ?? null) ? $rows : [];
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Audit Trail') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Audit Trail') ?></h1>
    <p><?= esc('Append-only operational history. Newest first.') ?></p>

    <?php if ($rows === []) : ?>
        <p><?= esc('No audit events yet.') ?></p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th><?= esc('When') ?></th>
                    <th><?= esc('Event') ?></th>
                    <th><?= esc('Actor') ?></th>
                    <th><?= esc('Resource') ?></th>
                    <th><?= esc('Revision') ?></th>
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
                        <td><?= esc((string) ($row['created_at'] ?? '')) ?></td>
                        <td><?= esc((string) ($row['event'] ?? '')) ?></td>
                        <td><?= esc((string) ($row['actor_label'] ?? '')) ?></td>
                        <td><?= esc($resourceLabel) ?></td>
                        <td><?= esc((string) ($row['revision_id'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?= $this->endSection() ?>
