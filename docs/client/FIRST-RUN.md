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

Sign in at `/cp`. After authentication, the Control Panel dashboard is at `/admin`. See [ADMIN-CONTROL-PANEL.md](ADMIN-CONTROL-PANEL.md) for an overview of all administration areas (Pages, Posts, Media, Menus, Settings, Themes, Audit, and others).

## First login

1. Sign in at `/cp` with the **administrator-provided** credentials used at `cms:install`.
2. When force password reset is active, change the password immediately through the application flow.
3. Do not leave the install-time password in use.

There is **no** hard-coded default password in the repository.

## Recommended first-run flow

```text
Login at /cp
  ↓
Change password (when prompted)
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
- [ ] Admin password changed after first login
- [ ] Publish a Page and open its public URL
- [ ] Publish a Post under `/news/...`
- [ ] Upload an image and a document
- [ ] `/sitemap.xml` and `/robots.txt` respond
