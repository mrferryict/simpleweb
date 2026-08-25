# ADR-022 — Theme Discovery and Lifecycle Persistence Contract

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 6 / Task 6.1A

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not create migrations, Models, Entities, Services, Controllers, routes, views, tests, AuthGroups changes, Preview, or a `theme_asset` helper.

It closes the Task 6.1A / Phase 5–8 gap-verification blocker: ADR-002 requires atomic persisted Theme activation, while the Phase 3 foundation still selects the ACTIVE Theme from `Config\Theme` only, and DOC-08 does not define a `themes` table.

It **reaffirms** ADR-002 (single ACTIVE Theme, Manifest, custom-page, non-destructive payload, preview headers, Admin cannot enable). It **does not rewrite** ADR-002 historical text. Where this ADR binds an ambiguity, the binding is labeled **NEW**.

## 2. Classification Key

| Label | Meaning |
| --- | --- |
| EXPLICIT SOURCE FACT | Written in DOC/ADR |
| STRONGLY IMPLIED | Necessary consequence of those facts |
| NEW | V1 binding of an ambiguity |
| DEFERRED | Out of V1 / later Phase 6 slice |

## 3. Decision Summary

| Decision | Classification | Accepted V1 result |
| --- | --- | --- |
| Theme identifier | EXPLICIT (DOC-05 §3) + STRONGLY IMPLIED (paths) | Developer-owned string; directory name **must equal** `ThemeManifest.php` `id` |
| Identifier charset | **NEW** (aligns existing template-key pattern) | `[a-z0-9]+(?:-[a-z0-9]+)*` (e.g. `default`) |
| Discovery root | EXPLICIT (ADR-013; ADR-016/017; CONTEXT) | `app/Views/themes/{themeId}/` |
| Manifest | EXPLICIT (ADR-002) | `ThemeManifest.php` returning one PHP array |
| Asset CSS root | EXPLICIT (DOC-08 §54–55) | `public/themes/{themeId}/` — **not** the PHP discovery root |
| Registry table `themes` | EXPLICIT absence (DOC-08; DOC-05 §25) | **No `themes` table in V1** |
| DRAFT | EXPLICIT (DOC-05 §4) | On-disk discovered Theme **not** developer-ENABLED |
| ENABLED | EXPLICIT (DOC-05 §4, §6) | Developer/deployment configuration only; Admin cannot enable |
| ENABLED persistence | **NEW** | `Config\Theme::$enabledThemeIds` (code/deploy artifact) |
| ACTIVE | EXPLICIT (ADR-002; DOC-05 §5) | Exactly one; public rendering uses this Theme |
| ACTIVE persistence | **NEW** | Existing CodeIgniter Settings row; **not** Config after first persist |
| `Config\Theme::$activeThemeId` | **NEW** | Bootstrap / fallback only when Settings has no ACTIVE override |
| Multiple ENABLED | EXPLICIT (DOC-09 Phase 6 gate; DOC-05 §5) | Yes (Theme A ACTIVE, Theme B ENABLED) |
| Multiple ACTIVE | EXPLICIT forbidden | Never |
| Zero ACTIVE | EXPLICIT forbidden (DOC-05 §24; ADR-002) | Activation failure leaves previous ACTIVE |
| Demotion | EXPLICIT (DOC-05 §5, §24) | Previous ACTIVE ceases to be ACTIVE; remains ENABLED if still in `$enabledThemeIds` |
| Independent deactivate | EXPLICIT (DOC-05 §24) | **Not in V1** |
| Activation transaction | EXPLICIT (ADR-002) over DOC-05 §23 cache-before-commit | DB transaction → validate → persist new ACTIVE → audit in-tx → **commit** → cache invalidation |
| Validation for ENABLED/ACTIVE | EXPLICIT (ADR-002; DOC-05 §18; ADR-015) | Manifest loads; `custom-page`; `custom-post`; required template PHP files exist |
| CMS/semver compatibility matrix | UNDEFINED in sources | **Do not invent**; not a V1 gate |
| Preview eligibility | EXPLICIT (ADR-002; DOC-05 §19) | ENABLED Themes only (ACTIVE remains previewable as an ENABLED Theme that is also ACTIVE) |
| Admin UI visibility | EXPLICIT (DOC-09 Phase 6) | Admin lists **ENABLED** (including ACTIVE); not DRAFT |
| Permissions | EXPLICIT (DOC-03; AuthGroups) | Reuse `theme.preview` / `theme.activate`; **no new permission** |
| Payload on switch | EXPLICIT (ADR-002) | No prune of `content_payload` |
| Theme version history table | EXPLICIT out (DOC-05 §25) | None |

