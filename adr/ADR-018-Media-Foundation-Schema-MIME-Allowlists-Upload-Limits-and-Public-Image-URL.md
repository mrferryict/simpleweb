# ADR-018 — Media Foundation Schema, MIME Allowlists, Upload Limits & Public Image URL

**Status:** Accepted  
**Date:** 2026-08-23  
**Task:** Phase 4 / Task 4.5B

## 1. Status

Accepted for SMITE CMS V1.

This ADR closes the Task 4.5A blocker **MEDIA CONTRACT UNDEFINED**. It is **documentation-only**; it does not create migrations, Media services, Controllers, routes, ThemeManifest changes, or storage directories.

It **reaffirms** ADR-007 where that ADR is already Accepted, and **explicitly resolves** schema, MIME, size, URL, and empty-`media_profiles` gaps that DOC-06 / DOC-08 left incomplete.

## 2. Context

Task 4.5A verified that Media Library sources exist (DOC-01/02/03/06, ADR-004/007) but several material values were undefined or conflicting:

| Gap | Problem |
| --- | --- |
| `media_assets` columns | DOC-06 conceptual fields; DOC-08 names the table but not the CREATE TABLE |
| Token column | ADR-007 `download_token` vs DOC-06/08 `download_hash` |
| IMAGE MIME allowlist | Required by DOC-03/06; never enumerated |
| SVG | Undocumented |
| DOCUMENT MIME map | Formats named (PDF, Office); MIME strings not mapped |
| Numeric upload caps | Profile/`upload limits` mentioned; no application numbers |
| Image FS root | ADR-007 `public/uploads/images/` vs DOC-08 `writable/uploads/images/` sketch |
| Public image URL | “controlled public asset URLs” without a path pattern |
| Empty `media_profiles` | ADR-004 requires profile fit; baseline Theme Manifest has `media_profiles: []` |

## 3. Decision Summary

The following V1 contracts are **Accepted**.

## 4. Token Naming (resolves DOC-06/08 vs ADR-007)

| Topic | Accepted V1 result |
| --- | --- |
| Canonical DB column | **`download_token`** |
| Classification | EXPLICIT (ADR-007) + this ADR **supersedes** DOC-06/08 terminology `download_hash` for the same concept |
| Generation | `bin2hex(random_bytes(16))` → 32 lowercase hex chars (ADR-007) |
| Uniqueness | UNIQUE INDEX on `media_assets.download_token` |
| Images | Column **NULL** (images are not downloaded via this token) |
| Documents | Column **NOT NULL** after successful create |
| Public URL | Raw token in path: `GET /download/document/{download_token}` (ADR-007) |
| Secrecy | The token is an unguessable capability secret; treat as sensitive identifier (do not log in full in production diagnostics) |
| Lookup | Exact match on `download_token` + `type = DOCUMENT` + lifecycle/public-access rules |

Implementers MUST NOT create a second synonym column named `download_hash`.

Where older docs say `download_hash`, read **`download_token`**.

## 5. Image Storage Root (resolves ADR-007 vs DOC-08)

| Topic | Accepted V1 result |
| --- | --- |
| Canonical image root | **`public/uploads/images/`** |
| Classification | EXPLICIT reaffirmation of **ADR-007** |
| DOC-08 tree | Treated as **illustrative / non-authoritative** for images; the `writable/uploads/images/` sketch MUST NOT override ADR-007 |
| Document root | **`writable/uploads/documents/`** (EXPLICIT ADR-007 / DOC-06) — unchanged |
| Temp processing | Outside public web root (DOC-06 §39); exact temp subdir deferred to implementation under `writable/` |

## 6. Public Image URL

