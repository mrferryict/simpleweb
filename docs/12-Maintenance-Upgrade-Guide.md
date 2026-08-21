DOC-12 — SMITE CMS Maintenance & Upgrade Guide

Document Version: 0.2.0
Status: Approved — Maintenance & Upgrade Guide
Last Updated: 21 August 2026

1. Purpose

This document defines the long-term maintenance, dependency upgrade, framework upgrade, security maintenance, data migration, integrity checking, rollback, and operational hygiene procedures for SMITE CMS.

The goal is deterministic long-term maintenance by a solo developer without:

data loss;

schema drift;

uncontrolled dependency changes;

silent security regressions;

undocumented production fixes.

2. Maintenance Principles

MNT-001 — Upgrade Must Be Traceable

Every maintenance release must be traceable to:

Git commit
dependency versions
database migrations
documentation/ADR

MNT-002 — Backup Before Risky Changes

Before a change that may affect production data:

database backup
+
uploads backup

must be available.

MNT-003 — Never Edit Production Code Manually

Normal maintenance must not use:

editing PHP directly in cPanel/hPanel
editing vendor/
editing already-applied migrations
manual undocumented schema changes

MNT-004 — Upgrade in Small Steps

Avoid combining unrelated high-risk upgrades in one release where practical.

Example:

PHP upgrade
+
CodeIgniter upgrade
+
MariaDB upgrade
+
Theme redesign

should not normally be treated as one maintenance change.

3. Maintenance Categories

Maintenance is classified as:

Patch
Minor Upgrade
Major Upgrade
Security Update
Dependency Update
Database Migration
Theme Update
Operational Maintenance

4. Dedicated Upgrade Branch

Framework/runtime upgrades SHALL be performed on a dedicated Git branch.

Examples:

upgrade/ci4-4.8
upgrade/shield-x.y
upgrade/php-8.6

The upgrade branch SHALL:

contain no unrelated feature work;

avoid new product features;

include only upgrade/compatibility fixes required by the target upgrade;

run the full test suite;

pass regression and compatibility checks before merging into main.

This is the approved zero-feature-addition rule for upgrade branches.

5. Patch Release

Patch releases generally include:

bug fix
security fix
small compatibility fix

Patch releases must remain narrowly scoped and must not silently introduce new product features.

6. Minor Application Release

Minor releases may contain approved enhancements and non-breaking improvements.

If behavior changes:

affected requirements must be reviewed;

documentation must be updated;

tests must be added/updated.

7. Major Release

A major release is required when there is a breaking change to:

public URL
database structure
Content Schema
Theme Manifest
authorization
deployment contract
stored content format

A major upgrade requires an explicit migration and rollback plan.

8. Standard Maintenance Workflow

Identify change
      ↓
Review affected DOC/REQ
      ↓
Create/Update ADR if required
      ↓
Create dedicated branch where appropriate
      ↓
Implement locally
      ↓
Add regression tests
      ↓
Run full test suite
      ↓
Run dependency/security checks
      ↓
Backup production
      ↓
Deploy
      ↓
Run migration if required
      ↓
Clear/invalidate cache
      ↓
Smoke test
      ↓
Record release

9. CodeIgniter Upgrade

Before upgrading CodeIgniter:

Review changelog
Check PHP compatibility
Check Shield compatibility
Run full tests
Review deprecations
Review affected CI4 APIs

Upgrade must occur on the dedicated upgrade branch.

Do not modify vendor/ manually.

10. CodeIgniter Upgrade Safety

After a CI4 upgrade, test at minimum:

authentication
authorization
session
CSRF
routing
database
migrations
file upload
image processing
Spark commands
scheduler
cache

11. Shield Upgrade

Shield is security-critical.

After upgrading Shield, test:

login
logout
password change
password reset
Admin recovery
session expiration
groups
permissions
HTMX session redirect

Do not assume a dependency patch cannot affect authentication behavior.

