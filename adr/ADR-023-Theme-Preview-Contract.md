# ADR-023 — Theme Preview Contract

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 6 / Task 6.2A

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not create migrations, Models, Entities, Services, Controllers, routes, views, tests, AuthGroups changes, or Preview implementation code.

It binds Theme Preview semantics for Task 6.2B implementation, following:

- **PHASE 6 / TASK 6.1A** — ADR-022 (Theme discovery & lifecycle)
- **PHASE 6 / TASK 6.1B** — Theme activation implementation
- **PHASE 6 / TASK 6.1-Final** — Acceptance Gate PASS

It **reaffirms** ADR-002 (ENABLED-only Admin preview, cache bypass, security headers), ADR-009 (Preview cache isolation under `/admin/preview/...`), and ADR-022 (Preview eligibility = ENABLED only). It does **not** rewrite those ADRs. Where this ADR binds an ambiguity, the binding is labeled **NEW**.

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
| Preview scope | EXPLICIT (REQ-THEME-008; DOC-02 §14; DOC-05 §19–21) | **Page-only** Theme Preview using actual Page content |
| Post Preview | EXPLICIT absence in REQ-THEME-008 / DOC-05 Preview sections | **Not in V1 Theme Preview** |
| Preview eligibility (Theme) | EXPLICIT (ADR-002; ADR-022 §12; DOC-05 §19) | **ENABLED only**; DRAFT not previewable; ACTIVE is ENABLED and remains preview-eligible |
| Permission | EXPLICIT (DOC-03 §5; ADR-002; AuthGroups) | Existing **`theme.preview`** only; **Admin only** in V1 matrix |
| Route namespace | EXPLICIT (ADR-009) | **`/admin/preview/...`** — authenticated Control Panel namespace |
| Route shape (detail) | EXPLICIT conceptual (DOC-05 §19) + NEW binding | **`GET /admin/preview/theme/{themeId}/page/{pageId}`** with optional `?locale=` |
| Public route `/preview/theme/...` | EXPLICIT (DOC-05 §19) | **Conceptual example only** — not normative; must not replace `/admin/preview/...` |
| Content states eligible | EXPLICIT (DOC-05 §22) | **DRAFT, PUBLISHED, UNPUBLISHED, ARCHIVED** Page (Admin-authorized) |
| TRASH Pages | STRONGLY IMPLIED (DOC-05 §22 list + public visibility rules) | **Not previewable** — same not-found/forbidden boundary as Admin edit |
| Content mutation | EXPLICIT (DOC-02 §14; DOC-05 §20–22) | **Read-only presentation** — no status, revision, autosave, lock_version, or audit mutation |
| ACTIVE isolation | EXPLICIT (REQ-THEME-008; DOC-05 §20; ADR-022) | **`Theme.activeThemeId` unchanged**; public routes keep persisted ACTIVE Theme |
| Theme selection persistence | EXPLICIT (DOC-05 flow) + NEW | **Request-scoped URL only** — no session, no Settings key, no DB table |
| Cache read | EXPLICIT (ADR-002; ADR-009; DOC-05 §20) | **Bypass** application/public content cache |
| Cache write | EXPLICIT (ADR-002; ADR-009; DOC-08 §43) | **Must not populate** normal public cache |
| Dedicated Preview cache | EXPLICIT absence | **None** — no second cache architecture |
| Response headers | EXPLICIT (ADR-002; ADR-009; DOC-07 §31) | `Cache-Control: no-store, no-cache, must-revalidate`; `Pragma: no-cache`; `X-Robots-Tag: noindex, nofollow, noarchive` |
| Authentication | EXPLICIT (ADR-002; DOC-05 §20) | **Required** — Shield session; not anonymously accessible |
| CSRF | STRONGLY IMPLIED (GET read-only) | **No CSRF exception** — Preview is GET-only |
| Audit | EXPLICIT absence in DOC-05 / ADR-019 vocabulary | **No Preview audit event** — no `THEME_PREVIEWED` |
| Theme validation | EXPLICIT (DOC-08 §21; ADR-022 §11) | Reuse **`ThemeService::validateActivationCandidate()`** contract (ENABLED + discovered + manifest + templates) |
| Content resolution | STRONGLY IMPLIED (DOC-05 §21–22; §5 boundary) | Reuse **PageService** (or shared admin Page read path) — not `PublicPageLookup` PUBLISHED-only filter |
| Locale | NEW | Optional **`?locale=id|en`**; default **`Site.defaultLocale`** when omitted |
| Unsaved editor snapshot preview | EXPLICIT optional (DOC-05 §22 “may”) | **DEFERRED** — not required for Task 6.2B |
| Admin UI surface | EXPLICIT (DOC-05 §21; DOC-09 Phase 6 gate) | Preview **action** on ENABLED Theme Admin surface; must not add Enable/Disable/Deactivate |

