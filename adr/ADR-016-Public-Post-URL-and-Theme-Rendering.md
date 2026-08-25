# ADR-016 — Public Post URL and Theme Rendering Contract

**Status:** Accepted  
**Date:** 2026-08-23  
**Task:** Phase 3 / Task 3.9A

## 1. Status

Accepted for SMITE CMS V1.

This ADR resolves the Task 3.9 blocker **PUBLIC POST URL CONTRACT UNDEFINED**. It is documentation-only; it does not implement routes, Controllers, Services, or Theme views.

## 2. Context

Task 3.9 attempted public `custom-post` rendering and stopped because the V1 public Post URL pattern was not locked.

### Existing documented facts (not invented here)

| Fact | Classification | Source |
| --- | --- | --- |
| Only **PUBLISHED** content is publicly visible/renderable; DRAFT / UNPUBLISHED / ARCHIVED / TRASH are not public | EXPLICIT | DOC-04 §§5–7, 15–17 |
| Primary Language uses root URLs (no locale prefix); Secondary uses `/{code}/...` (Strategy B) | EXPLICIT | DOC-07 §§3–5; ADR-003 |
| V1 language codes include `id` and `en`; Site default Primary is `id` | EXPLICIT | DOC-07 §2; `Config\Site::$defaultLocale` |
| `/id/...` is **not** the Primary URL shape (Primary has no prefix) | EXPLICIT | DOC-07 §3; ADR-003 |
| All current public URLs share **one global namespace** (Page + Post + Category + reserved + active redirects) | EXPLICIT | DOC-07 §§13, 16; ADR-003 |
| Developer owns route structure; Admin owns only slugs inside that structure | EXPLICIT | DOC-07 §14 |
| DOC-07 illustrates Post-like developer routes as `/news/{slug}` and `/en/news/{slug}` | EXPLICIT (example) | DOC-07 §14 |
| Post template key is fixed `custom-post`; no stored `template_key` | EXPLICIT | ADR-015 |
| Theme PHP views live under `app/Views/themes/{theme_id}/` (outside public web root) | EXPLICIT | ADR-013 |
| ADR-013 sketches theme layout as layouts / **templates** / partials / custom-page | STRONGLY IMPLIED | ADR-013 directory sketch |
| RICH_TEXT is stored as server-sanitized HTML; Theme rendering uses that stored HTML | EXPLICIT | ADR-014 |
| Public pipeline: Route → Locale → Resolve resource → Active Theme → Template → Render | EXPLICIT | DOC-08 §24 |
| LocaleFilter detects locale only; content resolution is a separate resolver/service concern | EXPLICIT | ADR-003 |
| `post_translations` uniqueness is `(locale, slug)` | EXPLICIT | Post foundation migration |
| ADR-015 deferred public Post rendering views | EXPLICIT | ADR-015 |

### Gap this ADR closes

DOC-07’s `/news/{slug}` is documented as an **example** of developer-owned routing, not a numbered REQ locking V1. Without an Accepted ADR, implementers must not invent `/post`, `/posts`, `/blog`, or treat the example as mandatory.

## 3. Decision

The following V1 contracts are **Accepted**.

## 4. Public URL Contract

### Accepted V1 Post public paths

| Locale role | Canonical public path |
| --- | --- |
| Primary (`id` in V1 defaults) | `/news/{slug}` |
| Secondary (`en` in V1 defaults) | `/en/news/{slug}` |

Where `{slug}` is the **locale-specific** value from `post_translations.slug` for the resolved locale (after Strategy B resolution / fallback rules in §5).

### Classification and rationale

