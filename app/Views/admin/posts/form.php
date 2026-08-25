<?php
/**
 * Post create/edit form (Phase 3–4 / Tasks 3.7–4.1).
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
    <h1><?= esc($mode === 'edit' ? 'Edit post' : 'New post') ?></h1>
    <p>
        <a href="<?= esc(site_url('admin/posts')) ?>"><?= esc('Back to Posts') ?></a>
        <?php if ($canViewRevisions && ! empty($item['id'])) : ?>
            · <a href="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/revisions')) ?>"><?= esc('Revision history') ?></a>
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

    <?php if ($mode === 'edit' && ! empty($item['id']) && $hasLifecycleActions) : ?>
        <div>
            <?php if ($canPublish) : ?>
                <form
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/publish')) ?>"
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
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/unpublish')) ?>"
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
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/archive')) ?>"
                    style="display:inline"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button type="submit"><?= esc('Archive') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canSubmitForReview) : ?>
                <form
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/submit-review')) ?>"
                    style="display:inline"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button type="submit"><?= esc('Submit for Review') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canReviewPublish) : ?>
                <form
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/review-publish')) ?>"
                    style="display:inline"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button type="submit"><?= esc('Publish') ?></button>
                </form>
            <?php endif; ?>
            <?php if ($canReturnForRevision) : ?>
                <form
                    method="post"
                    action="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/return-revision')) ?>"
                    style="display:inline"
                >
                    <?= csrf_field() ?>
                    <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
                    <button type="submit"><?= esc('Return for Revision') ?></button>
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

    <form method="post" action="<?= esc($formAction) ?>" id="post-edit-form">
        <?= csrf_field() ?>
        <?php if ($mode === 'edit') : ?>
            <input type="hidden" name="lock_version" value="<?= esc((string) $lockVersion, 'attr') ?>">
        <?php endif; ?>

        <?php if ($mode === 'edit' && ! empty($item['id'])) : ?>
            <div id="autosave-status" aria-live="polite"></div>
            <p>
                <button
                    type="button"
                    hx-post="<?= esc(site_url('admin/posts/' . (int) $item['id'] . '/autosave'), 'attr') ?>"
                    hx-include="#post-edit-form"
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
            <label for="manual_author"><?= esc('Author (public)') ?></label>
            <input
                type="text"
                id="manual_author"
                name="manual_author"
                required
                maxlength="200"
                value="<?= esc((string) ($item['manual_author'] ?? ''), 'attr') ?>"
            >
            <?php if (isset($errors['manual_author'])) : ?>
                <p><?= esc($errors['manual_author']) ?></p>
            <?php endif; ?>
        </div>

        <div>
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
                <p><?= esc('No active categories yet.') ?></p>
            <?php endif; ?>
            <?php if (isset($errors['categories'])) : ?>
                <p><?= esc($errors['categories']) ?></p>
            <?php endif; ?>
        </div>

        <div>
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
                <p><?= esc($errors['tags']) ?></p>
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

        <?php if ($contentSchema !== []) : ?>
            <?= view('admin/pages/_partials/content_fields', [
                'contentSchema'  => $contentSchema,
                'contentPayload' => $contentPayload,
                'errors'         => $errors,
            ]) ?>
        <?php endif; ?>

        <div>
            <button type="submit"><?= esc($mode === 'edit' ? 'Update post' : 'Create post') ?></button>
        </div>
    </form>
<?= $this->endSection() ?>
