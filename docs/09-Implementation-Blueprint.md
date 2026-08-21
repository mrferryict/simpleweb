DOC-09 — SMITE CMS Implementation Blueprint

Document Version: 0.2.0
Status: Approved — Implementation Blueprint
Last Updated: 21 August 2026

1. Purpose

This document defines the implementation sequence, phase boundaries, acceptance gates, Cursor execution discipline, and release-readiness rules for SMITE CMS V1.

It is an execution plan. It does not override approved product requirements or technical architecture.

2. Implementation Principles

IMP-001 — Small Vertical Slices

Implementation SHALL be performed in small, reviewable vertical slices.

Each slice should produce a coherent, testable result.

IMP-002 — One Cursor Prompt = One Sub-task

A single Cursor prompt SHALL perform only one vertical sub-task.

Examples:

1 migration + Model

1 Service method + focused PHPUnit tests

1 Controller + View fragment

1 validator feature + tests

A prompt SHALL NOT attempt to implement an entire Phase.

This keeps Cursor context focused and makes diffs easier to review.

IMP-003 — Requirement Traceability

Every implementation task should reference:

DOC-xx
REQ-xxx

Example:

Implement:
REQ-POST-002
REQ-POST-003
REQ-POST-005

IMP-004 — No Speculative Features

Cursor must not add features because they are:

common in CMS products;

available in the framework;

technically easy;

considered generally useful;

convenient for implementation.

IMP-005 — Documentation Before Architecture Changes

Changes affecting:

database;

security;

authorization;

URL behavior;

publishing;

Theme contract;

dependencies;

deployment

must be reviewed against approved documentation and, where necessary, recorded in an ADR before implementation.

3. Implementation Phases

SMITE CMS V1 is implemented through:

Phase 0  Project Foundation
Phase 1  Identity & Control Panel
Phase 2  Site Configuration & Navigation
Phase 3  Content Core
Phase 4  Revision & Editorial Workflow
Phase 5  Media & Documents
Phase 6  Theme System
Phase 7  Localization, URL & SEO
Phase 8  Scheduling & Caching
Phase 9  Security Hardening
Phase 10 Testing & Release Readiness

A phase is complete only when its acceptance gate passes.

4. Phase 0 — Project Foundation

Objective

Create a clean, runnable CodeIgniter 4 project foundation.

Scope

verify project identity;

initialize Git baseline;

configure CodeIgniter;

configure database connection;

establish environment structure;

establish migration strategy;

establish testing baseline;

establish Composer baseline;

place approved project documentation.

Requirements/Documents

CONTEXT.md
00-Project-Charter.md
08-Technical-Architecture.md

REQ-NFR-003
REQ-NFR-004
REQ-NFR-005
REQ-SCOPE-001

Acceptance Gate

Application boots
Database connects
Migrations run
Tests run
Composer install works
No unnecessary dependencies

5. Installation Bootstrap

A first-class idempotent Spark installation command SHALL be introduced during Phase 0/1:

php spark cms:install

The command is responsible for controlled initial system setup, including:

running migrations;

establishing required Shield groups/permissions;

creating required default system configuration;

validating required environment configuration;

creating the single initial Admin account through a safe bootstrap flow.

The command SHALL be idempotent.

It must detect an already-installed system and must not overwrite live content or credentials.

Admin bootstrap credentials may be supplied through controlled environment/configuration values or an interactive setup mechanism appropriate to the deployment environment.

Sensitive credentials must never be hard-coded into source code or committed to Git.

The exact production bootstrap mechanism belongs to the implementation/deployment documentation, but the cms:install command is the approved bootstrap entry point.

6. Phase 1 — Identity & Control Panel

Objective

Build the authentication and authorization boundary.

Scope

CodeIgniter Shield;

username/password authentication;

/cp;