## 4. Scope

V1 **Theme Preview** means:

> An authenticated Admin with `theme.preview` renders **one existing Page’s actual stored content** through **one candidate ENABLED Theme’s** public Page template — **without** changing the persisted ACTIVE Theme, **without** mutating Page/Post state, and **without** using or polluting public cache.

Out of scope for this ADR (implementation deferred to later tasks):

- Post Theme Preview
- Preview of unsaved/in-form editor deltas (autosave snapshot preview)
- `theme_asset()` helper
- Theme package upload / enable / disable UI
- Preview audit events
- New permissions
- New database tables or Settings keys for Preview state
- Phase 8 public cache population

## 5. Preview Eligibility (Theme)

| Theme state | Previewable |
| --- | --- |
| **DRAFT** (discovered, not in `$enabledThemeIds`) | **No** |
| **ENABLED** | **Yes** |
| **ACTIVE** | **Yes** (ACTIVE ⊆ ENABLED) |

Candidate Theme must pass the same validation boundary as activation (**ADR-022 §11** via `ThemeService::validateActivationCandidate()`): discovered, manifest loads, directory/id match, `custom-page` + `custom-post` keys and PHP template files present.

Invalid or non-ENABLED candidate → Preview rejected; **must not** affect persisted ACTIVE Theme or public rendering.

## 6. Permission

| Concern | V1 binding |
| --- | --- |
| Permission name | **`theme.preview`** (existing — no new permission) |
| Who holds it | **Admin only** (`AuthGroups`: `admin` → `theme.*`; Editor/Contributor matrices exclude `theme.preview`) |
| Service enforcement | **Authoritative** — Preview entry Service method checks `$actor->can('theme.preview')` |
| Route enforcement | **`permission:theme.preview`** filter on Preview routes |
| Content access | Admin (**AUTHZ-003** full content authority) may preview Pages in eligible states; no separate `page.edit` gate beyond authenticated Admin + `theme.preview` |

`theme.activate` remains separate and MUST NOT be implied by Preview.

## 7. Route

### Normative namespace (ADR-009)

Theme Preview requests **MUST** live under:

```text
/admin/preview/...
```

They **MUST NOT** be registered as public catch-all routes (`/{slug}`, `/news/{slug}`, etc.).

DOC-05 §19 route:

```text
/preview/theme/{theme}/{page}
```

is **conceptual documentation only**. V1 binds the admin-safe equivalent below.

### Accepted V1 route (NEW)

```text
GET /admin/preview/theme/{themeId}/page/{pageId}
```

| Segment | Rule |
| --- | --- |
| `{themeId}` | Theme identifier `[a-z0-9]+(?:-[a-z0-9]+)*`; must be ENABLED + valid |
| `{pageId}` | Positive integer Page primary key (consistent with existing Admin Page routes) |

Optional query:

```text
?locale=id|en
```