12. PHP Upgrade

Before PHP runtime upgrade:

check CI4 compatibility
check Shield compatibility
check Composer dependencies
run full tests
enable E_ALL
review deprecations

PHP upgrades must use a dedicated branch.

13. PHP Major Version Upgrade

Example:

PHP 8.5
  ↓
future PHP 9.x

requires:

Local PHP upgrade
  ↓
Composer dependency resolution
  ↓
Full test suite
  ↓
E_ALL / deprecation review
  ↓
Media/filesystem tests
  ↓
CLI tests
  ↓
production compatibility validation

14. Zero-Tolerance for Project Deprecations

During PHP or CodeIgniter upgrades in the local WSL2 environment:

error_reporting = E_ALL

must be used for the application's upgrade verification.

Any deprecation warning caused by application code under:

app/

must be resolved before release.

Project code must not knowingly accumulate compatibility debt toward future PHP/CodeIgniter versions.

Environment-only or third-party warnings may require separate assessment, but they must never be silently ignored.

15. MariaDB Upgrade

Before upgrading MariaDB:

create and verify backup;

review version compatibility;

test migrations;

test critical queries;

test JSON behavior;

verify indexes;

verify timezone behavior;

verify transaction behavior.

16. Database Migration Policy

Never modify an already-applied production migration.

Instead:

Old migration history
        ↓
New migration
        ↓
Upgrade

Every schema evolution remains visible in migration history.

17. Safe Migration Pattern

For important changes, prefer:

Expand
  ↓
Migrate data
  ↓
Switch application behavior
  ↓
Contract/remove old structure

Avoid immediately dropping structures required by the previous compatible application version.

18. Data Migration

Data migrations must be:

deterministic;

safely restartable where practical;

tested against a copy of production data;

documented;

auditable.

Do not perform large untracked SQL transformations directly against production.

19. Content Payload Migration

Content Payload migrations require special caution.

Theme changes must not automatically prune unknown data.

Approved principle:

Old Content Payload
        ↓
New Theme
        ↓
Unused old fields remain stored

If a migration intentionally transforms content, the migration documentation must define:

source fields
target fields
transform rules
validation rules
rollback/recovery strategy

20. Theme Upgrade

Theme source is developer-controlled.

Before deployment:

Theme Manifest validation
Template validation
Content Schema validation
Compatibility test
Preview test
Public rendering test

Theme updates must not prune existing Content Payload.

21. Theme Activation vs Theme Update

These are different operations.

Theme Update
=
developer changes source/configuration

Theme Activation
=
Admin changes the active presentation Theme

A newly deployed Theme may remain:

ENABLED

without becoming Active.

22. Dependency Update Policy

Before updating a dependency:

Identify reason
Review changelog
Review breaking changes
Run tests
Review security
Check shared-hosting compatibility

Do not update dependencies merely because a newer version exists.

23. Dependency Locking

Production dependencies are controlled through:

composer.json
composer.lock

The lock file is part of the release artifact.

24. Composer Security Audit

Before release and during periodic security hygiene:

composer audit

must be reviewed.

A reported vulnerability must be assessed for:

severity
affected package
affected feature
available patch
compatibility
mitigation

25. Quill Maintenance

After Quill upgrade test:

RICH_TEXT initialization
content loading
content editing
content serialization
auto-save
HTML sanitization
revision restore

The server-side sanitizer remains authoritative regardless of Quill version.

26. Tailwind Maintenance

Production CSS is a compiled artifact.

After a Tailwind upgrade:

rebuild CSS
review generated output
test Theme
test responsive layouts
test production asset loading

Production must not rely on Tailwind Play CDN.

27. Alpine.js Maintenance

After an Alpine.js upgrade test:

modal
dropdown
tabs
dirty state
Quill bridge
HTMX interaction

Do not introduce application-wide state architecture solely because of a frontend library upgrade.

