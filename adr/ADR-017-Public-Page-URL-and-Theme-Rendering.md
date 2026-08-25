# ADR-017 — Public Page URL and Theme Rendering Contract

**Status:** Accepted  
**Date:** 2026-08-23  
**Task:** Phase 4 / Task 4.4A

## 1. Status

Accepted for SMITE CMS V1.

This ADR locks the V1 public Page URL and Theme rendering contract. It is **documentation-only**; it does not implement routes, Controllers, Services, Theme views, migrations, or application code.

It closes the Page-side gap deferred by ADR-016 (“Page public catch-all / hierarchy routing implementation”) without copying Post collection-prefix behavior.

## 2. Context

Phase 4 / Task 4.3 delivered Page publishing (DRAFT|UNPUBLISHED → PUBLISHED → UNPUBLISHED). Public Page rendering remains unimplemented because the public Page URL contract was not locked.

Posts and Pages are **different** domain entities (DOC-02). ADR-016 locked Post paths under a fixed `news` collection prefix. Page public paths are illustrated in DOC-07 / ADR-003 as Strategy B **root** paths (e.g. `/about`, `/tentang-kami`) — not under `/news`.

### Existing documented facts (not invented here)

| Fact | Classification | Source |
| --- | --- | --- |
| Only **PUBLISHED** content is publicly visible/renderable; DRAFT / UNPUBLISHED / ARCHIVED / TRASH are not public | EXPLICIT | DOC-04 §§5–7, 15–17 |
| Primary Language uses root URLs (no locale prefix); Secondary uses `/{code}/...` (Strategy B) | EXPLICIT | DOC-07 §§3–5; ADR-003; CONTEXT.md Localization |
| V1 language codes include `id` (Primary default) and `en` (Secondary) | EXPLICIT | DOC-07 §2; `Config\Site::$defaultLocale` |
| `/id/...` is **not** the Primary URL shape | EXPLICIT | DOC-07 §3; ADR-003; CONTEXT.md |
| DOC-07 illustrates Primary Page-like paths as `/about`, `/tentang-kami` and Secondary as `/en/about-us` | EXPLICIT (example) | DOC-07 §§3, 11–12 |
| All current public URLs share **one global namespace** (Page + Post + Category + reserved + active redirects) | EXPLICIT | DOC-07 §§13, 16; REQ-PAGE-005/006; ADR-003; ADR-016 |
| Developer owns route structure; Admin owns only slugs inside that structure | EXPLICIT | DOC-07 §14 |
| Post public paths occupy `/news/{slug}` and `/en/news/{slug}` and reserve those prefixes | EXPLICIT | ADR-016 |
| Pages store `template_key` (default `custom-page`); every Theme must provide `custom-page` | EXPLICIT | DOC-05 §§7, 15; REQ-PAGE-007/008; `pages.template_key` migration |
| Posts do **not** store `template_key`; Pages **do** | EXPLICIT | ADR-015 vs Page foundation |
| Theme PHP views live under `app/Views/themes/{theme_id}/` (outside public web root) | EXPLICIT | ADR-013 |
| ADR-016 foreshadowed Page views as `templates/custom-page.php` for consistency | STRONGLY IMPLIED | ADR-016 §6 |
| RICH_TEXT is stored as server-sanitized HTML; Theme rendering uses that stored HTML | EXPLICIT | ADR-014 |
| Public pipeline: Route → Locale → Resolve resource → Active Theme → Template → Render | EXPLICIT | DOC-08 §§24, 51 |
| LocaleFilter detects locale only; content resolution is a separate Service concern | EXPLICIT | ADR-003 |
| `page_translations` uniqueness is `(locale, slug)` | EXPLICIT | CreatePagesTables migration |
| Pages support two-level **editorial** hierarchy (`parent_id`) | EXPLICIT | DOC-02 §8; REQ-PAGE-002; `pages.parent_id` |
| Missing Secondary translation falls back to Primary content; no auto-created Translation row | EXPLICIT | DOC-07 §§7–8; ADR-003; CONTEXT.md |
| Public rendering must not leak editorial status / internal ids | STRONGLY IMPLIED | DOC-04; DOC-08 §49; ADR-016 privacy pattern |

### Gap this ADR closes

Without an Accepted Page URL ADR, implementers must not invent `/page/{slug}`, `/pages/{slug}`, nested `/{parent}/{child}`, homepage flags, or Admin-configurable Page route trees. DOC-07’s root examples are illustrations of Strategy B, not a numbered REQ locking the exact V1 Page catch-all pattern — this ADR elevates the source-consistent pattern to Accepted V1 law.

