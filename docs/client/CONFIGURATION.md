# SMITE CMS — Configuration

Configuration lives in **`.env`** on the server. Never commit `.env`.

Start from `.env.example` (safe placeholders only).

## Application

| Setting | Notes |
|---|---|
| `CI_ENVIRONMENT` | Use `production` on live hosts |
| `app.baseURL` | Full HTTPS URL with trailing `/` |
| `app.forceGlobalSecureRequests` | Prefer `true` in production |

## Database

Configure `database.default.*` for MariaDB. Do not point production at a shared development database.

## Security secrets

| Variable | Notes |
|---|---|
| `skey` | Admin recovery secret — long, random, never logged |
| `EMAIL_ENCRYPTION_KEY` | 64 hex chars (Sodium) |
| `EMAIL_LOOKUP_HMAC_KEY` | 64 hex chars, **must differ** from encryption key |

Rotating `EMAIL_LOOKUP_HMAC_KEY` invalidates existing email lookup hashes — treat as a breaking migration, not a casual config tweak.

## Install-time Admin (first install only)

| Variable | Notes |
|---|---|
| `cms.install.admin_username` | Lowercase username |
| `cms.install.admin_email` | Unique email (stored encrypted) |
| `cms.install.admin_password` | Meets password policy; change after first login |

After installation these are unused for login; users authenticate with the credentials stored by Shield.

## Auth throttling

Operational rates come only from environment keys such as:

- `auth.throttle.login.capacity` / `.seconds`
- `auth.throttle.password_reset_request.*`
- `auth.throttle.password_reset_verify.*`
- `auth.throttle.admin_recovery.*`

Unconfigured surfaces fail closed. Do not invent product rate numbers in application code.

## SMTP

Configure `email.protocol`, `email.SMTPHost`, `email.SMTPUser`, `email.SMTPPass`, `email.SMTPPort`, `email.SMTPCrypto`, and from-address fields for password recovery.

## Cache

V1 uses the **file** cache handler under `writable/cache`. No Redis is required.

## Uploads

Application storage:

- `writable/uploads/images/`
- `writable/uploads/documents/`

Keep these writable and include them in backups.