## 4. Why No `themes` Table

DOC-08 never specifies Theme registry DDL. DOC-05 §25 forbids database-level Theme version history. ADR-011 / shared hosting discourage extra tables without a documented schema.

Inventing `themes` would violate this task’s boundary and DOC-08.

V1 therefore splits concerns:

| Concern | Store | Why |
| --- | --- | --- |
| PHP templates + Manifest | Filesystem `app/Views/themes/{id}/` | ADR-013; Git-owned |
| Compiled CSS/JS | Filesystem `public/themes/{id}/` | DOC-08 §54–55 |
| DRAFT vs ENABLED | Developer `Config\Theme::$enabledThemeIds` | DOC-05: enablement = deployment/configuration |
| Which Theme is ACTIVE | Settings (`Theme.activeThemeId`) | Satisfies ADR-002 “database transaction” using the **existing** `settings` table (already in V1; used by Site Settings) |

This is **NEW** binding, not a silent rewrite of ADR-002: the “status columns” ADR-002 described are realized as (filesystem + enabled list + single ACTIVE setting), not as Theme rows.

## 5. Theme Identity

**Authoritative id:** the developer-controlled Theme identifier (DOC-05 §3 examples: `classic`, `modern`, `corporate`; installed baseline: `default`).

**Equality rule (NEW):**

```text
directory name === ThemeManifest.php['id'] === runtime theme id
```

Mismatch → Theme is **not** eligible for ENABLED or ACTIVE (treat as undiscoverable/malformed).

Public view paths already assume `{activeThemeId}` in `app/Views/themes/{id}/templates/...` (ADR-016 / ADR-017 / CONTEXT.md).

## 6. Discovery Contract

**Where:** immediate child directories of `app/Views/themes/`.

**What counts as a discovered Theme:** directory contains `ThemeManifest.php`.

**Who may install:** developers/deployment only. Admin cannot upload packages (DOC-05 §6).

**Manifest authority:** `ThemeManifest.php` is the single source of truth for metadata, templates, Content Schema, and `media_profiles` (ADR-002 / ADR-018).

**When to discover:** Theme list, enablement check, preview, activate, and public ACTIVE resolve. Do not require a background indexer.

**Malformed / incompatible:**

- Missing or non-array Manifest → not ENABLED, not ACTIVE, not Admin-listed.
- Id/directory mismatch → same.
- Missing `custom-page` or `custom-post` PHP view or Manifest template key → cannot be ENABLED at runtime (even if listed in `$enabledThemeIds`); cannot be activated.
- A bad candidate MUST NOT corrupt the current ACTIVE Theme (DOC-08 §21).

DRAFT Themes may exist on disk for developers; they are not Admin-visible (DOC-09: Admin sees only ENABLED).

## 7. Lifecycle Semantics

```text
Developer deploys directory
        ↓
      DRAFT     (discovered, not in $enabledThemeIds)
        ↓
Developer adds id to Config\Theme::$enabledThemeIds and deploys
        ↓
     ENABLED    (Admin may list / preview / activate)
        ↓
Admin theme.activate
        ↓
      ACTIVE    (exactly one; public rendering)
```

- **DRAFT:** installed source not offered to Admin. Not previewable. Not activatable.
- **ENABLED:** developer-enabled; Admin may preview and activate. Many Themes MAY be ENABLED.
- **ACTIVE:** the one Theme used for public Page/Post rendering. Must also satisfy ENABLED runtime validation.
- Admin **cannot** DRAFT → ENABLED (REQ-THEME-003; AUTHZ-005).
- Admin **can** ENABLED → ACTIVE (`theme.activate`).
- Activating Theme B: Theme A is no longer ACTIVE; Theme A stays ENABLED if still in `$enabledThemeIds` (DOC-05 §5 “inactive” = not ACTIVE).
- No standalone “deactivate” (DOC-05 §24).

`$enabledThemeIds` MUST include the bootstrap Theme (`default` in V1) so the site is never stranded.

If Settings ACTIVE id is missing from `$enabledThemeIds` or fails validation, public rendering must fail closed / keep serving only if bootstrap ACTIVE still validates — implementation 6.1B must not silently pick a random Theme. **NEW:** refuse to activate an id not in `$enabledThemeIds`; if live Settings id becomes invalid after a bad deploy, that is an operational failure (previous ACTIVE files should remain until deploy is fixed). Exact fail-closed HTTP vs 500 is implementation, but MUST NOT auto-activate another Theme without Admin `theme.activate`.

