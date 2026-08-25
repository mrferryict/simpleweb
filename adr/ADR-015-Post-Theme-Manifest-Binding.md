# ADR-015 — Post Theme Manifest Binding

**Status:** Accepted  
**Date:** 2026-08-23  
**Amended:** 2026-08-23 (Phase 3 / Task 3.8C — baseline `custom-post` field contract)

## Context

SMITE CMS stores Post dynamic content in `post_translations.content_payload` and requires schema validation before persistence (DOC-02 §12; ADR-004). Content Schema definitions are owned by the Theme Manifest in general (DOC-05 §11; ADR-002).

Authoritative sources already define:

- Every Theme SHALL provide exactly one **Page** template named `custom-page` (REQ-THEME-006; DOC-05 §7; ADR-002).
- Additional templates MAY exist and are developer-defined (REQ-THEME-007).
- The name `article` appears only as an example template under Theme Classic (DOC-05 §7) and as colloquial UX wording (REQ-UX-004) — not as a mandatory Post contract.
- The Post domain conceptual model (DOC-02 §9) lists identity, editorial state, publication, manual author, categories, tags, featured media, localization, SEO, and revisions — **without** a template-binding node (unlike Page in DOC-02 §8).
- DOC-08 §19 sketches `post_translations` as `post_id`, `locale`, `title`, `content_payload`, and “SEO-related values” — **without** `template_key`.
- Phase 3 Post scope (DOC-09) includes create/edit, manual author, Categories, Tags, Featured Image reference, slug, and basic status — **not** Admin Post template selection.
- Phase 3 acceptance (DOC-09) includes **fill Content Schema** / **save Content Payload** for Posts — Posts cannot remain permanently empty-schema.
- ADR-004 requires `PostService` to call `ContentSchemaValidator::validate()` before INSERT/UPDATE of `content_payload`, using schema derived from Theme Manifest.
- ADR-014 states RICH_TEXT is used for formatted editorial content such as news articles (`artikel berita`) and uses `content_payload[body]` only as an **illustrative** Control Panel example — not a pre-existing Post field mandate.

## Decision

The following architecture is **Accepted** for SMITE CMS V1.

### 1. Post template key

Every Theme SHALL provide exactly one Post template named:

```text
custom-post
```

under:

```text
templates.custom-post
```

`custom-page` remains the mandatory **Page** template and is not a Post template.

`article` remains **example-only** and is **not** promoted to a mandatory contract.

### 2. Template storage / resolution

V1 Posts SHALL **not** store `template_key` in the database.

`PostService` SHALL resolve content schema as:

```text
ACTIVE Theme → Theme Manifest → templates.custom-post → fields
```

There is **no** Admin Post template selector in V1.

Rationale:

- Phase 3 Post scope does not define template selection (DOC-09).
- Avoids a migration solely for an undefined selection mechanism.
- Keeps Post foundation aligned with schema-driven validation without inventing Admin UX.

### 3. Content schema ownership

Post `content_payload` SHALL be governed by:

```text
Theme Manifest → templates.custom-post.fields
```

The existing persist pipeline remains authoritative:

```text
Raw content → RichTextSanitizer → ContentSchemaValidator → Persist
```

(ADR-004; ADR-014 for RICH_TEXT).

### 4. Relational fields (not payload)

The following remain relational / application fields and SHALL **not** be duplicated into `content_payload`:

| Field | Notes |
|-------|--------|
| `title` | `post_translations.title` |
| `slug` | `post_translations.slug` |
| `locale` | `post_translations.locale` |
| `manual_author` | `posts.manual_author` (REQ-POST-006) |
| `featured_image_id` | `posts.featured_image_id` (REQ-POST-009; DOC-08 §17) |
| categories | `post_categories` (REQ-POST-007) |
| tags | `post_tags` (REQ-POST-008) |
| lifecycle status | `posts.status` (+ `deleted_at` for Trash) |

### 5. Baseline custom-post Content Schema

**Accepted** (Task 3.8C). Minimal coherent schema — not a generic CMS field catalog.

| Field | Type | Required | Validation | Max / bounds | Kind | Rationale |
|-------|------|----------|------------|--------------|------|-----------|
| `body` | `RICH_TEXT` | `false` | Server-side allowlist sanitization (ADR-014) then ContentSchemaValidator | none beyond sanitizer/validator | **Architectural decision** — NECESSARY FOR V1 PIPELINE COVERAGE + STRONGLY IMPLIED editorial content | DOC-02 §9: Post is an editorial publication; DOC-09 Phase 3 requires fill Content Schema / save Content Payload for Posts; ADR-014: RICH_TEXT for article-style content. The **slot name** `body` is **not** a pre-existing Post REQ — it is formally chosen here, elevating the ADR-014 / DOC-08 illustrative `content_payload[body]` example for V1 `custom-post` only. Optional at draft foundation; publish-time required flags remain DOC-04 deferred. |

#### Explicitly rejected / not accepted as payload fields

