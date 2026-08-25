# ADR-026 — Security Hardening Contract

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 9 / Task 9.1A

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not implement Controllers, Services, Filters, Config changes, migrations, tests, routes, or AuthGroups changes.

It binds Phase 9 Task 9.1B **security hardening** scope after Phase 8 closure, following:

- **DOC-09 §14** — Phase 9 Security Hardening
- **DOC-03** — Authorization & Security (primary security boundary)
- **DOC-01** — REQ-NFR-001, REQ-NFR-008; auth/session/throttling REQs
- **DOC-08** — technical security architecture cross-cuts
- **DOC-10** — security testing expectations
- **CONTEXT.md** — username login override; email PII remains mandatory
- **`.cursorrules`** — CSRF logout pattern; PII encryption/HMAC; throttling on auth routes; `esc()`; no plaintext email/phone
- Existing Accepted ADRs **001, 007, 008, 014, 018, 019–025** (must not be redesigned)

Where this ADR binds an ambiguity, the binding is labeled **NEW**.  
Where sources do not define a concrete value, the item is **UNDEFINED / DEFERRED** — Task 9.1B must not invent product requirements.

## 2. Classification Key

| Label | Meaning |
| --- | --- |
| EXPLICIT | Written in DOC/ADR/CONTEXT/`.cursorrules` |
| STRONGLY IMPLIED | Necessary consequence of those facts |
| EXISTING FOUNDATION | Already implemented; Phase 9 must verify, not redesign |
| NEW | V1 binding of an ambiguity for Task 9.1B |
| UNDEFINED / DEFERRED | Not specified; do not invent |
| CONFLICTING | Sources disagree — resolved only if labeled NEW below |

## 3. Decision Summary

| Area | Classification | Accepted V1 result for Phase 9 |
| --- | --- | --- |
| Input validation | EXPLICIT (DOC-03 §14) | Retain Service/CI4 Validation; harden gaps only |
| Output encoding | EXPLICIT (DOC-03 §15) | `esc()` default; raw HTML only when intentionally sanitized |
| RichText | EXPLICIT (ADR-014; DOC-03 §16) | **EXISTING FOUNDATION** — `RichTextSanitizer` allowlist; no redesign |
| Uploads / Media / Documents | EXPLICIT (ADR-018; ADR-007; DOC-03 §18–21) | **EXISTING FOUNDATION** — MIME/size/SVG reject/token download; verify only |
| PII email at rest | EXPLICIT (ADR-008; DOC-03 §22; CONTEXT; `.cursorrules`) | **HARDEN** — implement/verify ADR-008 `PiiCipherService` + key separation if missing |
| AuthN | EXPLICIT (ADR-001; CONTEXT) | Username+password Shield at `/cp`; **EXISTING FOUNDATION** |
| AuthZ | EXPLICIT (DOC-03; AuthGroups) | Service-layer + permission filters; **EXISTING FOUNDATION** |
| CSRF | EXPLICIT (DOC-03 §11; `.cursorrules`) | Session CSRF + `X-CSRF-TOKEN`; logout except; HTMX sync — **EXISTING FOUNDATION** |
| Session / HTMX expiry | EXPLICIT (DOC-03 §10) | `SessionAuthFilter` HX-Redirect `/cp` — **EXISTING FOUNDATION** |
| Brute-force throttling | EXPLICIT surfaces (DOC-03 §13); capacity **UNDEFINED** | **HARDEN** — wire CI4 Throttler on listed surfaces; **do not invent DOC-mandated numeric limits** |
| Security headers | EXPLICIT intent (DOC-03 §30); exact CSP **UNDEFINED** | **HARDEN** — enable CI4 `SecureHeaders` baseline; full CSP **DEFERRED** |
| HTTPS | EXPLICIT (DOC-03 §31) | `forcehttps` / HSTS via App config — **EXISTING FOUNDATION** (verify production) |
| Preview isolation | EXPLICIT (ADR-023) | Headers + auth + cache bypass — **EXISTING FOUNDATION** |
| Public visibility | EXPLICIT (ADR-016/017/024) | PUBLISHED-only; opaque 404 — **EXISTING FOUNDATION** |
| Public cache purity | EXPLICIT (ADR-025) | No session/user/PII in packages — **EXISTING FOUNDATION** |
| Scheduler security | EXPLICIT (ADR-021) | CLI-only; `actor_id = null` on success — **EXISTING FOUNDATION** |
| Auth security audit events | EXPLICIT examples (DOC-03 §25) | **HARDEN** — add missing auth/security audit vocabulary if absent |
| Password policy details | EXPLICIT “strong via Shield” (DOC-03); exact rules **UNDEFINED** beyond Shield config | Keep Shield validators; **do not invent** new product password rules |
| Session lifetime seconds | EXPLICIT “configured lifetime” (DOC-01/03); exact seconds **UNDEFINED** as product REQ | Keep Config value; verify expiry UX; **do not invent** a new mandatory number |
| Rate limits on public/Admin/upload/Preview/scheduler | Surfaces beyond auth **UNDEFINED** for mandatory throttling | **DEFERRED** — not required by DOC-03 §13 list |
| Permissions-Policy / custom CSP | UNDEFINED | **DEFERRED** |
| Failed-authz audit flood | UNDEFINED | **DEFERRED** — do not invent per-403 audit events |

