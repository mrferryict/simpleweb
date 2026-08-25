# ADR-019 — Revision, Audit, OCC & Autosave Foundation

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 4 / Task 4.9A

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not create migrations, Services, Controllers, routes, views, or JavaScript.

It closes the Task 4.9 blocker **REVISION/AUDIT SCHEMA UNDEFINED** by supplying the exact persistence, snapshot, OCC, autosave, restore, permission, and retention contracts that DOC-02 §33 deferred and that ADR-005 left incomplete at DDL/serialization level.

It **reaffirms** ADR-005 behavioral rules. It does **not** supersede ADR-005. Where this ADR adds DDL or serialization detail, that detail is a **NEW architectural decision** labeled as such.

Conceptual separation (EXPLICIT — DOC-02 §25 / DOC-04 §12; preserved):

```text
Editorial Status  ≠  Revision History  ≠  Audit Log
```

These MUST remain separate tables/concepts. Do not merge them.

## 2. Context

Task 4.9 verified:

| Gap | Problem |
| --- | --- |
| `revisions` DDL | DOC-08 names the table; no columns |
| Snapshot JSON | DOC-02 §33 defers exact serialization |
| `audit_logs` DDL | DOC-08 names the table; no columns |
| `lock_version` | ADR-005 EXPLICIT; not on current `pages`/`posts` |
| Permission naming | `page.restore` / `post.restore` labels mix trash + revision |
| Polymorphic vs separate tables | DOC-08 single `revisions` vs DOC-02 anti-polymorphic preference |

Existing editorial **status** workflow (DRAFT / PENDING_REVIEW / PUBLISHED / UNPUBLISHED / ARCHIVED / TRASH) remains authoritative as implemented in Tasks 4.1–4.3. This ADR does not change those transitions.

## 3. Decision Summary

| Topic | Classification | Accepted V1 result |
| --- | --- | --- |
| Scope | EXPLICIT (REQ-PAGE-010, REQ-POST-010) | Pages **and** Posts |
| Table name | EXPLICIT (DOC-08) | `revisions`, `audit_logs` |
| Linking model | **NEW** | Single `revisions` table; `resource_type` + `resource_id` (no polymorphic FK) |
| Snapshot | **NEW** (fills DOC-02 §33) | UTF-8 JSON object; schema below |
| Media in snapshot | EXPLICIT (ADR-018) | Integer IDs only |
| OCC column | EXPLICIT (ADR-005) | `pages.lock_version`, `posts.lock_version` |
| Autosave flag | EXPLICIT (ADR-005) | `is_autosave` TINYINT(1) |
| Revision permissions | EXPLICIT + reconcile | Reuse `page.restore` / `post.restore`; no new permissions |
| Retention | EXPLICIT (DOC-12 §51) | Indefinite for revisions + audit |

## 4. Resource Scope

**Accepted:** Revision persistence applies to **Pages and Posts** only.

- EXPLICIT: REQ-PAGE-010 / REQ-REV-001 (Pages); REQ-POST-010 / REQ-REV-002 (Posts); CONTEXT.md (“Page and Post revisions are mandatory”).
- Do not add Media, Menu, Category, Tag, User, or Setting revisions in V1.

## 5. Separation of Concerns

| Concern | Owner | Persistence |
| --- | --- | --- |
| Editorial status | `pages.status` / `posts.status` (+ `deleted_at`) | Existing columns |
| Revision history | What content looked like | `revisions` |
| Audit trail | What action happened, by whom, when | `audit_logs` |
| Concurrency token | Stale-write detection | `lock_version` on `pages` / `posts` |

## 6. `lock_version` (OCC)

### 6.1 Column (EXPLICIT ADR-005 + NEW DDL binding)

| Table | Column | Type | Null | Default |
| --- | --- | --- | --- | --- |
| `pages` | `lock_version` | `INT UNSIGNED` | NOT NULL | `1` |
| `posts` | `lock_version` | `INT UNSIGNED` | NOT NULL | `1` |