| Topic | Accepted V1 result | Classification |
| --- | --- | --- |
| Pattern | **`/uploads/images/{storage_key}`** | **NEW ARCHITECTURAL DECISION** |
| `{storage_key}` | The same value stored in `media_assets.storage_key` (filename only; includes extension; never a path segment with `/`) | NEW + EXPLICIT generated-identity rule (DOC-06 §16) |
| Locale prefixes | Irrelevant — image URLs are **not** localized content routes | STRONGLY IMPLIED (ADR-003/016/017 concern Pages/Posts) |
| Auth | Public GET for ACTIVE images only (web server static); no Control Panel session required | EXPLICIT (ADR-007 browser-served) |
| V1 image Controller | **None** for serving bytes — Nginx/Apache serves `public/uploads/images/` | EXPLICIT (ADR-007) |
| Must not expose | Absolute FS paths, `original_filename`, internal IDs in the URL | EXPLICIT |
| TRASH / non-ACTIVE | Must not be newly referenced; public rendering of content using trashed media is a later presentation rule — files SHOULD be removed or made unreachable on permanent delete; TRASH soft-delete may leave file until permanent delete (DOC-06) |

Reserved public path prefix **`uploads`** is part of the global URL namespace (DOC-07): Pages/Posts MUST NOT claim `/uploads` or `/uploads/...`.

## 7. Document Download URL

Unchanged from ADR-007:

```text
GET /download/document/{download_token}
```

Stream via CI4 `$this->response->download($filePath, null)`. Never expose `writable/` paths.

## 8. V1 `media_assets` Schema

Exact migration target for Task 4.5. Classification: conceptual fields EXPLICIT (DOC-06); **column types/nullability/indexes = NEW ARCHITECTURAL DECISION** filling the DOC-08 schema gap.

Primary key style follows DOC-08 §15 (BIGINT UNSIGNED AUTO_INCREMENT).

| Column | SQL type | Null | Default | Index | Meaning |
| --- | --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | NO | AI | PK | Authoritative `media_id` |
| `type` | `VARCHAR(16)` | NO | — | INDEX | `IMAGE` or `DOCUMENT` |
| `title` | `VARCHAR(200)` | YES | NULL | — | Editable metadata |
| `description` | `TEXT` | YES | NULL | — | Editable metadata |
| `alt` | `VARCHAR(255)` | YES | NULL | — | Default alt (images); may be null |
| `original_filename` | `VARCHAR(255)` | NO | — | — | User-facing metadata only; never FS path |
| `storage_key` | `VARCHAR(128)` | NO | — | UNIQUE | Generated filename under type-specific root |
| `mime_type` | `VARCHAR(127)` | NO | — | — | Stored MIME of **persisted** file |
| `extension` | `VARCHAR(16)` | NO | — | — | Normalized lowercase extension without dot |
| `file_size` | `INT UNSIGNED` | NO | — | — | Bytes of **stored** file (processed master for IMAGE; stored document for DOCUMENT) |
| `width` | `INT UNSIGNED` | YES | NULL | — | IMAGE only after processing; NULL for DOCUMENT |
| `height` | `INT UNSIGNED` | YES | NULL | — | IMAGE only after processing; NULL for DOCUMENT |
| `download_token` | `CHAR(32)` | YES | NULL | UNIQUE | DOCUMENT only; NULL for IMAGE |
| `status` | `VARCHAR(16)` | NO | `'ACTIVE'` | INDEX | `ACTIVE` or `TRASH` |
| `uploaded_by` | `BIGINT UNSIGNED` | YES | NULL | INDEX | Shield user id; NULL if unknown |
| `created_at` | `DATETIME` | YES | NULL | — | CI4 timestamps |
| `updated_at` | `DATETIME` | YES | NULL | — | CI4 timestamps |
| `deleted_at` | `DATETIME` | YES | NULL | INDEX | Set when status → TRASH |

**No** `public_url` column. URLs are derived (ADR-007 / this ADR).

**No** content-hash dedup column in V1.

FK from `posts.featured_image_id` → `media_assets.id` may be added when Media exists (DOC-08); Task 4.5 may add that FK in a dedicated migration step if safe.

## 9. IMAGE MIME / Extension Policy

Validation MUST require agreement of **declared MIME**, **extension**, and **file signature / image structure** (DOC-03 §18; DOC-06 §10). Extension alone is never sufficient.

### Allowed IMAGE uploads (V1)

| Extension | Accepted MIME type(s) |
| --- | --- |
| `jpg`, `jpeg` | `image/jpeg` |
| `png` | `image/png` |
| `webp` | `image/webp` |
| `gif` | `image/gif` |

