<?php
/**
 * Post list + Trash (Phase 3 / Task 3.7 + Phase 4 / Task 4.10).
 *
 * @var list<array{post: \App\Entities\Post, translation: \App\Entities\PostTranslation|null, category_ids: list<int>, tag_ids: list<int>}> $rows
 * @var bool $isTrash
 * @var string|null $success
 * @var string|null $error
 * @var bool $canTrash
 * @var bool $canRestore
 * @var bool $canPermanentDelete
 */
$isTrash             = ! empty($isTrash);
$canTrash            = ! empty($canTrash);
$canRestore          = ! empty($canRestore);
$canPermanentDelete  = ! empty($canPermanentDelete);
$permanentDeleteMsg  = 'Permanent Delete: this Post will be removed. Revision and audit history are kept. Media files are not deleted. This cannot be undone.';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Posts
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc($isTrash ? 'Posts — Trash' : 'Posts') ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/posts')) ?>"><?= esc('Active') ?></a>
        |
        <a href="<?= esc(site_url('admin/posts?status=TRASH')) ?>"><?= esc('Trash') ?></a>
        <?php if (! $isTrash) : ?>
            |
            <a href="<?= esc(site_url('admin/posts/new')) ?>"><?= esc('Add post') ?></a>
        <?php endif; ?>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <?php if (! empty($error)) : ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>

    <?php if ($rows === []) : ?>
        <p><?= esc($isTrash ? 'No posts in Trash.' : 'No posts yet.') ?></p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th><?= esc('ID') ?></th>
                    <th><?= esc('Title') ?></th>
                    <th><?= esc('Slug') ?></th>
                    <th><?= esc('Locale') ?></th>
                    <th><?= esc('Author') ?></th>
                    <th><?= esc('Status') ?></th>
                    <th><?= esc('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php
                    $post        = $row['post'];
                    $translation = $row['translation'];
                    $lockVersion = (int) $post->lock_version;
                    $isRowTrash  = $post->status === \App\Enums\PostStatus::Trash->value;
                    ?>
                    <tr>
                        <td><?= esc((string) $post->id) ?></td>
                        <td><?= esc($translation?->title ?? '') ?></td>
                        <td><?= esc($translation?->slug ?? '') ?></td>
                        <td><?= esc($translation?->locale ?? '') ?></td>
                        <td><?= esc($post->manual_author) ?></td>
                        <td><?= esc($post->status) ?></td>
                        <td>
                            <?php if ($isRowTrash) : ?>
                                <?php if ($canRestore) : ?>
                                    <form
                                        method="post"
                                        action="<?= esc(site_url('admin/posts/' . $post->id . '/restore')) ?>"
                                        style="display:inline"
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                                        <button type="submit"><?= esc('Restore') ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($canPermanentDelete) : ?>
                                    <form
                                        method="post"
                                        action="<?= esc(site_url('admin/posts/' . $post->id . '/permanent-delete')) ?>"
                                        style="display:inline"
                                        data-confirm="<?= esc($permanentDeleteMsg, 'attr') ?>"
                                        onsubmit="return confirm(this.getAttribute('data-confirm'));"
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                                        <button type="submit"><?= esc('Permanent Delete') ?></button>
                                    </form>
                                <?php endif; ?>
                            <?php else : ?>
                                <a href="<?= esc(site_url('admin/posts/' . $post->id . '/edit')) ?>"><?= esc('Edit') ?></a>
                                <?php if ($canTrash) : ?>
                                    <form
                                        method="post"
                                        action="<?= esc(site_url('admin/posts/' . $post->id . '/delete')) ?>"
                                        style="display:inline"
                                    >
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                                        <button type="submit"><?= esc('Trash') ?></button>
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