## 8. Activation / Transaction Contract

Bind ADR-002 + ADR-009 over DOC-05 §23’s cache-before-commit sketch.

```text
BEGIN transaction (same DB as Settings)
  Validate candidate (ENABLED list + Manifest + custom-page + custom-post + files)
  Persist Settings Theme.activeThemeId = candidate id
  Append audit THEME_ACTIVATED (DOC-05 §39) in the same transaction
COMMIT
IF commit succeeded:
  Invalidate public presentation cache (ADR-002 / ADR-009 post-commit)
ELSE:
  Previous ACTIVE remains (DOC-05 §23 last line)
```

“Demote previous ACTIVE” is **writing the single ACTIVE setting**, not updating a second Theme row.

Do not invalidate cache inside an uncommitted transaction.

Exact cache keys stay ADR-009 / existing Phase 4 invalidation plus Theme-scoped keys when Phase 8 population exists. This ADR does **not** invent a new key scheme.

## 9. `Config\Theme` Relationship

| Property | Role |
| --- | --- |
| `$activeThemeId` | **Bootstrap / fallback** when Settings has no `Theme.activeThemeId` (fresh install / never switched). Not the live Admin-selected value after a successful activation persist. |
| `$enabledThemeIds` | **Authoritative developer ENABLED registry** (NEW). Deploy-controlled. |

Phase 3 code that reads only `$activeThemeId` is a **temporary foundation**. Task 6.1B MUST read live ACTIVE from Settings with Config fallback — not treat Config as forever authoritative.

## 10. Multi-Theme V1 Requirement

DOC-09 Phase 6 gate: developer deploys Theme A and Theme B; Admin sees ENABLED; Preview; Activate.

V1 **requires** discovery of multiple filesystem Themes and Admin switching among ENABLED Themes. V1 does **not** require a marketplace or Admin upload.

The currently installed tree may contain only `default` until a second Theme is deployed; the **contract** must support two ENABLED Themes.

## 11. Compatibility Validation Boundary

Documented minimum (DOC-05 §18; ADR-002; ADR-015) — do not invent a CMS version matrix:

1. Manifest file loads and passes existing structural validation (`ThemeService::validateManifestStructure` contract).
2. Exactly one `custom-page` template key + `templates/custom-page.php`.
3. Exactly one `custom-post` template key + `templates/custom-post.php` (ADR-015).
4. Defensive rendering remains the Theme author’s duty (ADR-002); activation does **not** rewrite payloads.

`media_profiles` / `cms_default` remain ADR-018 (empty catalog still allowed).

## 12. Preview Dependency (not implemented here)

- Eligible Themes: **ENABLED** (ADR-002).
- ACTIVE is ENABLED-and-selected; preview of ACTIVE must not change ACTIVE (DOC-05 §20).
- DRAFT is **not** previewable via Admin Preview.
- Route shape remains an implementation decision (DOC-05 §19 conceptual `/preview/theme/{theme}/{page}`).
- Headers and cache isolation remain ADR-002 / ADR-009.
- Permissions: existing `theme.preview` (Admin). No new permission.

## 13. Deferred (later Phase 6 tasks)

- Runtime discovery implementation and Admin Theme list UI
- Settings persist + activation Service
- Preview controller/route
- `theme_asset()` helper
- `THEME_ACTIVATED` enum/Service wiring
- Second sample Theme package
- Phase 8 cache population / Theme-id key fan-out

## 14. Consequences

### Positive

- Phase 6 can implement discovery/activation without inventing DOC-08 schema.
- ADR-002 atomicity is preserved via the existing `settings` table.
- Developer vs Admin authority stays intact.

### Trade-offs

- ENABLED set is a deploy artifact; enabling a Theme requires a release, not an Admin click (required by sources).
- ACTIVE lives in Settings; `$activeThemeId` can drift until 6.1B reads Settings first.

### Compliance

- No new Shield permission.
- No Admin Theme source editor.
- No payload prune on switch.
- No Redis/queue.

## 15. References

- CONTEXT.md Theme section
- DOC-01 REQ-THEME-001 → REQ-THEME-009
- DOC-02 §13–14
- DOC-03 AUTHZ-005; `theme.preview` / `theme.activate`
- DOC-05 §§3–8, 18–25, 39
- DOC-08 §§21, 40–42, 54–55 (no Theme table DDL)
- DOC-09 Phase 6 acceptance gate
- ADR-002, ADR-009, ADR-013, ADR-015, ADR-016, ADR-017, ADR-018
