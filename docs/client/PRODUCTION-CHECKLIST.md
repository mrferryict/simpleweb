# SMITE CMS — Production checklist

## Server

- [ ] PHP 8.5+
- [ ] Required PHP extensions (`intl`, `mbstring`, `gd`, `sodium`, DB driver)
- [ ] MariaDB
- [ ] Composer
- [ ] Git
- [ ] HTTPS
- [ ] Cron
- [ ] SMTP

## Application

- [ ] Repository cloned from release tag
- [ ] `composer install --no-dev --prefer-dist --optimize-autoloader`
- [ ] `.env` configured (not committed)
- [ ] Secrets generated (`skey`, PII keys)
- [ ] Database configured
- [ ] `php spark cms:install` completed with administrator-provided credentials
- [ ] Exactly one Admin created
- [ ] Admin password changed after first login
- [ ] `/` loads landing page (or published home content)
- [ ] `/cp` works
- [ ] Page publishing works
- [ ] Post publishing works
- [ ] Media upload works
- [ ] Documents work
- [ ] Sitemap works
- [ ] Robots works
- [ ] SMTP tested
- [ ] Scheduler cron tested (`cms:scheduled-content`)
- [ ] Backup configured (DB + uploads)

## Security

- [ ] `.env` not committed
- [ ] Production secrets not in Git
- [ ] Uploads writable by the app only as required
- [ ] Document root is `/public`
- [ ] Repository root is not the document root
- [ ] HTTPS active