When omitted, use **`Site.defaultLocale`** (existing SettingService / Site settings). Invalid or missing translation for the requested locale → controlled error response (404/422 per project HTTP convention); must not fall back to a different Theme or mutate ACTIVE.

### HTTP method

**GET only.** Preview is read-only presentation. No POST/PUT/PATCH/DELETE Preview mutations. **No CSRF filter exception.**

## 8. Content Eligibility

Theme Preview applies to **Pages only** (REQ-THEME-008; DOC-02 §14; DOC-05 §21).

| Page status | Previewable by Admin |
| --- | --- |
| **DRAFT** | Yes |
| **PUBLISHED** | Yes |
| **UNPUBLISHED** | Yes |
| **ARCHIVED** | Yes |
| **TRASH** (`deleted_at` set) | **No** |
| Non-existent Page id | **No** |

Preview **does not change** content status (DOC-05 §22).

Preview **does not** substitute public PUBLISHED-only lookup (ADR-017). Admin Preview intentionally renders non-public Page states for evaluation purposes while remaining authenticated and non-indexable.

Posts, Categories, Tags, Menus, and other entities are **out of scope** for V1 Theme Preview.

## 9. Theme Selection & ACTIVE Isolation

| Concern | V1 binding |
| --- | --- |
| How Theme is selected | **URL path** `{themeId}` — request-scoped |
| Session persistence | **None** |
| Settings persistence | **None** — **`Theme.activeThemeId` MUST NOT be read from or written to Preview** |
| ACTIVE during Preview | Unchanged — public site continues using persisted ACTIVE Theme via ADR-022 resolution |
| Public routes | `/{slug}`, `/en/{slug}`, `/news/{slug}`, `/en/news/{slug}` **unchanged** — always ACTIVE Theme |

## 10. Content Mutation Isolation

During Preview the system **MUST NOT**:

- change Page status;
- create or update revisions;
- create or update autosaves;
- increment `lock_version`;
- append audit log rows;
- mutate `content_payload` or translations;
- mutate Settings;
- activate a Theme.

Preview is **presentation-only** through existing Theme templates and sanitized payload/media resolution paths.

## 11. Cache Isolation

Bind ADR-002 + ADR-009 exactly:

| Operation | V1 binding |
| --- | --- |
| Read public cache | **Bypass** — do not read `page.public.*`, `content.*`, `nav.*`, `theme.active`, or other public presentation keys for Preview |
| Write public cache | **Forbidden** — Preview output must never populate normal public cache storage |
| Invalidate public cache | **Forbidden** on Preview — invalidation remains activation/mutation paths only |
| Dedicated Preview cache | **None** |
| Redis / CDN / cache table | **Forbidden** (ADR-011 / ADR-009) |

Rendering is **request-local** (compute on each Preview GET).

## 12. Request / Session Isolation

Preview context is fully determined by:

1. authenticated Admin session;
2. `{themeId}` and `{pageId}` URL segments;
3. optional `locale` query;
4. existing Page + Theme Services.

No Preview-specific session keys, cookies, or Settings rows.

## 13. Authorization & Security

| Control | V1 binding |
| --- | --- |
| Authentication | Shield **`session`** filter (same staff group boundary as `/admin/*`) |
| Authorization | **`theme.preview`** (route filter + Service) |
| Anonymous access | **Forbidden** |
| Unpublished content exposure | Allowed **only** to authenticated Admin with `theme.preview` — not linkable as public content |
| External sharing | Preview URLs require auth — not a public capability URL |
| SEO / indexing | **`X-Robots-Tag: noindex, nofollow, noarchive`** + no-store cache headers (DOC-07 §31) |

## 14. CSRF

Preview is **GET-only** read-only. **No CSRF exception** is introduced. Do not add Preview routes to CSRF `except` lists.

## 15. Audit

