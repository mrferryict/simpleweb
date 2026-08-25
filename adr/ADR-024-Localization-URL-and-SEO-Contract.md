# ADR-024 — Localization, URL & SEO Contract

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 7 / Task 7.1A

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not implement Controllers, Services, routes, views, migrations, tests, or AuthGroups changes.

It binds Phase 7 semantics for Task 7.1B implementation, following:

- **DOC-09 §12** — Phase 7 scope and Acceptance Gate
- **DOC-07** — Localization, URL & SEO
- **DOC-01** — REQ-LOC-001 → REQ-LOC-006, REQ-SEO-001 → REQ-SEO-007
- **ADR-003** — Strategy B routing, fallback, canonical, hreflang, redirects (reaffirmed, not rewritten)
- **ADR-016 / ADR-017** — fixed V1 public Post/Page path shapes (reaffirmed)
- **ADR-019** — revision snapshots include translations (reaffirmed)
- **ADR-022 / ADR-023** — Theme contracts remain unchanged (reaffirmed)

Where this ADR binds an ambiguity, the binding is labeled **NEW**.

## 2. Classification Key

| Label | Meaning |
| --- | --- |
| EXPLICIT SOURCE FACT | Written in DOC/ADR |
| STRONGLY IMPLIED | Necessary consequence of those facts |
| NEW | V1 binding of an ambiguity |
| DEFERRED | Out of V1 Phase 7 / later phase |
| FOUNDATION COMPLETE | Already implemented; Phase 7 must not redesign |

## 3. Decision Summary

| Decision | Classification | Accepted V1 result |
| --- | --- | --- |
| Language count | EXPLICIT (DOC-07 LOC-001/002) | **One Primary (required) + zero or one Secondary**; no third language |
| Language codes | EXPLICIT (DOC-07 LOC-003) | Fixed application list **`id` \| `en`** only |
| URL strategy | EXPLICIT (ADR-003; CONTEXT) | **Strategy B** — Primary unprefixed; Secondary `/en/...`; **`/id/...` is not a Primary content URL** |
| Translation storage | EXPLICIT (DOC-02; migrations) | Existing **`page_translations`** / **`post_translations`** — **no redesign** |
| Per-locale slug | EXPLICIT (DOC-07 §12) | Each translation row owns **`slug`**; uniqueness **`(locale, slug)`** |
| Public visibility | EXPLICIT (DOC-04; ADR-016/017) | **PUBLISHED-only** public render; localization must not weaken this |
| Secondary fallback | EXPLICIT (REQ-LOC-005; ADR-003) | Missing Secondary translation → **Primary content** with **`isFallback=true`** |
| Fallback URL shape | EXPLICIT (ADR-003) + NEW | Secondary fallback URL uses **`/en/{primary-slug}`** (or Post **`/en/news/{primary-slug}`**), not an invented secondary slug when no Secondary row exists |
| Canonical (real Secondary) | EXPLICIT (DOC-07 §23) | Self-referencing localized public URL |
| Canonical (fallback Secondary) | EXPLICIT (ADR-003; DOC-07 §24; DOC-09 gate) | Points to **Primary URL**; fallback URL is not an independent document |
| hreflang | EXPLICIT (ADR-003; DOC-07 §26–27) | Emit **only** for translations that **exist and are PUBLISHED**; **never** for fallback-only Secondary |
| x-default | EXPLICIT (ADR-003) | Always Primary public URL |
| Redirect history | EXPLICIT (REQ-SEO-005/006; ADR-003) | **`url_redirects`** table + HTTP **301** on published slug change |
| Global URL namespace | EXPLICIT (REQ-SEO-007; ADR-003) | Current paths + active redirects + reserved routes + active locale prefixes |
| SEO metadata fields | EXPLICIT (REQ-SEO-001; DOC-07 §25) | **`meta_title`**, **`meta_description`**, optional **`canonical_url` override**, **`og_image`** reference |
| SEO field storage | NEW | Columns on **`page_translations`** / **`post_translations`** (Task 7.1B migration) |
| Sitemap | EXPLICIT (REQ-SEO-003) | **`GET /sitemap.xml`** — PUBLISHED real translations only |
| Robots | EXPLICIT (REQ-SEO-004) | **`GET /robots.txt`** — controlled generation |
| Preview noindex | EXPLICIT (DOC-07 §31; ADR-023) | **FOUNDATION COMPLETE** for Theme Preview; content preview deferred |
| Language configuration | EXPLICIT (REQ-LOC-006) | Admin configures **Primary** + optional **Secondary** via Site Settings |
| LocaleFilter | EXPLICIT (ADR-003) | Detect prefix / set locale context **only** — **no content lookup** |
| Permissions | EXPLICIT (DOC-03) | **No new permission**; localization edits under existing **`page.*` / `post.*`** |
| Translation audit events | EXPLICIT absence | **No new** `TRANSLATION_*` / `LOCALE_*` / `SEO_*` audit events |
| Theme ACTIVE resolution | EXPLICIT (ADR-022) | Public render uses persisted **ACTIVE** Theme; Preview remains ADR-023 |
| CMS/Theme semver matrix | EXPLICIT (ADR-022 §49) | **UNDEFINED — not a V1 gate** |
| Twitter Card | UNDEFINED in DOC-07 V1 list | **DEFERRED** |
| Structured data JSON-LD | EXPLICIT optional (DOC-07 §33 area) | **DEFERRED** unless Theme emits from supplied DTO only |
| Category public URLs | EXPLICIT deferral (ADR-017 §13) | **DEFERRED** |
| Public cache population | EXPLICIT (Phase 8) | **DEFERRED**; Phase 7 must **invalidate** on slug/SEO-affecting writes |

