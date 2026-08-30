<?php

declare(strict_types=1);

/**
 * Media Library index (Phase 4 / Task 4.5 / TH-008 polish).
 *
 * @var string $status
 * @var list<array{asset: \App\Entities\MediaAsset, imageUrl: string|null}> $rows
 * @var string|null $success
 * @var string|null $error
 * @var bool $canPermanentDelete
 */
$activeNav          = 'media';
$isTrash            = $status === 'TRASH';
$canPermanentDelete = ! empty($canPermanentDelete);
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc('Media') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc($isTrash ? 'Media — Trash' : 'Media Library') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc($isTrash
                    ? 'Recover trashed media or permanently remove files when authorized.'
                    : 'Upload and manage images and documents for use in pages and posts.') ?>
            </p>
        </div>
    </header>

    <div class="admin-toolbar" aria-label="<?= esc('Media library actions') ?>">
        <div class="admin-toolbar__group">
            <span class="admin-toolbar__label"><?= esc('View') ?></span>
            <a
                class="admin-btn admin-btn--<?= $isTrash ? 'secondary' : 'primary' ?> admin-btn--small"
                href="<?= esc(site_url('admin/media')) ?>"
                <?= ! $isTrash ? ' aria-current="page"' : '' ?>
            ><?= esc('Active') ?></a>
            <a
                class="admin-btn admin-btn--<?= $isTrash ? 'primary' : 'secondary' ?> admin-btn--small"
                href="<?= esc(site_url('admin/media?status=TRASH')) ?>"
                <?= $isTrash ? ' aria-current="page"' : '' ?>
            ><?= esc('Trash') ?></a>
        </div>
        <?php if (! $isTrash) : ?>
            <div class="admin-toolbar__group">
                <a class="admin-btn admin-btn--primary admin-btn--small" href="<?= esc(site_url('admin/media/upload')) ?>">
                    <?= esc('Upload Media') ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success ?? null,
        'error'   => $error ?? null,
        'errors'  => [],
    ]) ?>

    <?php if ($rows === []) : ?>
        <div class="admin-empty-state">
            <h2 class="admin-empty-state__title">
                <?= esc($isTrash ? 'No media in Trash' : 'No media uploaded yet') ?>
            </h2>
            <p class="admin-empty-state__text">
                <?= esc($isTrash
                    ? 'Trashed files appear here until they are recovered or permanently removed when authorized.'
                    : 'Upload an image or document to use it in your content.') ?>
            </p>
            <?php if (! $isTrash) : ?>
                <a class="admin-btn admin-btn--primary" href="<?= esc(site_url('admin/media/upload')) ?>">
                    <?= esc('Upload Media') ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="admin-table-wrap">
            <table class="admin-table admin-table--media">
                <thead>
                    <tr>
                        <th scope="col" class="admin-table__preview-col"><?= esc('Preview') ?></th>
                        <th scope="col"><?= esc('ID') ?></th>
                        <th scope="col"><?= esc('Title / filename') ?></th>
                        <th scope="col"><?= esc('Type') ?></th>
                        <th scope="col"><?= esc('MIME') ?></th>
                        <th scope="col"><?= esc('Size') ?></th>
                        <th scope="col" class="admin-table__actions"><?= esc('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $asset     = $row['asset'];
                        $imageUrl  = $row['imageUrl'];
                        $fileSize  = (int) $asset->file_size;
                        $sizeLabel = $fileSize >= 1_048_576
                            ? number_format($fileSize / 1_048_576, 1) . ' MB'
                            : ($fileSize >= 1024
                                ? number_format($fileSize / 1024, 1) . ' KB'
                                : $fileSize . ' B');
                        $displayName = (string) ($asset->title ?: $asset->original_filename);
                        $dimensions  = $asset->width !== null && $asset->height !== null
                            ? (int) $asset->width . '×' . (int) $asset->height
                            : '';
                        ?>
                        <tr>
                            <td class="admin-table__preview-col">
                                <?php if ($imageUrl !== null) : ?>
                                    <img
                                        class="admin-media-thumb"
                                        src="<?= esc($imageUrl, 'attr') ?>"
                                        alt="<?= esc($asset->alt ?: $displayName, 'attr') ?>"
                                        width="48"
                                        height="48"
                                        loading="lazy"
                                    >
                                <?php else : ?>
                                    <div class="admin-media-thumb admin-media-thumb--document" aria-hidden="true">
                                        <span class="admin-media-thumb__ext"><?= esc(strtoupper((string) $asset->extension)) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc((string) $asset->id) ?></td>
                            <td>
                                <span class="admin-table__primary"><?= esc($displayName) ?></span>
                                <?php if ($asset->original_filename !== '' && $asset->title !== null && $asset->title !== '') : ?>
                                    <span class="admin-table__meta"><?= esc($asset->original_filename) ?></span>
                                <?php endif; ?>
                                <?php if ($dimensions !== '') : ?>
                                    <span class="admin-table__meta"><?= esc($dimensions) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= view('admin/_partials/type_badge', ['type' => (string) $asset->type]) ?></td>
                            <td><span class="admin-table__slug"><?= esc((string) $asset->mime_type) ?></span></td>
                            <td class="admin-table__date"><?= esc($sizeLabel) ?></td>
                            <td class="admin-table__actions">
                                <div class="admin-actions">
                                    <?php if (! $isTrash) : ?>
                                        <a
                                            class="admin-btn admin-btn--secondary admin-btn--small"
                                            href="<?= esc(site_url('admin/media/' . $asset->id . '/edit')) ?>"
                                        ><?= esc('Edit') ?></a>
                                        <?php if ($asset->type === 'DOCUMENT' && $asset->download_token) : ?>
                                            <a
                                                class="admin-btn admin-btn--secondary admin-btn--small"
                                                href="<?= esc(site_url('download/document/' . $asset->download_token)) ?>"
                                            ><?= esc('Download') ?></a>
                                        <?php elseif ($imageUrl !== null) : ?>
                                            <a
                                                class="admin-btn admin-btn--secondary admin-btn--small"
                                                href="<?= esc($imageUrl) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            ><?= esc('View') ?></a>
                                        <?php endif; ?>
                                        <form
                                            class="admin-actions__form"
                                            method="post"
                                            action="<?= esc(site_url('admin/media/' . $asset->id . '/trash')) ?>"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="admin-btn admin-btn--danger admin-btn--small" type="submit"><?= esc('Trash') ?></button>
                                        </form>
                                    <?php else : ?>
                                        <form
                                            class="admin-actions__form"
                                            method="post"
                                            action="<?= esc(site_url('admin/media/' . $asset->id . '/restore')) ?>"
                                        >
                                            <?= csrf_field() ?>
                                            <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Restore') ?></button>
                                        </form>
                                        <?php if ($canPermanentDelete) : ?>
                                            <form
                                                class="admin-actions__form"
                                                method="post"
                                                action="<?= esc(site_url('admin/media/' . $asset->id . '/delete')) ?>"
                                            >
                                                <?= csrf_field() ?>
                                                <button class="admin-btn admin-btn--danger admin-btn--small" type="submit"><?= esc('Delete permanently') ?></button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?= $this->endSection() ?>
