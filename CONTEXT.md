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
- V1 Revision + Audit foundation contract (ADR-019): `revisions` + `audit_logs` DDL; immutable full-snapshot JSON (`schema_version` 1) for Pages and Posts; `is_autosave` isolation; `pages.lock_version` / `posts.lock_version` OCC (HTTP 409); revision restore reuses `page.restore` / `post.restore` (distinct from trash restore in Services); indefinite retention (DOC-12). Behavioral rules remain ADR-005.
- V1 Page/Post Archive lifecycle (ADR-020): `PUBLISHED → ARCHIVED` via `page.archive` / `post.archive`; leave archive by republish (`ARCHIVED → PUBLISHED`) through existing `publish()` and `page.publish` / `post.publish`; inbound audit `PAGE_ARCHIVED` / `POST_ARCHIVED`; republish audit `*_PUBLISHED`. Not public. Not permanently deletable. No `*.unarchive`, no `*_UNARCHIVED`, no previous-status column.
- Normal deletion uses Trash/soft delete.
- Permanent deletion is an explicit Admin-only operation.
- Editor can publish directly.
- Contributor submits drafts for Editor review.

### Theme

- One Theme is ACTIVE at a time.
- Theme states: DRAFT, ENABLED, ACTIVE.
- Developer controls ENABLED state.
- Admin may activate only an ENABLED Theme.
- Every Theme must contain exactly one `custom-page` template (Pages).
- Every Theme must contain exactly one `custom-post` template (Posts) — ADR-015.
- V1 Posts do not store `template_key`; schema resolves as ACTIVE Theme → `custom-post`.
- Baseline `custom-post` payload field (ADR-015): `body` (`RICH_TEXT`, optional at draft). Exact schema lives in ADR-015 — not duplicated here.
- V1 public Post URLs (ADR-016): Primary `/news/{slug}`; Secondary `/en/news/{slug}`. Prefix `news` is fixed/developer-owned — not Admin-configurable.
- V1 public `custom-post` Theme view path (ADR-016): `app/Views/themes/{activeThemeId}/templates/custom-post.php`.
- V1 public Page URLs (ADR-017): Primary `/{slug}`; Secondary `/en/{slug}`. Single-segment Strategy B root paths — not Admin-configurable; hierarchy (`parent_id`) does not nest URL segments in V1.
- V1 public Page Theme view path (ADR-017): `app/Views/themes/{activeThemeId}/templates/{templateKey}.php` (typically `custom-page` from `pages.template_key`).
- Theme files are developer-controlled.
- Admin cannot modify Theme source, header, navigation markup, footer markup, or arbitrary templates.
- Theme preview is Admin-only and does not change the active Theme.
- V1 Theme discovery & lifecycle persistence (ADR-022, binds ADR-002): Themes are filesystem packages at `app/Views/themes/{themeId}/` (`ThemeManifest.php`; directory name equals manifest `id`). No `themes` table. DRAFT = discovered but not developer-ENABLED. ENABLED = `Config\Theme::$enabledThemeIds` (deploy only; Admin cannot enable). ACTIVE = exactly one, persisted in Settings `Theme.activeThemeId`; `Config\Theme::$activeThemeId` is bootstrap/fallback only. Multiple ENABLED allowed. Activation: validate → persist ACTIVE in a DB transaction → audit `THEME_ACTIVATED` in-tx → commit → public cache invalidation. Previous ACTIVE is demoted (no longer ACTIVE) but stays ENABLED if still listed. No independent deactivate. Preview eligibility: ENABLED only. Reuse `theme.preview` / `theme.activate`; no new permission.
- V1 Theme Preview contract (ADR-023, binds ADR-002/ADR-009/ADR-022): Page-only Preview of actual stored Page content through a candidate ENABLED Theme; Admin + `theme.preview`; GET `/admin/preview/theme/{themeId}/page/{pageId}`; optional `?locale=id|en`; cache bypass; security headers; no Settings/ACTIVE mutation; no Preview audit event; Post Preview deferred.

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
- V1 Media foundation contract (ADR-018): `media_assets` schema; column `download_token` (supersedes DOC `download_hash` naming); IMAGE allowlist jpeg/png/webp/gif (SVG rejected); DOCUMENT MIME map for PDF/Office formats; app upload caps 5 MiB IMAGE / 15 MiB DOCUMENT; image root `public/uploads/images/`; public image URL `/uploads/images/{storage_key}`; document download `GET /download/document/{download_token}`; baseline profile `cms_default` when Theme `media_profiles` is empty.