**Classification:** Format family STRONGLY IMPLIED by CMS image practice + ADR-007 output discussion; **exact allowlist = NEW ARCHITECTURAL DECISION**.

### SVG policy

| Topic | Result | Classification |
| --- | --- | --- |
| SVG upload | **REJECTED** in V1 | **NEW ARCHITECTURAL DECISION** |
| Extensions rejected | `.svg`, `.svgz` | NEW |
| MIMEs rejected | `image/svg+xml`, `image/svg` | NEW |

Rationale: SVG is XML/scriptable and is not covered by the GD raster pipeline; accepting it would invent an unsafe HTML-in-image surface without a documented sanitizer.

### Processed output (unchanged intent from ADR-007)

After GD processing, store WebP when supported; otherwise JPEG. Update `mime_type` / `extension` / `storage_key` to match the **stored** master, not the discarded original.

## 10. DOCUMENT MIME / Extension Policy

V1 formats from DOC-06 §19 only — **not broadened**.

| Format | Extension | Accepted MIME type(s) |
| --- | --- | --- |
| PDF | `pdf` | `application/pdf` |
| DOC | `doc` | `application/msword` |
| DOCX | `docx` | `application/vnd.openxmlformats-officedocument.wordprocessingml.document` |
| XLS | `xls` | `application/vnd.ms-excel` |
| XLSX | `xlsx` | `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` |
| PPT | `ppt` | `application/vnd.ms-powerpoint` |
| PPTX | `pptx` | `application/vnd.openxmlformats-officedocument.presentationml.presentation` |

**Classification:** Format names EXPLICIT (DOC-06); **MIME↔extension map = NEW ARCHITECTURAL DECISION**.

MIME + extension + signature (where applicable) MUST agree. Executable/script types (`.php`, `.phtml`, `.exe`, `.sh`, `.js`, etc.) MUST be rejected.

Documents are stored as uploaded (validated) bytes under `writable/uploads/documents/` with generated `storage_key` — no GD pipeline.

## 11. Maximum Upload Size

Application-level limits (**NEW ARCHITECTURAL DECISION**), enforced in MediaService **before** processing. PHP `upload_max_filesize` / `post_max_size` remain infrastructure ceilings (DOC-11); application limits MUST be ≤ those ceilings in production.

| Kind | Max original upload | Applies to |
| --- | --- | --- |
| IMAGE | **5 MiB** (5 × 1024 × 1024 bytes) | Original uploaded file **before** GD processing |
| DOCUMENT | **15 MiB** (15 × 1024 × 1024 bytes) | Stored document file |

| Topic | Rule |
| --- | --- |
| `file_size` column | Stores size of the **persisted** file (processed master for IMAGE; document bytes for DOCUMENT) |
| Oversized original IMAGE | Reject before processing; do not create ACTIVE MediaAsset |
| Profile `maximum_file_size` | If a Theme Image Profile defines a smaller cap, the **stricter** limit wins |

## 12. Image Profile Contract (empty Manifest `media_profiles`)

ADR-004 requires IMAGE `media_id` to reference an ACTIVE IMAGE asset consistent with an image profile. Baseline Theme Manifest currently has `media_profiles: []`.

### Accepted V1 rule

**NEW ARCHITECTURAL DECISION:**

1. The application defines a built-in baseline profile id: **`cms_default`**.
2. When the ACTIVE Theme Manifest `media_profiles` is empty **or** an IMAGE field definition does not name a profile, validation and processing use **`cms_default`**.
3. When the Manifest declares profiles and the field specifies one, that Theme profile is used (stricter of Theme vs §11 size caps).
4. Task 4.5 MUST NOT require editing ThemeManifest solely to unlock IMAGE fields on `custom-page`.

### `cms_default` parameters (NEW ARCHITECTURAL DECISION)

| Parameter | Value |
| --- | --- |
| `minimum_width` / `minimum_height` | None (no min-dimension reject for library upload) |
| `maximum_width` | 2560 |
| `maximum_height` | 2560 |
| `maximum_file_size` | 5 MiB (same as §11 IMAGE) |
| `allowed_formats` | jpeg, png, webp, gif (upload); stored master WebP/JPEG per ADR-007 |
| Upscaling | Forbidden (ADR-007 / DOC-06) |
| Aspect crop | None required in V1 baseline — resize down preserving aspect |

