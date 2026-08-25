# SMITE CMS — First run

After a successful [installation](INSTALLATION.md):

## Public site

```text
https://YOUR-DOMAIN/
```

A fresh install renders a **default landing page** (product-neutral). It does not require you to create a Page first. Replace it later by configuring themes and publishing real Pages/Posts.

## Control Panel

```text
https://YOUR-DOMAIN/cp
```

Authenticated administration lives under `/admin/*` after login.

## First login

1. Sign in with the **administrator-provided** credentials used at `cms:install`.
2. When force password reset is active, change the password immediately through the application flow.
3. Do not leave the install-time password in use.

There is **no** hard-coded default password in the repository.

## Recommended first configuration order

Configure through the Control Panel (do not edit application source for CMS settings):

1. Site settings (name, description, timezone)
2. Primary locale / secondary locale (if required)
3. Theme selection / preview
4. Pages and Posts
5. Menus
6. Media and documents
7. SEO fields, sitemap, robots
8. Confirm SMTP delivery for password recovery
9. Confirm cron runs `cms:scheduled-content`
10. Confirm backup pair is scheduled ([BACKUP-RESTORE.md](BACKUP-RESTORE.md))

## Smoke checks

- [ ] `/` shows the landing or your published homepage content
- [ ] `/cp` accepts login
- [ ] Publish a Page and open its public URL
- [ ] Publish a Post under `/news/...`
- [ ] Upload an image and a document
- [ ] `/sitemap.xml` and `/robots.txt` respond