## 4. Phase 7 Foundation Already Complete (Do Not Redesign)

Task 7.1B **must reuse** the following live foundations:

| Area | Evidence |
| --- | --- |
| Translation tables | `pages` + `page_translations`; `posts` + `post_translations` |
| Translation keys | `UNIQUE (page_id, locale)`, `UNIQUE (post_id, locale)`, `UNIQUE (locale, slug)` |
| Public Page routes | `GET /{slug}`, `GET /en/{slug}` (ADR-017) |
| Public Post routes | `GET /news/{slug}`, `GET /en/news/{slug}` (ADR-016) |
| PUBLISHED-only lookup | `PageService::findPublishedForPublic()`, `PostService::findPublishedForPublic()` |
| Same-slug Secondary fallback | Secondary locale + missing row → Primary translation by **same slug**, `isFallback=true` |
| Reserved routes | `cp`, `admin`, `logout`, `download`, `uploads`, `sitemap.xml`, `robots.txt`, `en`, `id`, `news`, … |
| Revision snapshots | ADR-019 §8.3/8.4 — `translations` object keyed by locale inside snapshot JSON |
| Theme Preview locale | ADR-023 optional `?locale=id\|en` — unchanged |
| Preview security headers | ADR-023 — unchanged |

## 5. Phase 7 Gaps (Task 7.1B Scope)

The following DOC-09 / REQ-* items are **not yet implemented** and are **in scope for Task 7.1B**:

1. **Site language settings** — Primary + optional Secondary (REQ-LOC-006); today only `Site.defaultLocale` exists.
2. **`LocaleFilter`** — route-driven locale context without content resolution (ADR-003).
3. **Central public URL resolver chain** — reserved route → redirect lookup → Page/Post resolution (DOC-08 §51).
4. **`url_redirects` persistence** — migration + Service; atomic with published slug changes (REQ-SEO-005, ADR-003).
5. **Global URL namespace validation** — slug create/update checks current URLs + active redirects + reserved paths + locale prefixes (REQ-SEO-007).
6. **SEO metadata columns** on translation tables + Admin edit surfaces (REQ-SEO-001).
7. **SEO rendering Service** — resolves title/description/canonical/hreflang/og for Theme views (DOC-08 §52).
8. **`GET /sitemap.xml`** and **`GET /robots.txt`** (REQ-SEO-003/004).
9. **Canonical + hreflang emission** in public Theme presentation (ADR-003, DOC-07 §22–27).
10. **Localized site SEO defaults** — Settings with locale dimension where DOC-07 §10 requires (site title/description defaults per locale).

**Not in Task 7.1B** unless a later ADR opens them: Category public URLs, Twitter Card, structured data package, public cache population (Phase 8).

## 6. Primary / Secondary Language Contract

