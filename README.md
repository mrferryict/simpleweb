# SMITE CMS

SMITE CMS is a single-organization, single-website public Content Management System
built on CodeIgniter 4. It is not a website builder.

**Latest repository distribution:** **v2.0.0** (V2 CORE — user management, password-reset email, password policy consistency)

**V2 application release:** **v2.0.0**

**V1 historical baseline:** **v1.1.6** (frozen V1 distribution; application behavior baseline **v1.1.2**)

## Release history

### V2

| Tag | Summary |
|---|---|
| `v2.0.0` | V2 CORE: user management UI, SMTP password-reset email, Shield password policy on all password-setting paths |

### V1 (frozen)

| Tag | Summary |
|---|---|
| `v1.0.0` | Original V1 baseline |
| `v1.1.0` | Theme 2026 + starter content + Admin Control Panel |
| `v1.1.1` | First-login password reset enforcement |
| `v1.1.2` | Backup path correction + application baseline |
| `v1.1.3` | Documentation alignment (client docs with v1.1.2) |
| `v1.1.4` | Final V1 deployment and developer documentation |
| `v1.1.5` | Admin User Guide and documentation integration |
| `v1.1.6` | Authentication UI polish (login, password change, password reset) |

**Which tag should I clone?** Use **`v2.0.0`** for new installations and updates. Use **`v1.1.6`** only when maintaining a frozen V1 deployment or reviewing historical V1 behavior.

Release notes: [docs/releases/v2.0.0.md](docs/releases/v2.0.0.md)

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
- **No Docker**, **no Redis**, **no queue workers** required

## New installation

1. Clone this repository (use the **`v2.0.0`** tag — see [Release history](#release-history) above).
2. Copy `.env.example` to `.env` and fill in required values (database, security keys, Admin credentials, **auth throttle**, SMTP if using password reset email).
3. Follow **[Client Installation](docs/client/INSTALLATION.md)** end-to-end.

After install:

- Public site: `https://YOUR-DOMAIN/`
- Control Panel: `https://YOUR-DOMAIN/cp`

First Admin credentials are **never hard-coded**. Provide them via `.env` or CLI flags at `cms:install` time. See the installation guide. First login requires a mandatory password change (`force_reset`).

Optional starter content (generic demo Pages and a sample Post) is available after install via `php spark cms:demo` — see [First run](docs/client/FIRST-RUN.md).

For day-to-day website and content management (Pages, Posts, Media, Menus, Users), operators should use the **[Admin User Guide](docs/client/ADMIN-USER-GUIDE.md)**.

## Updating an existing installation

Follow **[Client Update](docs/client/UPDATE.md)**.

Do **not** run `composer update` in production. Use `composer install --no-dev` from the lockfile.

Upgrading from **v1.1.6** to **v2.0.0** requires **no database migration**.

## Client documentation

| Document | Purpose |
|---|---|
| [Admin User Guide](docs/client/ADMIN-USER-GUIDE.md) | **Daily website/content operation** for Admin, Editor, and Contributor (operator guide) |
| [Control Panel Reference](docs/client/ADMIN-CONTROL-PANEL.md) | Concise Control Panel area and route overview |
| [Client Installation](docs/client/INSTALLATION.md) | New server install |
| [Client First Run](docs/client/FIRST-RUN.md) | First login and site setup |
| [Client Configuration](docs/client/CONFIGURATION.md) | `.env` and operational config |
| [Client Update](docs/client/UPDATE.md) | Safe production updates |
| [Client Backup & Restore](docs/client/BACKUP-RESTORE.md) | Database + uploads pairing |
| [Production Checklist](docs/client/PRODUCTION-CHECKLIST.md) | Go-live checklist |

**Maintainer / developer documentation:**

| Document | Purpose |
|---|---|
| [Developer Client Deployment](docs/DEVELOPER-CLIENT-DEPLOYMENT.md) | SOP for onboarding a new client |
| [Theme Development Guide](docs/05-Theme-Development-Guide.md) | Quick-start for creating a new Theme from Theme 2026 |
| [v2.0.0 Release Notes](docs/releases/v2.0.0.md) | V2 CORE release summary |

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