## 4. Conflicts

### 4.1 PII env key naming

| Source A | Source B | Conflict |
| --- | --- | --- |
| ADR-008 / DOC-03 §22.3: `EMAIL_ENCRYPTION_KEY`, `EMAIL_LOOKUP_HMAC_KEY` | `.cursorrules`: `pii.encryption_key` / `pii.hmac_secret` (Config\Pii pattern) | Different env key names for the same semantic secrets |

**NEW resolution for V1:** ADR-008 remains authoritative for SMITE CMS key **names and sodium semantics**. Task 9.1B implements ADR-008. A Config adapter may map those env keys; do **not** introduce a second independent key pair. Do not rewrite ADR-008.

### 4.2 No other material ADR-019…025 conflicts found

Phase 9 hardening must preserve ADR-019–025 as Accepted. If implementation would require changing those contracts, STOP and open a dedicated ADR — do not silently redesign.

## 5. Existing Security Foundation (Do Not Redesign)

Task 9.1B **must verify** and **must not unnecessarily redesign**:

| Mechanism | Evidence |
| --- | --- |
| Shield session auth + username login | ADR-001; CONTEXT; `/cp` |
| AuthGroups permissions / groups | `Config\AuthGroups`; DOC-03 families |
| Service-layer authorization | Page/Post/Theme/Media/Settings Services |
| CSRF session + `X-CSRF-TOKEN` + regenerate sync | `Config\Security`; `CsrfTokenHeaderFilter`; logout except |
| HTMX session expiry | `SessionAuthFilter` → `HX-Redirect: /cp` |
| RichText allowlist sanitizer | ADR-014; `RichTextSanitizer` |
| Media MIME/extension/signature; SVG reject; 5/15 MiB | ADR-018 |
| Document private store + `download_token` | ADR-007 / ADR-018 |
| OCC / revisions / editorial audit | ADR-019 |
| Archive/trash permissions | ADR-020 |
| Scheduler CLI-only + null actor audit | ADR-021; `cms:scheduled-content` |
| Theme activation auth | ADR-022 |
| Theme Preview auth + no-store + X-Robots-Tag | ADR-023 |
| Locale/public URL/SEO boundaries | ADR-024 |
| Public File Cache PUBLISHED-only packages | ADR-025 |
| Force HTTPS filter | `Config\Filters` required `forcehttps` |
| Public PUBLISHED-only + opaque 404 | ADR-016/017 |

## 6. Hardening Required (Task 9.1B Scope)

### 6.1 PII encryption & lookup (EXPLICIT)

Implement/verify ADR-008 end-to-end:

- Central `PiiCipherService` (or equivalent) with fail-fast key validation
- Email stored as ciphertext + HMAC lookup hash — **no plaintext email** in application tables
- Separate encryption vs HMAC secrets; never log decrypted email/keys
- Round-trip tests (DOC-10 §25)