| Rule | Binding |
| --- | --- |
| Primary count | Exactly **one** (REQ-LOC-001) |
| Secondary count | **Zero or one** (REQ-LOC-002) |
| Allowed codes | **`id`**, **`en`** only (DOC-07 LOC-003) |
| Configuration store | **Site Settings** (REQ-LOC-006) |
| **NEW** Settings keys | `Site.primaryLocale` (required), `Site.secondaryLocale` (nullable / empty = disabled) |
| Bootstrap fallback | `Config\Site::$defaultLocale` seeds Primary when Settings empty |
| Runtime change | Admin may change via Settings UI; **must not** auto-migrate slugs or translations |
| Secondary disabled | When `Site.secondaryLocale` empty: Secondary public routes (`/en/...`) **must not resolve content** (404) |
| Prefix derivation | Secondary URL prefix = `/` + secondary locale code + `/` (V1: `/en/` when secondary is `en`) |

Changing Primary language after content exists is an **operational risk** (existing URLs/canonicals). V1 does not define automatic content migration — **NEW:** Admin change updates configuration only; content URLs remain as stored until edited.

## 7. Translation Entity Contract

Existing schema (FOUNDATION COMPLETE):

**`page_translations`**

| Column | Role |
| --- | --- |
| `id` | PK |
| `page_id` | FK → `pages.id` CASCADE |
| `locale` | `VARCHAR(16)` — `id` or `en` |
| `title` | Display title |
| `slug` | Locale-specific public slug segment |
| `content_payload` | JSON text (Theme schema fields) |

**`post_translations`** — same shape with `post_id` FK.

**NEW columns (Task 7.1B migration):**

| Column | Type | Notes |
| --- | --- | --- |
| `meta_title` | `VARCHAR(255)` nullable | REQ-SEO-001 |
| `meta_description` | `VARCHAR(500)` nullable | REQ-SEO-001 |
| `canonical_url` | `VARCHAR(500)` nullable | Optional override; when null, generated from public URL |
| `og_image_id` | `INT UNSIGNED` nullable | FK optional at app level to `media_assets.id` |

No separate Translation table type. No per-field translation revision system beyond existing Page/Post revision snapshots (ADR-019).

**Completeness:** Secondary translation row is **optional** (DOC-07 §7). Primary translation is **required before publish** (STRONGLY IMPLIED — publish validation in Services).

## 8. Locale / URL Contract

V1 public paths (reaffirmed):

| Resource | Primary | Secondary |
| --- | --- | --- |
| Page | `/{slug}` | `/en/{slug}` |
| Post | `/news/{slug}` | `/en/news/{slug}` |

Rules:

- **Primary locale** requests use **no** locale prefix.
- **Secondary locale** requests use **`/en/`** prefix when Secondary is `en`.
- **`/id/{slug}`** is **invalid** as Primary content URL (ADR-003; CONTEXT).
- Locale is determined **route-first** (prefix present ⇒ Secondary; absent ⇒ Primary). `LocaleFilter` may set request locale context but **must not** resolve content.
- Page hierarchy **`parent_id` does not nest URL segments** (ADR-017).
- Post collection prefix **`news`** is fixed (ADR-016).

## 9. Slug Contract

| Rule | Source / Binding |
| --- | --- |
| Slug ownership | Per **translation row** (FOUNDATION COMPLETE) |
| Normalization | Existing Service normalization (lowercase, trim, allowed charset) — reuse |
| Uniqueness scope | **`UNIQUE (locale, slug)`** per translation table + **global namespace** checks (NEW enforcement in 7.1B) |
| Cross-resource collision | Page vs Post vs redirect vs reserved — **must reject** (REQ-SEO-007) |
| Slug change on PUBLISHED content | Atomic: new slug persisted + **`url_redirects`** 301 from old public path (REQ-SEO-005) |
| Redirect target | **Flattened** final public URL (ADR-003) — no chains |
| Localized redirect | Redirect records carry **`locale`**; paths include locale prefix when Secondary |
| Old slug retention | Active redirect **reserves** source path (REQ-SEO-006) |
| Redirect removal | **DEFERRED** admin UX details — only authorized maintenance may deactivate (REQ-SEO-006 conceptual) |
| Slug history without redirect table | **Not allowed** once Phase 7 ships |

Slug changes participate in existing **revision + OCC + audit** on Page/Post update — no separate slug revision table.

## 10. Translation Fallback Contract

Resolution order for public Page/Post lookup (reaffirms ADR-003):

