<?php
/**
 * ENABLED Themes list and activation (Phase 6 / Task 6.1B / ADR-022).
 *
 * @var list<array{
 *     id: string,
 *     name: string,
 *     version: string,
 *     author: string,
 *     state: string,
 *     is_active: bool
 * }> $themes
 * @var list<array{id: int, title: string}> $previewPages
 * @var string|null $success
 * @var string|null $error
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Themes
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Themes') ?></h1>

    <p><?= esc('Activate an ENABLED Theme. DRAFT Themes are not listed here.') ?></p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>

    <?php if (! empty($error)) : ?>
        <p role="alert"><?= esc((string) $error) ?></p>
    <?php endif; ?>

    <?php if ($themes === []) : ?>
        <p><?= esc('No ENABLED Themes are available.') ?></p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th scope="col"><?= esc('Theme') ?></th>
                    <th scope="col"><?= esc('Version') ?></th>
                    <th scope="col"><?= esc('Author') ?></th>
                    <th scope="col"><?= esc('State') ?></th>
                    <th scope="col"><?= esc('Action') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($themes as $theme) : ?>
                    <tr>
                        <td>
                            <?= esc($theme['name']) ?>
                            <span><?= esc('(' . $theme['id'] . ')') ?></span>
                        </td>
                        <td><?= esc($theme['version']) ?></td>
                        <td><?= esc($theme['author']) ?></td>
                        <td><?= esc($theme['state']) ?></td>
                        <td>
                            <?php if ($theme['is_active']) : ?>
                                <?= esc('Active') ?>
                            <?php else : ?>
                                <form
                                    method="post"
                                    action="<?= esc(site_url('admin/themes/' . $theme['id'] . '/activate')) ?>"
                                >
                                    <?= csrf_field() ?>
                                    <button type="submit"><?= esc('Activate') ?></button>
                                </form>
                            <?php endif; ?>
                            <?php if ($previewPages !== []) : ?>
                                <?php foreach ($previewPages as $previewPage) : ?>
                                    <a
                                        href="<?= esc(site_url(
                                            'admin/preview/theme/' . $theme['id'] . '/page/' . $previewPage['id'],
                                        )) ?>"
                                    ><?= esc('Preview: ' . $previewPage['title']) ?></a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?= $this->endSection() ?>
