# SMITE CMS — Installation (new server)

Use this guide for a **new** installation. For an existing site, use [UPDATE.md](UPDATE.md) instead.

## Overview

```text
Prepare server
  → clone release
  → composer install --no-dev
  → create .env from .env.example
  → configure database and security keys
  → configure initial Admin credentials
  → configure auth throttle (operational)
  → php spark cms:install
  → configure web server + HTTPS + cron
  → verify / and /cp
  → first login and password change
```

## 1. Prepare the server

- PHP **8.5+**
- Required PHP extensions: `intl`, `mbstring`, `gd`, `sodium`, and MariaDB/MySQL client extensions
- **MariaDB**
- **Composer**
- **Git**
- Cron capability
- SMTP capability (recommended for password recovery)
- HTTPS (required for production)

SMITE CMS V1 does **not** require Docker, Redis, or a queue worker.

## 2. Prepare domain and DNS

Point your domain at the server before setting `app.baseURL` in `.env`.

## 3. Create the database

Create an empty MariaDB database and a dedicated database user with rights on that database only.

## 4. Clone the repository

```bash
cd /var/www
git clone <YOUR_GITHUB_REPO_URL> smite-cms
cd smite-cms
```

## 5. Check out the desired release

Prefer the latest documented release tag over an arbitrary branch tip:

```bash
git fetch --tags
git checkout v1.1.5
```

