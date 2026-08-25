<?php
/**
 * Page create/edit form (Phase 2–4 / Tasks 2.5–4.3).
 *
 * Lifecycle: Publish / Unpublish / Archive via CSRF POST (DOC-04; ADR-020).
 *
 * @var string $mode create|edit
 * @var array{
 *     id?: int,
 *     title: string,
 *     slug: string,
 *     locale: string,
 *     template_key: string,
 *     parent_id: int|null,
 *     status?: string,
 *     content_payload?: array<string, mixed>
 * } $item
 * @var list<\App\Entities\Page> $parents
 * @var list<string> $locales
 * @var array<string, string> $errors
 * @var string $formAction
 * @var array<string, array<string, mixed>> $contentSchema
 * @var array<string, mixed> $contentPayload
 * @var string|null $success
 * @var string|null $flashError
 * @var bool $canPublish
 * @var bool $canUnpublish
 * @var bool $canArchive
 * @var bool $canViewRevisions
 */
$canPublish        = ! empty($canPublish);
$canUnpublish      = ! empty($canUnpublish);
$canArchive        = ! empty($canArchive);
$canViewRevisions  = ! empty($canViewRevisions);
$success           = $success ?? null;
$flashError        = $flashError ?? null;
$lockVersion       = isset($item['lock_version']) ? (int) $item['lock_version'] : 1;
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc($mode === 'edit' ? 'Edit page' : 'New page') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc($mode === 'edit' ? 'Edit page' : 'New page') ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/pages')) ?>"><?= esc('Back to Pages') ?></a>
        <?php if ($canViewRevisions && ! empty($item['id'])) : ?>
            · <a href="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/revisions')) ?>"><?= esc('Revision history') ?></a>
        <?php endif; ?>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>
    <?php if (! empty($flashError)) : ?>
        <p role="alert"><?= esc((string) $flashError) ?></p>
    <?php endif; ?>

    <?php if ($errors !== []) : ?>
        <ul role="alert">
            <?php foreach ($errors as $message) : ?>
                <li><?= esc((string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (! empty($item['status'])) : ?>
        <p><?= esc('Status: ' . $item['status']) ?></p>
    <?php endif; ?>

    <?php if ($mode === 'edit' && ! empty($item['id']) && ($canPublish || $canUnpublish || $canArchive)) : ?>
        <div>
            <?php if ($canPublish) : ?>
                <form
                    method="post"
                    action="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/publish')) ?>"
                    style="display:inline"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button type="submit"><?= esc('Publish') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canUnpublish) : ?>
                <form
                    method="post"
                    action="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/unpublish')) ?>"
                    style="display:inline"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button type="submit"><?= esc('Unpublish') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canArchive) : ?>
                <form
                    method="post"
                    action="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/archive')) ?>"
                    style="display:inline"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button type="submit"><?= esc('Archive') ?></button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($mode === 'edit' && ! empty($item['id'])) : ?>
        <?= view('admin/_partials/scheduled_actions', [
            'canSchedulePublish'   => $canSchedulePublish ?? false,
            'canScheduleUnpublish' => $canScheduleUnpublish ?? false,
            'scheduledActions'     => $scheduledActions ?? [],
            'siteTimezone'         => $siteTimezone ?? 'Asia/Jakarta',
            'scheduleCreateUrl'    => $scheduleCreateUrl ?? '',
            'scheduleCancelBase'   => $scheduleCancelBase ?? '',
        ]) ?>
    <?php endif; ?>

    <form method="post" action="<?= esc($formAction) ?>" id="page-edit-form">
        <?= csrf_field() ?>
        <?php if ($mode === 'edit') : ?>
            <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
        <?php endif; ?>

        <?php if ($mode === 'edit' && ! empty($item['id'])) : ?>
            <div id="autosave-status" aria-live="polite"></div>
            <p>
                <button
                    type="button"
                    hx-post="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/autosave'), 'attr') ?>"
                    hx-include="#page-edit-form"
                    hx-target="#autosave-status"
                    hx-swap="innerHTML"
                ><?= esc('Save draft') ?></button>
            </p>
        <?php endif; ?>

        <div>
            <label for="title"><?= esc('Title') ?></label>
            <input
                type="text"
                id="title"
                name="title"
                required
                maxlength="200"
                value="<?= esc((string) ($item['title'] ?? ''), 'attr') ?>"
            >
            <?php if (isset($errors['title'])) : ?>
                <p><?= esc($errors['title']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="slug"><?= esc('Slug') ?></label>
            <input
                type="text"
                id="slug"
                name="slug"
                required
                maxlength="200"
                value="<?= esc((string) ($item['slug'] ?? ''), 'attr') ?>"
            >
            <?php if (isset($errors['slug'])) : ?>
                <p><?= esc($errors['slug']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="locale"><?= esc('Locale') ?></label>
            <select id="locale" name="locale" required>
                <?php foreach ($locales as $locale) : ?>
                    <option
                        value="<?= esc($locale, 'attr') ?>"
                        <?= ($item['locale'] ?? '') === $locale ? 'selected' : '' ?>
                    ><?= esc($locale) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['locale'])) : ?>
                <p><?= esc($errors['locale']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="template_key"><?= esc('Template') ?></label>
            <select id="template_key" name="template_key" required>
                <option
                    value="custom-page"
                    <?= ($item['template_key'] ?? '') === 'custom-page' ? 'selected' : '' ?>
                ><?= esc('custom-page') ?></option>
            </select>
            <?php if (isset($errors['template_key'])) : ?>
                <p><?= esc($errors['template_key']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="parent_id"><?= esc('Parent page') ?></label>
            <select id="parent_id" name="parent_id">
                <option value=""><?= esc('No parent / Top level') ?></option>
                <?php foreach ($parents as $parent) : ?>
                    <?php
                    $selected = isset($item['parent_id'])
                        && $item['parent_id'] !== null
                        && (int) $item['parent_id'] === (int) $parent->id;
                    ?>
                    <option
                        value="<?= esc((string) $parent->id, 'attr') ?>"
                        <?= $selected ? 'selected' : '' ?>
                    ><?= esc('#' . $parent->id) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['parent_id'])) : ?>
                <p><?= esc($errors['parent_id']) ?></p>
            <?php endif; ?>
        </div>

        <fieldset>
            <legend><?= esc('SEO') ?></legend>
            <div>
                <label for="meta_title"><?= esc('Meta title') ?></label>
                <input type="text" id="meta_title" name="meta_title" maxlength="255"
                    value="<?= esc((string) ($item['meta_title'] ?? ''), 'attr') ?>">
            </div>
            <div>
                <label for="meta_description"><?= esc('Meta description') ?></label>
                <textarea id="meta_description" name="meta_description" maxlength="500" rows="2"><?= esc((string) ($item['meta_description'] ?? '')) ?></textarea>
            </div>
            <div>
                <label for="canonical_url"><?= esc('Canonical URL override') ?></label>
                <input type="text" id="canonical_url" name="canonical_url" maxlength="500"
                    value="<?= esc((string) ($item['canonical_url'] ?? ''), 'attr') ?>">
            </div>
            <div>
                <label for="og_image_id"><?= esc('OG image media ID') ?></label>
                <input type="number" id="og_image_id" name="og_image_id" min="1"
                    value="<?= esc((string) ($item['og_image_id'] ?? ''), 'attr') ?>">
            </div>
        </fieldset>

        <fieldset>
            <legend><?= esc('Content') ?></legend>
            <?= view('admin/pages/_partials/content_fields', [
                'contentSchema'  => $contentSchema,
                'contentPayload' => $contentPayload,
                'errors'         => $errors,
            ]) ?>
        </fieldset>

        <div>
            <button type="submit"><?= esc($mode === 'edit' ? 'Update' : 'Create') ?></button>
        </div>
    </form>
<?= $this->endSection() ?>
