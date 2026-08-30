# SMITE CMS — Backup and restore

Authoritative operational policy also lives in `docs/11-Deployment-Operations.md`. This client guide summarizes the V1 baseline.

## Backup set (paired)

A successful backup includes **both**:

1. **MariaDB database** dump
2. Upload files:
   - `writable/uploads/images/`
   - `writable/uploads/documents/`

Together these form the important application recovery set.

Database-only backup is **insufficient** (broken images/downloads). Uploads-only backup is **insufficient** (orphaned files without database references).

## Retention

DOC-11 baseline: daily backups with **at least 7 days** rolling retention (longer if hosting allows).

Do not invent RPO/RTO numbers beyond the DOC-11 contract.

## Operational examples

The following are **operational examples** using standard tools. Adapt paths, credentials, and scheduling to your hosting environment. SMITE CMS does not ship backup scripts or Spark backup commands.

### Database dump (example)

```bash
mysqldump -h localhost -u YOUR_DB_USER -p YOUR_DATABASE \
  > /backups/smite-cms-$(date +%F).sql
```

### Uploads archive (example)

```bash
tar -czf /backups/smite-uploads-$(date +%F).tar.gz \
  -C /path/to/smite-cms/writable/uploads images documents
```

Store the database dump and uploads archive together with the same date identifier so they can be restored as a pair.

## Verification

Periodically restore the pair to a **non-production** environment and confirm:

- application boots
- content and users exist
- revisions / audit history readable
- media/document links resolve through the application

## Restore caution

Never restore over a live production database without a confirmed maintenance window and a verified pre-restore backup.

Do not restore into a developer machine that still points at production credentials.

Restore order (general guidance):

1. Restore the MariaDB database
2. Restore `writable/uploads/images/` and `writable/uploads/documents/`
3. Verify `.env` points at the restored database
4. Smoke-test `/` and `/cp`