| Question | Result | Classification |
| --- | --- | --- |
| Is `news` the canonical Post path prefix in V1? | **Yes** | **NEW ARCHITECTURAL DECISION** — elevates DOC-07 §14’s illustrative developer route to the V1 fixed Post collection prefix |
| Is it fixed in V1? | **Yes** — hardcoded in application routing (developer-owned) | STRONGLY IMPLIED by DOC-07 §14 (Admin cannot invent routes) + this ADR |
| Is it Admin-configurable? | **No** in V1 | EXPLICIT ownership rule (DOC-07 §14) |
| Shared with Pages? | **No** — Pages use the Primary/Secondary **root content** namespace (e.g. `/{slug}`, `/en/{slug}` per Strategy B examples); Posts use the **`news` collection** prefix | STRONGLY IMPLIED by DOC-07 examples + global namespace separation needs |
| Can a Page use the same path? | **No** — a Page/Category/other resource MUST NOT claim `/news`, `/news/{slug}`, `/en/news`, or `/en/news/{slug}` as a current public URL | EXPLICIT global uniqueness (DOC-07 §16) + this ADR’s prefix reservation |
| Collisions | Reject at write/publish validation against the global public URL namespace (current paths + active redirects + reserved system/locale paths + this reserved Post prefix) | EXPLICIT (DOC-07; ADR-003) |

### Why not `/posts` or `/blog`?

Those prefixes are **not** used in SMITE source examples. Inventing them would violate Task 3.9 / CONTEXT.md §8 discipline. `/news` is the only Post collection prefix repeatedly exemplified in DOC-07 and ADR-003 secondary examples.

### Why not translate the collection segment (`/berita/...`)?

ADR-003’s `/berita/...` primary example is illustrative of Strategy B shape, not a requirement that the collection segment localize. DOC-07’s paired example keeps **`news` stable** across Primary and Secondary (`/news/...` and `/en/news/...`). V1 locks the **non-localized** segment `news` for routing simplicity.

### Out of scope for this path contract

- Public Post **listing** at `/news` (index/pagination) — deferred
- Category archive URLs — deferred
- RSS / sitemap generation details — deferred (sitemap rules remain ADR-003)

## 5. Locale Contract

V1 locales: **`id`** (Primary by current Site default) and **`en`** (Secondary).

| Topic | Contract | Classification |
| --- | --- | --- |
| Indonesian (Primary) canonical Post URL | `/news/{slug}` | EXPLICIT Strategy B + this ADR’s path |
| English (Secondary) canonical Post URL | `/en/news/{slug}` | EXPLICIT Strategy B + this ADR’s path |
| Is `/id/news/{slug}` a valid Primary URL? | **No** — Primary has no language prefix | EXPLICIT (DOC-07 §3; ADR-003) |
| Is `/en/news/{slug}` valid? | **Yes** for Secondary | EXPLICIT |
| Locale detection | **Route-driven** (locale prefix present ⇒ Secondary; absent ⇒ Primary). LocaleFilter must not perform content lookup | EXPLICIT (ADR-003) |
| Missing Secondary translation | Resolve Primary translation content for the corresponding Post (deterministic fallback). Do **not** auto-create a Translation row. Canonical for fallback responses points to the Primary URL | EXPLICIT (DOC-07 §§8, 20–22; ADR-003) |
| Missing Primary translation for a publicly requested Primary URL | Treat as not publicly renderable → same public not-found behavior as §8 | STRONGLY IMPLIED (Primary translation required for publishable content — DOC-07 §6) |
| Fallback-only Secondary URL | Not an independent translated document; no independent hreflang/sitemap as Secondary translation | EXPLICIT (ADR-003) |

Changing Primary/Secondary codes later is a high-impact config change (DOC-07 §39) and must re-validate reserved prefixes; it does not by itself redefine the `news` segment.

## 6. Theme Rendering Contract

### Template key (unchanged)

```text
ACTIVE Theme → Theme Manifest → templates.custom-post
```

(ADR-015 — EXPLICIT)

### Public view path (locked here)

For template key `custom-post`, the deterministic public Theme view is:

```text
app/Views/themes/{activeThemeId}/templates/custom-post.php
```

CI4 view name:

```text
themes/{activeThemeId}/templates/custom-post
```

Example for the baseline theme:

```text
app/Views/themes/default/templates/custom-post.php
```