Initial value for existing rows after migration: **1**.

### 6.2 Increment rules

**Increments** `lock_version` by 1 on every **successful permanent mutation** of the live resource (ADR-005), including:

**Post:** `update`, `publish`, `unpublish`, `submitForReview`, `reviewAndPublish`, `returnForRevision`, trash, restore-from-trash, revision-restore.  
**Page:** `update`, `publish`, `unpublish`, trash, restore-from-trash, revision-restore.

**Does NOT increment** on:

- autosave (ADR-005 EXPLICIT)
- read-only operations
- failed validation / failed authorization / failed OCC check

**NEW clarification:** Status-only transitions (submit/review/return/publish/unpublish/trash/restore-from-trash) **do** increment `lock_version` because they permanently mutate the live row.

### 6.3 Client contract

Every mutating Control Panel request that changes live Page/Post state (including autosave) MUST submit the `lock_version` currently held by the browser (ADR-005).

Conditional update pattern (EXPLICIT ADR-005 / DOC-04 / DOC-08):

```sql
UPDATE {pages|posts}
SET ..., lock_version = lock_version + 1
WHERE id = :id AND lock_version = :submitted_version;
```

If affected rows = 0 → **HTTP 409 Conflict**.

**NEW response contract (docs only):**

- Status: `409`
- Body: may be HTML fragment (HTMX) or JSON; MUST include at least the **current** `lock_version`
- MUST NOT silently discard the client’s unsaved editor content
- Exact HTMX/Alpine UX is an implementation detail deferred to Task 4.9B+

### 6.4 Interaction with existing Services

Future implementation MUST add OCC checks to the listed PostService / PageService mutations. Until then, current behavior without `lock_version` remains as shipped; Task 4.9B introduces the columns and wiring. This ADR does not authorize silent behavior change without that implementation task.

## 7. `revisions` Table

### 7.1 Linking strategy (**NEW**)

DOC-08 names a single table `revisions`. DOC-02 prefers avoiding polymorphic mega-tables.  

**Accepted NEW decision:** one table `revisions` with:

- `resource_type` — `VARCHAR(16)` NOT NULL; allowed values exactly: `page`, `post`
- `resource_id` — `INT UNSIGNED` NOT NULL (Page id or Post id)

No database FOREIGN KEY from `resource_id` to `pages`/`posts` (polymorphic FK is unsupported). Integrity is application-enforced.

Rationale: matches DOC-08/ADR-005 single-table naming; avoids inventing `page_revisions` / `post_revisions` table names absent from sources.

### 7.2 Columns (**NEW** DDL binding ADR-005)

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | `INT UNSIGNED` AI PK | NO | — | Primary key |
| `resource_type` | `VARCHAR(16)` | NO | — | `page` \| `post` |
| `resource_id` | `INT UNSIGNED` | NO | — | Target entity id |
| `revision_number` | `INT UNSIGNED` | NO | — | Monotonic per resource |
| `is_autosave` | `TINYINT(1)` | NO | `0` | ADR-005 |
| `snapshot` | `LONGTEXT` | NO | — | UTF-8 JSON (see §8) |
| `created_by` | `INT UNSIGNED` | YES | `NULL` | Actor user id; `NULL` = system |
| `created_at` | `DATETIME` | NO | — | Creation time |

**JSON storage type (**NEW**):** `LONGTEXT` storing a JSON **string**, consistent with existing `content_payload` using `TEXT` (not native JSON column) for MariaDB shared-hosting predictability. Snapshot uses `LONGTEXT` because bilingual payloads can exceed typical `TEXT` comfort for full snapshots.

### 7.3 Indexes & uniqueness (**NEW**)

