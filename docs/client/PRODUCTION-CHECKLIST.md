# SMITE CMS — Production checklist

Use before go-live and after major changes.

## Server

- [ ] PHP 8.5+
- [ ] Required PHP extensions (`intl`, `mbstring`, `gd`, `sodium`, MariaDB/MySQL driver)
- [ ] MariaDB
- [ ] Composer
- [ ] Git
- [ ] HTTPS enabled
- [ ] Cron available
- [ ] SMTP available (if password recovery is required)
- [ ] Correct filesystem permissions on `writable/cache`, `writable/session`, `writable/logs`, `writable/uploads/documents/`, and `public/uploads/images/`
- [ ] Web server document root points to `/public`

## Environment

- [ ] `.env` created from `.env.example` (not committed)
- [ ] `CI_ENVIRONMENT = production`
- [ ] `app.baseURL` set to actual public HTTPS URL
- [ ] Database credentials configured
- [ ] `encryption.key` generated
- [ ] `skey` generated
- [ ] `EMAIL_ENCRYPTION_KEY` and `EMAIL_LOOKUP_HMAC_KEY` generated (different values)
- [ ] Initial Admin credentials configured for `cms:install`
- [ ] SMTP configured (if required)
- [ ] Auth throttle operational values configured for all four surfaces (required before `/cp` login)

## Application

- [ ] Repository cloned from release tag
- [ ] `composer install --no-dev --prefer-dist --optimize-autoloader`
- [ ] `php spark cms:install` completed with administrator-provided credentials
- [ ] Exactly one Admin created
- [ ] `/` loads landing page (or published home content)
- [ ] `/cp` works
- [ ] Initial Admin login works
- [ ] Admin password changed after first login
- [ ] Site settings configured
- [ ] Theme configured
- [ ] Localization configured
- [ ] Page publishing works
- [ ] Post publishing works
- [ ] Media upload works
- [ ] Documents work
- [ ] Sitemap works (`/sitemap.xml`)
- [ ] Robots works (`/robots.txt`)
- [ ] SMTP tested (if used)
- [ ] Scheduler cron tested (`cms:scheduled-content`)

## Security

- [ ] `.env` not in Git
- [ ] Production secrets not committed
- [ ] HTTPS enforced (`app.forceGlobalSecureRequests = true`)
- [ ] Initial install password changed
- [ ] Upload directories writable only as required
- [ ] Repository root is not the document root

## Backup

- [ ] Database backup configured
- [ ] `public/uploads/images/` included in backup
- [ ] `writable/uploads/documents/` included in backup
- [ ] Database and both upload locations backed up as a **set**
- [ ] Restore procedure covers both upload locations and is understood or tested on non-production