| Topic | Contract | Classification |
| --- | --- | --- |
| Mapping | Manifest template key `custom-post` → filesystem `templates/custom-post.php` under the active theme | **NEW ARCHITECTURAL DECISION**, consistent with ADR-013’s `themes/default/(Layouts, templates, partials, custom-page)` sketch |
| Who resolves the path | ThemeService (or a thin Theme rendering helper owned by ThemeService) using `activeThemeId()` — Controllers/Views must not `require` ThemeManifest.php | STRONGLY IMPLIED (ADR-002 / existing ThemeService abstraction) |
| Baseline field to render | `body` (RICH_TEXT) only for V1 public Post foundation | EXPLICIT (ADR-015) |
| Relational fields on public view | `title` and `manual_author` MAY be rendered (public-facing by DOC-01 REQ-POST-006 / domain model). Do **not** render internal ids, status, or unpublished metadata | STRONGLY IMPLIED |
| RICH_TEXT output | Render persisted sanitized HTML for `body` (not `esc()` as plain text). Persist-time `RichTextSanitizer` remains the authoritative XSS boundary; do not invent a second sanitizer pipeline | EXPLICIT (ADR-014) |
| Defensive rendering | Theme views MUST tolerate missing/unknown payload keys without fatal errors (ADR-002 defensive payload rule) | EXPLICIT |
| Do not create the file in this ADR task | Implementation belongs to Task 3.9 | — |

Future Page public views SHOULD follow the same convention (`templates/custom-page.php`) for consistency; Page public rendering remains a separate implementation task.

## 7. Ownership / Boundaries

```text
Public HTTP request
  → Route (fixed `/news/{slug}` or `/en/news/{slug}`)
  → Locale context (route-driven; LocaleFilter if present)
  → Public Controller (e.g. Controllers/Site/* per ADR-013 sketch)
       → PostService public lookup (PUBLISHED only; locale/fallback rules)
       → ThemeService active theme + custom-post view resolution
       → Theme view with prepared view-model
  → HTTP response
```

| Layer | May | Must not |
| --- | --- | --- |
| Controller | Read route params; call Services; map not-found to HTTP 404; pass prepared view-model | Query DB; parse `content_payload`; sanitize HTML; load ThemeManifest directly; invent visibility rules |
| PostService | Authoritative public Post resolution and lifecycle visibility | Render Theme HTML |
| ThemeService | Active theme id, Manifest schema metadata, deterministic view path for `custom-post` | Authorize editorial users; own Post status rules |
| Theme view | Present prepared fields (`title`, `manual_author`, sanitized `body`) | Query DB; parse Manifest; call Models; trust request input as HTML |

This matches ADR-013 layering and DOC-08 §24.

## 8. Not-Found / Privacy Contract

For public Post requests, the following SHALL produce the **same indistinguishable public not-found outcome** (HTTP **404** via the project’s established error handling — DOC-08 §49):

- nonexistent `/news/{slug}` (or secondary equivalent)
- Post exists but status is DRAFT, UNPUBLISHED, ARCHIVED, or TRASH
- Post cannot be resolved safely for the requested Primary URL (e.g. missing required Primary translation)

| Rule | Classification |
| --- | --- |
| Non-PUBLISHED must not render | EXPLICIT (DOC-04) |
| Response must not reveal whether a private/non-public Post exists (no status leakage, no id leakage, no distinct “unpublished” public page) | **NEW ARCHITECTURAL DECISION** refining DOC-08 production error opacity for this resource class |
| Do not expose stack traces, SQL, or filesystem paths | EXPLICIT (DOC-08 §49) |

**Exception (not a 404):** Secondary URL with missing Secondary translation but resolvable Primary PUBLISHED Post → Strategy B **fallback render** (DOC-07 / ADR-003), not not-found.

## 9. Namespace / Collision Contract