| Name / constraint | Definition |
| --- | --- |
| PRIMARY | `id` |
| UNIQUE `revisions_resource_rev_unique` | (`resource_type`, `resource_id`, `revision_number`) |
| INDEX `revisions_resource_idx` | (`resource_type`, `resource_id`, `created_at`) |
| INDEX `revisions_resource_autosave_idx` | (`resource_type`, `resource_id`, `is_autosave`, `created_at`) |
| INDEX `revisions_created_by_idx` | (`created_by`) |

Optional soft FK: `created_by` → `users.id` ON DELETE SET NULL (allowed if migration environment supports it; application must tolerate orphaned NULL).

### 7.4 Revision numbering (**NEW**)

| Rule | Value |
| --- | --- |
| Start | `1` for the first revision of each (`resource_type`, `resource_id`) |
| Monotonic | Yes, per resource |
| Autosave consumes number | **Yes** — same sequence as editorial revisions |
| Shared sequence | Yes (`is_autosave` distinguishes type) |
| Gaps | Allowed only if a row is physically removed (V1 does not delete revisions — see retention) |
| Immutable | `revision_number` and historical rows are never updated |

`lock_version` ≠ `revision_number`. Do not conflate them.

### 7.5 When rows are created

| Trigger | `is_autosave` | Audit event |
| --- | --- | --- |
| Explicit Save Draft / Update | `0` | Yes (`*_UPDATED` / create events as applicable) |
| Publish / Unpublish / Archive / Submit / Review / Return / Trash / Restore-from-trash | `0` | Yes (matching event) |
| Revision restore | `0` (new row after apply) | Yes `REVISION_RESTORED` |
| Autosave | `1` | **No** (ADR-005) |

Editorial history UI queries MUST use `WHERE is_autosave = 0` (ADR-005 EXPLICIT).

### 7.6 Trash / permanent delete

- Soft-trashed resources **may** retain and continue to accumulate revisions (including a revision capturing trash/restore-from-trash).
- Autosave on `TRASH` resources: **forbidden** (**NEW**).
- Permanent delete of a Page/Post: **does not** delete `revisions` or `audit_logs` rows (DOC-12 indefinite retention). History may reference a no-longer-existing `resource_id`.

## 8. Snapshot Serialization (**NEW** — fills DOC-02 §33)

### 8.1 Encoding

- UTF-8 JSON object
- `json_encode` / `json_decode` only — **never** PHP `serialize()`
- `content_payload` inside snapshot is the persist-sanitized object (RichTextSanitizer already applied for RICH_TEXT) — same authority as live storage (ADR-014)
- Unknown legacy keys inside `content_payload` are **preserved** (ADR-002 non-destructive legacy rule applies to snapshot copies of payload)

### 8.2 Envelope

Every snapshot MUST include:

```json
{
  "schema_version": 1,
  "resource_type": "page" | "post",
  "resource_id": <int>,
  "captured_at": "<ISO-8601 datetime string>",
  "status": "<editorial status string>",
  "translations": { ... }
}
```

`schema_version` is **NEW** for forward-compatible snapshot evolution.

### 8.3 Post snapshot (`resource_type` = `post`)

Complete editable + publication-related state (DOC-04 “may include”; **NEW** exact shape using **only existing** Post fields):

```json
{
  "schema_version": 1,
  "resource_type": "post",
  "resource_id": 12,
  "captured_at": "2026-08-25T09:00:00+07:00",
  "status": "DRAFT",
  "manual_author": "Jane",
  "featured_image_id": 44,
  "category_ids": [1, 3],
  "tag_ids": [2],
  "translations": {
    "id": {
      "title": "...",
      "slug": "...",
      "content_payload": { }
    },
    "en": {
      "title": "...",
      "slug": "...",
      "content_payload": { }
    }
  }
}
```

Rules:

- `featured_image_id`: integer or `null` only
- `category_ids` / `tag_ids`: arrays of unsigned ints; order = attachment order as stored at capture time
- `translations`: object keyed by locale (`id`, `en` only for V1). Omit locale key if that translation row does not exist
- Do **not** invent excerpt, SEO columns, or unpublished schema fields

