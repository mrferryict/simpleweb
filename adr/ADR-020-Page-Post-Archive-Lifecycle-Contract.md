# ADR-020 — Page/Post Archive Lifecycle Contract

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 4 / Task 4.11A

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not create migrations, Services, Controllers, routes, views, permissions, statuses, or tests.

It closes the Task 4.11 blocker **ARCHIVE LIFECYCLE CONTRACT UNDEFINED** (unarchive permission, unarchive destination, leaving-ARCHIVED audit event).

It **reaffirms** DOC-04 §16, DOC-03 archive/publish permissions, ADR-016/ADR-017 PUBLISHED-only public visibility, ADR-019 trash-restore audit semantics, and Task 4.10 `TRASH` contracts. It does **not** supersede those documents.

Where this ADR binds an ambiguous DOC-04 phrase to a V1 Service/API shape, that binding is labeled **NEW**.

## 2. Context

Task 4.11 verified inbound archive is sufficiently sourced, then stopped because leaving `ARCHIVED` was not an implementable contract.

| Topic | Task 4.11 finding |
| --- | --- |
| Inbound | `PUBLISHED → ARCHIVED` via `page.archive` / `post.archive` |
| Public | Non-public (ADR-016 / ADR-017 PUBLISHED-only) |
| Permanent delete | `TRASH` only (Task 4.10) |
| Audit inbound | `PAGE_ARCHIVED` / `POST_ARCHIVED` already in vocabulary |
| Leaving `ARCHIVED` | DOC-04 §16: “may be restored or republished according to authorization” — two verbs, no permission, no destination status, no unarchive event |
| `*_RESTORED` | ADR-019: restore from **trash**, not from archive |
| `publish()` today | `DRAFT \| UNPUBLISHED → PUBLISHED` only |

Inventing `page.unarchive`, `ARCHIVED → DRAFT`, or reusing `*_RESTORED` would contradict existing restore/trash contracts.

## 3. Classification Key

| Label | Meaning |
| --- | --- |
| EXPLICIT SOURCE FACT | Written in DOC/ADR/enum/AuthGroups |
| STRONGLY IMPLIED | Necessary consequence of those facts; not a new product feature |
| NEW | V1 binding of an ambiguity; labeled so it is not mistaken for pre-existing DOC text |
| DEFERRED | Desired by loose wording, unsupported by schema/events; not implemented in V1 |

## 4. Decision Summary

| Decision | Classification | Accepted V1 result |
| --- | --- | --- |
| Archive source | EXPLICIT (DOC-02 §22; DOC-04 §3, §7) | `PUBLISHED` only |
| Archive target | EXPLICIT (enums; DOC-04 §16) | `ARCHIVED` |
| Archive permission | EXPLICIT (DOC-03 §5; AuthGroups) | `page.archive` / `post.archive` |
| Leaving ARCHIVED — action name | **NEW** (binds DOC-04 §16 “republished”) | **Republish** — reuse existing `publish()`, not a new `unarchive()` |
| Leaving ARCHIVED — permission | STRONGLY IMPLIED (DOC-04 §16 “according to authorization”; DOC-03 publish permissions) | `page.publish` / `post.publish` |
| Leaving ARCHIVED — destination | STRONGLY IMPLIED (“republished”) | `PUBLISHED` |
| Previous-state restoration | EXPLICIT gap (no `previous_status` column) | **DEFERRED** — do not add schema |
| Distinct unarchive to DRAFT/UNPUBLISHED | Not sourced | **Not in V1** |
| OCC / `lock_version` | STRONGLY IMPLIED (DOC-04 §13–14; ADR-019 §6.2 permanent mutation rule) | Same OCC as unpublish/publish; HTTP 409 |
| Revision | EXPLICIT (DOC-04 §13 Archive is an explicit editorial action) | Editorial revision via existing `recordEditorialFromLive` |
| Audit inbound | EXPLICIT (DOC-03 §26; ADR-019 §11.4) | `PAGE_ARCHIVED` / `POST_ARCHIVED` |
| Audit leaving ARCHIVED | STRONGLY IMPLIED (republish = publish; ADR-019 `*_PUBLISHED`) | `PAGE_PUBLISHED` / `POST_PUBLISHED` |
| `*_UNARCHIVED` | Not in vocabulary | **Do not add** |
| `*_RESTORED` for archive | EXPLICIT (ADR-019: trash restore) | **Do not reuse** |
| Public visibility | EXPLICIT (ADR-016; ADR-017; DOC-04 §16) | Not public |
| Permanent delete | EXPLICIT (Task 4.10; DOC-04 §19) | `TRASH` only — `ARCHIVED` is ineligible |
| `deleted_at` | EXPLICIT (DOC-04 §17 trash-only) | Archive is **status-only**; do not set `deleted_at` |

