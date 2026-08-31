# SMITE CMS — First run

After a successful [installation](INSTALLATION.md):

## What a fresh install contains

| Item | State after `cms:install` |
|---|---|
| Admin users | Exactly **one** Admin (`admin` group) |
| Pages | **None** |
| Posts | **None** |
| Demo content | **None** (optional: `php spark cms:demo`) |
| Hard-coded password | **None** — credentials come from installer/env only |

## Public site

```text
https://YOUR-DOMAIN/
```

`GET /` renders the SMITE 2026 starter landing page when that theme is active. It does not require you to create a Page first. Publish Pages and Posts from the Control Panel to build out the site.

## Optional starter content

After installation you may load generic starter Pages and a sample Post:

```bash
php spark cms:demo
```

This command is **optional**, idempotent, and separate from `cms:install`. It creates published content at `/about`, `/contact`, `/berita` (News landing Page), and `/news/welcome` (sample Post). The slug `news` is reserved for the Post URL prefix in V1 (`/news/{post-slug}`), so the News landing Page uses `/berita` while the navigation label remains **News**.

## Control Panel

```text
https://YOUR-DOMAIN/cp
```

Sign in at `/cp`. After authentication and any required password change, the Control Panel dashboard is at `/admin`. See [ADMIN-CONTROL-PANEL.md](ADMIN-CONTROL-PANEL.md) for an overview of all administration areas (Pages, Posts, Media, Menus, Settings, Themes, Audit, and others).

## First login and mandatory password change

After `cms:install`, exactly **one** Admin account exists. Its credentials are the values you configured in `.env` (or CLI flags) at install time — there is no hard-coded default password in the repository.

The installer sets **`force_reset = 1`** on that Admin. The first successful login therefore **requires** an immediate password change before normal Control Panel access is allowed.

### Exact flow

```text
/cp
  ↓
sign in with the installer-created Admin (configured install password)
  ↓
/cp/password-change
  ↓
submit current password + new password + confirmation
  ↓
/admin
```

What happens at each step:

1. **`/cp`** — Control Panel login form.
2. **Sign in** — use the administrator-provided install username and password.
3. **Redirect to `/cp/password-change`** — the application redirects here automatically when `force_reset` is active. You are **not** taken to `/admin` yet.
4. **Change password** — enter the current (install) password, a new password, and confirmation. CSRF protection is active on this form.
5. **`/admin`** — after a successful password change, `force_reset` is cleared and the dashboard loads normally.

While `force_reset` is still active:

- **`/admin`** and other Control Panel routes redirect back to **`/cp/password-change`**.
- Direct navigation to `/admin/pages` or similar cannot bypass the password-change requirement.

After the password is changed:

- The **old install password is rejected** at `/cp`.
- **Subsequent logins** with the new password proceed normally to `/admin`.
- Do not leave the install-time password in use.

## Primary locale

A fresh installation bootstraps site settings with:

```text
Default primary locale: id
```

Secondary locale defaults to **`en`** (configurable later in Settings).

Implications for content authors:

- New **Pages** and **Posts** should normally use locale **`id`** unless you have configured a different primary locale in Settings.
- Optional starter content from `php spark cms:demo` is created in locale **`id`**.
- A Page or Post created under a locale that does not match the site's primary locale may not appear at the public URL you expect until locale settings and content are aligned.

V1 supports the locale identifiers documented in site settings (typically **`id`** and **`en`**). Adjust primary/secondary locale in **Settings** after install if your site requires a different default.

## First login (summary)

1. Sign in at `/cp` with the **administrator-provided** credentials used at `cms:install`.
2. Complete the mandatory password change at **`/cp/password-change`** when `force_reset` is active.
3. Do not leave the install-time password in use.

There is **no** hard-coded default password in the repository.

## Recommended first-run flow

```text
Login at /cp
  ↓
Password change at /cp/password-change (first login only)
  ↓
Configure site settings
  ↓
Configure theme
  ↓
Configure localization
  ↓
Configure SMTP (if required)
  ↓
Create and manage content
```

Configure through the Control Panel — do not edit application source for CMS settings.

### Suggested configuration order

1. Site settings (name, description, timezone)
2. Primary locale / secondary locale (if required)
3. Theme selection / preview
4. Pages and Posts
5. Menus
6. Media and documents
7. SEO fields, sitemap, robots
8. Confirm SMTP delivery for password recovery
9. Confirm cron runs `cms:scheduled-content`
10. Confirm backup pair is scheduled ([BACKUP-RESTORE.md](BACKUP-RESTORE.md))

## Smoke checks

- [ ] `/` shows the landing page or your published homepage content
- [ ] `/cp` accepts login
- [ ] First login completes password change at `/cp/password-change`
- [ ] Admin password changed after first login (install password no longer works)
- [ ] Publish a Page and open its public URL
- [ ] Publish a Post under `/news/...`
- [ ] Upload an image and a document
- [ ] `/sitemap.xml` and `/robots.txt` respond

## Day-to-day content management

After first login, password change, and initial site configuration, staff who manage Pages, Posts, Media, and Menus should use **[ADMIN-USER-GUIDE.md](ADMIN-USER-GUIDE.md)** for practical step-by-step instructions.

That guide is for **operators** (Admin, Editor, Contributor). Server installation, CMS updates, and backup remain developer/server tasks — see [INSTALLATION.md](INSTALLATION.md), [UPDATE.md](UPDATE.md), and [BACKUP-RESTORE.md](BACKUP-RESTORE.md).
