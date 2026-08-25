# SMITE CMS — Updating an existing installation

Use this for an **already installed** production (or staging) site.

For a brand-new server, use [INSTALLATION.md](INSTALLATION.md).

## Principles

- Prefer Git tags / release commits over ad-hoc local edits on the server.
- Use `composer install --no-dev` from `composer.lock` — **not** `composer update`.
- Never overwrite server-local `.env` or `writable/uploads/` from Git.
- If the server working tree has uncommitted local modifications, **stop** and resolve them before updating.

## Recommended sequence

```bash
cd /path/to/smite-cms

# Stop if the tree is dirty with unexpected local edits
git status

# Fetch and check out the target release tag (example)
git fetch --tags
git checkout v1.0.0

composer install --no-dev --prefer-dist --optimize-autoloader

php spark migrate:status
```

If pending migrations are shown and the release notes require them:

```bash
php spark migrate
```

Only run migrations when the release documentation says they are required and you have a verified backup.

Then smoke-test:

- `https://YOUR-DOMAIN/`
- `https://YOUR-DOMAIN/cp`
- Publish flow, media, scheduler cron, SMTP recovery as appropriate

## Flow summary

```text
GitHub release tag
    ↓
git fetch / checkout tag
    ↓
composer install --no-dev (from lock)
    ↓
migrate if required (after backup)
    ↓
smoke test
```

## Do not

- Run `composer update` on production
- Commit secrets
- Blindly `git pull` over a dirty tree with local hotfixes
- Point the web root at the repository root
