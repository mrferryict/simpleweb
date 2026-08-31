# SMITE CMS — Developer Client Deployment Runbook

**Audience:** SMITE CMS maintainer / developer onboarding a new organization  
**Not for end clients** — clients receive the documents linked in [Client documentation](#client-documentation-references) below.

This runbook orchestrates a production deployment. It does not replace the canonical installation steps; it references them.

---

## 1. Purpose

Provide a repeatable SOP for deploying SMITE CMS to a new client's server, configuring security, completing first-login hardening, optionally loading demo content, and handing over a verified installation.

**Current distribution:** clone tag **`v2.0.0`** (latest repository distribution; V2 CORE). Historical V1 distribution: **`v1.1.6`** (application behavior baseline **`v1.1.2`**).

---

## 2. Before client deployment

- [ ] Confirm contract scope (custom Theme vs Theme 2026 baseline)
- [ ] Confirm whether demo/starter content is wanted
- [ ] Confirm SMTP requirement (password recovery)
- [ ] Confirm domain and HTTPS plan
- [ ] Confirm backup/restore expectations
- [ ] Read release notes for the target tag

---

## 3. Client information required

### Client provides

| Item | Notes |
|---|---|
| Domain | Production hostname |
| Database host, name, user, password | MariaDB/MySQL |
| SMTP account | If email delivery is required |
| Organization / site name | For Settings |
| Contact information | Public contact details as needed |
| Preferred administrator username | Install-time Admin login |
| Administrator email | Install-time Admin email |

### Developer generates / configures

| Item | Notes |
|---|---|
| `encryption.key` | Unique per installation |
| `skey` | Unique per installation |
| `EMAIL_ENCRYPTION_KEY` | Unique per installation |
| `EMAIL_LOOKUP_HMAC_KEY` | Unique per installation |
| Auth throttle configuration | Required — see [CONFIGURATION.md](client/CONFIGURATION.md) |
| Application deployment | Git checkout, Composer, permissions |
| Filesystem permissions | `writable/`, upload directories |
| Cron / scheduler | `cms:scheduled-content` |
| Backup procedure | DB + both upload locations |
| Initial install password | Provided to client once; must be changed at first login |

**Never** store actual client secrets in this document, Git, or shared notes.

---

## 4. Server requirements

Match [README.md](../README.md#requirements):

- PHP 8.5+ with required extensions (`intl`, `mbstring`, `gd`, `sodium`, database drivers)
- MariaDB (MySQL-compatible)
- Composer, Git
- HTTPS in production
- Cron capability
- Document root on `/public`

---

## 5. Domain / HTTPS

- [ ] DNS points to the correct server
- [ ] TLS certificate installed and valid
- [ ] `app.baseURL` in `.env` uses `https://` with trailing slash
- [ ] `app.forceGlobalSecureRequests = true` in production

---

## 6. Database preparation

- [ ] Create empty database (UTF-8 / `utf8mb4`)
- [ ] Create dedicated database user with least privilege on that database only
- [ ] Record credentials in the client's secure password manager — not in Git

---

## 7. Repository deployment

Clone the recommended release tag:

```bash
git clone --branch v2.0.0 <repository-url> smite-cms
cd smite-cms
```

Or follow [INSTALLATION.md](client/INSTALLATION.md) §5 for `git fetch --tags` / `git checkout v2.0.0`.

Install production dependencies:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Ensure writable directories exist and are writable by the web server user (`writable/cache`, `writable/session`, `writable/logs`, `writable/uploads/documents`, `public/uploads/images`).

---

## 8. Environment configuration

```bash
cp .env.example .env
```

Edit `.env` on the server only. Full reference: [CONFIGURATION.md](client/CONFIGURATION.md).

Minimum sections: Environment, Application, Database, Security keys, CMS Admin credentials, Auth throttle. Add SMTP if required.

---

## 9. Security key generation

Generate **unique** values per client installation for:

- `encryption.key`
- `skey`
- `EMAIL_ENCRYPTION_KEY`
- `EMAIL_LOOKUP_HMAC_KEY`

Use the project's documented generation approach (see `.env.example` comments and [CONFIGURATION.md](client/CONFIGURATION.md)). Never reuse keys across clients.

---

## 10. Auth throttle configuration

Auth throttling is **required** operational configuration. Unconfigured throttle values cause login to fail closed at `/cp`.

Configure all `auth.throttle.*` values in `.env` per [CONFIGURATION.md](client/CONFIGURATION.md).

---

## 11. CMS installation

Run the canonical installer (see [INSTALLATION.md](client/INSTALLATION.md)):

```bash
php spark cms:install
```

Verify:

- [ ] Migrations applied
- [ ] Exactly one Admin user created
- [ ] Site settings bootstrapped (primary locale **`id`**, timezone, etc.)
- [ ] Theme 2026 active

**Do not** run `cms:install` against a database that already has CMS data unless this is intentional.

---

## 12. First Admin login

Direct the client (or perform yourself during handover):

1. Open `https://YOUR-DOMAIN/cp`
2. Sign in with the install-time Admin credentials

Details: [FIRST-RUN.md](client/FIRST-RUN.md#first-login-and-mandatory-password-change).

---

## 13. First-login password change

After first login the application redirects to:

```text
/cp/password-change
```

- Install sets `force_reset = 1`
- `/admin` is blocked until password change succeeds
- Successful change clears `force_reset`
- Subsequent logins go directly to `/admin`

Complete this step **before** client handover. Never leave the install password active.

---

## 14. Optional demo content

Ask the client explicitly:

> Do you want generic starter Pages and a sample Post?

If **yes**:

```bash
php spark cms:demo
```

Expected public URLs (locale **`id`**):

| URL | Content |
|---|---|
| `/about` | About page |
| `/contact` | Contact page |
| `/berita` | News landing Page (navigation label "News") |
| `/news/welcome` | Sample Post |

Notes:

- `/news` alone is a **reserved namespace** — no archive page at `/news`
- Post URLs use `/news/{post-slug}`

If **no**, do **not** run `cms:demo`.

---

## 15. Initial site configuration

In `/admin/settings`, verify:

- [ ] Site identity (name, description)
- [ ] Primary locale (**`id`** by default)
- [ ] Timezone
- [ ] Contact email
- [ ] SEO defaults

In `/admin/menus`:

- [ ] Primary menu
- [ ] Footer menu

In `/admin/themes`:

- [ ] Theme 2026 confirmed (or client-approved custom Theme)

Do not invent settings beyond what the Control Panel exposes.

---

## 16. Theme / branding

**Normal client deployment:**

```text
Use Theme 2026
    → configure site settings
    → replace client branding and content
```

Not every client needs a custom Theme.

**Custom visual design required:**

```text
Theme 2026
    → developer copies and implements new theme (see 05-Theme-Development-Guide.md)
    → preview → activate
```

Clients should not edit application source for routine branding.

---

## 17. Content setup

- [ ] Create or confirm Pages (slugs, locales, publication state)
- [ ] Create or confirm Posts (include **Author** field — entered manually per article)
- [ ] Assign categories/tags if used
- [ ] Confirm public URLs resolve

---

## 18. Media setup

- [ ] Upload images (served from `public/uploads/images/`)
- [ ] Upload documents (stored in `writable/uploads/documents/`, tokenized download)
- [ ] Confirm image URLs render on public Pages/Posts

---

## 19. Menu setup

- [ ] Primary navigation items point to correct Pages
- [ ] Footer navigation configured
- [ ] Locale-appropriate labels

---

## 20. SMTP

If password recovery or notifications are required:

- [ ] Configure SMTP in `.env`
- [ ] Send a test message (for example password reset flow)
- [ ] Confirm delivery — configuration alone does not prove live email works

---

## 21. Scheduler / cron

Add crontab entry (adjust path):

```bash
* * * * * php /path/to/project/spark cms:scheduled-content
```

Confirm the cron user can execute `php spark` and write to `writable/`.

---

## 22. Backup

Back up **all three** together before handover and on schedule:

| Component | Path |
|---|---|
| Database | MariaDB dump |
| Images | `public/uploads/images/` |
| Documents | `writable/uploads/documents/` |

Canonical procedure: [BACKUP-RESTORE.md](client/BACKUP-RESTORE.md).

**Do not** use `writable/uploads/images/` — images are not stored there in V1.

---

## 23. Production smoke test

Minimum verification (developer performs):

| Check | URL / action |
|---|---|
| Public homepage | `GET /` |
| Login | `GET /cp` → sign in |
| Password change | `/cp/password-change` (first login only) |
| Dashboard | `GET /admin` |
| Pages | `GET /admin/pages` |
| Posts | `GET /admin/posts` |
| Media | `GET /admin/media` |
| Menus | `GET /admin/menus` |
| Settings | `GET /admin/settings` |
| Logout | Sign out from Control Panel |

If demo content installed, also verify:

- `GET /about`
- `GET /contact`
- `GET /berita`
- `GET /news/welcome`

**Not automatically verified** by passing the above: live HTTPS quality, SMTP delivery, cron execution — confirm each explicitly if in scope.

---

## 24. Client handover

Transfer securely (password manager or encrypted channel — never email/plain chat):

- [ ] Domain confirmed
- [ ] HTTPS confirmed
- [ ] Admin credentials transferred securely
- [ ] Initial password changed (install password invalidated)
- [ ] Site identity configured
- [ ] Theme confirmed
- [ ] Menu configured
- [ ] Pages configured
- [ ] Posts configured
- [ ] Media uploaded
- [ ] SMTP tested (if required)
- [ ] Scheduler/cron confirmed
- [ ] Backup procedure explained
- [ ] Restore procedure documented
- [ ] Current release recorded (`v2.0.0` distribution; V1 historical: `v1.1.6` / application baseline `v1.1.2`)

Provide links to [FIRST-RUN.md](client/FIRST-RUN.md), [ADMIN-CONTROL-PANEL.md](client/ADMIN-CONTROL-PANEL.md), and **[ADMIN-USER-GUIDE.md](client/ADMIN-USER-GUIDE.md)** (primary handover document for operators managing day-to-day content).

---

## 25. Record deployment version

Record in your internal client registry:

```text
Distribution tag: v2.0.0
Application release: v2.0.0
V1 historical baseline: v1.1.2 (distribution v1.1.6)
Deploy date:
Git commit:
Database migration batch (php spark migrate:status):
```

---

## 26. Future updates

Developer responsibility for client updates:

1. Review target release and read release notes
2. Backup client database
3. Backup `public/uploads/images/` and `writable/uploads/documents/`
4. Verify client working tree is clean
5. Preserve `.env` (never overwrite from Git)
6. `git fetch --tags` and checkout target release
7. `composer install --no-dev`
8. `php spark migrate:status` — run migrations if required
9. Smoke test (§23)
10. Record deployed version

Full client procedure: [UPDATE.md](client/UPDATE.md)  
Restore procedure: [BACKUP-RESTORE.md](client/BACKUP-RESTORE.md)

No automated deployment tooling is assumed.

---

## Security rules

- Never commit `.env`
- Never put production secrets into documentation or Git
- Generate unique security keys per installation
- Use unique Admin credentials per client
- Ensure first-login password is changed (`/cp/password-change`)
- Configure auth throttling before go-live
- Use HTTPS in production
- Protect private document storage (`writable/uploads/documents/`)

---

## Client documentation references

| Document | Use |
|---|---|
| [INSTALLATION.md](client/INSTALLATION.md) | Canonical install steps |
| [FIRST-RUN.md](client/FIRST-RUN.md) | First login, password change, locale |
| [CONFIGURATION.md](client/CONFIGURATION.md) | `.env` reference |
| [UPDATE.md](client/UPDATE.md) | Production updates |
| [BACKUP-RESTORE.md](client/BACKUP-RESTORE.md) | Backup pairing |
| [PRODUCTION-CHECKLIST.md](client/PRODUCTION-CHECKLIST.md) | Go-live checklist |
| [ADMIN-CONTROL-PANEL.md](client/ADMIN-CONTROL-PANEL.md) | Control Panel area/route reference |
| [ADMIN-USER-GUIDE.md](client/ADMIN-USER-GUIDE.md) | **Operator handover** — daily content management |
| [05-Theme-Template-Architecture.md](05-Theme-Template-Architecture.md) | Theme architecture |
| [05-Theme-Development-Guide.md](05-Theme-Development-Guide.md) | New Theme quick-start |