## 13. Lifecycle (confirmed, not redesigned)

```text
ACTIVE → TRASH (deleted_at set)
TRASH → ACTIVE (restore; clear deleted_at)
TRASH → Permanent Delete (Admin only + DependencyChecker)
```

- Replacement = **new** MediaAsset + explicit reference change (REQ-MEDIA-006 / ADR-007).
- No cascade delete of Pages/Posts.
- No silent mutation of shared assets on disk.

## 14. Authorization (confirmed)

Existing Shield permissions only:

- `media.upload`
- `media.edit`
- `media.delete`
- `media.restore`
- Admin `media.*`

| Permission | V1 |
| --- | --- |
| `media.view` | **Not required** — do not add |
| `media.manage` | **Not required** — do not add (DOC-08 name is non-binding vs AuthGroups) |

Browse/list is authorized via existing `media.upload` / `media.edit` (and Admin wildcard) as already granted in `AuthGroups`.

## 15. Content Schema Integration (confirmed)

- IMAGE / DOCUMENT → positive integer `media_id` (ADR-004).
- `ContentSchemaValidator` remains structural authority; Media existence/type/ACTIVE/(profile) via injected Media resolver.
- Nested REPEATABLE fields must validate nested `media_id` the same way.
- No validator changes in this ADR task.

## 16. Security Contract (preserved)

- Generated `storage_key` / `download_token`; never user-controlled paths.
- MIME + extension + signature validation.
- Reject executables / PHP / scripts; reject SVG.
- Documents never web-root served.
- Images only from `public/uploads/images/` via `/uploads/images/{storage_key}`.
- No internal FS path leakage in responses/logs.
- No silent overwrite of an existing asset’s file identity.

## 17. Consequences

### Positive

- Task 4.5 can migrate `media_assets`, implement MediaService, and wire IMAGE/DOCUMENT without inventing policy mid-code.
- Stable public image URL and document download token semantics.
- Empty Theme `media_profiles` no longer blocks `custom-page` IMAGE fields.

### Trade-offs

- Static public image storage (no object storage in V1).
- Fixed MIME/size policy; changes need ADR/doc update.
- No content-hash deduplication.
- SVG unsupported in V1.
- GIF allowed as raster upload; still processed through GD (animated GIF behavior may flatten — acceptable V1 trade-off; do not invent animation preservation requirements).

## 18. Deferred Decisions

- Object storage / CDN abstraction
- AVIF
- Content-hash dedup
- Media picker UX polish beyond foundation
- Full Theme-authored Image Profile catalog for `default` Theme
- Exact TRASH-time file quarantine vs leave-until-permanent-delete filesystem policy detail (soft-delete record is EXPLICIT; physical move optional)
- Response `Content-Disposition` nuances for document downloads beyond CI4 download helper
- Wiring public Theme views to resolve `media_id` → `/uploads/images/{storage_key}` (implementation Task 4.5+)

## 19. References

- CONTEXT.md (§ Media)
- docs/01-Product-Requirements.md (REQ-MEDIA-*, REQ-DOC-*)
- docs/02-Domain-Model.md (§§16–19)
- docs/03-Authorization-Security.md (§§18–21)
- docs/05-Theme-Template-Architecture.md
- docs/06-Media-Document-Management.md
- docs/08-Technical-Architecture.md (§§15–17, 44–46)
- docs/09-Implementation-Blueprint.md (Phase 5 Media order — foundation may land earlier once contracted)
- docs/11-Deployment-Operations.md (§ Upload Limits)
- adr/ADR-004-Native-Content-Schema-Validator.md
- adr/ADR-007-Media-Storage-Download-Token.md
- `app/Config/AuthGroups.php`

## Amendment history

| Date | Change |
| --- | --- |
| 2026-08-23 | Initial acceptance (Task 4.5B): schema; `download_token`; IMAGE/DOCUMENT allowlists; SVG reject; 5/15 MiB caps; reaffirm `public/uploads/images/`; public URL `/uploads/images/{storage_key}`; baseline profile `cms_default`. |
