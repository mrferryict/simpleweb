# SMITE CMS — Installation (new server)

Use this guide for a **new** installation. For an existing site, use [UPDATE.md](UPDATE.md) instead.

## 1. Prepare the server

- PHP **8.5+** with extensions: `intl`, `mbstring`, `gd`, `sodium`, and MariaDB/MySQL client extensions
- MariaDB
- Composer
- Git
- Cron capability
- SMTP capability
- HTTPS

SMITE CMS V1 does **not** require Docker, Redis, or a queue worker.

## 2. Create the database

Create an empty MariaDB database and a dedicated database user with rights on that database only.

## 3. Clone the release

```bash
cd /var/www
git clone --branch v1.0.0 --depth 1 <YOUR_GITHUB_REPO_URL> smite-cms
cd smite-cms
```

Prefer the `v1.0.0` tag (or a later documented release tag) over an arbitrary branch tip.

## 4. Install PHP dependencies (production)

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

Do **not** run `composer update` on production.

## 5. Create `.env`

```bash
cp .env.example .env
```

Configure at least:

- `CI_ENVIRONMENT = production`
- `app.baseURL` (HTTPS URL ending with `/`)
- `app.forceGlobalSecureRequests = true` (recommended in production)
- Database connection settings
- `skey` (long random recovery secret — never commit)
- `EMAIL_ENCRYPTION_KEY` (64 hexadecimal characters)
- `EMAIL_LOOKUP_HMAC_KEY` (64 hexadecimal characters, **different** from encryption key)
- SMTP settings (`email.*`)
- First Admin credentials (see next section)

Generate secrets with a secure random generator. Never reuse example values.

## 6. First Admin credentials

Credentials are supplied by the administrator. They are **not** stored in the repository.

### Option A — environment variables in `.env`

```dotenv
cms.install.admin_username = YOUR_ADMIN_USERNAME
cms.install.admin_email    = YOUR_ADMIN_EMAIL
cms.install.admin_password = YOUR_ADMIN_PASSWORD
```

Then:

```bash
php spark cms:install
```

### Option B — CLI flags

```bash
php spark cms:install --username YOUR_ADMIN_USERNAME --email YOUR_ADMIN_EMAIL --password YOUR_ADMIN_PASSWORD
```

Use **space-separated** flags (`--username value`), not `--username=value`.

**Example placeholders only (do not use literally in production):**

```text
username: admin
email:    admin@example.com
password: CHANGE_THIS_PASSWORD
```

The installer creates exactly **one** Admin, assigns the `admin` group, stores email with PII encryption, and enables **force password reset** on first login.

## 7. Idempotency

Running `php spark cms:install` again on an already-installed system prints an informational message and makes **no** destructive changes (no second Admin, no credential reset).

## 8. Web server

Point the virtual host **document root** to:

```text
/path/to/smite-cms/public
```

Enable HTTPS. Do not expose the repository root as the document root.

Ensure these paths are writable by the application user:

- `writable/cache`
- `writable/uploads` (and subfolders as needed)
- `writable/session`
- `writable/logs`

## 9. Cron

```bash
* * * * * php /path/to/smite-cms/spark cms:scheduled-content
```

## 10. Verify

1. Open `https://YOUR-DOMAIN/` — default landing page (“Website is ready.”)
2. Open `https://YOUR-DOMAIN/cp` — login
3. Sign in with the credentials you provided
4. Change the Admin password when prompted
5. Continue with [FIRST-RUN.md](FIRST-RUN.md)

## Commands reference

| Command | Purpose |
|---|---|
| `php spark cms:install` | Install / upgrade schema bootstrap (idempotent) |
| `php spark cms:scheduled-content` | Process due publish/unpublish actions |
| `php spark migrate:status` | Show migration status |
| `php spark list` | List Spark commands |
