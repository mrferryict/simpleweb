# SMITE CMS

SMITE CMS is a single-organization, single-website public Content Management System
built on CodeIgniter 4. It is not a website builder.

Official release: **v1.1.1**

## Requirements

- **PHP 8.5+**
- Required extensions include at least: `intl`, `mbstring`, `gd`, `sodium`, and MariaDB/MySQL drivers
- **MariaDB** (MySQL-compatible)
- **Composer**
- **Git**
- HTTPS in production
- Cron (for scheduled publish/unpublish)
- SMTP (password recovery / notifications)
- Writable application storage for cache, session, and uploads
- **File cache** (`FileHandler` under `writable/cache`) — no Redis required
- **No Docker**, **no Redis**, **no queue workers** required for V1

## New installation

1. Clone this repository (prefer the `v1.1.1` tag or a later documented release tag).
2. Copy `.env.example` to `.env` and fill in required values (database, security keys, Admin credentials, **auth throttle**).
3. Follow **[Client Installation](docs/client/INSTALLATION.md)** end-to-end.

After install:

- Public site: `https://YOUR-DOMAIN/`
- Control Panel: `https://YOUR-DOMAIN/cp`

First Admin credentials are **never hard-coded**. Provide them via `.env` or CLI flags at `cms:install` time. See the installation guide.

Optional starter content (generic demo Pages and a sample Post) is available after install via `php spark cms:demo` — see [First run](docs/client/FIRST-RUN.md).

## Updating an existing installation

Follow **[Client Update](docs/client/UPDATE.md)**.

Do **not** run `composer update` in production. Use `composer install --no-dev` from the lockfile.

## Client documentation

| Document | Purpose |
|---|---|
| [Client Installation](docs/client/INSTALLATION.md) | New server install |
| [Client First Run](docs/client/FIRST-RUN.md) | First login and site setup |
| [Client Configuration](docs/client/CONFIGURATION.md) | `.env` and operational config |
| [Client Update](docs/client/UPDATE.md) | Safe production updates |
| [Client Backup & Restore](docs/client/BACKUP-RESTORE.md) | Database + uploads pairing |
| [Production Checklist](docs/client/PRODUCTION-CHECKLIST.md) | Go-live checklist |

Internal architecture / product docs remain under [`docs/`](docs/) and [`adr/`](adr/).

## Environment configuration

Copy the template and edit on the server only:

```bash
cp .env.example .env
```

Never commit `.env`. See [Client Configuration](docs/client/CONFIGURATION.md) for security keys, SMTP, and **auth throttle** (required operational configuration — unconfigured surfaces fail closed at `/cp`).

## Security warnings

- Never commit `.env`, passwords, encryption keys, or HMAC secrets.
- Keep the web server document root on `/public` — not the repository root.
- Generate unique production values for `encryption.key`, `skey`, `EMAIL_ENCRYPTION_KEY`, and `EMAIL_LOOKUP_HMAC_KEY`.
- Back up **MariaDB**, **`public/uploads/images/`**, and **`writable/uploads/documents/`** together.

## Scheduler

```bash
* * * * * php /path/to/project/spark cms:scheduled-content
```

Exact path depends on hosting. See installation and production checklist documents.

## License

See [LICENSE](LICENSE).