### Ambiguity resolved here (explicitly)

Sources define Page **hierarchy** (max two levels) but **do not** define nested multi-segment public paths for child Pages. All DOC-07 Page URL examples are single content segments under Strategy B. Nested hierarchy URLs are therefore **not** accepted for V1; see §4.

## 3. Decision

The following V1 contracts are **Accepted**.

## 4. Public URL Contract

### Accepted V1 Page public paths

| Locale role | Canonical public path |
| --- | --- |
| Primary (`id` in V1 defaults) | `/{slug}` |
| Secondary (`en` in V1 defaults) | `/en/{slug}` |

Where `{slug}` is the **locale-specific** value from `page_translations.slug` for the resolved locale (after Strategy B resolution / fallback rules in §5).

### Classification and rationale

| Question | Result | Classification |
| --- | --- | --- |
| Is root `/{slug}` the canonical Primary Page path in V1? | **Yes** | **NEW ARCHITECTURAL DECISION** — elevates DOC-07 Strategy B Page examples to the fixed V1 Page path shape |
| Is `/en/{slug}` the canonical Secondary Page path? | **Yes** | Same + EXPLICIT Strategy B |
| Do Pages use a collection prefix (e.g. `/pages`)? | **No** | Would invent a prefix absent from Page source examples; ADR-016 already assigned `news` to Posts |
| Is path Admin-configurable? | **No** in V1 | EXPLICIT (DOC-07 §14) |
| Does `parent_id` compose nested public paths (`/{parent-slug}/{child-slug}`)? | **No** in V1 | **NEW ARCHITECTURAL DECISION** resolving undocumented hierarchy-URL composition; hierarchy remains editorial/structural; each Page’s public path is its own single slug segment under Strategy B |
| Site root `/` as a Page homepage? | **Deferred** | No homepage-flag / root-Page binding in sources — do not invent |

### Out of scope for this path contract

- Public Page **listing/index** — not part of V1 under this ADR
- Nested multi-segment Page URLs — rejected for V1 (see above)
- Category archive URLs — deferred
- Sitemap / hreflang wiring details — rules remain ADR-003; Page emission deferred to implementation follow-ups
- Historical redirects table wiring — rules remain DOC-07 / ADR-003; not invented here

## 5. Locale Contract

V1 locales: **`id`** (Primary by current Site default) and **`en`** (Secondary).

| Topic | Contract | Classification |
| --- | --- | --- |
| Indonesian (Primary) canonical Page URL | `/{slug}` | EXPLICIT Strategy B + this ADR’s path |
| English (Secondary) canonical Page URL | `/en/{slug}` | EXPLICIT Strategy B + this ADR’s path |
| Is `/id/{slug}` a valid Primary URL? | **No** — Primary has no language prefix | EXPLICIT |
| Locale detection | **Route-driven** (locale prefix present ⇒ Secondary; absent ⇒ Primary). LocaleFilter must not perform content lookup | EXPLICIT (ADR-003) |
| Missing Secondary translation | Resolve Primary translation content for the corresponding **PUBLISHED** Page (deterministic fallback). Do **not** auto-create a Translation row. Canonical for fallback responses points to the Primary URL | EXPLICIT (DOC-07; ADR-003) |
| Missing Primary translation for a publicly requested Primary URL | Treat as not publicly renderable → same public not-found behavior as §8 | STRONGLY IMPLIED (Primary translation required for publishable content — DOC-07 §6; Page publish validation) |
| Fallback-only Secondary URL | Not an independent translated document; no independent hreflang/sitemap as Secondary translation | EXPLICIT (ADR-003) |

## 6. Theme Rendering Contract

### Template key

Pages retain a stored `pages.template_key` (foundation default: `custom-page`).

Public rendering resolves:

```text
ACTIVE Theme → Theme Manifest → templates.{page.template_key}
```

| Topic | Contract | Classification |
| --- | --- | --- |
| Must the stored key exist in the ACTIVE Theme Manifest? | **Yes** — if missing/unavailable, public render is not-found (same privacy class as §8) | EXPLICIT Theme/Page binding (DOC-05) + privacy refinement |
| Required Theme baseline | Every Theme still MUST provide `custom-page` | EXPLICIT (DOC-05; ADR-002) |
| Difference from Posts | Posts always resolve Manifest `custom-post` with no stored key (ADR-015). Pages use the stored key against the active Manifest | EXPLICIT |

### Public view path (locked here)

For a resolved template key `{templateKey}` (typically `custom-page`), the deterministic public Theme view is:

```text
app/Views/themes/{activeThemeId}/templates/{templateKey}.php
```

CI4 view name:

```text
themes/{activeThemeId}/templates/{templateKey}
```

Example for the baseline theme and `custom-page`:

```text
app/Views/themes/default/templates/custom-page.php
```

| Topic | Contract | Classification |
| --- | --- | --- |
| Mapping | Manifest template key → filesystem `templates/{templateKey}.php` under the active theme | **NEW ARCHITECTURAL DECISION**, parallel to ADR-016’s `custom-post` mapping and ADR-013’s theme directory sketch |
| Who resolves the path | ThemeService (or a thin helper owned by ThemeService) using `activeThemeId()` — Controllers/Views must not `require` ThemeManifest.php | STRONGLY IMPLIED (ADR-002 / existing ThemeService) |
| Payload fields | Render fields exposed by the active template schema from the prepared view-model. Do **not** invent SEO fields, breadcrumbs, or analytics | EXPLICIT defensive Theme rules (DOC-05; ADR-002) |
| RICH_TEXT output | Render persisted sanitized HTML for RICH_TEXT fields only (not `esc()` as plain text). Persist-time `RichTextSanitizer` remains the authoritative XSS boundary; do **not** introduce a second sanitizer at render time | EXPLICIT (ADR-014) |
| Non-HTML fields | TEXT / TEXTAREA / URL / etc. remain escaped or otherwise safely presented per type | EXPLICIT (ADR-014 / Theme discipline) |
| Defensive rendering | Theme views MUST tolerate missing/unknown payload keys without fatal errors | EXPLICIT (ADR-002) |
| Do not create the view file in this ADR task | Implementation belongs to Task 4.4 | — |

## 7. Ownership / Boundaries

```text
Public HTTP request
  → Route (fixed `/{slug}` or `/en/{slug}` — after reserved/higher-precedence routes)
  → Locale context (route-driven; LocaleFilter if present)
  → Public Controller (e.g. Controllers/Site/PageController per ADR-013 Site namespace)
       → PageService public lookup (PUBLISHED only; locale/fallback rules)
       → ThemeService active theme + view resolution for page.template_key
       → Theme view with prepared view-model
  → HTTP response
```

| Layer | May | Must not |
| --- | --- | --- |
| Controller | Read route params; call Services; map not-found to HTTP 404; pass prepared view-model | Query DB; parse `content_payload`; sanitize HTML; load ThemeManifest directly; invent visibility rules |
| PageService | Authoritative public Page resolution and lifecycle visibility | Render Theme HTML |
| ThemeService | Active theme id, Manifest schema metadata, deterministic view path for the Page template key | Authorize editorial users; own Page status rules |
| Theme view | Present prepared fields (title, sanitized RICH_TEXT, other schema fields as prepared) | Query DB; parse Manifest; call Models; trust request input as HTML |

This matches ADR-013 layering and DOC-08 §§24, 51.

## 8. Visibility / Not-Found Contract

For public Page requests, the following SHALL produce the **same indistinguishable public not-found outcome** (HTTP **404** via the project’s established error handling — DOC-08 §49):

- nonexistent `/{slug}` (or secondary equivalent)
- Page exists but status is DRAFT, UNPUBLISHED, ARCHIVED, or TRASH
- Page cannot be resolved safely for the requested Primary URL (e.g. missing required Primary translation)
- stored `template_key` missing from the ACTIVE Theme Manifest / view unavailable

| Rule | Classification |
| --- | --- |
| Non-PUBLISHED must not render | EXPLICIT (DOC-04) |
| Response must not reveal whether a private/non-public Page exists (no status leakage, no id leakage, no distinct “unpublished” public page) | **NEW ARCHITECTURAL DECISION** refining DOC-08 production error opacity for this resource class (parallel to ADR-016) |
| Do not expose stack traces, SQL, or filesystem paths | EXPLICIT (DOC-08 §49) |

**Exception (not a 404):** Secondary URL with missing Secondary translation but resolvable Primary **PUBLISHED** Page → Strategy B **fallback render** (DOC-07 / ADR-003), not not-found.

## 9. Namespace / Collision Contract