| Topic | Contract | Classification |
| --- | --- | --- |
| Namespace model | **One global public URL namespace** shared by Pages, Posts, Categories, reserved system routes, locale prefixes, and active historical redirects | EXPLICIT |
| Route patterns | **Separate patterns**, shared uniqueness: Posts under `/news/...`; Pages under Strategy B root paths (exact Page hierarchy routing remains a Page implementation concern) | EXPLICIT namespace + this ADR’s Post prefix |
| Reserved Post prefix | Path prefixes `news` and `en/news` are reserved for Post public routing in V1 | **NEW ARCHITECTURAL DECISION** |
| Collision rejection | Creating/changing a published (or otherwise current-public) resource path that equals an existing current public path, active redirect source, reserved system/locale path, or reserved Post prefix path SHALL fail validation | EXPLICIT (DOC-07; ADR-003) + this ADR |
| Route precedence (implementation note) | When public routing is implemented, the fixed `/news/{slug}` and `/en/news/{slug}` routes MUST be registered so they cannot be captured by a generic Page catch-all | STRONGLY IMPLIED |
| Slug uniqueness vs URL uniqueness | Translation slug uniqueness remains **per locale** (`(locale, slug)`). Global uniqueness is enforced on the **full public path**, not the bare slug string alone. Therefore `/news/foo` (Primary) and `/en/news/foo` (Secondary) are distinct public URLs and may legally share the slug token `foo` across locales | EXPLICIT (migration + DOC-07 global path uniqueness) |

If future work discovers that Page two-level hierarchy or Category URLs need additional reserved segments, that requires a separate ADR/amendment — not silent reinterpretation of this Post prefix.

## 10. Consequences

### Positive

- Unblocks Phase 3 / Task 3.9 public `custom-post` rendering without inventing `/post` or `/blog`.
- Keeps Admin out of route design; preserves developer-owned Theme/view layout.
- Aligns Post URLs with Strategy B and the global namespace already Accepted in ADR-003.
- Gives ThemeService a deterministic view path parallel to Manifest template keys.

### Trade-offs / risks

- Locking `news` is a product-facing path choice elevated from documentation examples; renaming later is a breaking public URL change (redirects required).
- Post listing at `/news` is still undefined — implementers must not assume an index exists.
- Full SlugService global collision checking across Pages/Posts/redirects may land partly in Phase 7; Task 3.9 may implement Post routes first while recording incomplete cross-type collision enforcement as follow-up if Page public routes are not yet live.

## 11. Deferred Decisions

- Public Post index/listing, pagination, RSS
- Category public URLs
- Page public catch-all / hierarchy routing implementation
- SEO field rendering, hreflang emission, sitemap entries (rules exist in ADR-003; Post wiring deferred)
- Admin Theme activation UI / multiple themes
- Publishing/review/revision workflows (required to create real PUBLISHED content through product UX)
- Render-time re-sanitization (rejected here; ADR-014 persist-time boundary stands)
- Configurable Post path prefix (rejected for V1)

## 12. References

- CONTEXT.md (§ Theme, Localization, No Silent Architecture Changes)
- docs/01-Product-Requirements.md (REQ-POST-*, REQ-LOC-*)
- docs/02-Domain-Model.md
- docs/04-Content-Publishing.md
- docs/05-Theme-Template-Architecture.md
- docs/07-Localization-URL-SEO.md
- docs/08-Technical-Architecture.md (§24, §49)
- docs/09-Implementation-Blueprint.md
- adr/ADR-002-Single-Active-Theme-Manifest.md
- adr/ADR-003-Bilingual-Routing-Strategy-B.md
- adr/ADR-004-Native-Content-Schema-Validator.md
- adr/ADR-013-Standard-Layered-CI4-Architecture.md
- adr/ADR-014-Quill-Alpine-RichText-Integration.md
- adr/ADR-015-Post-Theme-Manifest-Binding.md

## Amendment history

| Date | Change |
| --- | --- |
| 2026-08-23 | Initial acceptance (Task 3.9A): V1 `/news/{slug}` + `/en/news/{slug}`; Theme view `templates/custom-post.php`; ownership; indistinguishable 404; global namespace + reserved `news` prefix. |
