# SMITE CMS — Backup and restore

Authoritative operational policy also lives in `docs/11-Deployment-Operations.md`. This client guide summarizes the V1 baseline.

## Backup set (paired)

A complete application backup includes **all three** components below, stored together with the same date identifier:

1. **MariaDB database** dump
2. **Public image uploads** — `public/uploads/images/`
3. **Private document uploads** — `writable/uploads/documents/`

Together these form the important application recovery set.

### Why two upload locations

SMITE CMS stores uploads in two places by design:

- **Images** — processed public web assets served under `/uploads/images/…` from `public/uploads/images/`
- **Documents** — private files kept under `writable/uploads/documents/` and delivered through the application (not as direct public static files)

Do **not** treat `writable/uploads/images/` as the current image storage path. V1 image binaries live under `public/uploads/images/`.

Database-only backup is **insufficient** (broken images/downloads). Uploads-only backup is **insufficient** (orphaned files without database references).

## Retention

DOC-11 baseline: daily backups with **at least 7 days** rolling retention (longer if hosting allows).

Do not invent RPO/RTO numbers beyond the DOC-11 contract.

## Operational examples

The following are **operational examples** using standard tools. Adapt paths, credentials, and scheduling to your hosting environment. SMITE CMS does not ship backup scripts or Spark backup commands.

Do **not** include `.env`, passwords, encryption keys, or other secrets in ordinary application backup artifacts. Manage secrets separately through your hosting secret-management procedure.

### Database dump (example)

```bash
mysqldump -h localhost -u YOUR_DB_USER -p YOUR_DATABASE \
  > /backups/smite-cms-$(date +%F).sql
```

### Uploads archives (example)

Archive **both** upload locations. Either use separate archives (shown below) or a single archive that includes both paths — the important requirement is that neither location is omitted.

```bash
DATE=$(date +%F)
BASE=/path/to/smite-cms

tar -czf /backups/smite-public-images-${DATE}.tar.gz \
  -C "${BASE}/public/uploads" images

tar -czf /backups/smite-documents-${DATE}.tar.gz \
  -C "${BASE}/writable/uploads" documents
```

Store the database dump and both uploads archives together with the same date identifier so they can be restored as a set.

## Verification

Periodically restore the set to a **non-production** environment and confirm:

- application boots
- content and users exist
- revisions / audit history readable
- public images load from `/uploads/images/…`
- document download links resolve through the application

## Restore caution

Never restore over a live production database without a confirmed maintenance window and a verified pre-restore backup.

Do not restore into a developer machine that still points at production credentials.

Restore order (general guidance):

1. Restore the MariaDB database
2. Restore `public/uploads/images/`
3. Restore `writable/uploads/documents/`
4. Verify `.env` points at the restored database (restore `.env` separately from secure secret storage — do not rely on application backup artifacts for secrets)
5. Smoke-test `/` and `/cp`

### Restore examples

```bash
DATE=2026-08-30
BASE=/path/to/smite-cms

mysql -h localhost -u YOUR_DB_USER -p YOUR_DATABASE \
  < /backups/smite-cms-${DATE}.sql

tar -xzf /backups/smite-public-images-${DATE}.tar.gz \
  -C "${BASE}/public/uploads"

tar -xzf /backups/smite-documents-${DATE}.tar.gz \
  -C "${BASE}/writable/uploads"
```
