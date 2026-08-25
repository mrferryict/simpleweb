<?php
/**
 * Media Library index (Phase 4 / Task 4.5).
 *
 * @var string $status
 * @var list<array{asset: \App\Entities\MediaAsset, imageUrl: string|null}> $rows
 * @var string|null $success
 * @var string|null $error
 * @var bool $canPermanentDelete
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Media') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Media Library') ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/media')) ?>"><?= esc('Active') ?></a>
        |
        <a href="<?= esc(site_url('admin/media?status=TRASH')) ?>"><?= esc('Trash') ?></a>
        |
        <a href="<?= esc(site_url('admin/media/upload')) ?>"><?= esc('Upload') ?></a>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <?php if (! empty($error)) : ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>

    <?php if ($rows === []) : ?>
        <p><?= esc('No media in this list.') ?></p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th><?= esc('ID') ?></th>
                    <th><?= esc('Type') ?></th>
                    <th><?= esc('Title / filename') ?></th>
                    <th><?= esc('MIME') ?></th>
                    <th><?= esc('Size') ?></th>
                    <th><?= esc('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php $asset = $row['asset']; ?>
                    <tr>
                        <td><?= esc((string) $asset->id) ?></td>
                        <td><?= esc((string) $asset->type) ?></td>
                        <td>
                            <?= esc((string) ($asset->title ?: $asset->original_filename)) ?>
                            <?php if ($row['imageUrl'] !== null) : ?>
                                <br><code><?= esc($row['imageUrl']) ?></code>
                            <?php endif; ?>
                            <?php if ($asset->type === 'DOCUMENT' && $asset->download_token) : ?>
                                <br><a href="<?= esc(site_url('download/document/' . $asset->download_token)) ?>"><?= esc('Download') ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?= esc((string) $asset->mime_type) ?></td>
                        <td><?= esc((string) $asset->file_size) ?></td>
                        <td>
                            <?php if ($status === 'ACTIVE') : ?>
                                <a href="<?= esc(site_url('admin/media/' . $asset->id . '/edit')) ?>"><?= esc('Edit') ?></a>
                                <form method="post" action="<?= esc(site_url('admin/media/' . $asset->id . '/trash')) ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit"><?= esc('Trash') ?></button>
                                </form>
                            <?php else : ?>
                                <form method="post" action="<?= esc(site_url('admin/media/' . $asset->id . '/restore')) ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit"><?= esc('Restore') ?></button>
                                </form>
                                <?php if (! empty($canPermanentDelete)) : ?>
                                    <form method="post" action="<?= esc(site_url('admin/media/' . $asset->id . '/delete')) ?>" style="display:inline">
                                        <?= csrf_field() ?>
                                        <button type="submit"><?= esc('Delete permanently') ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?= $this->endSection() ?>
