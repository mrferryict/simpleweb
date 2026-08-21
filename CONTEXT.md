# SMITE CMS — Project Context

**Version:** 0.1.0  
**Status:** Draft for implementation  
**Project path:** `/var/www/html/simpleweb`

## 1. Project Identity

Project name: SMITE CMS.

SMITE CMS is a single-organization, single-website public Content Management System.

It is not a website builder.

## 2. Mandatory Project-Specific Decisions

### Authentication

- CodeIgniter Shield.
- Login identifier: username + password.
- Username is unique, normalized lowercase and trimmed.
- Email is stored as PII for password recovery/notifications and is unique.
- Admin count: exactly one.
- Editor count: one or more.
- Contributor count: zero or more.
- Admin cannot be deleted.
- User accounts are deactivated rather than permanently deleted.
- Control Panel entry: `/cp`.
- Authenticated Control Panel routes: `/admin/*`.

### Recovery

- Admin recovery secret environment variable: `skey`.
- It is a long secret, never stored in DB, never logged, never committed.
- Recovery is rate-limited and audited.
- Password reset invalidates existing sessions.

### Content

- Pages have two-level hierarchy.
- Posts support multiple Categories and Tags.
- Categories are flat.
- Tags are flat.
- Page and Post revisions are mandatory.
- Audit trail is mandatory and immutable.
- Normal deletion uses Trash/soft delete.
- Permanent deletion is an explicit Admin-only operation.
- Editor can publish directly.
- Contributor submits drafts for Editor review.

### Theme

- One Theme is ACTIVE at a time.
- Theme states: DRAFT, ENABLED, ACTIVE.
- Developer controls ENABLED state.
- Admin may activate only an ENABLED Theme.
- Every Theme must contain exactly one `custom-page` template.
- Theme files are developer-controlled.
- Admin cannot modify Theme source, header, navigation markup, footer markup, or arbitrary templates.
- Theme preview is Admin-only and does not change the active Theme.

### Content Schema

V1 Content Item types:

- TEXT
- TEXTAREA
- RICH_TEXT
- IMAGE
- YOUTUBE_URL
- URL
- DOCUMENT

Content fields are developer-defined; Admin cannot create arbitrary fields.

### Media

- Media Library supports Image and Document.
- Images are resized/optimized according to usage-specific profiles.
- Original processed images are discarded.
- V1 documents include PDF and selected common office formats.
- Only published/active documents are publicly downloadable.

### Localization

- Primary Language required.
- Secondary Language optional.
- Pages and Posts support localization.
- Missing secondary translations fall back to Primary Language.

### Scheduling

- Pages and Posts support scheduled publish and scheduled unpublish.
- Scheduler is a CI4 Spark command invoked by cron.
- Scheduler must be idempotent and catch up late jobs.

### Frontend

- Semantic HTML5.
- Tailwind CSS 4.
- Tailwind Play CDN for development where applicable.
- Production Tailwind build follows the global `.cursorrules`.
- Alpine.js for ephemeral UI state only.
- HTMX for asynchronous/server-driven interactions.
- No jQuery.
- No SPA framework.

### Infrastructure

- Development: Windows 11 Pro + WSL Ubuntu 24.04 LTS.
- Production target: shared hosting/cPanel/hPanel.
- MariaDB.
- Cron.
- SMTP may be used for password recovery.
- No Docker requirement.
- No Redis requirement.
- No queue requirement.

## 3. Explicit Global Rule Override

The global `.cursorrules` specifies email-based Shield login when the project uses the email-hash identity pattern.

SMITE CMS intentionally uses username + password for V1. This project-specific choice overrides the email-login instruction for this project.

Email PII storage/encryption rules from `.cursorrules` remain mandatory.

## 4. Source of Truth

Read these before implementation:

1. `.cursorrules`
2. `CONTEXT.md`
3. `docs/README.md`
4. Relevant `docs/*.md`
5. Relevant ADRs

Do not infer product behavior from code alone when documentation is explicit.

## 5. Scope Discipline

Cursor must not add features merely because they are technically easy, common, or convenient.

Out of scope for V1 includes:

- Membership.
- Ecommerce.
- Comments.
- Search.
- Proprietary analytics engine.
- Full page builder.
- Arbitrary custom fields.
- Queue infrastructure.
- Redis requirement.
- Docker requirement.
- Multi-tenant architecture.

Future extensibility is permitted only when it does not materially complicate V1.

## 6. Project Identity Safety Gate

Before making code changes, verify that the current workspace is `/var/www/html/simpleweb` or the repository identity clearly matches SMITE CMS.

Inspect reliable local evidence such as:

- Repository root.
- Git remote.
- `CONTEXT.md`.
- Composer metadata.
- Documentation.
- Existing project structure.

If project identity cannot be established with sufficient confidence, stop and ask for clarification.

## 7. Implementation Discipline

Never generate an entire application in one speculative pass.

Implement in small vertical slices.

For every change:

- inspect existing code;
- identify relevant requirement;
- preserve architecture;
- write tests for non-trivial business logic;
- run relevant tests;
- report changes and unresolved issues.

## 8. No Silent Architecture Changes

If implementation requires a decision not covered by this context or Source of Truth, stop when the decision materially affects:

- data model;
- security;
- authorization;
- public URL behavior;
- publishing;
- Theme contract;
- external dependencies;
- deployment architecture.

Record approved significant architectural decisions as ADRs.