<?php

declare(strict_types=1);

/**
 * Site Settings form (Phase 2 / Task 2.1 / TH-009 polish).
 *
 * @var array{
 *     site_name: string,
 *     site_description: string,
 *     default_locale: string,
 *     primary_locale: string,
 *     secondary_locale: string,
 *     timezone: string,
 *     contact_email: string,
 *     seo_meta_title_id: string,
 *     seo_meta_title_en: string,
 *     seo_meta_description_id: string,
 *     seo_meta_description_en: string
 * } $settings
 * @var array<string, string> $errors
 * @var string|null $success
 */
$activeNav = 'settings';
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Site Settings
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <header class="admin-page-header">
        <div>
            <h1 class="admin-page-header__title"><?= esc('Site Settings') ?></h1>
            <p class="admin-page-header__lead">
                <?= esc('Configure site identity, localization, timezone, contact details, and default SEO values.') ?>
            </p>
        </div>
    </header>

    <div class="admin-toolbar" aria-label="<?= esc('Settings actions') ?>">
        <div class="admin-toolbar__group">
            <a class="admin-btn admin-btn--secondary admin-btn--small" href="<?= esc(site_url('admin/menus')) ?>">
                <?= esc('Manage menus') ?>
            </a>
            <span class="admin-toolbar__label"><?= esc('PRIMARY / FOOTER') ?></span>
        </div>
    </div>

    <?= view('admin/_partials/flash_messages', [
        'success' => $success ?? null,
        'error'   => null,
        'errors'  => $errors,
    ]) ?>

    <form class="admin-form admin-settings" method="post" action="<?= esc(site_url('admin/settings')) ?>">
        <?= csrf_field() ?>

        <section class="admin-form-section admin-settings-section">
            <h2 class="admin-form-section__title"><?= esc('Site identity') ?></h2>
            <div class="admin-form-section__grid">
                <div class="admin-form-field">
                    <label for="site_name">
                        <?= esc('Site name') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="site_name"
                        name="site_name"
                        required
                        maxlength="150"
                        value="<?= esc($settings['site_name'] ?? '', 'attr') ?>"
                    >
                    <?php if (isset($errors['site_name'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['site_name']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="site_description"><?= esc('Site description') ?></label>
                    <textarea
                        id="site_description"
                        name="site_description"
                        maxlength="500"
                        rows="3"
                    ><?= esc($settings['site_description'] ?? '') ?></textarea>
                    <?php if (isset($errors['site_description'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['site_description']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="contact_email">
                        <?= esc('Contact email') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="email"
                        id="contact_email"
                        name="contact_email"
                        required
                        maxlength="254"
                        value="<?= esc($settings['contact_email'] ?? '', 'attr') ?>"
                    >
                    <?php if (isset($errors['contact_email'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['contact_email']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-form-section admin-settings-section">
            <h2 class="admin-form-section__title"><?= esc('Localization') ?></h2>
            <div class="admin-form-section__grid admin-form-section__grid--two">
                <div class="admin-form-field">
                    <label for="default_locale">
                        <?= esc('Default locale') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <select id="default_locale" name="default_locale" required>
                        <?php
                        $locale = $settings['default_locale'] ?? 'id';
                        foreach (['id' => 'id', 'en' => 'en'] as $value => $label) :
                            ?>
                            <option
                                value="<?= esc($value, 'attr') ?>"
                                <?= $locale === $value ? 'selected' : '' ?>
                            ><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['default_locale'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['default_locale']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="primary_locale">
                        <?= esc('Primary locale') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <select id="primary_locale" name="primary_locale" required>
                        <?php
                        $primaryLocale = $settings['primary_locale'] ?? 'id';
                        foreach (['id' => 'id', 'en' => 'en'] as $value => $label) :
                            ?>
                            <option value="<?= esc($value, 'attr') ?>" <?= $primaryLocale === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['primary_locale'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['primary_locale']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="secondary_locale"><?= esc('Secondary locale (empty = disabled)') ?></label>
                    <select id="secondary_locale" name="secondary_locale">
                        <option value="" <?= ($settings['secondary_locale'] ?? '') === '' ? 'selected' : '' ?>><?= esc('Disabled') ?></option>
                        <?php
                        $secondaryLocale = $settings['secondary_locale'] ?? '';
                        foreach (['id' => 'id', 'en' => 'en'] as $value => $label) :
                            ?>
                            <option value="<?= esc($value, 'attr') ?>" <?= $secondaryLocale === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['secondary_locale'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['secondary_locale']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="admin-form-field">
                    <label for="timezone">
                        <?= esc('Timezone') ?>
                        <span class="admin-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        type="text"
                        id="timezone"
                        name="timezone"
                        required
                        maxlength="64"
                        value="<?= esc($settings['timezone'] ?? '', 'attr') ?>"
                        list="timezone-suggestions"
                    >
                    <datalist id="timezone-suggestions">
                        <option value="Asia/Jakarta"></option>
                        <option value="UTC"></option>
                        <option value="Asia/Makassar"></option>
                        <option value="Asia/Jayapura"></option>
                    </datalist>
                    <?php if (isset($errors['timezone'])) : ?>
                        <p class="admin-field-error"><?= esc($errors['timezone']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section class="admin-form-section admin-settings-section">
            <fieldset class="admin-form-fieldset">
                <legend><?= esc('SEO defaults') ?></legend>
                <p class="admin-form-hint">
                    <?= esc('Default meta values used when content does not provide its own SEO fields.') ?>
                </p>
                <div class="admin-form-section__grid admin-form-section__grid--two">
                    <div class="admin-form-field">
                        <label for="seo_meta_title_id"><?= esc('Default meta title (id)') ?></label>
                        <input type="text" id="seo_meta_title_id" name="seo_meta_title_id" maxlength="255"
                            value="<?= esc($settings['seo_meta_title_id'] ?? '', 'attr') ?>">
                    </div>
                    <div class="admin-form-field">
                        <label for="seo_meta_title_en"><?= esc('Default meta title (en)') ?></label>
                        <input type="text" id="seo_meta_title_en" name="seo_meta_title_en" maxlength="255"
                            value="<?= esc($settings['seo_meta_title_en'] ?? '', 'attr') ?>">
                    </div>
                    <div class="admin-form-field">
                        <label for="seo_meta_description_id"><?= esc('Default meta description (id)') ?></label>
                        <textarea id="seo_meta_description_id" name="seo_meta_description_id" maxlength="500" rows="2"><?= esc($settings['seo_meta_description_id'] ?? '') ?></textarea>
                    </div>
                    <div class="admin-form-field">
                        <label for="seo_meta_description_en"><?= esc('Default meta description (en)') ?></label>
                        <textarea id="seo_meta_description_en" name="seo_meta_description_en" maxlength="500" rows="2"><?= esc($settings['seo_meta_description_en'] ?? '') ?></textarea>
                    </div>
                </div>
            </fieldset>
        </section>

        <div class="admin-form-actions">
            <button class="admin-btn admin-btn--primary" type="submit"><?= esc('Save settings') ?></button>
        </div>
    </form>
<?= $this->endSection() ?>