`v1.1.5` is the current repository distribution. Its application code is identical to **`v1.1.2`**. See [README.md](../../README.md#release-history-v1) for the release history.

## 6. Install PHP dependencies (production)

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Do **not** run `composer update` on production.

## 7. Create `.env`

```bash
cp .env.example .env
```

Edit `.env` on the server. See [CONFIGURATION.md](CONFIGURATION.md) for section-by-section guidance.

## 8. Configure the database

Set at minimum:

```dotenv
database.default.hostname = localhost
database.default.database = 'your_database_name'
database.default.username = 'your_database_user'
database.default.password = 'your_database_password'
```

## 9. Configure application URL

```dotenv
CI_ENVIRONMENT = production
app.baseURL = 'https://your-domain.example/'
app.forceGlobalSecureRequests = true
```

Replace `your-domain.example` with your actual public HTTPS URL (trailing slash required).

## 10. Generate security keys

Before `cms:install`, set these in `.env`:

| Variable | How to generate |
|---|---|
| `encryption.key` | `php spark key:generate` (or `php spark key:generate --show` to preview) |
| `skey` | `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` |
| `EMAIL_ENCRYPTION_KEY` | `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` |
| `EMAIL_LOOKUP_HMAC_KEY` | Run the same command again — **must differ** from `EMAIL_ENCRYPTION_KEY` |

Each PII key must be exactly **64 hexadecimal characters**. Never reuse one secret for both PII keys.

## 11. Configure initial Admin credentials

Credentials are supplied by the administrator. They are **not** stored in the repository. There is **no** hard-coded Admin password in the application.

### Option A — environment variables in `.env`

```dotenv
cms.install.admin_username = 'admin'
cms.install.admin_email = 'admin@example.com'
cms.install.admin_password = 'YOUR_SECURE_PASSWORD'
```

Choose a unique password. Replace `admin@example.com` with a real address you control.

### Option B — CLI flags

```bash
php spark cms:install \
    --username admin \
    --email admin@example.com \
    --password 'YOUR_SECURE_PASSWORD'
```

Use **space-separated** flags (`--username value`), not `--username=value`.

CLI flags override environment values when provided.

## 12. Configure auth throttle (operational — required)

Authentication surfaces (`/cp` login, password reset, Admin recovery) use `AuthThrottleService` with CI4 Throttler. ADR-026 does **not** prescribe numeric product values — capacity and window are **deployment configuration**.

If throttle values are missing or invalid, the application **fails closed** and `/cp` login returns “Too many attempts” even on the first try.

Copy the example operational values from `.env.example` section 7, then adjust them for your deployment security policy:

```dotenv
auth.throttle.login.capacity = 10
auth.throttle.login.seconds = 60
auth.throttle.password_reset_request.capacity = 5
auth.throttle.password_reset_request.seconds = 300
auth.throttle.password_reset_verify.capacity = 5
auth.throttle.password_reset_verify.seconds = 300
auth.throttle.admin_recovery.capacity = 3
auth.throttle.admin_recovery.seconds = 600
```

These numbers are **example deployment values**, not SMITE CMS product policy. See [CONFIGURATION.md](CONFIGURATION.md#auth-throttling).

Configure throttle **before** opening `/cp`. Do not commit `.env` to Git.

## 13. Configure SMTP (if needed)

SMTP is optional in `.env.example` but recommended for password recovery in production. See [CONFIGURATION.md](CONFIGURATION.md#smtp).

## 14. Run the installer

```bash
php spark cms:install
```

The installer:

- runs pending migrations
- bootstraps default Site settings
- creates exactly **one** Admin in the `admin` group
- stores email with PII encryption
- enables **force password reset** on first login
- does **not** create demo Pages, Posts, or other content

### Idempotency

Running `php spark cms:install` again on an already-installed system prints an informational message and makes **no** destructive changes (no second Admin, no credential reset).

## 14b. Optional starter content

After a successful install, you may populate generic demo Pages and a sample Post:

```bash
php spark cms:demo
```

This is **optional** and idempotent. It does not modify `cms:install` behavior, create a second Admin, or overwrite existing content that already uses the same slugs (`about`, `contact`, `berita`, `welcome`). The slug `news` is reserved for Post URLs in V1 (`/news/{post-slug}`), so the News landing Page is created at `/berita`. See [FIRST-RUN.md](FIRST-RUN.md).

## 15. Configure the web server

Point the virtual host **document root** to:

```text
/path/to/smite-cms/public
```

Enable HTTPS. Do not expose the repository root as the document root.

Ensure these paths are writable by the application user:

- `writable/cache`
- `writable/session`
- `writable/logs`
- `writable/uploads/documents/` — private document uploads
- `public/uploads/images/` — public processed image uploads (served under `/uploads/images/…`)

Both upload paths must be included in backups — see [BACKUP-RESTORE.md](BACKUP-RESTORE.md).

## 16. Enable HTTPS

Production should serve the site over HTTPS. `app.forceGlobalSecureRequests = true` is recommended.

## 17. Open the public site

```text
https://your-domain.example/
```

A fresh install shows the default **Theme 2026** landing page at `GET /`. No Page or Post must exist first.

## 18. Open the Control Panel

```text
https://your-domain.example/cp
```

After login, the dashboard is at `/admin`. See [ADMIN-CONTROL-PANEL.md](ADMIN-CONTROL-PANEL.md) for a concise map of administration areas.

## 19. Log in

Sign in with the administrator-provided credentials from step 11. Throttle must be configured (step 12) or login is denied.

## 20. Change the initial password

When force password reset is active, change the password immediately through the application flow. Do not leave the install-time password in use.

## 21. Configure the CMS

Continue with [FIRST-RUN.md](FIRST-RUN.md) for site settings, theme, localization, content, and operational checks.

## 22. Configure cron (scheduled content)

```bash
* * * * * php /path/to/smite-cms/spark cms:scheduled-content
```

Adjust the path to match your deployment.

## 23. Configure backup

Schedule paired backups of the MariaDB database, `public/uploads/images/`, and `writable/uploads/documents/` — see [BACKUP-RESTORE.md](BACKUP-RESTORE.md).

## 24. Production smoke test

Use [PRODUCTION-CHECKLIST.md](PRODUCTION-CHECKLIST.md) before go-live.

## Commands reference

| Command | Purpose |
|---|---|
| `php spark cms:install` | Install / upgrade schema bootstrap (idempotent) |
| `php spark cms:demo` | Optional starter Pages/Posts (idempotent; separate from install) |
| `php spark cms:scheduled-content` | Process due publish/unpublish actions |
| `php spark migrate:status` | Show migration status |
| `php spark migrate` | Run pending migrations (updates only — after backup) |
| `php spark list` | List Spark commands |
