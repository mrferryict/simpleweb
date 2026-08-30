<?php

declare(strict_types=1);

/**
 * Post create/edit form (Phase 3–4 / Tasks 3.7–4.1 / TH-007 polish).
 *
 * Content fields from ACTIVE Theme → templates.custom-post (no template selector).
 * Lifecycle: Publish / Unpublish / Archive / Submit for Review / Review (DOC-04; ADR-020).
 *
 * @var string $mode create|edit
 * @var array<string, mixed> $item
 * @var list<string> $locales
 * @var list<\App\Entities\Category> $categories
 * @var list<\App\Entities\Tag> $tags
 * @var array<string, string> $errors
 * @var string $formAction
 * @var array<string, array<string, mixed>> $contentSchema
 * @var array<string, mixed> $contentPayload
 * @var string|null $success
 * @var string|null $flashError
 * @var bool $canPublish
 * @var bool $canUnpublish
 * @var bool $canArchive
 * @var bool $canSubmitForReview
 * @var bool $canReviewPublish
 * @var bool $canReturnForRevision
 * @var bool $canViewRevisions
 */
$activeNav = 'posts';
$selectedCategories = $item['category_ids'] ?? [];
$selectedTags       = $item['tag_ids'] ?? [];
if (! is_array($selectedCategories)) {
    $selectedCategories = [];
}
if (! is_array($selectedTags)) {
    $selectedTags = [];
}
$canPublish           = ! empty($canPublish);
$canUnpublish         = ! empty($canUnpublish);
$canArchive           = ! empty($canArchive);
$canSubmitForReview   = ! empty($canSubmitForReview);
$canReviewPublish     = ! empty($canReviewPublish);
$canReturnForRevision = ! empty($canReturnForRevision);
$canViewRevisions     = ! empty($canViewRevisions);
$success              = $success ?? null;
$flashError           = $flashError ?? null;
$lockVersion          = isset($item['lock_version']) ? (int) $item['lock_version'] : 1;
$hasLifecycleActions  = $canPublish || $canUnpublish || $canArchive || $canSubmitForReview
    || $canReviewPublish || $canReturnForRevision;
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc($mode === 'edit' ? 'Edit post' : 'New post') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc($mode === 'edit' ? 'Edit post' : 'New post') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc($mode === 'edit'
                    ? 'Update post content, taxonomy, SEO, and publication settings.'
                    : 'Create a new post for your website news and articles.') ?>
            </p>
        </div>
    </header>

    <div class="admin-form-toolbar">
        <div class="admin-form-toolbar__links">
            <a href="<?= esc(site_url('admin/posts')) ?>"><?= esc('← Back to Posts') ?></a>
            <?php if ($canViewRevisions && ! empty($item['id'])) : ?>
                <a href="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/revisions')) ?>"><?= esc('Revision history') ?></a>
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

    <?php if ($mode === 'edit' && ! empty($item['id']) && $hasLifecycleActions) : ?>
        <div class="admin-lifecycle-actions" aria-label="<?= esc('Post lifecycle actions') ?>">
            <p class="admin-lifecycle-actions__label"><?= esc('Publication') ?></p>
            <?php if ($canPublish) : ?>
                <form
                    class="admin-actions__form"
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/publish')) ?>"
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
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/unpublish')) ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Unpublish') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canArchive) : ?>
                <form
                    class="admin-actions__form"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/archive')) ?>"
                    method="post"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Archive') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canSubmitForReview) : ?>
                <form
                    class="admin-actions__form"
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/submit-review')) ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Submit for Review') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canReviewPublish) : ?>
                <form
                    class="admin-actions__form"
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/review-publish')) ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--primary admin-btn--small" type="submit"><?= esc('Publish') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canReturnForRevision) : ?>
                <form
                    class="admin-actions__form"
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/return-revision')) ?>"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button class="admin-btn admin-btn--secondary admin-btn--small" type="submit"><?= esc('Return for Revision') ?></button>
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

    <form method="post" action="<?= esc($formAction) ?>" id="post-edit-form" class="admin-form">
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
                    hx-post="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/autosave')) ?>"
                    hx-include="#post-edit-form"
                    hx-target="#autosave-status"
                    hx-swap="innerHTML"
                ><?= esc('Save draft') ?></button>
            </div>
        <?php endif; ?>

        <section class="admin-form-section" aria-labelledby="post-basics-title">
            <h2 id="post-basics-title" class="admin-form-section__title"><?= esc('Basics') ?></h2>
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
                    <label for="manual_author"><?= esc('Author (public)') ?> <span class="admin-required" aria-hidden="true">*</span></label>
                    <input
                        type="text"
                        id="manual_author"
                        name="manual_author"
                        required
                        maxlength="200"
                        value="<?= esc((string) ($item['manual_author'] ?? '')) ?>"
                    >
                    <?php if (isset($errors['manual_author'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['manual_author']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-form-section" aria-labelledby="post-taxonomy-title">
            <h2 id="post-taxonomy-title" class="admin-form-section__title"><?= esc('Categories & Tags') ?></h2>
            <div class="admin-form-section__grid admin-form-section__grid--two">
                <div class="admin-form-field">
                    <label for="category_ids"><?= esc('Categories') ?></label>
                    <select id="category_ids" name="category_ids[]" multiple size="5">
                        <?php foreach ($categories as $category) : ?>
                            <?php $selected = in_array($category->id, $selectedCategories, true); ?>
                            <option
                                value="<?= esc((string) $category->id, 'attr') ?>"
                                <?= $selected ? 'selected' : '' ?>
                            ><?= esc($category->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($categories === []) : ?>
                        <p class="admin-form-hint"><?= esc('No active categories yet.') ?></p>
                    <?php endif; ?>
                    <?php if (isset($errors['categories'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['categories']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="tag_ids"><?= esc('Tags (optional)') ?></label>
                    <select id="tag_ids" name="tag_ids[]" multiple size="5">
                        <?php foreach ($tags as $tag) : ?>
                            <?php $selected = in_array($tag->id, $selectedTags, true); ?>
                            <option
                                value="<?= esc((string) $tag->id, 'attr') ?>"
                                <?= $selected ? 'selected' : '' ?>
                            ><?= esc($tag->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['tags'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['tags']) ?></p>
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

        <?php if ($contentSchema !== []) : ?>
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
        <?php endif; ?>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit"><?= esc($mode === 'edit' ? 'Update post' : 'Create post') ?></button>
            <a class="admin-btn admin-btn--secondary" href="<?= esc(site_url('admin/posts')) ?>"><?= esc('Cancel') ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
