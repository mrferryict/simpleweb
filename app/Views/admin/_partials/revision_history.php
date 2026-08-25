<?php

/**
 * Editorial revision history list (ADR-019 / Task 4.9C).
 * Autosaves (is_autosave=1) are excluded by RevisionService::listEditorialHistory.
 *
 * @var list<array{id: int, revision_number: int, is_autosave: bool, created_at: string, actor_label: string}> $revisions
 * @var bool   $canRestore
 * @var string $restoreBaseUrl e.g. site_url('admin/posts/12/revisions')
 * @var int    $lockVersion
 */
$revisions      = is_array($revisions ?? null) ? $revisions : [];
$canRestore     = ! empty($canRestore);
$restoreBaseUrl = (string) ($restoreBaseUrl ?? '');
$lockVersion    = isset($lockVersion) ? (int) $lockVersion : 1;
?>
<?php if ($revisions === []) : ?>
    <p><?= esc('No editorial revisions yet.') ?></p>
<?php else : ?>
    <table>
        <thead>
            <tr>
                <th><?= esc('Revision') ?></th>
                <th><?= esc('Type') ?></th>
                <th><?= esc('Actor') ?></th>
                <th><?= esc('Created') ?></th>
                <?php if ($canRestore) : ?>
                    <th><?= esc('Actions') ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($revisions as $row) : ?>
                <?php
                if (! is_array($row)) {
                    continue;
                }
                $revId     = (int) ($row['id'] ?? 0);
                $revNumber = (int) ($row['revision_number'] ?? 0);
                $actor     = (string) ($row['actor_label'] ?? '');
                $created   = (string) ($row['created_at'] ?? '');
                $autosave  = ! empty($row['is_autosave']);
                ?>
                <tr>
                    <td><?= esc('#' . $revNumber) ?></td>
                    <td><?= esc($autosave ? 'Autosave' : 'Manual') ?></td>
                    <td><?= esc($actor) ?></td>
                    <td><?= esc($created) ?></td>
                    <?php if ($canRestore && $revId > 0 && $restoreBaseUrl !== '') : ?>
                        <td>
                            <form
                                method="post"
                                action="<?= esc(rtrim($restoreBaseUrl, '/') . '/' . $revId . '/restore', 'attr') ?>"
                                style="display:inline"
                            >
                                <?= csrf_field() ?>
                                <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                                <button type="submit"><?= esc('Restore') ?></button>
                            </form>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
