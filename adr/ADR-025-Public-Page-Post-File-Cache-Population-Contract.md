# ADR-025 — Public Page/Post File Cache Population Contract

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 8 / Task 8.1A

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not implement Controllers, Services, routes, views, migrations, tests, or AuthGroups changes.

It binds Phase 8 Task 8.1B **population / read** semantics for public Page and Post File Cache, following:

- **DOC-09 §13** — Phase 8 Scheduling & Caching
- **DOC-01** — REQ-CACHE-001 → REQ-CACHE-004
- **DOC-08 §§40–43, §70** — Cache architecture, keys, invalidation, preview isolation, graceful fallback
- **DOC-05 §31 / §20** — Theme-aware keys; Preview must not populate public cache
- **DOC-10 §44** — Cache hit / miss / invalidation / Preview bypass tests
- **ADR-009** — FileHandler, theme-aware keys, fully resolved public data, post-commit invalidation, Preview isolation, TTL as safety net
- **ADR-016 / ADR-017** — PUBLISHED-only public Page/Post resolution
- **ADR-022 / ADR-023** — ACTIVE Theme; Preview cache bypass
- **ADR-024** — Locale in public cache keys; Phase 8 population was deferred from Phase 7

Where this ADR binds an ambiguity, the binding is labeled **NEW**.

## 2. Classification Key

| Label | Meaning |
| --- | --- |
| EXPLICIT SOURCE FACT | Written in DOC/ADR |
| STRONGLY IMPLIED | Necessary consequence of those facts |
| NEW | V1 binding of an ambiguity |
| DEFERRED | Out of V1 Task 8.1B / later phase |
| FOUNDATION COMPLETE | Already implemented; Phase 8 must not redesign |

## 3. Decision Summary

| Decision | Classification | Accepted V1 result |
| --- | --- | --- |
| Backend | EXPLICIT (ADR-009; REQ-CACHE-003) | CI4 **FileHandler** only; `writable/cache/`; **no Redis / Memcached / CDN / cache table** |
| Cacheable content | EXPLICIT (ADR-016/017; DOC-08) | **PUBLISHED** Page/Post public resolutions only |
| Never cache | EXPLICIT / STRONGLY IMPLIED | DRAFT, PENDING_REVIEW, UNPUBLISHED, ARCHIVED, TRASH; Admin Preview; redirect responses; non-public lookups |
| Population location | EXPLICIT (ADR-009 Compliance) | **PageService** / **PostService** public lookup paths (`findPublishedForPublic`) — not Controllers |
| Cached abstraction | EXPLICIT (ADR-009) + NEW | Fully public-resolved **cache entry** containing the public view DTO **and** resolved SEO DTO (see §7) |
| Population keys | EXPLICIT (ADR-009) + NEW (FileHandler) | Dot-safe ADR-009 shape: `content.page.{themeId}.{locale}.{slug}` / `content.post.{themeId}.{locale}.{slug}` |
| Locale in key | EXPLICIT (ADR-024 §15; ADR-009) | **Required** — requested public locale (including fallback requests) |
| Theme in key | EXPLICIT (ADR-009; DOC-05 §31) | **Required** — ACTIVE Theme id at write time |
| Slug in key | EXPLICIT (ADR-009) | **Required** — slug used for the public request path segment |
| Phase 4 id keys | FOUNDATION COMPLETE + NEW | Retain `page.public.{id}` / `post.public.{id}` as **invalidation reverse-index** keys (not sole population keys) |
| Invalidation timing | FOUNDATION COMPLETE (ADR-009) | Post-commit only; Controllers must not delete cache |
| Negative caching (404/null) | UNDEFINED → DEFERRED | **Do not** cache null / 404 / failed lookups in V1 |
| Redirect caching | STRONGLY IMPLIED (ADR-024) | Redirect lookup stays **outside** content cache; redirects are **not** stored as Page/Post cache entries |
| TTL | EXPLICIT range (ADR-009) + NEW | Safety-net TTL **3600 seconds (1 hour)** for public content population writes |
| org_id in key | EXPLICIT product scope (CONTEXT) | **Not required** — SMITE CMS V1 is single-organization / single-website |
| Observability | UNDEFINED → DEFERRED | No hit/miss metrics/debug headers required for V1 Task 8.1B |
| Schema / migration | STRONGLY IMPLIED | **None** |

## 4. Phase 4 Foundation Already Complete (Do Not Redesign)

Task 8.1B **must reuse**:

| Area | Evidence |
| --- | --- |
| Invalidator | `App\Services\Cache\PublicContentCacheInvalidator` |
| Id invalidation keys | `page.public.{id}`, `post.public.{id}` |
| Theme activation fan-out | `invalidateThemePresentation()` deletes `theme.active` + `deleteMatching` on `content.*`, `nav.*`, `page.public.*`, `post.public.*` |
| Post-commit calls | `PageService` / `PostService` / `ScheduledContentService` / `ThemeService` after successful commit |
| FileHandler | `Config\Cache::$handler = 'file'` |
| Colon prohibition | CI4 FileHandler rejects `:`; Phase 4 already uses **dots** |
| Public lookup | `PageService::findPublishedForPublic()`, `PostService::findPublishedForPublic()` — PUBLISHED-only, fallback, media resolution into DTOs |
| Preview isolation | ADR-023 — bypass read/write of public cache |

Phase 4 did **not** populate cache on public read. That is Task 8.1B.

## 5. Why This ADR Exists (Material Gaps)

ADR-009 already defined population intent, but Phase 8 cannot implement safely without binding:

1. **Key collision with live invalidation** — Phase 4 invalidates `page.public.{id}` while ADR-009 population keys are theme+locale+slug. **NEW:** reverse-index bridge (§8).
2. **FileHandler key charset** — ADR-009 examples use `:`; live code uses `.`. **NEW:** V1 keys are **dot-separated** ADR-009 semantics.
3. **SEO placement** — Live Controllers call `SeoService` after Service DTOs; ADR-009 requires SEO resolution inside cached public-resolved data. **NEW:** cache entry includes resolved `PublicSeoViewDto` (§7).
4. **ADR-024 locale taxonomy** — deferred from Phase 7 to Phase 8; now bound here.
5. **Exact TTL** — ADR-009 gives 1–24h range only; Config default `ttl = 60` is not a public-content correctness value. **NEW:** 3600s safety-net TTL for population `save()`.

## 6. Cacheable Content Contract

| Content | Cacheable? |
| --- | --- |
| PUBLISHED Page public resolution | **Yes** |
| PUBLISHED Post public resolution | **Yes** |
| Secondary-locale real translation | **Yes** (when secondary enabled and row exists) |
| Secondary-locale **fallback** (`isFallback=true`) | **Yes** — under the **requested** locale key (§9) |
| DRAFT / PENDING_REVIEW / UNPUBLISHED / ARCHIVED / TRASH | **No** |
| Theme Preview (`findForThemePreview`) | **No** — must not read or write public cache |
| Redirect targets / 301 responses | **No** — not Page/Post content entries |
| Disabled secondary public routes | **No population** — lookup must return null / 404 without writing cache |
| Sitemap / robots responses | **Out of Task 8.1B scope** (may remain uncached) |

## 7. Cache Value Contract

**NEW binding for V1 Task 8.1B:**

Each successful public Page/Post resolution may store a single cache entry whose payload is a **serializable public cache package** containing:

1. The existing public view DTO (`PublicPageViewDto` / `PublicPostViewDto`) — already includes content payload, media map / body, locale, slug, `isFallback`, template key, and translation SEO field sources.
2. The resolved SEO presentation DTO (`PublicSeoViewDto`) produced by `SeoService` for that view — document title, meta description, absolute canonical, hreflang alternates, x-default, OG image URL.

Rationale:

- ADR-009 EXPLICITLY requires caching after media URL resolution **and** SEO resolution.
- Theme templates must continue to receive Service/DTO-supplied SEO without Theme DB access (ADR-024).
- Controllers may unpack the package on HIT and skip both DB content lookup and SEO rebuild for that request.

**Not stored:**

- Raw DB Entity rows as the sole representation
- Rendered HTML (Theme views remain request-time; ACTIVE Theme template path is selected using `templateKey` + ACTIVE Theme)
- Session / CSRF / auth / request headers
- Admin Preview packages

**NEW:** Introducing a thin readonly package DTO (e.g. `PublicPageCacheEntry` / `PublicPostCacheEntry`) in Task 8.1B is allowed and preferred over ad-hoc arrays.

## 8. Cache Key Contract

### 8.1 Population keys (canonical)

FileHandler-safe adaptation of ADR-009:

```text
content.page.{themeId}.{locale}.{slug}
content.post.{themeId}.{locale}.{slug}
```

Where:

- `{themeId}` = ACTIVE Theme id at write time (`ThemeService::activeThemeId()`)
- `{locale}` = **requested** public locale for the lookup (primary or secondary), including fallback requests
- `{slug}` = normalized public slug segment used for the lookup (Page) or Post slug under `/news/` (Post) — **not** the full path string

**Forbidden in keys:** `:` characters.

### 8.2 Invalidation reverse-index keys (Phase 4 preserved)

