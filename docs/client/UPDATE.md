# SMITE CMS — Updating an existing installation

Use this for an **already installed** production (or staging) site.

For a brand-new server, use [INSTALLATION.md](INSTALLATION.md).

## Principles

- Prefer Git **release tags** over ad-hoc local edits on the server.
- Use `composer install --no-dev` from `composer.lock` — **not** `composer update`.
- Never overwrite server-local `.env`, `public/uploads/images/`, or `writable/uploads/documents/` from Git.
- If the server working tree has uncommitted local modifications, **stop** and resolve them before updating. `git pull` is not universally safe on a dirty tree.

## Recommended sequence

### 1. Back up the database

Take a MariaDB dump before any update. See [BACKUP-RESTORE.md](BACKUP-RESTORE.md).

### 2. Back up uploads

Archive `public/uploads/images/` and `writable/uploads/documents/` together with the database backup.

### 3. Maintenance state (if required)

If your hosting policy requires a maintenance window, enable it before changing application code.

### 4. Check the working tree

```bash
cd /path/to/smite-cms
git status
```

If unexpected local source modifications appear, stop and resolve them before continuing.

### 5. Fetch and check out the target release

```bash
git fetch --tags
git checkout v1.1.5
```

Replace `v1.1.5` with the release tag you intend to deploy. Prefer tagged releases over arbitrary branch tips. For example, an installation on **v1.1.1** can update to **v1.1.2** (or the latest **`v1.1.5`** documentation distribution, which has the same application code as v1.1.2) by checking out that tag after backup.

### 6. Install PHP dependencies

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

### 7. Check migration status

```bash
php spark migrate:status
```

### 8. Run pending migrations (if required)

If pending migrations are shown and the release notes require them — and you have a verified backup:

```bash
php spark migrate
```

Only run migrations when the release documentation says they are required.

### 9. Verify environment

Confirm `.env` on the server was **not** overwritten by Git. Required secrets and database settings must still be present.

### 10. Smoke test

- Public site: `https://YOUR-DOMAIN/`
- Control Panel: `https://YOUR-DOMAIN/cp`
- Page and Post publish flows
- Media upload
- SMTP password recovery (if used)
- Scheduler cron (`cms:scheduled-content`)

## Flow summary

```text
Backup database + uploads
  ↓
Check git status (stop if dirty)
  ↓
git fetch / checkout release tag
  ↓
composer install --no-dev (from lock)
  ↓
php spark migrate:status
  ↓
php spark migrate (if required, after backup)
  ↓
Verify .env unchanged
  ↓
Smoke test / and /cp
```

## Do not

- Run `composer update` on production
- Commit secrets
- Blindly `git pull` over a dirty tree with local hotfixes
- Point the web root at the repository root
- Overwrite `public/uploads/images/` or `writable/uploads/documents/` from Git

## Commands reference

| Command | Purpose |
|---|---|
| `php spark migrate:status` | Show migration status |
| `php spark migrate` | Run pending migrations |
| `php spark list` | List Spark commands |

There is no separate `cms:update` command. Updates use Git + Composer + migrations as documented above.
