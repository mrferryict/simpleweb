<?php

declare(strict_types=1);

/**
 * Menu item create/edit form (Phase 2 / Task 2.4 / TH-009 polish).
 *
 * Server-rendered: all destination fields are shown; MenuService enforces
 * type consistency (no JavaScript type switching).
 *
 * @var string $mode create|edit
 * @var array{
 *     id?: int,
 *     location: string,
 *     parent_id: int|null,
 *     label: string,
 *     target_type: string,
 *     target_id: int|null,
 *     external_url: string,
 *     display_order: int,
 *     is_active: bool
 * } $item
 * @var list<string> $locations
 * @var list<\App\Enums\MenuTargetType> $targetTypes
 * @var list<\App\Entities\MenuItem> $parents
 * @var array<string, string> $errors
 * @var string $formAction
 */
$activeNav = 'menus';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
<?= esc($mode === 'edit' ? 'Edit menu item' : 'New menu item') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc($mode === 'edit' ? 'Edit menu item' : 'New menu item') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Configure label, destination, display order, and visibility for a navigation item.') ?>
            </p>
        </div>
    </header>

    <div class="admin-form-toolbar">
        <div class="admin-form-toolbar__links">
            <a href="<?= esc(site_url('admin/menus')) ?>"><?= esc('Back to Menus') ?></a>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => null,
        'error'   => null,
        'errors'  => $errors,
    ]) ?>

    <form class="admin-form" method="post" action="<?= esc($formAction) ?>">
        <?= csrf_field() ?>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('Placement') ?></h2>
            <div class="admin-form-section__grid admin-form-section__grid--two">
                <div class="admin-form-field">
                    <label for="location">
                        <?= esc('Location') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <select id="location" name="location" required>
                        <?php foreach ($locations as $location) : ?>
                            <option
                                value="<?= esc($location, 'attr') ?>"
                                <?= ($item['location'] ?? '') === $location ? 'selected' : '' ?>
                            ><?= esc($location) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['location'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['location']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="parent_id"><?= esc('Parent') ?></label>
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
                            ><?= esc($parent->location . ' — ' . $parent->label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['parent_id'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['parent_id']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('Label') ?></h2>
            <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="label">
                        <?= esc('Menu label') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="label"
                        name="label"
                        required
                        maxlength="150"
                        value="<?= esc((string) ($item['label'] ?? ''), 'attr') ?>"
                    >
                    <?php if (isset($errors['label'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['label']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-form-section">
            <fieldset class="admin-form-fieldset">
                <legend><?= esc('Destination') ?></legend>
                <p class="admin-form-hint">
                    <?= esc('Choose one destination type. Fill only the matching field; leave the others empty.') ?>
                </p>
                <div class="admin-form-section__grid">
                    <div class="admin-form-field">
                        <label for="target_type">
                            <?= esc('Destination type') ?>
                            <span class="admin-required" aria-hidden="true">*</span>
                        </label>
                        <select id="target_type" name="target_type" required>
                            <?php foreach ($targetTypes as $targetType) : ?>
                                <option
                                    value="<?= esc($targetType->value, 'attr') ?>"
                                    <?= ($item['target_type'] ?? '') === $targetType->value ? 'selected' : '' ?>
                                ><?= esc($targetType->label()) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['target_type'])) : ?>
                            <p class="admin-field-error"><?= esc($errors['target_type']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-form-field">
                        <label for="target_id"><?= esc('Page / Post Category ID') ?></label>
                        <input
                            type="number"
                            id="target_id"
                            name="target_id"
                            min="1"
                            step="1"
                            value="<?= esc($item['target_id'] !== null ? (string) $item['target_id'] : '', 'attr') ?>"
                        >
                        <p class="admin-form-hint"><?= esc('Numeric Page ID or Post Category ID. Page IDs must exist and not be in Trash.') ?></p>
                        <?php if (isset($errors['target_id'])) : ?>
                            <p class="admin-field-error"><?= esc($errors['target_id']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="admin-form-field">
                        <label for="external_url"><?= esc('External URL') ?></label>
                        <input
                            type="url"
                            id="external_url"
                            name="external_url"
                            maxlength="500"
                            placeholder="https://example.com/path"
                            value="<?= esc((string) ($item['external_url'] ?? ''), 'attr') ?>"
                        >
                        <p class="admin-form-hint"><?= esc('HTTP or HTTPS only.') ?></p>
                        <?php if (isset($errors['destination'])) : ?>
                            <p class="admin-field-error"><?= esc($errors['destination']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </fieldset>
        </section>

        <section class="admin-form-section">
            <h2 class="admin-form-section__title"><?= esc('Display') ?></h2>
            <div class="admin-form-section__grid admin-form-section__grid--two">
                <div class="admin-form-field">
                    <label for="display_order">
                        <?= esc('Display order') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="number"
                        id="display_order"
                        name="display_order"
                        required
                        min="0"
                        step="1"
                        value="<?= esc((string) ($item['display_order'] ?? 0), 'attr') ?>"
                    >
                    <?php if (isset($errors['display_order'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['display_order']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label>
                        <input
                            type="checkbox"
                            name="is_active"
                            value="1"
                            <?= ! empty($item['is_active']) ? 'checked' : '' ?>
                        >
                        <?= esc('Active') ?>
                    </label>
                    <p class="admin-form-hint"><?= esc('Inactive items are hidden from the public theme navigation.') ?></p>
                </div>
            </div>
        </section>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit">
                <?= esc($mode === 'edit' ? 'Update menu item' : 'Create menu item') ?>
            </button>
            <a class="admin-btn admin-btn--secondary" href="<?= esc(site_url('admin/menus')) ?>"><?= esc('Cancel') ?></a>
        </div>
    </form>
<?= $this->endSection() ?>