28. HTMX Maintenance

After an HTMX upgrade test:

form submit
fragment replacement
HX-Redirect
HX-Trigger
CSRF headers
session expiry
auto-save
modal interactions

Verify the required session-expiry behavior:

HX-Redirect: /cp

29. Security Patch Procedure

For security vulnerabilities:

Identify affected component
      ↓
Assess impact
      ↓
Dedicated/security maintenance branch
      ↓
Create minimal patch
      ↓
Add regression/security test
      ↓
Run full suite
      ↓
Backup production
      ↓
Deploy
      ↓
Smoke test
      ↓
Document

Do not combine unrelated feature work with an urgent security patch.

30. Emergency Security Release

An emergency release may shorten the normal feature cadence, but must not bypass:

backup
testing
change tracking
rollback planning

A post-release record must capture:

affected component
risk
fix
release commit
migration if any
verification

31. Scheduled Security Hygiene

At least every three months, and additionally during security-sensitive releases:

Run:

composer audit

Review package/security advisories.

Verify PII encryption configuration.

Verify that encryption/HMAC secrets are not exposed in application logs.

Review recent authentication/security failures.

Restore a recent database backup to a local/test environment.

Verify the paired uploads backup where applicable.

Record the hygiene check result.

This is maintenance activity and does not require a new product feature.

32. Revision and Audit Preservation

Maintenance must never silently delete:

revisions
audit_logs

unless an explicit approved retention policy exists.

Database migrations must preserve historical records.

33. URL Preservation During Upgrade

Upgrades must preserve:

current public URLs
historical redirects
reserved URL rules
locale prefixes
canonical behavior

Any URL migration must document:

old path
new path
redirect behavior
SEO impact

34. Media Preservation During Upgrade

Upgrades must preserve:

MediaAsset records
storage keys
images
documents
media references
download tokens

Storage paths must not be rewritten without a tested migration plan.

35. Backup Before Risky Migration

Before a production migration changing:

database structure
content payload
media references
URL structure
Theme schema

create the paired:

database backup
+
uploads backup

according to 11-Deployment-Operations.md.

36. Upgrade Testing Environment

Upgrade testing should use:

local WSL2

and, where practical, a copy of production data.

Sensitive production data must be handled according to the PII/security policy.

37. Production-Like Upgrade Testing

Important upgrades should be tested against realistic data volumes containing:

Pages
Posts
Categories
Tags
Media
Documents
Revisions
Audit Logs
Scheduled Actions

The objective is to detect:

migration issues;

query regressions;

memory problems;

timeouts;

broken references.

38. Rollback Strategy

Before a major upgrade, ensure availability of:

known-good Git commit
+
database backup
+
uploads backup

Rollback may require:

Application rollback
+
Database rollback/restore
+
Uploads restore if required
+
Cache clear
+
Smoke test

39. Cache After Upgrade

After upgrading application/Theme code:

clear/invalidate application cache

especially when changing:

Theme
Content Schema
SEO rendering
routes
settings
publishing logic

40. Post-Upgrade Smoke Test

Minimum:

Homepage
Page
Post
Category
Navigation
Language switch
Image
Document download
/cp
Admin login
Editor login
Create Draft
Edit Post
Publish
Unpublish
Revision restore
Scheduler
Sitemap
robots.txt

41. Failed Upgrade Response

If an upgrade fails:

Stop further changes
   ↓
Capture error/logs
   ↓
Determine affected state
   ↓
Rollback or fix forward safely
   ↓
Run full test suite
   ↓
Verify production

Do not layer unrelated fixes over an uncertain state.

42. Maintenance Windows

Routine maintenance should be scheduled during low-traffic periods where practical.

V1 does not require a dedicated Admin Maintenance Mode feature.

43. Long-Term Code Health

Periodically review:

unused dependencies
unused code
deprecated APIs
duplicate services
unused configuration
unused Theme assets
orphaned Media
failed ScheduledActions
growing audit/revision tables

