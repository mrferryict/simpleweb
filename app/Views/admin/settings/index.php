<?php
/**
 * Site Settings form (Phase 2 / Task 2.1).
 *
 * @var array{
 *     site_name: string,
 *     site_description: string,
 *     default_locale: string,
 *     timezone: string,
 *     contact_email: string
 * } $settings
 * @var array<string, string> $errors
 * @var string|null $success
 */
?>
<?= $this->extend('admin/layouts/main') ?>

<?= $this->section('title') ?>
Site Settings
<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <h1><?= esc('Site Settings') ?></h1>

    <p>
        <a href="<?= esc(site_url('admin/menus')) ?>"><?= esc('Manage menus') ?></a>
        <?= esc('(PRIMARY / FOOTER)') ?>
    </p>

    <?php if (! empty($success)) : ?>
        <p role="status"><?= esc((string) $success) ?></p>
    <?php endif; ?>

    <?php if ($errors !== []) : ?>
        <ul role="alert">
            <?php foreach ($errors as $message) : ?>
                <li><?= esc((string) $message) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" action="<?= esc(site_url('admin/settings')) ?>">
        <?= csrf_field() ?>

        <div>
            <label for="site_name"><?= esc('Site name') ?></label>
            <input
                type="text"
                id="site_name"
                name="site_name"
                required
                maxlength="150"
                value="<?= esc($settings['site_name'] ?? '', 'attr') ?>"
            >
            <?php if (isset($errors['site_name'])) : ?>
                <p><?= esc($errors['site_name']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="site_description"><?= esc('Site description') ?></label>
            <textarea
                id="site_description"
                name="site_description"
                maxlength="500"
                rows="3"
            ><?= esc($settings['site_description'] ?? '') ?></textarea>
            <?php if (isset($errors['site_description'])) : ?>
                <p><?= esc($errors['site_description']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="default_locale"><?= esc('Default locale') ?></label>
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
                <p><?= esc($errors['default_locale']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="primary_locale"><?= esc('Primary locale') ?></label>
            <select id="primary_locale" name="primary_locale" required>
                <?php
                $primaryLocale = $settings['primary_locale'] ?? 'id';
                foreach (['id' => 'id', 'en' => 'en'] as $value => $label) :
                    ?>
                    <option value="<?= esc($value, 'attr') ?>" <?= $primaryLocale === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['primary_locale'])) : ?>
                <p><?= esc($errors['primary_locale']) ?></p>
            <?php endif; ?>
        </div>

        <div>
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
                <p><?= esc($errors['secondary_locale']) ?></p>
            <?php endif; ?>
        </div>

        <fieldset>
            <legend><?= esc('SEO defaults') ?></legend>
            <div>
                <label for="seo_meta_title_id"><?= esc('Default meta title (id)') ?></label>
                <input type="text" id="seo_meta_title_id" name="seo_meta_title_id" maxlength="255"
                    value="<?= esc($settings['seo_meta_title_id'] ?? '', 'attr') ?>">
            </div>
            <div>
                <label for="seo_meta_title_en"><?= esc('Default meta title (en)') ?></label>
                <input type="text" id="seo_meta_title_en" name="seo_meta_title_en" maxlength="255"
                    value="<?= esc($settings['seo_meta_title_en'] ?? '', 'attr') ?>">
            </div>
            <div>
                <label for="seo_meta_description_id"><?= esc('Default meta description (id)') ?></label>
                <textarea id="seo_meta_description_id" name="seo_meta_description_id" maxlength="500" rows="2"><?= esc($settings['seo_meta_description_id'] ?? '') ?></textarea>
            </div>
            <div>
                <label for="seo_meta_description_en"><?= esc('Default meta description (en)') ?></label>
                <textarea id="seo_meta_description_en" name="seo_meta_description_en" maxlength="500" rows="2"><?= esc($settings['seo_meta_description_en'] ?? '') ?></textarea>
            </div>
        </fieldset>

        <div>
            <label for="timezone"><?= esc('Timezone') ?></label>
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
                <p><?= esc($errors['timezone']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <label for="contact_email"><?= esc('Contact email') ?></label>
            <input
                type="email"
                id="contact_email"
                name="contact_email"
                required
                maxlength="254"
                value="<?= esc($settings['contact_email'] ?? '', 'attr') ?>"
            >
            <?php if (isset($errors['contact_email'])) : ?>
                <p><?= esc($errors['contact_email']) ?></p>
            <?php endif; ?>
        </div>

        <div>
            <button type="submit"><?= esc('Save settings') ?></button>
        </div>
    </form>
<?= $this->endSection() ?>