| Event | V1 binding |
| --- | --- |
| Opening Preview | **No audit row** |
| Selecting Theme in Preview URL | **No audit row** |
| Rendering Preview | **No audit row** |
| **`THEME_PREVIEWED`** | **Must not be invented** |
| **`THEME_ACTIVATED`** | Remains **activation-only** (ADR-022) |

## 16. Theme Validation

Reuse existing ThemeService contract — **do not** create a parallel Preview validator.

Before rendering, candidate `{themeId}` must satisfy **`ThemeService::validateActivationCandidate($themeId)`** (same checks as activation eligibility: ENABLED, discovered, manifest, id match, `custom-page` / `custom-post` files).

Failure → Preview error response; ACTIVE Theme and public cache unchanged.

## 17. Public Route Isolation

Normal public Page/Post rendering continues to:

1. resolve **persisted ACTIVE** Theme (ADR-022);
2. apply **PUBLISHED-only** public visibility (ADR-016 / ADR-017);
3. use public cache per ADR-009 when Phase 8 population exists.

Preview routes are **orthogonal** — they render candidate ENABLED Theme templates without altering the above.

## 18. Presentation & Service Boundary

### Theme boundary

Theme views remain **presentation-only** (ADR-013). Preview must not grant Theme templates new DB/filesystem/Settings access beyond the existing public render variable contract.

### Content boundary

Preview must reuse:

- **PageService** (or equivalent existing admin Page read + payload path);
- **ThemeService** for candidate Theme manifest/template resolution;
- **ContentSchemaValidator** / **RichTextSanitizer** / **MediaService** resolution as used for public render preparation;

Do **not** bypass Services with ad hoc SQL in Controllers or Views.

## 19. Admin UI (minimum)

DOC-05 §21 and DOC-09 Phase 6 gate require Admin to **Preview** an ENABLED Theme.

Minimum V1 Admin surface for Task 6.2B:

| Required | Forbidden on Preview UI |
| --- | --- |
| Preview action reachable from Admin Theme lifecycle context | Enable / Disable / Deactivate |
| Uses POST-free Preview link/form **GET navigation** to Preview route | Activate (remains separate POST + `theme.activate`) |
| Theme list may link Preview when a Page context is available | Theme persistence controls |

Exact Page picker UX (modal vs dedicated select Page step) is an **implementation detail** provided it honors this ADR’s route, permission, and read-only semantics.

## 20. Deferred (later Phase 6+ tasks)

- Post Theme Preview
- Preview of unsaved editor form state / autosave snapshot (DOC-05 §22 optional “may”)
- Browser automation / E2E Preview workflows (DOC-09 mentions Theme preview in browser tests — testing task, not this contract)
- `theme_asset()` and public Theme CSS asset serving
- Dedicated Preview cache layer
- Preview audit logging
- Editor/Contributor Preview access (would require explicit product change — not in V1 sources)

## 21. Consequences

### Positive

- Task 6.2B can implement Preview without guessing content-state or cache semantics.
- ACTIVE Theme and public URLs remain stable during Preview.
- Reuses ADR-022 Theme validation and existing `theme.preview` permission.

### Trade-offs

- Page-only Preview leaves Post Theme evaluation to manual/public ACTIVE inspection until a future requirement exists.
- Admin-only Preview matches ADR-002 but excludes Editor/Contributor even for Pages they edit.

### Compliance

- No new permission.
- No new audit event.
- No migration.
- No Settings key for Preview state.
- No public `/preview/...` route.

## 22. References

- CONTEXT.md Theme section
- DOC-01 REQ-THEME-008
- DOC-02 §14
- DOC-03 §4.1, §5, AUTHZ-003, AUTHZ-005
- DOC-05 §§6, 19–22
- DOC-07 §31
- DOC-08 §21, §43
- DOC-09 Phase 6 acceptance gate
- DOC-10 §36
- ADR-002, ADR-009, ADR-013, ADR-016, ADR-017, ADR-018, ADR-022
