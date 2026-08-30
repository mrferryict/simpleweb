# SMITE CMS — Configuration

Configuration lives in **`.env`** on the server. Never commit `.env`.

Start from `.env.example`:

```bash
cp .env.example .env
```

The example file is organized into seven sections: Environment, Application, Database, Security keys, CMS Admin credentials, SMTP, and Auth throttle.

## 1. Environment

| Setting | Notes |
|---|---|
| `CI_ENVIRONMENT` | Use `production` on live hosts; `development` only on local workstations |

## 2. Application

| Setting | Notes |
|---|---|
| `app.baseURL` | Full public URL with trailing `/` (HTTPS in production) |
| `app.forceGlobalSecureRequests` | Prefer `true` in production |
| `app.indexPage` | Use `''` when the web server removes `index.php` from URLs |
| `app.CSPEnabled` | V1 baseline: `false` |
| `app.appTimezone` | PHP timezone for the application (e.g. `Asia/Jakarta`) |

Site-level timezone and locale are also configurable in the Control Panel after install (`Config\Site` bootstrap defaults are persisted by `cms:install`). See [ADMIN-CONTROL-PANEL.md](ADMIN-CONTROL-PANEL.md) for `/cp` vs `/admin` and the main administration areas.

### Session and cache

- Sessions use the **file** handler (`CodeIgniter\Session\Handlers\FileHandler`); default save path is `WRITEPATH/session`.
- V1 cache uses the **file** handler (`FileHandler`) under `writable/cache`. No Redis is required.

## 3. Database

Configure `database.default.*` for MariaDB. Do not point production at a shared development database.

## 4. Security keys

| Variable | Purpose | Generation |
|---|---|---|
| `encryption.key` | CodeIgniter application encryption | `php spark key:generate` or `php spark key:generate --show` |
| `skey` | Admin recovery secret — long, random, never logged | `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` |
| `EMAIL_ENCRYPTION_KEY` | PII email encryption (Sodium) — 64 hex chars | `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` |
| `EMAIL_LOOKUP_HMAC_KEY` | Email lookup HMAC — 64 hex chars, **must differ** | Run the same command again with a different output |

All four must be set before `php spark cms:install`.

Rotating `EMAIL_LOOKUP_HMAC_KEY` invalidates existing email lookup hashes — treat as a breaking migration, not a casual config tweak.

## 5. CMS Admin credentials (first install only)

| Variable | Notes |
|---|---|
| `cms.install.admin_username` | Lowercase alphanumeric and `.` only; 3–30 characters |
| `cms.install.admin_email` | Valid email (stored encrypted) |
| `cms.install.admin_password` | Meets password policy; leave empty in `.env.example`; set a unique value before install |

These values are used only during the initial `cms:install` run. They are not demo credentials and are not hard-coded in the application. Change the Admin password after first login.

CLI alternative:

```bash
php spark cms:install \
    --username admin \
    --email admin@example.com \
    --password 'YOUR_SECURE_PASSWORD'
```

## 6. SMTP

SMTP is optional in the template but recommended for password recovery in production.

| Setting | Notes |
|---|---|
| `email.fromEmail` | Sender address |
| `email.fromName` | Sender display name |
| `email.protocol` | Use `smtp` for remote SMTP |
| `email.SMTPHost` | SMTP server hostname |
| `email.SMTPUser` | Leave empty until configured |
| `email.SMTPPass` | Leave empty until configured |
| `email.SMTPPort` | Common value: `587` |
| `email.SMTPCrypto` | Common value: `tls` |

## 7. Auth throttling

Authentication is throttled on four surfaces:

| Surface | Environment prefix |
|---|---|
| Login (`/cp`) | `auth.throttle.login` |
| Password reset request | `auth.throttle.password_reset_request` |
| Password reset verification | `auth.throttle.password_reset_verify` |
| Admin recovery | `auth.throttle.admin_recovery` |

Each surface requires **both** keys:

- `auth.throttle.<surface>.capacity`
- `auth.throttle.<surface>.seconds`

### Deployment configuration (not product policy)

ADR-026 wires required authentication surfaces to CI4 Throttler but **does not** define numeric capacity/window values. The numbers in `.env.example` are **example deployment operational values** — adjust them to match your security policy. They are not SMITE CMS product mandates.

### Fail-closed behavior

If throttle configuration is missing or invalid for a surface, `AuthThrottleService` denies the request. A fresh deployment without throttle values configured will show “Too many attempts” at `/cp` even on the first login attempt.

Configure all four surfaces in `.env` before using authentication routes. `.env.example` is a template only — create `.env` on the server and never commit it to Git.

## Uploads

SMITE CMS uses two upload locations. Include **both** in backups — see [BACKUP-RESTORE.md](BACKUP-RESTORE.md).

| Path | Role |
|---|---|
| `public/uploads/images/` | Public processed image assets (served under `/uploads/images/…`) |
| `writable/uploads/documents/` | Private document storage (served through the application) |

Ensure the application user can write to both paths. Do not move public images into `writable/` — V1 stores image binaries under `public/uploads/images/`.
