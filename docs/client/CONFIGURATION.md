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

Site-level timezone and locale are also configurable in the Control Panel after install (`Config\Site` bootstrap defaults are persisted by `cms:install`).

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

Operational rates come only from environment keys:

- `auth.throttle.login.capacity` / `.seconds`
- `auth.throttle.password_reset_request.capacity` / `.seconds`
- `auth.throttle.password_reset_verify.capacity` / `.seconds`
- `auth.throttle.admin_recovery.capacity` / `.seconds`

The V1 product contract does **not** define numeric throttle defaults. Set **both** capacity and seconds per surface in your deployment environment. Unconfigured surfaces fail closed. Do not invent product rate numbers in application code or documentation.

## Uploads

Application storage (include in backups):

- `writable/uploads/images/`
- `writable/uploads/documents/`

Keep these writable and include them in backups.