| Topic | Contract | Classification |
| --- | --- | --- |
| Namespace model | **One global public URL namespace** shared by Pages, Posts, Categories, reserved system routes, locale prefixes, and active historical redirects | EXPLICIT |
| Route patterns | **Separate patterns**, shared uniqueness: Pages under Strategy B root single-segment paths; Posts under `/news/...` (ADR-016) | EXPLICIT namespace + ADR-016 + this ADR |
| Reserved collisions | A Page MUST NOT claim paths reserved for Posts (`news`, `news/{slug}`, `en/news`, `en/news/{slug}`), system routes (`cp`, `admin`, `logout`, `download`, `sitemap.xml`, `robots.txt`, …), or active locale prefixes (`en`) | EXPLICIT (DOC-07; ADR-003; ADR-016) + this ADR |
| Route precedence (implementation note) | Fixed Post routes and other reserved routes MUST be registered so they cannot be captured by the Page `/{slug}` / `/en/{slug}` catch-all | STRONGLY IMPLIED (ADR-016 §9) |
| Slug uniqueness vs URL uniqueness | Translation slug uniqueness remains **per locale** (`(locale, slug)`). Global uniqueness is enforced on the **full public path**, not the bare slug alone. Therefore `/{slug}` (Primary) and `/en/{slug}` (Secondary) are distinct public URLs and may legally share the slug token across locales | EXPLICIT (migration + DOC-07) |
| Hierarchy vs uniqueness | Because V1 Page paths are single-segment, two Pages cannot both publish the same locale slug even if they have different parents | Consequence of this ADR’s flat-path decision + `(locale, slug)` uniqueness |

Full SlugService cross-type collision enforcement may land partly with later URL/redirect work; Task 4.4 MUST at minimum respect reserved prefixes and Post route precedence.

## 10. Public Listing

V1 under this ADR does **not** define a public Page index/listing route (no `/`, no `/pages`, no sitemap UI). DOC-04’s general “may appear in public listings” does not create a Page listing endpoint. Implementers must not invent one merely because Posts have a `/news` collection prefix (and even ADR-016 deferred Post listing).

## 11. Admin Configurability

Admin/Editor may edit Page **slugs** (and localized slugs) within the developer-owned path shape. Admin **may not** configure Page URL structure, prefixes, catch-all rules, or hierarchy nesting in V1 (DOC-07 §14). Any future Admin-configurable routing requires a separate ADR.

## 12. Consequences

### Positive

- Unblocks Phase 4 / Task 4.4 public Page rendering without inventing `/page` or nested hierarchy URLs.
- Keeps Page and Post namespaces distinct while sharing one global uniqueness domain.
- Aligns Page URLs with Strategy B examples already present in DOC-07 / ADR-003.
- Reuses ThemeService view-path convention introduced for Posts (ADR-016), adapted for stored `template_key`.

### Trade-offs / risks

- Flat single-segment paths mean hierarchy is not reflected in the URL; Themes/Menus must express structure separately.
- Catch-all `/{slug}` increases reserved-prefix discipline and route-order sensitivity relative to Posts.
- Renaming the Page path shape later is a breaking public URL change (redirects required).

## 13. Deferred Decisions

- Homepage binding for `/`
- Nested multi-segment Page URLs (explicitly rejected for V1; reopen only via new ADR)
- Public Page listing/index
- Category public URLs
- SEO field rendering, hreflang emission, sitemap entries (rules exist in ADR-003; Page wiring deferred)
- Historical `url_redirects` persistence/wiring for Page slug changes
- Admin Theme activation UI / multiple themes
- Page revisions / OCC / scheduling (separate phases)
- Render-time re-sanitization (rejected here; ADR-014 persist-time boundary stands)
- Configurable Page path prefix or Admin route designer (rejected for V1)

## 14. References

- CONTEXT.md (§ Theme, Localization, No Silent Architecture Changes)
- docs/01-Product-Requirements.md (REQ-PAGE-*, REQ-SEO-*, REQ-LOC-*)
- docs/02-Domain-Model.md (§8 Page, §20 URL)
- docs/04-Content-Publishing.md
- docs/05-Theme-Template-Architecture.md
- docs/07-Localization-URL-SEO.md
- docs/08-Technical-Architecture.md (§§24, 51, 49)
- docs/09-Implementation-Blueprint.md
- adr/ADR-002-Single-Active-Theme-Manifest.md
- adr/ADR-003-Bilingual-Routing-Strategy-B.md
- adr/ADR-004-Native-Content-Schema-Validator.md
- adr/ADR-013-Standard-Layered-CI4-Architecture.md
- adr/ADR-014-Quill-Alpine-RichText-Integration.md
- adr/ADR-015-Post-Theme-Manifest-Binding.md
- adr/ADR-016-Public-Post-URL-and-Theme-Rendering.md

## Amendment history

| Date | Change |
| --- | --- |
| 2026-08-23 | Initial acceptance (Task 4.4A): V1 `/{slug}` + `/en/{slug}`; flat (non-nested) hierarchy paths; Theme view `templates/{templateKey}.php`; PUBLISHED-only; indistinguishable 404; global namespace + Post `news` precedence. |
