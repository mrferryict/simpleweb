DOC-11 — SMITE CMS Deployment & Operations

Document Version: 0.2.0
Status: Approved — Deployment & Operations
Last Updated: 21 August 2026

1. Purpose

This document defines the deployment, installation, backup, scheduling, production operation, and operational safety procedures for SMITE CMS V1.

The design targets shared hosting and similar environments while remaining portable to a VPS.

2. Operations Principles

OPS-001 — Reproducible Deployment

Production deployment must be repeatable and documented.

It must not depend on:

undocumented manual changes;

files that exist only on the developer workstation;

untracked SQL statements;

manually installed vendor packages.

OPS-002 — Production Is Not Development

Production must not be used as an experimental development environment.

OPS-003 — No Manual Schema Drift

Production database schema changes originate from CI4 migrations.

OPS-004 — Secrets Stay Outside Git

Production secrets remain in protected environment/configuration.

3. Target Production Environment

V1 targets:

Shared Hosting
├── cPanel / hPanel
├── PHP 8.5+
├── MariaDB
├── HTTPS
├── Cron
└── SMTP

V1 does not require:

Docker
Redis
RabbitMQ
Node.js runtime
Queue worker

4. Required Production Components

Minimum requirements include:

PHP
required PHP extensions
MariaDB
Composer-compatible deployment process
HTTPS
Writable storage
Cron
SMTP capability

Required application extensions include at least:

ext-gd
ext-sodium

The actual production environment must be verified before release.

5. Environment Configuration

Production environment/configuration provides values such as:

CI_ENVIRONMENT=production
app.baseURL=...
database...
skey=...

EMAIL_ENCRYPTION_KEY=...
EMAIL_LOOKUP_HMAC_KEY=...

SMTP...

Secrets must never be committed to Git.

6. .env Policy

Production .env is environment-specific and must not be committed.

The repository may contain:

.env.example

with safe placeholder values only.

7. Deployment Source

Production deployment should originate from the Git repository.

Recommended flow:

Local development
   ↓
Tests
   ↓
Git commit
   ↓
Git push
   ↓
Production deployment

Possible deployment mechanisms include:

Git deployment
SFTP
Hosting deployment integration

The deployed Git commit/release identifier must remain traceable.

8. Release Identification

Every production release should be traceable to:

Git commit
application/release identifier
database migration state

Operational documentation should allow the maintainer to identify which release is live.

9. Composer Deployment

Production dependencies are installed through Composer.

Production deployments should install only required production dependencies.

composer.lock must be committed.

Manual installation of production packages outside Composer is prohibited.

10. Database Migration

Before activating application code that depends on a schema change:

Migration available
   ↓
Backup
   ↓
Run migration
   ↓
Verify migration
   ↓
Activate compatible application code

Migration order must preserve application compatibility.

11. Installation Command

Fresh or already-installed systems use:

php spark cms:install

The command is idempotent safe-exit.

If the application is already installed, it must display an informational message such as:

[INFO] SMITE CMS is already installed. No changes made.

and exit successfully with code 0.

If pending migrations exist, the command SHALL run those migrations before exiting, provided the current installation/configuration is valid.

The command must not:

overwrite existing content;

reset existing passwords;

recreate the Admin;

duplicate permissions;

recreate default settings destructively.

The installer must detect an already-installed system safely.

12. Initial Admin Bootstrap

For a fresh installation, cms:install may create the single initial Admin through:

controlled environment/configuration values; or

an explicit interactive setup mechanism.

The credentials must never be hard-coded into source code.

The installer must create exactly one Admin and remain safe when run repeatedly.

13. Cron

Scheduling is implemented through CI4 Spark.

Recommended command:

php spark cms:scheduled-content

Recommended cron:

* * * * * php /path/to/project/spark cms:scheduled-content

The exact executable/project path depends on hosting.

The target cadence is once per minute.

14. Scheduler Operations

The scheduler must:

detect due actions;

safely process concurrent execution;

validate target state;

process overdue jobs;

create audit events;

invalidate cache;

record failures.

Operational output should identify:

execution time
processed count
skipped count
failed count