If live code is missing this foundation, Phase 9 **must** deliver it (not defer).

### 6.2 Brute-force throttling (EXPLICIT surfaces; UNDEFINED capacity)

**Must protect** with CI4 Throttler (DOC-03 §13):

1. Login (`/cp`)
2. Password-reset request
3. Password-reset verification
4. Admin recovery (`skey` flow)

**NEW process binding (not numeric invention):**

- Capacities live in a dedicated Config class (testable)
- Failures must not reveal whether username/email exists (DOC-03 §13)
- Exact attempt/window numbers are **UNDEFINED** in DOC/ADR sources — Task 9.1B records chosen **operational** Config values in its report without claiming they are DOC-mandated product constants
- Do **not** invent mandatory throttling for public Page/Post, sitemap, robots, Theme Preview, uploads, or scheduler CLI unless a future ADR requires it

### 6.3 Security headers baseline (EXPLICIT intent; CSP UNDEFINED)

**NEW:** Enable CI4 `secureheaders` global after-filter for production-compatible responses, providing at least:

- `X-Frame-Options: SAMEORIGIN`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: same-origin`
- plus other headers already emitted by CI4 `SecureHeaders` (e.g. `X-Download-Options`, `X-Permitted-Cross-Domain-Policies`)

**DEFERRED:** Full `Content-Security-Policy` directive set (DOC-03 §30: exact configuration belongs to technical/deployment architecture). Do not invent a CSP policy in 9.1B.

**EXISTING:** Theme Preview continues ADR-023 headers (`Cache-Control` no-store family; `X-Robots-Tag: noindex, nofollow, noarchive`).

**HSTS:** Continues via ForceHTTPS / App `forceGlobalSecureRequests` when HTTPS is enforced — verify; do not invent alternate HSTS policy.

### 6.4 Auth / security audit vocabulary (EXPLICIT examples)

DOC-03 §25 requires security-sensitive auth events such as:

- LOGIN / LOGIN_FAILED / LOGOUT
- PASSWORD_CHANGED / PASSWORD_RESET
- ADMIN_RECOVERY
- USER_CREATED / USER_ACTIVATED / USER_DEACTIVATED

**NEW:** Task 9.1B must extend `AuditEvent` (and append paths) for those **DOC-listed** auth/security events that are still missing, without inventing additional event types beyond DOC-03 §25 + already Accepted content/theme events.

Do **not** invent failed-authorization audit spam for every 403.

### 6.5 Verification sweep (EXISTING contracts)

Task 9.1B must **prove** (tests + review), not redesign:

- CSRF still covers mutations; logout exception remains
- Session expiry + HTMX HX-Redirect still works
- RichText rejects script/on*/javascript:/iframe/img per ADR-014
- Upload rejects SVG/executables; enforces MIME+extension+signature and size caps
- Documents not publicly listable; token download enforces lifecycle rules
- Public routes never serve DRAFT/UNPUBLISHED/ARCHIVED/TRASH
- Preview cannot populate/read public content cache
- Scheduler remains CLI-only; no HTTP scheduler
- Production error pages do not expose stack/SQL/paths/env/PII (DOC-03 §28)
- Public cache packages contain no session/CSRF/user identity (ADR-025)

### 6.6 Secrets review (EXPLICIT)

Verify `.env.example` documents required secrets without real values; no secrets in git; recovery `skey` never logged (CONTEXT; DOC-03 §23).

## 7. Security Boundary Matrix (Source-Supported)

| Surface | Anonymous | Contributor | Editor | Admin | CLI |
| --- | --- | --- | --- | --- | --- |
| Public Page/Post | PUBLISHED only | same | same | same | n/a |
| Sitemap / robots.txt | yes | yes | yes | yes | n/a |
| Theme Preview | no | if `theme.preview` | if `theme.preview` | if `theme.preview` | n/a |
| Admin Pages/Posts | no | permission + ownership rules | permissions | full content authority | n/a |
| Media upload/edit | no | media.* (own scope as implemented) | media.* | media.* | n/a |
| Document download | token + lifecycle rules | same | same | same | n/a |
| Settings | no | no | no | `site.manage` | n/a |
| Theme activate | no | no | no | `theme.activate` | n/a |
| Schedule create/cancel | no | matching publish/unpublish + Post ownership | matching permissions | matching permissions | n/a |
| Scheduler execution | no | no | no | no | `cms:scheduled-content` only |

No new permissions are introduced by this ADR.

## 8. Area Contracts (Accepted V1)

### 8.1 Input / Output

- All external input untrusted; server-side validation mandatory for DOC-03 §14 list.
- Dynamic output escaped with `esc()` unless intentionally sanitized HTML (RICH_TEXT after sanitizer).
- Identifiers/slugs/paths validated; no ad-hoc path concatenation for security decisions; use CI4 URI/routing (DOC-03 §24; `.cursorrules` Uri extension where URL feeds access control).

### 8.2 RichText

- Persist sanitized HTML only (ADR-014).
- Allowlist tags/attributes/protocols per ADR-014; reject script/style/iframe/object/embed/img/form/input and `on*` handlers; links `http`/`https`/`mailto` only.
- Quill is not a security boundary.
- No Markdown-as-storage; no stored unsanitized HTML.
- Existing stored content: if any pre-sanitizer rows exist, 9.1B may re-sanitize on write paths only — **no mandatory bulk migration** unless evidence requires it (**UNDEFINED** bulk migration).

### 8.3 Upload / Media / Document

- Untrusted uploads; authz + CSRF; MIME + extension + signature; generated storage names; no user path control.
- IMAGE ≤ 5 MiB; DOCUMENT ≤ 15 MiB; SVG rejected (ADR-018).
- Images: `public/uploads/images/{storage_key}` via web server.
- Documents: `writable/uploads/documents/` + `GET /download/document/{download_token}`; non-ACTIVE/TRASH not publicly downloadable (DOC-03).
- Dependency check before permanent media delete (EXISTING).

### 8.4 PII / Information Disclosure

- User email is PII; ciphertext + lookup hash; normalize trim+lowercase before hash.
- Never log decrypted email, keys, or `skey`.
- Production errors: no stack, SQL, filesystem paths, env, credentials, unnecessary internal IDs, personal data (DOC-03 §28).
- Public/cache responses must not include auth/session/PII (ADR-025).
- Phone storage: not a V1 product feature beyond any `.cursorrules` global rule if introduced later — **no phone schema invented here**.

### 8.5 Authentication / Authorization

- `/cp` login entry; `/admin/*` authenticated Control Panel.
- Permissions remain DOC-03 / AuthGroups; Service remains authoritative.
- Contributor ownership AUTHZ-001 preserved.
- Permanent delete Admin-only; Theme activate Admin-only; Settings Admin `site.manage`.
- Scheduler mutations: null `actor_id` on success audit (ADR-021).
- Inactive user sessions invalid (DOC-01).

### 8.6 CSRF / Session

- CSRF mandatory for state-changing browser requests; session-backed; header `X-CSRF-TOKEN`.
- Logout exempt per `.cursorrules`; idempotent.
- CSRF regenerate + client sync via existing after-filter.
- Session FileHandler under `writable/session`; expiry is configured lifetime (currently Config-driven) — verify redirect-to-`/cp` behavior; **do not invent** a new mandatory lifetime number.
- Password change / reset / deactivate invalidate sessions (DOC-01/03).

### 8.7 Rate Limiting

| Surface | V1 requirement |
| --- | --- |
| Login / password-reset / Admin recovery | **Must** throttle (DOC-03 §13) |
| Exact capacities | **UNDEFINED** — Config-backed operational values only |
| Public content / sitemap / robots / Preview / uploads / scheduler CLI | **DEFERRED** (not in DOC-03 mandatory list) |

### 8.8 Security Headers

| Header / concern | V1 |
| --- | --- |
| SecureHeaders baseline (frame/nosniff/referrer/…) | **Enable** (NEW) |
| Preview Cache-Control / X-Robots-Tag | **Keep** ADR-023 |
| CSP | **DEFERRED** |
| Permissions-Policy | **DEFERRED** |
| HSTS | Via existing ForceHTTPS when HTTPS enforced |
| Cache-Control on public content | Public File Cache is application cache (ADR-025); do not invent CDN Cache-Control product rules |

### 8.9 Public / Admin / Preview Isolation

- Public: PUBLISHED only; opaque 404; no Admin Preview leakage.
- Preview: authenticated + `theme.preview`; GET-only; read-only; no public cache; no ACTIVE mutation.
- Sitemap/robots: public; no draft URLs; robots disallow Admin/`/cp` (ADR-024 foundation).
- Shared public cache: no user/session/CSRF/draft (ADR-025).

### 8.10 Audit / Errors

- Content/theme audit vocabulary remains ADR-019/020/022.
- Add DOC-03 §25 auth/security events if missing.
- Autosave must not flood audit (EXISTING ADR-019).
- Production error disclosure rules DOC-03 §28.

### 8.11 CLI / Background

- `cms:scheduled-content` only scheduler entry; no HTTP impersonation.
- No fake interactive actor on scheduled success audits.
- Filesystem writes remain under approved upload/session/cache paths.

## 9. Intentionally NOT Implemented / Deferred

- Full CSP policy authoring
- Permissions-Policy
- Numeric DOC-mandated throttle capacities
- Throttling of public Page/Post/sitemap/Preview/upload/CLI beyond DOC-03 §13
- New permissions or public endpoints
- Redis/CDN/WAF product requirements
- Password complexity invention beyond Shield configuration already present
- Forced session lifetime number change
- Bulk historical RichText migration unless proven necessary
- Per-request failed-authorization audit events
- Multi-tenant / org isolation
- Phone PII schema (unless already required elsewhere — not introduced here)
- Changing ADR-019–025 semantics

## 10. Migration / Schema

- **No new security table required** by this ADR solely for headers/throttling.
- PII column work follows **ADR-008** if not already present — Task 9.1B may need a migration **only** if live schema lacks ADR-008 columns. If columns already exist, do not churn schema.
- Expected baseline before 9.1B: **23 tables**, App migration batch **10** (unless 9.1B itself must add ADR-008 columns — then document as 9.1B schema delta).

## 11. Permissions / Routes

- No new permissions.
- No new public endpoints.
- No new CSRF exceptions beyond existing logout rule.
- No HTTP scheduler.

## 12. Testing Expectations (DOC-10 aligned)

Task 9.1B must add/extend tests for at least:

- Throttling engages on protected auth/recovery surfaces
- CSRF mutation protection + HTMX token sync still green
- RichText XSS rejection samples (script/onerror/javascript:/iframe)
- Upload SVG/executable rejection + size caps
- Document token access rules
- PII encrypt/decrypt + lookup hash (if implemented/verified in 9.1B)
- Preview headers + cache isolation regression
- Production-safe error behavior where testable
- Full suite remains green

## 13. Non-Goals

- Redesigning AuthGroups role matrix
- Replacing Shield
- Inventing a security product dashboard
- Enabling CI4 HTML PageCache as the ADR-025 content store
- “Best practice” controls without DOC/ADR support

## 14. Consequences

### Positive

- Phase 9 has an explicit verify-vs-harden split.
- Gaps (PII wiring, throttling, SecureHeaders, auth audits) are actionable without redesigning Phases 1–8.
- Undefined numeric/CSP items stay deferred instead of fabricated.

### Trade-offs

- Operational throttle capacities must be chosen carefully in 9.1B Config without DOC numeric cover.
- CSP remains deployment-sensitive and deferred.

## 15. References

- CONTEXT.md
- `.cursorrules`
- docs/01-Product-Requirements.md (REQ-NFR-001/008; auth/session)
- docs/03-Authorization-Security.md
- docs/08-Technical-Architecture.md
- docs/09-Implementation-Blueprint.md §14
- docs/10-Testing-Quality-Strategy.md
- adr/ADR-001, ADR-007, ADR-008, ADR-014, ADR-018, ADR-019–025
