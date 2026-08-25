# SMITE CMS — Backup and restore

Authoritative operational policy also lives in `docs/11-Deployment-Operations.md`. This client guide summarizes the V1 baseline.

## Backup set (paired)

A successful backup includes **both**:

1. **MariaDB database** dump
2. Upload files:
   - `writable/uploads/images/`
   - `writable/uploads/documents/`

Database-only backup is **insufficient** (broken images/downloads). Uploads-only backup is **insufficient** (orphaned files).

## Retention

DOC-11 baseline: daily backups with **at least 7 days** rolling retention (longer if hosting allows).

## Verification

Periodically restore the pair to a **non-production** environment and confirm:

- application boots
- content and users exist
- revisions / audit history readable
- media/document links resolve through the application

## Restore caution

Never restore over a live production database without a confirmed maintenance window and a verified pre-restore backup.

Do not restore into a developer machine that still points at production credentials.
