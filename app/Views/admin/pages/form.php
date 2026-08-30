<?php

declare(strict_types=1);

/**
 * Page create/edit form (Phase 2–4 / Tasks 2.5–4.3 / TH-007 polish).
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
$activeNav         = 'pages';
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
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc($mode === 'edit' ? 'Edit page' : 'New page') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc($mode === 'edit'
                    ? 'Update page content, SEO, and publication settings.'
                    : 'Create a new page for your website.') ?>
            </p>
        </div>
    </header>

    <div class="admin-form-toolbar">
        <div class="admin-form-toolbar__links">
            <a href="<?= esc(site_url('admin/pages')) ?>"><?= esc('← Back to Pages') ?></a>
            <?php if ($canViewRevisions && ! empty($item['id'])) : ?>
                <a href="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/revisions')) ?>"><?= esc('Revision history') ?></a>
            <?php endif; ?>
        </div>
        <?php if (! empty($item['status'])) : ?>
            <div class="admin-form-meta">
                <?= view('admin/_partials/status_badge', ['status' => (string) $item['status']]) ?>
            </div>
        <?php endif; ?>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success,
        'error'   => $flashError,
        'errors'  => $errors ?? [],
    ]) ?>

    <?php if ($mode === 'edit' && ! empty($item['id']) && ($canPublish || $canUnpublish || $canArchive)) : ?>
        <div class="admin-lifecycle-actions" aria-label="<?= esc('Page lifecycle actions') ?>">
            <p class="admin-lifecycle-actions__label"><?= esc('Publication') ?></p>
            <?php if ($canPublish) : ?>
                <form
                    class="admin-actions__form"
                    method="post"
                    action="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/publish')) ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--primary admin-btn--small" type="submit"><?= esc('Publish') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canUnpublish) : ?>
                <form
                    class="admin-actions__form"
                    method="post"
                    action="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/unpublish')) ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Unpublish') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canArchive) : ?>
                <form
                    class="admin-actions__form"
                    method="post"
                    action="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/archive')) ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Archive') ?></button>
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

    <form method="post" action="<?= esc($formAction) ?>" id="page-edit-form" class="admin-form">
        <?= csrf_field() ?>
        <?php if ($mode === 'edit') : ?>
            <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
        <?php endif; ?>

        <?php if ($mode === 'edit' && ! empty($item['id'])) : ?>
            <div class="admin-autosave">
                <p class="admin-autosave__label"><?= esc('Draft autosave') ?></p>
                <div id="autosave-status" aria-live="polite"></div>
                <button
                    class="admin-btn admin-btn--secondary admin-btn--small"
                    type="button"
                    hx-post="<?= esc(site_url('admin/pages/' . (int) $item['id'] . '/autosave')) ?>"
                    hx-include="#page-edit-form"
                    hx-target="#autosave-status"
                    hx-swap="innerHTML"
                ><?= esc('Save draft') ?></button>
            </div>
        <?php endif; ?>

        <section class="admin-form-section" aria-labelledby="page-basics-title">
            <h2 id="page-basics-title" class="admin-form-section__title"><?= esc('Basics') ?></h2>
            <div class="admin-form-section__grid admin-form-section__grid--two">
                <div class="admin-form-field">
                    <label for="title"><?= esc('Title') ?> <span class="admin-required" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        required
                        maxlength="200"
                        value="<?= esc((string) ($item['title'] ?? '')) ?>"
                    >
                    <?php if (isset($errors['title'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['title']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="slug"><?= esc('Slug') ?> <span class="admin-required" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="slug"
                        name="slug"
                        required
                        maxlength="200"
                        value="<?= esc((string) ($item['slug'] ?? '')) ?>"
                    >
                    <?php if (isset($errors['slug'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['slug']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="locale"><?= esc('Locale') ?> <span class="admin-required" aria-hidden="true">*</span></label>
                    <select id="locale" name="locale" required>
                        <?php foreach ($locales as $locale) : ?>
                            <option
                                value="<?= esc($locale, 'attr') ?>"
                                <?= ($item['locale'] ?? '') === $locale ? 'selected' : '' ?>
                            ><?= esc($locale) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['locale'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['locale']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="template_key"><?= esc('Template') ?> <span class="admin-required" aria-hidden="true">*</span></label>
                    <select id="template_key" name="template_key" required>
                        <option
                            value="custom-page"
                            <?= ($item['template_key'] ?? '') === 'custom-page' ? 'selected' : '' ?>
                        ><?= esc('custom-page') ?></option>
                    </select>
                    <?php if (isset($errors['template_key'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['template_key']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
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
                        <p class="admin-field-error"><?= esc($errors['parent_id']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-form-section">
            <fieldset class="admin-form-fieldset">
                <legend>SEO</legend>
                <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="meta_title"><?= esc('Meta title') ?></label>
                    <input type="text" id="meta_title" name="meta_title" maxlength="255"
                        value="<?= esc((string) ($item['meta_title'] ?? ''), 'attr') ?>">
                </div>
                <div class="admin-form-field">
                    <label for="meta_description"><?= esc('Meta description') ?></label>
                    <textarea id="meta_description" name="meta_description" maxlength="500" rows="2"><?= esc((string) ($item['meta_description'] ?? '')) ?></textarea>
                </div>
                <div class="admin-form-field">
                    <label for="canonical_url"><?= esc('Canonical URL override') ?></label>
                    <input type="text" id="canonical_url" name="canonical_url" maxlength="500"
                        value="<?= esc((string) ($item['canonical_url'] ?? ''), 'attr') ?>">
                </div>
                <div class="admin-form-field">
                    <label for="og_image_id"><?= esc('OG image media ID') ?></label>
                    <input type="number" id="og_image_id" name="og_image_id" min="1"
                        value="<?= esc((string) ($item['og_image_id'] ?? '')) ?>">
                </div>
                </div>
            </fieldset>
        </section>

        <section class="admin-form-section">
            <fieldset class="admin-form-fieldset">
                <legend>Content</legend>
                <?= view('admin/pages/_partials/content_fields', [
                    'contentSchema'  => $contentSchema,
                    'contentPayload' => $contentPayload,
                    'errors'         => $errors,
                ]) ?>
            </fieldset>
        </section>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit"><?= esc($mode === 'edit' ? 'Update page' : 'Create page') ?></button>
            <a class="admin-btn admin-btn--secondary" href="<?= esc(site_url('admin/pages')) ?>"><?= esc('Cancel') ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