Cleanup must be evidence-based.

Avoid large refactors without a concrete maintenance reason.

44. Database Housekeeping

Periodically inspect:

audit_logs
revisions
scheduled_actions
url_redirects
media_assets

Do not delete historical data simply because tables grow.

Any retention/deletion policy requires explicit approval.

45. Orphan Media Review

Maintenance may identify:

MediaAsset record with missing physical file
physical file without MediaAsset record

The integrity result must first be reported.

Automatic deletion is prohibited by the standard integrity check.

46. Orphan Redirect Review

Review redirects that:

target missing resources;

form unnecessary chains;

are obsolete;

conflict with current routing.

Do not automatically delete redirect history because it may have SEO value.

47. Failed ScheduledAction Review

Review:

FAILED
SKIPPED
CANCELLED

actions periodically.

A FAILED action should expose a safe operational reason without storing secrets or sensitive payloads.

48. Dry-Run Integrity Command

SMITE CMS SHALL provide a non-destructive integrity check:

php spark cms:integrity-check

The command is audit/dry-run only.

It SHALL report at least:

physical files under writable/uploads/
without corresponding media_assets records

media_assets records whose physical files are missing

url_redirects whose targets resolve to 404

ScheduledActions with FAILED status
within the previous 30 days

The command SHALL NOT delete files, records, redirects, or ScheduledActions automatically.

Any cleanup remains a separate explicitly reviewed maintenance operation.

49. Integrity Check Output

The command should provide structured, human-readable output such as:

[INFO] Media assets checked: 842
[WARN] Orphan files: 3
[WARN] Missing media files: 1
[WARN] Broken redirects: 2
[WARN] Failed scheduled actions in last 30 days: 1

Exit status may be non-zero when integrity findings exist, but the command must not treat findings as automatic destructive failures.

The exact exit-code contract is defined in the CLI implementation.

50. Orphan Cleanup Policy

cms:integrity-check never performs automatic deletion.

If cleanup is required:

Integrity report
      ↓
Manual review
      ↓
Explicit approved cleanup
      ↓
Backup if destructive
      ↓
Cleanup operation
      ↓
Re-run integrity check

51. Audit and Revision Growth

V1 retains Audit and Revision history indefinitely.

Before introducing any cleanup:

retention requirement
business need
backup strategy
historical/audit requirement

must be reviewed.

52. Operational Upgrade Checklist

[ ] affected documents reviewed
[ ] requirement impact reviewed
[ ] ADR created if required
[ ] dedicated upgrade branch created where appropriate
[ ] dependencies reviewed
[ ] backup pair created
[ ] local upgrade tested
[ ] migrations tested
[ ] E_ALL enabled
[ ] deprecations reviewed
[ ] full test suite passed
[ ] composer audit reviewed
[ ] deployment completed
[ ] cache cleared
[ ] smoke test passed
[ ] integrity check reviewed
[ ] release recorded

53. Release Record

Each maintenance/upgrade release should record:

release identifier
date
previous commit/version
new commit/version
dependency changes
migration IDs
Theme changes
security changes
known limitations
verification result

Secrets do not belong in the release record.

54. Future Upgrade Policy

Future major infrastructure changes such as:

PHP 9+
MariaDB major release
CodeIgniter major release
new storage backend
Redis
queue
object storage

require a dedicated compatibility review.

They must not be introduced casually during routine maintenance.

55. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

03-Authorization-Security.md;

04-Content-Publishing.md;

05-Theme-Template-Architecture.md;

06-Media-Document-Management.md;

07-Localization-URL-SEO.md;

08-Technical-Architecture.md;

09-Implementation-Blueprint.md;

10-Testing-Quality-Strategy.md;

11-Deployment-Operations.md.

Maintenance and upgrade procedures must preserve all approved product, domain, security, deployment, testing, and data-integrity invariants.