```text
page.public.{pageId}
post.public.{postId}
```

**NEW bridge:**

On successful population write for a Page/Post:

1. Save the population key (§8.1) with the cache package.
2. Update the reverse-index key (§8.2) so `invalidatePage` / `invalidatePost` can delete **all** population keys for that id (all locales / prior slugs still referenced).

Minimum acceptable reverse-index representation (implementation detail for 8.1B):

- JSON list of population key strings currently associated with that id, **or**
- Equivalent deterministic structure that guarantees invalidate-by-id removes every live population entry for that resource.

On `invalidatePage(id)` / `invalidatePost(id)`:

1. Read reverse-index (if present).
2. Delete each listed population key.
3. Delete the reverse-index key itself.

On Theme activation, existing `invalidateThemePresentation()` `deleteMatching('content.*')` remains authoritative and must continue to clear population keys even if reverse-index is stale.

### 8.3 Compatibility with ADR-024

Slug-based keys **must** include locale so primary and secondary (and fallback-under-secondary-request) cannot collide (ADR-024 §15 — now bound, no longer deferred).

## 9. Locale Contract

| Case | Behavior |
| --- | --- |
| Primary request | Key locale = primary; cache on successful PUBLISHED resolution |
| Enabled secondary + real translation | Key locale = secondary; cache real translation package |
| Enabled secondary + fallback | Key locale = secondary; package has `isFallback=true`; SEO package must already reflect primary canonical / no false secondary hreflang |
| Disabled secondary | Public lookup fails before cache write; **no** secondary population |
| `/id/...` | Rejected by `LocaleFilter` — never reaches content cache population |
| Locale Settings change (primary/secondary enable/disable) | **NEW:** MUST invalidate public presentation caches (at minimum `content.*` via existing Theme-style fan-out or dedicated invalidator method). Settings UI/`SettingService` did not do this in Phase 7 — Task 8.1B must close this gap when wiring population |

## 10. Public Lookup Flow (Task 8.1B)

**NEW ordered algorithm** for `findPublishedForPublic` (Page and Post), after Controllers have already performed redirect lookup:

```text
1. Resolve ACTIVE themeId (for key construction only; Preview paths must not enter here)
2. Build population key from themeId + requestedLocale + normalizedSlug
3. Cache GET
4. HIT + valid package → return view DTO (Controller uses packaged SEO; no DB content re-query)
5. MISS / corrupt / unreadable → existing DB public resolution pipeline:
     reserved-path checks → translation lookup → PUBLISHED parent check →
     secondary fallback rules → media resolution → Public*ViewDto
6. If resolution is null → return null (do NOT write cache)
7. If resolution succeeds → SeoService resolve → build package → Cache SAVE (best-effort) → return DTO
```

Redirect handling remains in Controllers **before** calling `findPublishedForPublic` (FOUNDATION COMPLETE). Redirects never write content cache entries.

## 11. Cache Hit / Miss Semantics

| Event | Behavior |
| --- | --- |
| HIT (valid package) | Bypass DB content/translation lookup and SEO rebuild for that request; Theme view still renders from DTO + packaged SEO |
| HIT (corrupt / wrong shape / unserialize failure) | Treat as MISS; log at most `warning`/`error` without PII; regenerate from DB (ADR-009 / DOC-08 §70) |
| MISS | Full public resolution; write only on successful PUBLISHED package |
| Cache read backend failure | Fall back to DB; public response must still succeed when DB is healthy |
| Cache write failure | Still return successful DB-resolved result; write is **best-effort** and must not fail the HTTP response |

HIT must **not** bypass:

- Controller redirect lookup
- LocaleFilter / disabled-secondary 404
- ACTIVE Theme template path selection using `templateKey` (template file comes from filesystem Theme package; key already scoped by themeId)

## 12. Theme Contract

- Population keys include ACTIVE `themeId` (EXPLICIT ADR-009 / DOC-05).
- Cached package stores `templateKey` and content/media/SEO data — **not** rendered HTML.
- Theme activation continues to call `invalidateThemePresentation()` (FOUNDATION COMPLETE).
- Preview must not read/write these keys (ADR-023).

## 13. SEO / Media Contract

| Concern | Binding |
| --- | --- |
| SEO | Resolved by `SeoService` **before** cache write; packaged `PublicSeoViewDto` served on HIT |
| Media | Page `contentMedia` (and Post body already sanitized) resolved before write; HIT uses packaged media URLs |
| Stale SEO/media | Correctness via invalidation on content/SEO/media-affecting mutations + TTL safety net |
| Theme DB access | Still forbidden — Theme only consumes DTOs |

## 14. Invalidation Contract (Population Compatibility)