without logging secrets or sensitive data.

15. SMTP

SMTP is used for approved capabilities such as:

password recovery
future approved notifications

SMTP credentials remain environment-specific.

V1 does not require a dedicated mail queue.

16. HTTPS

Production must use HTTPS.

At minimum:

/cp
/admin/*
password recovery
document downloads

must operate through HTTPS.

HTTP-to-HTTPS enforcement belongs to infrastructure/application configuration.

17. File Permissions

Writable directories must be writable by the application while avoiding unnecessarily broad permissions.

The application must correctly configure:

writable/

Documents remain outside the public web root.

18. Public Files

The web root should contain only intended public assets:

public/
├── index.php
├── themes/
├── public assets
└── explicitly public files

It must not expose:

.env
app/
writable/
tests/
reference/
private document storage
logs
database dumps

19. Document Storage

Private documents remain in:

writable/uploads/documents/

The browser never receives the physical storage path.

Downloads occur through the application-controlled document endpoint.

20. Media Storage Backup

Backups must include:

writable/uploads/images/
writable/uploads/documents/

and the MariaDB database.

The backup policy treats database and upload storage as a paired recovery set.

21. Backup Strategy

The V1 baseline is:

Daily backup
+
minimum 7-day rolling retention

The backup set SHALL include:

MariaDB database
writable/uploads/images/
writable/uploads/documents/

The database and uploads should represent the same operational snapshot/window as closely as the hosting platform permits.

The goal is an atomic backup pairing:

Database snapshot
        +
Uploads snapshot

A database without its associated media can produce broken images/downloads.

Uploads without their corresponding database can produce orphaned storage.

22. Backup Verification

A backup is not considered successful merely because a file exists.

At least monthly, a restore verification should be performed against a local/test environment:

Backup
  ↓
Restore database
  ↓
Restore uploads
  ↓
Run integrity checks
  ↓
Smoke test application

Verification should confirm:

application boots;

database is readable;

content exists;

Users exist;

revisions exist;

audit history exists;

Media references resolve;

documents are downloadable through the application.

23. Backup Retention

Minimum baseline:

7 daily backups

A longer retention period may be used if hosting/storage cost permits.

Retention policy must be documented for the production environment actually used.

24. Rollback Strategy

Application rollback and database rollback are separate concerns.

Application:

Git
  ↓
Deploy previous known-good commit

Database:

approved migration rollback
or
restore from backup

Do not assume Git rollback automatically rolls back the database.

Destructive schema migrations require an explicit recovery plan before release.

25. Release Sequence

Recommended production release:

1. Confirm release commit
2. Run complete test suite
3. Create database + uploads backup pair
4. Verify production environment
5. Run compatible database migrations
6. Deploy compatible application code
7. Clear/invalidate application cache
8. Verify cron
9. Verify public site
10. Verify /cp
11. Run smoke tests

The exact code/migration ordering may be adjusted if a migration requires backwards-compatible deployment sequencing.

26. Smoke Tests

After deployment verify:

Homepage
Page
Post
Category
Menu
Language switch
Image
Document download
/cp
Admin login
Editor login
Scheduler
Sitemap
robots.txt

27. Cache Operations

After deployment:

clear/invalidate application cache

where required.

Do not delete unrelated writable files.

28. Maintenance Mode

V1 does not provide an Admin UI Maintenance Mode toggle.

When maintenance is required, use infrastructure/web-server controls appropriate to the hosting environment.

Possible mechanisms include:

web-server rewrite/rule
temporary maintenance file
hosting maintenance feature
deployment-level routing

The mechanism must not expose production internals or permanently lock the Admin out.

29. Staging Environment

A separate staging environment is optional and not required for V1.

The primary pre-production environment is the local WSL2 development environment.

Where the hosting provider supplies staging, it may be used for higher-risk releases without becoming a mandatory part of the V1 architecture.

30. Log Management

Production logs should be monitored for:

500 errors
database errors
authentication failures
scheduler failures
upload failures
document download failures
Theme activation failures

Sensitive data must not be logged.

31. Log Retention

Log retention depends on hosting capabilities.

The application must avoid excessive repetitive logging.

Routine successful public requests should not create unnecessary database/application logs.

32. Failed Scheduler Monitoring

Important operational signals include:

failed actions
repeatedly failed action
large overdue backlog
database lock/timeouts

V1 does not require an external monitoring service.

33. Health Verification

Operational smoke verification should confirm:

PHP working
MariaDB working
filesystem writable
cache writable
uploads writable
cron working
SMTP working

34. Deployment Environment Parity

Development should approximate production assumptions for:

PHP version
MariaDB behavior
filesystem permissions
HTTPS behavior
CLI/cron behavior

WSL2 does not need to reproduce the hosting control panel UI.

35. Shared Hosting Constraints

The application must remain compatible with:

limited memory
limited CPU
limited execution time
limited filesystem
cron execution limits
PHP upload limits

Long-running background processes are not assumed.

36. Upload Limits

Production configuration must provide upload limits compatible with CMS requirements.

Application validation must remain stricter than infrastructure defaults where necessary.

Relevant PHP/webserver settings include:

upload_max_filesize
post_max_size
memory_limit
max_execution_time

37. PHP Configuration Validation

Before production release verify:

PHP version
memory_limit
upload_max_filesize
post_max_size
max_execution_time
max_input_vars
file_uploads
ext-gd
ext-sodium

38. MariaDB Validation

Production database should be verified for:

version compatibility
InnoDB
utf8mb4
timezone behavior
transaction support
required indexes

39. Cron Environment

Cron may run under a different environment than web requests.

The Spark command must explicitly load the correct application configuration/environment.

Never assume:

web environment == cron environment

40. File Path Handling

Application code must use framework/configured paths.

Do not hard-code server-specific paths such as:

/home/user123/public_html/

41. Deployment Safety

Deployment must not expose:

.env
tests/
reference/
writable/
private documents
logs
database dumps

Development-only artifacts should be excluded from the public deployment surface.

42. Production Security Checks

Before go-live verify:

HTTPS
debug disabled
production environment
security headers
file permissions
private document storage
Admin route protection
CSRF
brute-force protection
secret configuration

43. Update Procedure

Routine application update:

Backup database + uploads
  ↓
Test release
  ↓
Deploy compatible application code
  ↓
Run migrations
  ↓
Clear/invalidate cache
  ↓
Smoke test

The exact order must remain compatible with the migration's backward-compatibility requirements.

44. Dependency Update

Before updating:

PHP
CodeIgniter
Shield
Quill

and other managed dependencies:

full test suite
dependency audit
compatibility review

must pass.

Production should be updated through a controlled release rather than ad-hoc package changes.

45. Emergency Fix Procedure

For urgent security/production issues:

Identify issue
   ↓
Create minimal fix
   ↓
Add regression test
   ↓
Run full test suite
   ↓
Create backup pair
   ↓
Deploy
   ↓
Smoke test
   ↓
Document incident/change

Emergency status does not authorize undocumented production edits.

46. Operational Ownership

The project maintainer is responsible for:

release preparation;

backup verification;

database migrations;

production configuration;

cron;

SMTP;

dependency updates;

incident handling;

restore testing.

47. Operational Documentation

Production-specific information should be documented without exposing secrets.

Examples:

hosting provider
PHP version
MariaDB version
cron schedule
deployment method
backup schedule
SMTP provider
active Theme
current release identifier

Secrets remain outside documentation.

48. Future Operations

V1 does not require:

Kubernetes
Docker
Redis monitoring
queue monitoring
distributed tracing
service mesh
complex observability stack

Future infrastructure must be justified by approved requirements.

49. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

03-Authorization-Security.md;

06-Media-Document-Management.md;

08-Technical-Architecture.md;

09-Implementation-Blueprint.md;

10-Testing-Quality-Strategy.md.

Primary requirement groups:

REQ-AUTH-*
REQ-DOC-*
REQ-MEDIA-*
REQ-SCHED-*
REQ-CACHE-*
REQ-NFR-*

Operational procedures defined here are mandatory for V1 production deployment and maintenance.