```text
1. Resolve locale from route (Primary vs Secondary prefix)
2. Lookup translation by (slug, requestedLocale)
3. If found → require parent Page/Post status == PUBLISHED
4. If not found AND requestedLocale is Secondary:
     lookup Primary translation by same slug
     if found AND parent PUBLISHED → render Primary translation, isFallback=true
5. Else → 404 (indistinguishable for non-public statuses)
```

**NEW binding for localized slugs:**

- When Secondary translation row **does not exist**, the only valid Secondary fallback URL is **`/en/{primary-slug}`** (or Post equivalent), **not** `/en/{unrelated-slug}`.
- When Secondary translation row **exists** with its own slug, **`/en/{secondary-slug}`** renders that translation.
- Fallback **must not** create Translation rows.
- Fallback **must not** expose DRAFT/UNPUBLISHED/ARCHIVED/TRASH via locale tricks.
- **`isFallback=true`** DTO flag (FOUNDATION COMPLETE) drives SEO rules (canonical/hreflang/sitemap exclusion).

Theme Preview (ADR-023) **does not** use public fallback — exact locale translation required.

## 11. SEO Contract

| Item | Phase 7 V1 |
| --- | --- |
| Meta title | **REQUIRED** capability (REQ-SEO-001); field + fallback chain |
| Meta description | **REQUIRED** capability |
| Canonical URL | **REQUIRED**; generated default; optional per-translation override column |
| Open Graph image | **REQUIRED** capability (REQ-SEO-001); via `og_image_id` |
| hreflang | **REQUIRED** when real PUBLISHED translations exist |
| x-default | **REQUIRED** → Primary URL |
| Site SEO defaults | **REQUIRED** (REQ-SEO-002); localized defaults per DOC-07 §10 where applicable |
| Sitemap | **REQUIRED** endpoint; PUBLISHED + real translations only |
| robots.txt | **REQUIRED** endpoint |
| Preview noindex | **COMPLETE** (Theme Preview ADR-023); general content preview **DEFERRED** |
| Twitter Card | **DEFERRED** |
| Structured data | **DEFERRED** (Theme may render from trusted DTO later) |

**Metadata resolution order (NEW):**

```text
Page/Post translation-specific field (if set)
    ↓
Site localized default for locale
    ↓
Site global default
    ↓
Deterministic generated fallback (title from translation title, etc.)
```

Theme templates receive normalized SEO DTO values — **no DB access in Theme** (DOC-08 §52).

## 12. Public Visibility Interaction

Localization **does not** change editorial status semantics (ADR-020, ADR-021):

| Status | Public render | Sitemap | hreflang |
| --- | --- | --- | --- |
| DRAFT | No | No | No |
| PENDING_REVIEW (Post) | No | No | No |
| PUBLISHED | Yes (per locale rules) | Yes (real translations) | Yes (real translations) |
| UNPUBLISHED | No | No | No |
| ARCHIVED | No | No | No |
| TRASH | No | No | No |

## 13. Revision / OCC / Autosave Interaction

| Action | Behavior |
| --- | --- |
| Translation edit | Existing Page/Post update flow — revision snapshot includes all translation rows (ADR-019) |
| Slug change | Same mutation; triggers redirect when status is PUBLISHED |
| OCC | Existing `lock_version` on `pages` / `posts` — unchanged |
| Autosave | Existing editorial autosave — translations inside snapshot |
| Restore | Restores translation rows from snapshot — may change slugs; redirect rules apply on next publish/slug mutation policy (**NEW:** slug change on restore follows same redirect contract when resulting live row is PUBLISHED) |
| Separate translation revision table | **Forbidden** |

## 14. Theme Interaction

| Rule | Binding |
| --- | --- |
| ACTIVE Theme | Public routes continue `ThemeService::activeThemeId()` / `publicViewNameForTemplate()` |
| Preview | ADR-023 unchanged — request-scoped candidate Theme |
| Locale in Theme | Passed as view data (`locale`, `requestedLocale`, `isFallback`) — **FOUNDATION COMPLETE** on public DTOs |
| SEO in Theme | Theme renders tags from Service-supplied values — **Task 7.1B** wires head partial |
| Language switcher | **OPTIONAL** Theme affordance (DOC-07 §11); links only when real translation URL exists |

## 15. Cache Interaction