### Localization

- Primary Language required.
- Secondary Language optional.
- Pages and Posts support localization.
- Missing secondary translations fall back to Primary Language.
- V1 URL Strategy B (ADR-003 / ADR-016 / ADR-017): Primary has no locale prefix; Secondary uses `/en/...`. `/id/...` is not a Primary content URL shape.
- V1 Localization, URL & SEO contract (ADR-024, binds ADR-003/ADR-016/ADR-017/ADR-019): reuse `page_translations` / `post_translations`; Settings `Site.primaryLocale` + optional `Site.secondaryLocale`; global URL namespace + `url_redirects` 301; SEO columns on translation rows; `LocaleFilter` (locale only); `GET /sitemap.xml` + `GET /robots.txt`; canonical/hreflang per ADR-003; PUBLISHED-only public visibility preserved; no new permissions or translation audit events.
- V1 public Page/Post File Cache population (ADR-025, binds ADR-009/ADR-016/ADR-017/ADR-022/ADR-023/ADR-024): FileHandler only; population keys `content.page|post.{themeId}.{locale}.{slug}`; Phase 4 `page.public.{id}` / `post.public.{id}` retained as reverse-index for invalidation; cache package = public view DTO + resolved SEO DTO; PUBLISHED-only; no Preview/negative/redirect caching; post-commit invalidation remains authoritative; TTL safety-net 3600s; locale Settings changes must invalidate public presentation caches. Phase 4 invalidation foundation remains; population is Task 8.1B.
- V1 Security Hardening contract (ADR-026, Phase 9 / Task 9.1A): verify existing foundations (Shield, AuthGroups, CSRF+HTMX sync, SessionAuthFilter HX-Redirect, RichTextSanitizer, Media MIME/size/SVG reject, document tokens, Preview headers, PUBLISHED-only public, cache purity, scheduler CLI); harden ADR-008 PII wiring if missing, CI4 Throttler on login/reset/recovery (numeric capacities UNDEFINED—Config operational only), enable SecureHeaders baseline (full CSP deferred), add DOC-03 §25 auth/security audit events if missing; no new permissions/public endpoints; preserve ADR-019–025. Implementation is Task 9.1B.

### Scheduling

- Pages and Posts support scheduled publish and scheduled unpublish.
- Scheduler is a CI4 Spark command invoked by cron.
- Scheduler must be idempotent and catch up late jobs.
- V1 scheduler contract (ADR-021, binds ADR-006): `scheduled_actions` for `PUBLISH`/`UNPUBLISH` only; Site timezone for UI, UTC storage; no schedule-time `lock_version`; cron skips TRASH/ARCHIVED/PENDING_REVIEW; reuse `page.publish`/`page.unpublish`/`post.publish`/`post.unpublish`; no new permission; no queue; Spark `cms:scheduled-content` only. Interactive Archive remains ADR-020.

### Frontend

- Semantic HTML5.
- Tailwind CSS 4.
- Tailwind Play CDN for development where applicable.
- Production Tailwind build follows the global `.cursorrules`.
- Alpine.js for ephemeral UI state only.
- HTMX for asynchronous/server-driven interactions.
- Quill 2.x for Control Panel RICH_TEXT editing UI only (not a security boundary).
- Control Panel Alpine.js / Quill JS+CSS: pinned vendored static assets under `public/assets/admin/` (ADR-010 / `ASSETS.md`); no production CDN; no production Node.js.
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