## 5. Inbound Archive

### Accepted transitions

```text
Page:  PUBLISHED → ARCHIVED    permission: page.archive
Post:  PUBLISHED → ARCHIVED    permission: post.archive
```

### Who may archive (EXPLICIT AuthGroups — do not change in the implementation task)

| Resource | Admin | Editor | Contributor |
| --- | --- | --- | --- |
| Page | Yes (`page.*`) | No (`page.archive` not in Editor matrix) | No |
| Post | Yes (`post.*`) | Yes (`post.archive`) | No |

DOC-03 §4.2 lists Editor “archive Posts”; it does not list archive Pages. That split is preserved.

### Rejected inbound sources (do not implement without a new requirement)

- `DRAFT → ARCHIVED`
- `UNPUBLISHED → ARCHIVED`
- `PENDING_REVIEW → ARCHIVED`
- `TRASH → ARCHIVED` via archive action (Trash restore remains the Task 4.10 / DOC-04 §18 path)

### Persistence

Status column only. Do **not** set `deleted_at`. Do **not** add archive metadata columns.

## 6. Leaving ARCHIVED — Republish (not Unarchive)

### Why this is not a new permission

There is no `page.unarchive` / `post.unarchive` in DOC-03. Creating one would be a new permission without source authority.

DOC-04 §16’s implementable verb is **republished according to authorization**. V1 authorization for making content public is `page.publish` / `post.publish`.

DOC-04 §15 already allows unpublished content to be published again via that same permission. This ADR applies the same exit to `ARCHIVED`.

### Accepted transition

```text
Page:  ARCHIVED → PUBLISHED    permission: page.publish    (existing publish())
Post:  ARCHIVED → PUBLISHED    permission: post.publish    (existing publish())
```

**NEW (Service binding):** Implementation SHALL extend the existing `PageService::publish` / `PostService::publish` allowed source set from `DRAFT | UNPUBLISHED` to `DRAFT | UNPUBLISHED | ARCHIVED`. It SHALL NOT add `unarchive()`, `restoreFromArchive()`, or a new route family.

Publish-time validation already required for `DRAFT | UNPUBLISHED` still applies when the source is `ARCHIVED`.

### What “restored” in DOC-04 §16 does **not** mean

DOC-04 §18 **Restore** is the named Trash operation (previous valid editorial state; Task 4.10 V1 implements `TRASH → DRAFT`). ADR-019 binds `PAGE_RESTORED` / `POST_RESTORED` to trash restore.

V1 therefore:

- does **not** treat `ARCHIVED` as Trash;
- does **not** call `restoreFromTrash()` for archived rows;
- does **not** emit `*_RESTORED` when leaving `ARCHIVED`;
- does **not** invent `ARCHIVED → DRAFT` or `ARCHIVED → UNPUBLISHED`.

If a later requirement needs “unarchive to previous status”, that requires a documented previous-state store (new schema) and is **DEFERRED**.

## 7. OCC, Revision, Audit

### OCC / `lock_version`

ADR-019 §6.2: every **successful permanent mutation** of the live Page/Post row increments `lock_version`, including status-only transitions; stale token → HTTP 409; no revision/audit side effects on conflict.

**STRONGLY IMPLIED + named here:** inbound `archive` is such a mutation (same class as `unpublish`). Republish is already `publish()`.

Client must submit `lock_version`. Reuse `beginOccMutation` / `OccConflictResponder`. No second OCC mechanism.