Existing post-commit invalidation **remains** the correctness mechanism. Task 8.1B must ensure reverse-index-aware deletes for population keys.

| Mutation | Existing invalidation | 8.1B requirement |
| --- | --- | --- |
| Page update / publish / unpublish / archive / trash / restore / permanent delete / revision restore | `invalidatePage` | Must clear reverse-index + population keys |
| Post update / publish / unpublish / archive / review publish / trash / restore / permanent delete / revision restore | `invalidatePost` | Same |
| Published slug / translation / SEO field changes | Covered by update invalidation | Same |
| Scheduled publish/unpublish | `ScheduledContentService` invalidates | Same |
| Theme ACTIVE change | `invalidateThemePresentation` | Continues to wipe `content.*` |
| Site locale Settings change | **Gap today** | **NEW:** invalidate public content presentation (`content.*` at minimum) |
| Media asset change affecting public fields | Partially policy (DOC-06) | Prefer targeted invalidation of affected Page/Post when dependency known; do not invent full-site flush for editorial media edits |

Homepage/category listing caches remain **out of Task 8.1B** unless already keyed; ADR-009 mentions them but V1 public listing population is not required to ship in 8.1B.

## 15. Security Contract

- Shared public cache only; no per-user / session / CSRF state.
- No draft/admin/Preview data in public keys.
- User input may influence slug/locale only through the same validated public lookup path; keys are built server-side after normalization — clients cannot select arbitrary resource packages by forging cache keys directly.
- Disabled secondary and `/id/...` remain non-public regardless of any stale key (invalidation + locale filters + TTL).

## 16. Failure / Recovery Contract

| Failure | Behavior |
| --- | --- |
| Corrupt cache file | Treat as MISS; regenerate (EXPLICIT ADR-009) |
| Cache unavailable | Fall back to DB (EXPLICIT DOC-08 §70) |
| Write fails | Response still OK from DB (NEW best-effort) |
| DB unhealthy | Existing public error handling — cache cannot invent content |

## 17. TTL / Backend Contract

| Item | Binding |
| --- | --- |
| Backend | FileHandler / `writable/cache/` only |
| Redis/Memcached/CDN/cache table | **Prohibited** for V1 |
| Correctness | Explicit invalidation |
| Safety-net TTL | **3600** seconds on public Page/Post population `save()` |
| `$cache->clean()` | Still forbidden on editorial paths (ADR-009) |

## 18. Multi-Tenant Contract

CONTEXT.md: SMITE CMS V1 is **single-organization, single-website**.

**NEW:** Population keys do **not** include `org_id`. Resource ids and public slugs are scoped by this single deployment.

## 19. Observability Contract

Hit/miss counters, debug headers, and metrics are **DEFERRED** (UNDEFINED in V1 requirements for mandatory delivery). DOC-10 requires tests for hit/miss behavior — PHPUnit assertions suffice for Task 8.1B.

## 20. Migration Impact

**None.** No new tables. Expected table count remains **23**; latest App migration remains batch **10** unless an unrelated future task adds schema.

## 21. Permissions / Audit

No new permissions. No new audit events for cache hit/miss/write.

## 22. Non-Goals (Task 8.1B)

- HTML full-page cache / CI4 PageCache filter as the public content store
- Category/homepage listing population (unless already trivial reuse)
- Sitemap/robots response caching
- Negative caching
- Redis / CDN
- Multi-tenant org key schemes
- Rewriting ADR-009 historical text

## 23. Consequences

### Positive

- ADR-009 population intent becomes implementable against live FileHandler + Phase 4 invalidation.
- Locale/theme/slug collisions are prevented (ADR-024 + DOC-05).
- SEO/media remain consistent on HIT without Theme DB access.

### Trade-offs

- Reverse-index maintenance adds write complexity on population and invalidation.
- Caching fallback under secondary locale keys requires careful SEO packaging and invalidation when primary translations change.

## 24. References

- CONTEXT.md (single-site identity; Theme/Preview cache notes)
- docs/01-Product-Requirements.md (REQ-CACHE-*)
- docs/05-Theme-Template-Architecture.md (§§20, 31)
- docs/08-Technical-Architecture.md (§§40–43, 70)
- docs/09-Implementation-Blueprint.md (§13)
- docs/10-Testing-Quality-Strategy.md (§44)
- adr/ADR-009-Shared-Hosting-File-Cache.md
- adr/ADR-016 / ADR-017 / ADR-022 / ADR-023 / ADR-024
- `app/Services/Cache/PublicContentCacheInvalidator.php`
- `PageService::findPublishedForPublic()` / `PostService::findPublishedForPublic()`
