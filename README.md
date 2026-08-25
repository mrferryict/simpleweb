# SMITE CMS

SMITE CMS is a single-organization, single-website public Content Management System
built on CodeIgniter 4. It is not a website builder.

Official release: **v1.0.0**

## Requirements

- **PHP 8.5+**
- Required extensions include at least: `intl`, `mbstring`, `gd`, `sodium`, and MySQL/MariaDB drivers
- **MariaDB** (MySQL-compatible)
- **Composer**
- **Git**
- HTTPS in production
- Cron (for scheduled publish/unpublish)
- SMTP (password recovery / notifications)
- Writable application storage for cache and uploads
- **File cache** (no Redis required)
- **No Docker**, **no Redis**, **no queue workers** required for V1

## Quick start (new installation)

1. Clone this repository (prefer the `v1.0.0` tag for a known release).
2. Follow **[docs/client/INSTALLATION.md](docs/client/INSTALLATION.md)** end-to-end.
3. After install:
   - Public site: `https://YOUR-DOMAIN/`
   - Control Panel: `https://YOUR-DOMAIN/cp`

First Admin credentials are **never hard-coded**. Provide them via CLI flags or environment variables at install time (see installation guide). Placeholders:

```text
cms.install.admin_username = YOUR_ADMIN_USERNAME
cms.install.admin_email    = YOUR_ADMIN_EMAIL
cms.install.admin_password = YOUR_ADMIN_PASSWORD
```

## Updating an existing installation

Follow **[docs/client/UPDATE.md](docs/client/UPDATE.md)**.

Do **not** run `composer update` in production. Use `composer install --no-dev` from the lockfile.

## Client documentation

| Document | Purpose |
|---|---|
| [INSTALLATION.md](docs/client/INSTALLATION.md) | New server install |
| [FIRST-RUN.md](docs/client/FIRST-RUN.md) | First login and site setup |
| [CONFIGURATION.md](docs/client/CONFIGURATION.md) | `.env` and operational config |
| [UPDATE.md](docs/client/UPDATE.md) | Safe production updates |
| [BACKUP-RESTORE.md](docs/client/BACKUP-RESTORE.md) | Database + uploads pairing |
| [PRODUCTION-CHECKLIST.md](docs/client/PRODUCTION-CHECKLIST.md) | Go-live checklist |

Internal architecture / product docs remain under [`docs/`](docs/) and [`adr/`](adr/).

## Security warnings

- Never commit `.env`, passwords, encryption keys, or HMAC secrets.
- Keep the web server document root on `/public` — not the repository root.
- Generate unique production values for `skey`, `EMAIL_ENCRYPTION_KEY`, and `EMAIL_LOOKUP_HMAC_KEY`.
- Back up **MariaDB** and **`writable/uploads/`** together.

## Scheduler

```bash
* * * * * php /path/to/project/spark cms:scheduled-content
```

Exact path depends on hosting. See installation and production checklist documents.

## License

See [LICENSE](LICENSE).