| Rule | Binding |
| --- | --- |
| Phase 8 population | **DEFERRED** |
| Invalidation on slug change | **REQUIRED** when public path changes (reuse `PublicContentCacheInvalidator`) |
| Invalidation on publish/unpublish | Existing paths — unchanged |
| Locale in cache keys | **NEW:** when public cache keys are used, they must not collide across locales; slug-based keys require locale dimension — detailed key taxonomy deferred to Phase 8 unless 7.1B introduces slug-keyed cache |
| Preview cache | ADR-009/023 — bypass remains |

## 16. Permissions / Security

| Rule | Binding |
| --- | --- |
| New permissions | **None** |
| Page translation edit | Existing **`page.edit`** (Admin/Editor) |
| Post translation edit | Existing **`post.edit_*` / ownership** rules |
| Site language settings | Existing **`site.manage`** (Admin) |
| SEO fields | Same as parent Page/Post edit permissions |
| Public redirect endpoint | None — redirects are automatic |

## 17. Audit Contract

No new AuditEvent values for Phase 7.

Translation/slug/SEO changes continue to emit existing events (`PAGE_UPDATED`, `POST_UPDATED`, publish/unpublish/archive, etc.) when live mutations occur.

Reading sitemap/robots does not audit.

## 18. Migration Impact (Task 7.1B — Not This Task)

**Expected schema additions:**

1. **`url_redirects`** table — columns per DOC-07 §19 / DOC-02 §21 + `locale`:
   - `source_path`, `target_path`, `http_code`, `resource_type`, `resource_id`, `locale`, `active`, `created_at`
2. **`page_translations`** — SEO columns (§7)
3. **`post_translations`** — SEO columns (§7)

**No changes** to `pages` / `posts` status model.

Settings keys added via codeigniter4/settings (no DDL): `Site.primaryLocale`, `Site.secondaryLocale`, localized site SEO defaults as bound in 7.1B.

## 19. DOC-09 Phase 7 Acceptance Gate (Binding)

Task 7.1-Final must verify:

```text
Primary:   /about
Secondary: /en/about-us   (when EN translation exists)

Missing EN translation:
  Request: /en/about  (primary slug under /en/ prefix)
  Render:  Primary content (isFallback=true)
  Canonical: /about
  hreflang: no false hreflang="en"
```

Plus: redirect on slug change, sitemap/robots endpoints, global namespace enforcement, Settings language configuration.

## 20. Deferred (Explicit)

- Category/tag public localized URLs
- Twitter Card metadata
- Structured data framework
- Third-language support
- Admin redirect management UI (beyond automatic 301 creation)
- `/id/...` Primary prefix routes
- Content preview (non-Theme) unsaved snapshot SEO
- Public cache population (Phase 8)
- CMS–Theme semver compatibility matrix (ADR-022)

## 21. Consequences

### Positive

- Task 7.1B can implement against a single contract without redesigning translation tables or public route shapes.
- ADR-003 SEO rules are wired to concrete schema and Service boundaries.
- Phase 6 Theme contracts remain stable.

### Trade-offs

- SEO columns require a migration despite existing translation tables.
- Global namespace validation adds Service complexity on every slug write.
- Secondary language disablement must explicitly 404 `/en/...` routes.

## 22. References

- CONTEXT.md § Localization
- docs/01-Product-Requirements.md (REQ-LOC-*, REQ-SEO-*)
- docs/02-Domain-Model.md (§§8–9, 21)
- docs/04-Content-Publishing.md
- docs/05-Theme-Template-Architecture.md
- docs/07-Localization-URL-SEO.md
- docs/08-Technical-Architecture.md (§§17–19, 51–52)
- docs/09-Implementation-Blueprint.md (§12)
- docs/10-Testing-Quality-Strategy.md
- adr/ADR-003-Bilingual-Routing-Strategy-B.md
- adr/ADR-009-Shared-Hosting-File-Cache.md
- adr/ADR-015-Post-Theme-Manifest-Binding.md
- adr/ADR-016-Public-Post-URL-and-Theme-Rendering.md
- adr/ADR-017-Public-Page-URL-and-Theme-Rendering.md
- adr/ADR-019-Revision-Audit-OCC-and-Autosave-Foundation.md
- adr/ADR-022-Theme-Discovery-and-Lifecycle-Persistence.md
- adr/ADR-023-Theme-Preview-Contract.md

## Amendment history

| Date | Change |
| --- | --- |
| 2026-08-25 | Initial acceptance (Task 7.1A): Phase 7 V1 contract; foundation vs gap inventory; schema bindings for 7.1B |