### 8.4 Page snapshot (`resource_type` = `page`)

```json
{
  "schema_version": 1,
  "resource_type": "page",
  "resource_id": 5,
  "captured_at": "2026-08-25T09:00:00+07:00",
  "status": "PUBLISHED",
  "template_key": "custom-page",
  "parent_id": null,
  "translations": {
    "id": {
      "title": "...",
      "slug": "...",
      "content_payload": { }
    }
  }
}
```

Rules:

- `parent_id`: integer or `null`
- `template_key`: string as stored on `pages`
- Same translation / payload rules as Post
- Do **not** invent SEO fields

### 8.5 Media references in snapshots

**Accepted:** store **only** integer media identifiers:

- `featured_image_id`
- IMAGE/DOCUMENT values inside `content_payload` as already persisted (`media_id` ints)

Do **not** store `storage_key`, filesystem paths, or generated URLs in snapshots (ADR-018).

On restore, live MediaService / ContentSchemaValidator rules apply to the restored payload (**NEW**): invalid/TRASH media references fail validation the same as a normal update — do not invent silent media rewriting.

## 9. Restore Semantics

### 9.1 Permission reconcile (**NEW** clarification; no new permission)

Existing Shield permissions (AuthGroups):

- `page.restore` — description currently “revisions / trash”
- `post.restore` — description currently “revisions / trash”

**Accepted:**

| Operation | Permission |
| --- | --- |
| Restore Page from **Trash** | `page.restore` |
| Restore Page from **Revision** | `page.restore` |
| Restore Post from **Trash** | `post.restore` |
| Restore Post from **Revision** | `post.restore` |

Service methods MUST remain distinct (`restoreFromTrash` vs `restoreRevision`) so callers never confuse the two. **No new Shield permission names.**

Editor: has both restore permissions (DOC-03 §4.2 “restore permitted revisions”).  
Admin: wildcards.  
Contributor: **no** `post.restore` / `page.restore` — cannot restore revisions or trash.

### 9.2 Ownership

- Creating revisions is a side effect of mutations already authorized by `page.edit` / `post.edit_*` / publish/review permissions (AUTHZ-001/002 unchanged).
- Listing editorial revisions (`is_autosave = 0`): users who may **edit** that resource (Editor `post.edit_any` / Page edit; Contributor **NEW:** may **not** list revision history in V1 Control Panel — only Editor/Admin). Autosave recovery UX for Contributor may use the latest autosave without exposing full history (implementation detail).
- Restoring revisions: `*.restore` as above; Editors/Admins may restore another user’s content (AUTHZ-002/003).

### 9.3 Apply rules (**NEW** + ADR-005)