### Revision

DOC-04 §13 lists Archive beside Publish/Unpublish as an explicit editorial action that persists the current state/revision.

- Archive: `recordEditorialFromLive` with `PAGE_ARCHIVED` / `POST_ARCHIVED`
- Republish: existing publish revision semantics with `PAGE_PUBLISHED` / `POST_PUBLISHED`

Snapshot shape remains ADR-019 schema_version 1. Autosave still MUST NOT archive or publish.

### Audit

| Action | Event | Notes |
| --- | --- | --- |
| Archive Page | `PAGE_ARCHIVED` | Already in enum / ADR-019 (“when archive is implemented”) |
| Archive Post | `POST_ARCHIVED` | Same |
| Republish Page | `PAGE_PUBLISHED` | Same event as `UNPUBLISHED → PUBLISHED` |
| Republish Post | `POST_PUBLISHED` | Same |
| — | `*_RESTORED` | Trash restore only |
| — | `*_UNARCHIVED` | Not in V1 vocabulary; do not add |

Failed/unauthorized/OCC-conflict mutations create **no** archive or publish audit/revision rows.

## 8. Public Visibility and Adjacent Lifecycles

- `ARCHIVED` is not publicly renderable (ADR-016 Posts; ADR-017 Pages). Public 404 remains indistinguishable from missing content.
- Existing public paths are unchanged: `/{slug}`, `/en/{slug}`, `/news/{slug}`, `/en/news/{slug}`.
- Permanent delete remains `TRASH` + `content.permanent_delete` only.
- Existing `trash` / `restoreFromTrash` / `permanentlyDelete` contracts are unchanged. An `ARCHIVED` row may later be trashed by the existing trash permission (non-`TRASH` → `TRASH`), then restored or permanently deleted under Task 4.10 rules.
- Control Panel: `ARCHIVED` remains in the default non-Trash list (same as `UNPUBLISHED`). No separate Archive navigation unless a later requirement says so.

## 9. Implementation Constraints (for a later Task 4.11)

When implementing:

- Do not modify AuthGroups.
- Do not add permissions, statuses, audit events, or columns.
- Do not add public routes.
- Do not edit ADR-019; this ADR extends its mutation list for archive only.
- Archive UI follows existing Publish/Unpublish POST + `lock_version` + CSRF patterns.
- Republish UI is the existing Publish action, visible when status is `ARCHIVED` and the actor has `page.publish` / `post.publish`.

## 10. Deferred / Rejected

| Item | Disposition |
| --- | --- |
| Previous-status column / restore-to-prior-state | DEFERRED (schema not documented) |
| `*.unarchive` permission | Rejected for V1 |
| `*_UNARCHIVED` audit event | Rejected for V1 |
| `ARCHIVED → DRAFT` or `ARCHIVED → UNPUBLISHED` | Rejected for V1 |
| Archive from DRAFT / UNPUBLISHED / PENDING_REVIEW | Rejected for V1 |
| Treating ARCHIVED as Trash | Rejected |
| Scheduled archive | Out of scope (scheduler remains DOC-04 / ADR-006; not this ADR) |

## 11. Consequences

- Task 4.11 can implement `archive()` plus a source-state extension of `publish()` without new permissions or events.
- Editor can archive and republish **Posts**; Page archive remains Admin-only; Page republish remains whoever already has `page.publish` (Admin in the current matrix).
- DOC-04 §16 is not rewritten; this ADR is the V1 reading of “republished according to authorization”.

## 12. References

- CONTEXT.md
- docs/01-Product-Requirements.md (audit of archive/publish)
- docs/02-Domain-Model.md §22
- docs/03-Authorization-Security.md §4.2, §5, §26
- docs/04-Content-Publishing.md §3, §7, §13–19
- docs/09-Implementation-Blueprint.md Phase 4
- adr/ADR-005-Revision-Autosave-Concurrency.md
- adr/ADR-016-Public-Post-URL-and-Theme-Rendering.md
- adr/ADR-017-Public-Page-URL-and-Theme-Rendering.md
- adr/ADR-019-Revision-Audit-OCC-and-Autosave-Foundation.md