| Candidate | Classification | Decision |
|-----------|----------------|----------|
| `excerpt` | EXPLICIT concept (DOC-07 §9) / UNDEFINED storage | **Deferred** — not Manifest; not relational until storage is decided |
| `seo_title`, `seo_description`, `seo_keywords` | EXPLICIT SEO capability (REQ-SEO / DOC-07) / UNDEFINED Post column or Manifest layout | **Deferred** |
| `summary` | UNDEFINED | **Rejected** |
| `content` | UNDEFINED as slot name | **Rejected** (use `body`) |
| `featured_image` | Would duplicate `featured_image_id` | **Rejected** |
| `author` | Would duplicate `manual_author` | **Rejected** |
| Additional TEXT / TEXTAREA / IMAGE / YOUTUBE_URL / URL / DOCUMENT / REPEATABLE slots | Not required by Post sources for baseline | **Rejected** for baseline — Page `custom-page` already exercises the Content Schema engine; do not invent Post business slots merely to re-cover types |

#### Content-type coverage stance

| Type | Baseline `custom-post` |
|------|-------------------------|
| TEXT | Not included |
| TEXTAREA | Not included |
| RICH_TEXT | **Included** (`body`) |
| IMAGE | Not included (featured image is relational) |
| YOUTUBE_URL | Not included |
| URL | Not included |
| DOCUMENT | Not included |
| REPEATABLE | Not included |

## Detailed contract

### Existing source facts (unchanged)

- Hybrid storage: relational core + schema-validated JSON on translations (DOC-02 §12; DOC-08 §19).
- Theme Manifest format remains PHP array `ThemeManifest.php` (ADR-002).
- Native Content Schema validator and V1 field types remain as ADR-004 / REQ-CONT-003.
- Legacy unknown keys in stored payloads are preserved on update via merge (ADR-002 / ADR-004).
- Localization of Post payload remains on `post_translations` (DOC-07 §9; DOC-02 §15).

### Newly accepted architectural decisions

- Mandatory Post template key: **`custom-post`**.
- Fixed resolution: always `custom-post` from the ACTIVE Theme; no `posts.template_key`.
- No V1 Admin Post template selection UI.
- Explicit ban on duplicating the relational fields listed above into `content_payload`.
- Baseline payload field: **`body`** (`RICH_TEXT`, optional at draft) as documented in §5.

### Explicitly deferred

- `excerpt` storage (payload vs relational vs later module).
- SEO column / field layout on `post_translations`.
- Multiple Post templates / Admin selection.
- Public Post rendering templates/views for `custom-post`.
- Publishing-time required-field enforcement for `body` (DOC-04).
- Any expansion of `custom-post.fields` beyond `body`.

## Consequences

### Positive

- Closes the ADR-004 / empty-schema gap for Posts with a minimal, named contract.
- Unblocks Phase 3 / Task 3.8 implementation (Manifest + PostService + form/Quill wiring) without a field catalog.
- Preserves Page/`custom-page` contracts; does not promote example key `article`.
- Avoids an unnecessary `template_key` migration for V1.

### Trade-offs / risks

- Slot name `body` is an ADR architectural choice (elevated from example), not a historical REQ-POST field name — Themes/docs must treat ADR-015 as authoritative for that name.
- Future public rendering will depend on Theme views for `custom-post` (DOC-08 §24) — implication only.
- Themes that omit `custom-post` (or omit `body` once the Manifest is implemented) become invalid once ThemeService / schema resolution enforces this contract.

## Non-goals

This ADR does **not**:

- Implement `custom-post` in `ThemeManifest.php` (implementation = Task 3.8).
- Change Controllers, Views, tests, or migrations by itself.
- Add `excerpt` / SEO columns.
- Implement publishing, revisions, preview, or public Post rendering.
- Change AuthGroups, CSRF, or HTMX contracts.
- Require every V1 Content Item type on Posts.

## Relationship to other ADRs

| ADR | Relationship |
|-----|----------------|
| **ADR-002** | Extends Theme Manifest: mandatory `custom-page` (Pages) **and** mandatory `custom-post` (Posts) with baseline `body` field. Non-destructive payload preservation continues for posts. |
| **ADR-004** | Supplies Manifest template + field map for `PostService` validation. |
| **ADR-013** | Service-layer ownership unchanged. |
| **ADR-014** | `body` RICH_TEXT uses Quill + `RichTextSanitizer`; illustrative `content_payload[body]` example is now the accepted V1 Post slot name under `custom-post`. |

## Amendment history

| Date | Change |
|------|--------|
| 2026-08-23 | Initial acceptance: binding (`custom-post`, no stored `template_key`, relational exclusions). Field list deferred. |
| 2026-08-23 | Task 3.8C: accept baseline schema `{ body: RICH_TEXT, required: false }`; defer excerpt/SEO/type catalog. |

## References

- DOC-01 REQ-POST-*, REQ-CONT-*, REQ-THEME-006/007, REQ-SEO-*
- DOC-02 §8–§12, §15
- DOC-05 §7–§11, §15
- DOC-07 §9, §25
- DOC-08 §19–§21, §24, §31
- DOC-09 Phase 3 Post / Baseline Theme / acceptance gate
- CONTEXT.md Theme + Content Schema
- Phase 3 / Tasks 3.8A–3.8C