1. Load immutable historical snapshot (`is_autosave` may be `0` or `1` — autosave restore **allowed** for users who can edit; restore permission for autosave: same as edit for apply-to-working-copy? **NEW:** restoring **any** revision including autosave into live state requires `page.restore` / `post.restore` (Editor/Admin only). Contributor recovers via continuing to edit + autosave persistence without restore API.
2. Validate OCC `lock_version`.
3. Re-run RichTextSanitizer + ContentSchemaValidator (+ Media resolver) on restored payload before persist.
4. Apply relational fields from snapshot (Post categories/tags/manual_author/featured_image_id; Page template_key/parent_id) and translation rows.
5. **Status is NOT changed by revision restore** (**NEW**). Publication remains via existing publish/unpublish/review transitions. Snapshot `status` is historical context only.
6. Increment `lock_version`.
7. Insert new revision row `is_autosave = 0` capturing post-restore state.
8. Write audit `REVISION_RESTORED` with `revision_id` pointing to the **historical** revision that was restored (and/or metadata noting both source and new revision ids).
9. Invalidate public caches as applicable (ADR-009 when wired).

Never mutate historical revision rows (REQ-REV-006).

## 10. Autosave

Reaffirm ADR-005:

| Topic | Accepted |
| --- | --- |
| Creates `revisions` row | Yes, `is_autosave = 1` |
| Visible in revision history UI | No (`is_autosave = 0` filter) |
| Publicly routable | Never |
| Changes live PUBLISHED translations | Never (ADR-005) |
| Increments `lock_version` | Never |
| Changes editorial status | Never |
| Creates audit event | Never |
| Debounce / safety interval | 60s debounce; force ≤ every 5 minutes while dirty (ADR-005) — UI timing is implementation |
| Endpoint | Dedicated mutating route (example DOC-08: `POST /admin/posts/{id}/autosave`; Pages analogous) — exact paths deferred to implementation |
| Permission | Same as edit on that resource; **no new permission** |
| Who | Contributor (own Posts), Editor, Admin — per existing edit rights |

## 11. `audit_logs` Table

### 11.1 Columns (**NEW**)

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | `INT UNSIGNED` AI PK | NO | — | |
| `actor_id` | `INT UNSIGNED` | YES | `NULL` | User id; NULL = system |
| `event` | `VARCHAR(64)` | NO | — | Vocabulary §11.3 |
| `resource_type` | `VARCHAR(32)` | YES | `NULL` | e.g. `page`, `post`, `user`, `theme`, `setting` |
| `resource_id` | `INT UNSIGNED` | YES | `NULL` | |
| `revision_id` | `INT UNSIGNED` | YES | `NULL` | Optional link to `revisions.id` |
| `metadata` | `TEXT` | YES | `NULL` | UTF-8 JSON string; no decrypted PII |
| `created_at` | `DATETIME` | NO | — | |

### 11.2 Indexes (**NEW**)

- PRIMARY `id`
- INDEX (`event`, `created_at`)
- INDEX (`resource_type`, `resource_id`, `created_at`)
- INDEX (`actor_id`, `created_at`)
- INDEX (`revision_id`)

Optional: `actor_id` → `users.id` ON DELETE SET NULL; `revision_id` → `revisions.id` ON DELETE SET NULL.

### 11.3 Mutability & retention

- **Append-only** in V1 (CONTEXT.md / DOC-03): no update, no delete APIs
- Retained **indefinitely** (DOC-12 §51)
- Permanent content delete does **not** purge audit rows

### 11.4 Event vocabulary (V1 minimum)

Include only events required by DOC-02 §26, DOC-04, ADR-005, and existing workflows. **Stable string constants:**

**Post**

| Event | Notes |
| --- | --- |
| `POST_CREATED` | |
| `POST_UPDATED` | Explicit save/update only — not autosave |
| `POST_SUBMITTED_FOR_REVIEW` | |
| `POST_REVIEWED_PUBLISHED` | reviewAndPublish |
| `POST_RETURNED_FOR_REVISION` | |
| `POST_PUBLISHED` | Direct publish |
| `POST_UNPUBLISHED` | |
| `POST_ARCHIVED` | When archive is implemented |
| `POST_TRASHED` | |
| `POST_RESTORED` | Restore from trash |
| `POST_PERMANENTLY_DELETED` | Admin only |

**Page**

| Event | Notes |
| --- | --- |
| `PAGE_CREATED` | |
| `PAGE_UPDATED` | |
| `PAGE_PUBLISHED` | |
| `PAGE_UNPUBLISHED` | |
| `PAGE_ARCHIVED` | When archive is implemented |
| `PAGE_TRASHED` | |
| `PAGE_RESTORED` | Restore from trash |
| `PAGE_PERMANENTLY_DELETED` | |

**Revision / shared**

| Event | Notes |
| --- | --- |
| `REVISION_RESTORED` | ADR-005 / DOC-02 |

**Explicitly excluded from V1 audit vocabulary**

- Autosave (ADR-005)
- HTTP 409 Conflict (transport-only; **NEW**)
- Login/security events already covered elsewhere may be added later without changing this table

`audit.view` / AUTHZ-007: Admin-only Audit Trail inspection remains authoritative. Editors do not gain `audit.view` in V1.

## 12. Public Visibility

- Revisions and autosaves are **Control Panel / editorial** data only.
- No public routes for revisions or autosaves.
- Public rendering remains ADR-016 (Posts) and ADR-017 (Pages): **PUBLISHED** live state only.
- Restoring a revision does **not** publish (see §9.3).

## 13. Security

Preserve:

- Shield session auth; SessionAuthFilter; permission filters
- AUTHZ-001 ownership for Contributor edits
- CSRF + HTMX CSRF architecture (Task 3.6) — autosave is a mutation → CSRF required; **no new CSRF exceptions**
- RichTextSanitizer before persist (including restore + autosave payloads)
- ContentSchemaValidator + MediaService resolver
- `esc()` on admin output; no PII in `audit_logs.metadata`

Do not create unauthenticated revision/audit endpoints.

## 14. Migration Contract & Order

Future Task 4.9B MUST migrate in this order (justified: OCC columns before Services write revisions; revisions before audit FK to `revision_id`):

1. **Alter** `pages` — add `lock_version INT UNSIGNED NOT NULL DEFAULT 1`
2. **Alter** `posts` — add `lock_version INT UNSIGNED NOT NULL DEFAULT 1`
3. **Create** `revisions` (§7)
4. **Create** `audit_logs` (§11)

Expected table count after implementation: **19 → 21** (`revisions`, `audit_logs`).

No migration is created by this ADR task.

## 15. Deferred Implementation Details (non-blocking)

These do **not** block accepting this ADR; they are UI/wiring details for later tasks:

- Exact autosave HTMX/Alpine debounce wiring
- Exact 409 fragment markup
- Revision history list/compare UI chrome
- Whether Pages share an identical `/admin/pages/{id}/autosave` path shape (yes in spirit; path string is implementation)
- Cache invalidation hook details beyond ADR-009

## 16. Relationship to ADR-005

| ADR-005 | This ADR |
| --- | --- |
| Behavioral rules (immutable snapshots, is_autosave, OCC, published autosave boundary, restore+audit) | **Reaffirmed** |
| Missing DDL / JSON shape / permission reconcile / retention binding | **Supplied here as NEW** |

Do not treat this ADR as deleting or weakening ADR-005.

## 17. Consequences

### Positive

- Task 4.9B can migrate and implement without inventing schema mid-code.
- Editorial status, revision history, and audit remain separable.
- No new Shield permissions required.

### Trade-offs

- Polymorphic `resource_type` + `resource_id` without DB FK (DOC-02 tension acknowledged; DOC-08 single-table naming wins).
- Snapshot LONGTEXT growth under indefinite retention (DOC-12 accepted).
- Contributor has no revision-history UI in V1.

## 18. References

- CONTEXT.md (revisions + audit mandatory)
- docs/01-Product-Requirements.md (REQ-PAGE-010–012, REQ-POST-010–013, REQ-REV-*, REQ-AUDIT-*)
- docs/02-Domain-Model.md (§§24–26, §33 deferred items closed here for V1 foundation)
- docs/03-Authorization-Security.md (§4, AUTHZ-001–007)
- docs/04-Content-Publishing.md (§§7, 12–14, 17–20)
- docs/08-Technical-Architecture.md (§§16, 35–36)
- docs/09-Implementation-Blueprint.md (Phase 4)
- docs/12-Maintenance-Upgrade-Guide.md (§51)
- ADR-005, ADR-014, ADR-016, ADR-017, ADR-018
- app/Config/AuthGroups.php (`page.restore`, `post.restore`, `audit.view`)

## 19. Revision History

| Date | Notes |
| --- | --- |
| 2026-08-25 | Initial acceptance (Task 4.9A): revisions + audit_logs DDL; snapshot JSON schema_version 1; lock_version; restore/permission reconcile; retention |