/admin/*;

Admin;

Editor;

Contributor;

groups;

permissions;

session timeout;

HTMX session redirect;

CSRF;

brute-force throttling;

password reset;

Admin recovery skey;

initial Admin bootstrap.

Requirements

REQ-AUTH-001 → REQ-AUTH-012
REQ-UX-001
REQ-UX-003

Security Tests

At minimum:

login success
wrong password
inactive user
permission denial
direct forbidden URL
session expiry
HTMX session expiry
CSRF failure
brute-force throttling
Admin recovery
password reset

Acceptance Gate

Admin can:

/cp
  ↓
login
  ↓
/admin

Editor and Contributor receive only authorized functionality.

7. Phase 2 — Site Configuration & Navigation

Objective

Build website-level settings and navigation.

Scope

Site Settings;

localized/non-localized settings;

Logo;

Favicon;

Footer text;

SEO defaults;

language settings;

Primary Menu;

Footer Menu;

two-level Menu hierarchy.

Requirements

REQ-SITE-001 → REQ-SITE-004
REQ-MENU-001 → REQ-MENU-006
REQ-LOC-006

Acceptance Gate

Admin can:

change site title
change logo
change footer
configure languages
create menu
reorder menu
create child menu item

Public website displays current settings.

8. Phase 3 — Content Core

Objective

Build Page/Post core plus Content Schema validation.

Scope

Page

create;

edit;

hierarchy;

template selection;

slug;

basic status.

Post

create;

edit;

manual author;

Categories;

Tags;

Featured Image reference;

slug;

basic status.

Content

Content Schema loading;

scalar fields;

Repeatable Blocks;

Content Payload;

validation.

Baseline Theme Contract

Phase 3 SHALL initialize one developer-controlled Baseline Theme Manifest so the Content Schema engine has a real contract from the beginning.

Conceptually:

themes/default/theme.json

or the approved equivalent Manifest format.

The baseline Theme SHALL provide:

custom-page

and enough Content Schema definitions to exercise:

scalar Content Items;

required/optional fields;

at least one Repeatable Block where needed for validation testing.

Phase 3 does not implement Theme switching or the full Theme Preview system. It only establishes the baseline Theme contract required by the Content Core.

Requirements

REQ-PAGE-001 → REQ-PAGE-009
REQ-POST-001 → REQ-POST-009
REQ-CAT-001 → REQ-CAT-003
REQ-TAG-001 → REQ-TAG-003
REQ-CONT-001 → REQ-CONT-012

Acceptance Gate

Admin/Editor can:

create Page
create Post
edit Post
assign Category
assign Tag
fill Content Schema
save Content Payload

Contributor can create Draft Posts but cannot publish.

9. Phase 4 — Revision & Editorial Workflow

Objective

Add editorial state machine and revision safety.

Scope

Draft;

Pending Review;

Published;

Unpublished;

Archived;

Trash;

Restore;

Permanent Delete;

Revision snapshots;

Auto-save;

Published-content auto-save;

lock_version;

409 Conflict.

Requirements

REQ-PAGE-010 → REQ-PAGE-012
REQ-POST-010 → REQ-POST-013
REQ-AUDIT-*
REQ-REV-*
REQ-UX-004 → REQ-UX-006

Critical Acceptance

Published content:

Edit
 ↓
Auto-save
 ↓
LIVE CONTENT unchanged

Explicit Update
 ↓
LIVE CONTENT changed
 ↓
Revision created
 ↓
Cache invalidated

Concurrency:

lock_version = 5

Browser A → save → 6
Browser B → save 5 → 409 Conflict

10. Phase 5 — Media & Documents

Objective

Build the secure Media Library.

Scope

MediaAsset;

Image;

Document;

PHP GD;

Image Profiles;

resizing;

optimization;

Media metadata;

alt text hierarchy;

dependency checking;

Trash/Restore;

permanent delete;

protected document storage;

random document download token;

streaming downloads.

Requirements

REQ-MEDIA-001 → REQ-MEDIA-007
REQ-DOC-001 → REQ-DOC-005

Acceptance Gate

Image:

Upload
 ↓
Validate
 ↓
Resize
 ↓
Optimize
 ↓
Store
 ↓
Original removed

Document:

Upload
 ↓
Store outside public/
 ↓
Random public token
 ↓
Controlled download

Referenced Media cannot be permanently deleted.

11. Phase 6 — Theme System

Objective

Build the developer-controlled Theme presentation system.

Scope

Theme Manifest;

Theme discovery;

DRAFT;

ENABLED;

ACTIVE;

custom-page;

Template registry;

Content Schema registry;

Theme asset helper;

Theme activation;

compatibility checks;

Preview;

preview cache isolation.

Requirements

REQ-THEME-001 → REQ-THEME-009

Acceptance Gate

Developer deploys:

Theme A
Theme B

Admin sees only:

ENABLED

Admin can:

Preview
Activate

Admin cannot:

Modify Theme source
Enable Theme
Create Template

Theme switching does not prune stored Content Payload.

12. Phase 7 — Localization, URL & SEO

Objective

Build the public URL namespace and localization architecture.

Scope

Primary Language;

Secondary Language;

Translation entities;

fallback;

Primary root + Secondary /en/...;

global URL uniqueness;

reserved routes;

slug history;

301 redirects;

canonical;

hreflang;

sitemap;

robots.txt;

preview noindex.

Requirements

REQ-LOC-001 → REQ-LOC-006
REQ-SEO-001 → REQ-SEO-007

Acceptance Gate

Primary:

/about

Secondary:

/en/about-us

Missing English translation:

/en/about-us
  ↓
Primary fallback content
  ↓
canonical = /about

No false hreflang="en" is emitted.

13. Phase 8 — Scheduling & Caching

Objective

Build deterministic publication scheduling and application caching.

Scope

ScheduledAction;

Spark command;

cron;

publish;

unpublish;

catch-up;

idempotency;

SKIPPED;

CANCELLED;

FAILED;

File Cache;

targeted invalidation.

Requirements

REQ-SCHED-001 → REQ-SCHED-006
REQ-CACHE-001 → REQ-CACHE-004

Acceptance Gate

Example:

Post scheduled for 08:00
Cron unavailable
Cron runs at 08:15
  ↓
Post publishes
  ↓
Audit event
  ↓
Cache invalidated

Duplicate execution must not publish twice.

14. Phase 9 — Security Hardening

Objective

Perform security review across the system.

Scope

input validation;

output encoding;

Rich Text sanitization;

upload validation;

document access;

PII encryption;

lookup hash;

key separation;

secrets review;

authorization review;

CSRF review;

session review;

rate limiting;

security headers;

HTTPS assumptions.

Requirements

03-Authorization-Security.md
REQ-NFR-001
REQ-NFR-008

Acceptance Gate

No known high-severity security issue remains open.

15. Phase 10 — Testing & Release Readiness

Objective

Determine whether V1 is ready for production.

Scope

full test suite;

migration verification;

seed/bootstrap verification;

security regression;

browser/HTMX behavior;

responsive checks;

performance checks;

shared-hosting simulation;

deployment dry run;

backup/restore test.

Acceptance Gate

Production checklist passes with no blocking findings.

16. Per-Task Implementation Order

For each sub-task, preferred order is:

1. Requirement verification
2. Domain/architecture verification
3. Migration, if required
4. Model
5. Entity
6. Service
7. Authorization
8. Controller
9. View
10. HTMX/Alpine behavior
11. Tests
12. Documentation, if behavior changed

The exact order may vary when implementation requires a different sequence, but security and tests must not be postponed indefinitely.

17. Migration Discipline

Each migration must:

have one clear purpose;

be reversible where practical;

use safe naming;

define required indexes;

define required foreign keys;

avoid destructive changes unless explicitly approved;

pass on a fresh database;

pass against realistic existing data where applicable.

Never edit an already-applied migration to change production history.

Create a new migration.

18. Content Schema Implementation Order

Baseline Theme Manifest
   ↓
Theme/Template Definition
   ↓
Content Schema
   ↓
ContentSchemaValidator
   ↓
Control Panel renderer
   ↓
Persistence
   ↓
Theme Template rendering

This keeps validation logic centralized.

19. Theme Implementation Order

Recommended:

1. Theme Manifest reader
2. Manifest validator
3. Template registry
4. Content Schema registry
5. Theme asset helper
6. Theme loader
7. custom-page
8. Preview
9. Theme activation
10. Compatibility checks

The baseline Theme Manifest from Phase 3 is reused rather than rebuilt from scratch.

20. Media Implementation Order

Recommended:

1. MediaAsset model
2. Storage abstraction
3. Upload validation
4. GD ImageProcessor
5. Image Profiles
6. Media Library
7. Media references
8. Dependency checker
9. Document storage
10. Download endpoint
11. Trash/Restore
12. Permanent deletion

21. Publishing Implementation Order

Recommended:

1. State definitions
2. State transition Service
3. Revision
4. Audit
5. Explicit publish/update
6. Unpublish
7. Archive
8. Trash
9. Restore
10. Permanent delete
11. Auto-save
12. Optimistic concurrency
13. Scheduler

22. Testing Pyramid

Recommended distribution:

Many
  ↓
Unit Tests
  ↓
Service/Integration Tests
  ↓
HTTP/Feature Tests
  ↓
Browser Tests
Few

Browser automation should focus on workflows where browser behavior materially matters:

HTMX
session expiry
auto-save
conflict handling
Theme preview
upload/download UX

23. Definition of Done

A sub-task is complete only when:

Requirement implemented
        +
Authorization implemented
        +
Validation implemented
        +
Error handling implemented
        +
Relevant tests pass
        +
No obvious regression
        +
Documentation updated if behavior changed

"Works in browser" alone is not sufficient.

24. Phase Gate Enforcement

A Phase cannot be marked complete until its acceptance gate passes.

Before creating a commit that completes a Phase:

the entire local test suite SHALL be executed;

the suite SHALL be green;

PHP/CodeIgniter deprecation warnings relevant to the implementation SHALL be resolved;

required static/quality checks SHALL pass;

no known blocking regression SHALL remain.

Recommended command:

composer test

or the repository's approved equivalent, such as:

./vendor/bin/phpunit

A single failing test means:

Phase Gate = FAILED

A relevant PHP/CI4 deprecation warning that indicates an incompatible or unsafe implementation means:

Phase Gate = FAILED

The Phase must not be committed/pushed as complete until the gate is resolved.

25. Cursor Prompt Pattern

Every Cursor prompt must begin with the Project Identity Safety Gate.

Preferred structure:

PROJECT IDENTITY SAFETY GATE

Verify that the current workspace is the intended SMITE CMS project.
Inspect:
- repository root
- git remote
- CONTEXT.md
- .cursorrules
- composer.json
- docs/

If identity cannot be established with sufficient confidence, STOP.

TASK

Implement one sub-task only.

Reference:
REQ-POST-002
REQ-POST-003

READ BEFORE CODING

- CONTEXT.md
- 01-Product-Requirements.md
- 02-Domain-Model.md
- 03-Authorization-Security.md
- 04-Content-Publishing.md
- 08-Technical-Architecture.md

CONSTRAINTS

- Do not add unrequested features.
- Preserve existing architecture.
- Keep Controllers thin.
- Put business logic in Services.
- Add focused tests.
- Do not modify unrelated code.

ACCEPTANCE

...

A Cursor prompt must not ask it to implement an entire Phase.

26. Cursor Change Scope

Cursor should modify the minimum number of files necessary for the assigned sub-task.

Avoid unrelated:

refactoring
cleanup
dependency replacement
architecture rewrite

unless the current sub-task explicitly requires it and the change is covered by approved documentation.

27. Commit Strategy

Commits should represent coherent, reviewable functional changes.

Examples:

feat(auth): add Shield username authentication
feat(content): add post draft workflow
feat(media): add GD image processing
feat(theme): add manifest loader
feat(scheduler): add scheduled publishing
fix(auth): handle HTMX session expiry
fix(content): reject stale lock version

Avoid giant commits containing unrelated phases or features.

28. Phase Completion Sequence

A completed Phase follows:

Implementation slices
      ↓
Focused tests
      ↓
Integration tests
      ↓
Full test suite
      ↓
Deprecation/warning review
      ↓
Acceptance gate review
      ↓
Git commit
      ↓
Optional push
      ↓
Next Phase

The test gate occurs before the Phase completion commit.

29. Rollback Principle

Every phase must remain recoverable through Git.

If a regression occurs:

Identify regression
    ↓
Fix forward where safe
or
Rollback coherent change
    ↓
Run full test suite
    ↓
Re-evaluate phase gate

Avoid undocumented manual production fixes.

30. V1 Scope Guard

The following remain out of scope unless explicitly approved:

Membership
Ecommerce
Search engine
Comments
Redis
Queue workers
Docker
Microservices
Generic Page Builder
Arbitrary Custom Fields
Multi-tenant support
Third language
Advanced analytics

Cursor must reject scope creep.

31. Final V1 Acceptance

V1 is implementation-complete when:

Authentication works
        +
Authorization works
        +
Pages work
        +
Posts work
        +
Editorial workflow works
        +
Revision works
        +
Media works
        +
Documents work
        +
Themes work
        +
Localization works
        +
URLs/SEO work
        +
Scheduling works
        +
Caching works
        +
Security tests pass
        +
Deployment dry run passes

32. Traceability

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

global .cursorrules;

CONTEXT.md.

This document controls implementation sequence and quality gates but does not override approved requirements, security rules, or technical architecture.