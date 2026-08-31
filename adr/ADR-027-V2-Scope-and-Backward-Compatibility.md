# ADR-027 — SMITE CMS V2 Scope and Backward Compatibility

**Status:** Accepted  
**Date:** 2026-08-31  
**Task:** V2-002 — V2 Charter

## 1. Status

Accepted for SMITE CMS V2.

This ADR is **documentation-only**. It does not implement Controllers, Services, Models, migrations, routes, tests, Views, or configuration changes.

It is the **binding scope contract** for V2 development. V2 implementation tasks execute this ADR. Release audits verify compliance with this ADR.

**Relationship to prior work:**

| Artifact | Role |
| --- | --- |
| **V2-001** | Planning analysis — input to this ADR |
| **ADR-027** (this document) | Binding V2 scope contract |
| **V2-003 … V2-005** | v2.0.0 CORE implementation |
| **V2-006+** | Optional / post-core improvements |
| **Release audit** | Verifies ADR-027 compliance before tagging `v2.0.0` |

## 2. Context

### 2.1 Frozen V1 baseline

| Item | Value |
| --- | --- |
| Repository distribution (frozen) | **`v1.1.6`** |
| Application behavior baseline | **`v1.1.2`** |
| V1 status | **FROZEN** |

V1 delivered a single-organization, single-website CMS on CodeIgniter 4.7, PHP 8.5+, MariaDB, Shield, HTMX, Alpine.js, and Theme 2026. V1 freeze audit (FINAL-003) concluded **CONDITIONAL PASS** with no application blockers.

V2 is an **incremental evolution** of the frozen baseline. V2 **must not** rewrite V1 architecture. V2 **must** preserve working V1 functionality unless an explicit breaking change is separately approved through a future ADR.

### 2.2 Why V2 exists

V2-001 identified three **P0** gaps that prevent meaningful operational improvement without changing frozen V1:

1. **User management** — `user.manage` permission exists; no Admin UI to onboard Editor/Contributor accounts.
2. **Password reset email** — reset tokens are generated and cached; SMTP delivery is not implemented.
3. **Password policy consistency** — forced password change uses Shield `passwords()->check()`; password reset and admin recovery paths do not.

Optional improvements (audit UX, tag lifecycle, menu reorder, etc.) are **not** required for v2.0.0 and are listed in §11.

### 2.3 V1 constraints that remain binding

- **ADR-001** — username + password login at `/cp`; email is PII, not a login identifier.
- **ADR-008** — encrypted email + HMAC lookup hash; no plaintext email at rest.
- **ADR-019** — immutable audit trail vocabulary and append-only semantics.
- **ADR-026** — CSRF, throttling, opaque auth failures, security hardening baseline.
- **CONTEXT.md** — exactly **one** Admin; one or more Editors; zero or more Contributors; accounts deactivated rather than permanently deleted.

V2 does **not** supersede these unless this ADR or a later Accepted ADR explicitly says so. ADR-027 does **not** change the single-Admin invariant.

## 3. Decision

### 3.1 V2.0.0 CORE (locked)

The following three features are the **only mandatory functional changes** for **`v2.0.0`**:

| ID | Feature | Summary |
| --- | --- | --- |
| **P0-1** | **User management UI** | Admin UI for staff account lifecycle within existing Shield groups |
| **P0-2** | **Password reset email delivery** | SMTP-delivered reset link using existing token/cache flow |
| **P0-3** | **Password policy consistency** | Shield `passwords()->check()` on every password-setting path |

No other feature may be added to v2.0.0 CORE without the change-control process in §14.

P1 optional features (§12) **must not** silently enter v2.0.0 CORE during implementation of P0-1…P0-3.

---

### 3.2 User management (P0-1)

#### 3.2.1 Purpose

Allow the sole Admin to manage **Editor** and **Contributor** accounts without developer/CLI intervention, using the existing `user.manage` permission and Shield user model.

#### 3.2.2 In-scope capabilities

| Capability | Requirement |
| --- | --- |
| List users | Admin-only; show username, role/group, active status; **never** show decrypted email in list UI |
| Create user | Username, email (PII), initial password or forced-reset flow, role assignment, PIC/display label if already part of install model |
| Edit user | Username (if allowed by Shield constraints), email, role, active status |
| Activate / deactivate | Use Shield `active` flag; **deactivation, not physical delete** (CONTEXT.md) |
| Role assignment | **Only** `admin`, `editor`, `contributor` — no arbitrary new roles |
| Authorization | All routes/actions gated by `user.manage` |
| PII | Email normalized (`strtolower(trim())`); stored via existing encrypted + HMAC pattern (ADR-008); accessed through dedicated Config/Service — not bare `env()` |
| Password handling | Never log, audit, or render password values; initial passwords set through secure generation or forced-reset path |
| Audit | Append-only events for create, activate, deactivate, role change (see §3.2.4) |

#### 3.2.3 Single-Admin invariant (mandatory)

Per **ADR-001** and **CONTEXT.md**:

- The system maintains **exactly one** Admin account.
- V2 user management **must not** provide UI to create a second Admin.
- V2 user management **must not** demote, deactivate, or delete the sole Admin if that would leave zero usable Admins.
- Editors and Contributors may be created, edited, activated, and deactivated subject to last-admin protection rules above.

**Implementation tasks (V2-003)** must enforce these invariants in the Service layer before any database write.

#### 3.2.4 Role matrix (no new roles)

| Group | V2 user-management scope |
| --- | --- |
| **admin** | Sole account; not creatable via UI; protected from self-deactivation and from role demotion that removes last Admin |
| **editor** | Creatable; assignable; activatable/deactivatable |
| **contributor** | Creatable; assignable; activatable/deactivatable |

Editors and Contributors **without** `user.manage` **must not** access user management routes or actions.

#### 3.2.5 Audit events (user management)

Use existing `AuditEvent` vocabulary where applicable:

- `USER_CREATED`
- `USER_ACTIVATED`
- `USER_DEACTIVATED`

Role changes and profile updates **must** append audit rows with actor, resource (`user`), and resource ID. Metadata **must not** contain passwords, email plaintext, encryption keys, or HMAC secrets.

Additional audit event types require a separate ADR amendment — do not invent silently.

#### 3.2.6 Additive route proposals (non-final)

Implementation may introduce additive Admin routes such as:

- `GET /admin/users` — list
- `GET /admin/users/new` — create form
- `POST /admin/users` — store
- `GET /admin/users/{id}/edit` — edit form
- `POST /admin/users/{id}` — update
- `POST /admin/users/{id}/activate` — activate (if not folded into update)
- `POST /admin/users/{id}/deactivate` — deactivate (if not folded into update)

Final route names and HTTP verbs follow existing Admin conventions (POST mutations, permission filters, HTMX fragments where applicable). This ADR does not mandate exact paths.

#### 3.2.7 Decisions deferred to V2-003

- Whether initial password is admin-set vs email invite vs forced-reset-only on create
- Whether email is editable after create or requires re-verification
- Exact form fields beyond username, email, role, active status
- Whether `user.manage` appears in sidebar for Admin only (expected: yes)

---

### 3.3 Password reset email (P0-2)

#### 3.3.1 V1 behavior (frozen reference)

- `GET/POST /cp/password-reset` — opaque responses; throttled
- Valid email lookup via `email_lookup_hash` only
- Token stored in cache: `auth.reset.{token}` → `userId`, TTL 3600 seconds
- **No email is sent**
- `GET/POST /cp/password-reset/verify` — token + new password; throttled

#### 3.3.2 V2 contract

Password reset **must** be deliverable through **configured SMTP** (CI4 Email service / existing `.env` SMTP settings).

**Expected flow (behavior preserved, delivery added):**

```
User submits email at /cp/password-reset
  → throttle check
  → opaque response (whether or not account exists)
  → if account exists: generate token, cache token, send email with verification link
  → user opens link (GET /cp/password-reset/verify?token=…)
  → user submits new password (POST /cp/password-reset/verify)
  → Shield password update
  → force_reset cleared where applicable (undoForcePasswordReset)
  → cache token deleted (single-use)
  → audit PASSWORD_CHANGED / PASSWORD_RESET as today
  → session invalidation per CONTEXT.md password-reset rule (implementation in V2-004)
```

#### 3.3.3 Preserved security properties

| Property | Requirement |
| --- | --- |
| CSRF | Unchanged on all POST surfaces |
| Throttling | `password_reset_request` and `password_reset_verify` remain throttled |
| Opaque responses | No account enumeration via response text or timing leaks beyond existing design |
| Token entropy | Cryptographically secure token generation (existing `random_bytes` pattern) |
| Token expiration | Cache TTL remains bounded (3600s unless separately ADR-approved) |
| Single-use | Token deleted after successful verify |
| Password non-disclosure | Password never echoed in HTML, logs, or audit metadata |
| Email content | Reset link only; no password in email body |

#### 3.3.4 SMTP failure behavior

If SMTP is not configured or send fails, implementation **must** fail safely: opaque user-facing response, server-side error logging **without** PII, no token exposure in logs. Exact behavior is defined in V2-004; must not weaken throttling or CSRF.

---

### 3.4 Password policy consistency (P0-3)

#### 3.4.1 Problem

V1 applies Shield `passwords()->check()` in `PasswordChangeController` (forced change) but **not** in `PasswordResetController::verifySubmit` or `AdminRecoveryController::recover`.

#### 3.4.2 V2 contract

**Shield password validators remain the single authoritative policy.** V2 **must not** duplicate password rules in Controllers or ad-hoc regex.

All password-setting paths **must** call the same centralized validation entry point (Service or Shield `passwords()->check()`), including:

| Path | Controller / surface |
| --- | --- |
| Forced first-login change | `PasswordChangeController` (already compliant — verify, do not regress) |
| Password reset verify | `PasswordResetController` |
| Admin recovery | `AdminRecoveryController` |

Future password-setting paths **must** use the same helper. V2-005 implements centralization; V2-003 user create/update must use it if passwords are set in UI.

#### 3.4.3 Acceptance

- Weak passwords rejected consistently with identical error semantics per surface (generic where required for security)
- No path accepts empty or trivial passwords that Shield would reject on forced change
- Unit/feature tests prove parity across all three paths

---

### 3.5 Backward compatibility

#### 3.5.1 Public URLs (frozen)

| URL | Contract |
| --- | --- |
| `/` | Public home (Theme 2026 default) |
| `/{slug}` | Published page (primary locale) |
| `/en/{slug}` | Published page (secondary locale) |
| `/news/{slug}` | Published post (primary locale) |
| `/en/news/{slug}` | Published post (secondary locale) |
| `/news` | **Reserved — returns 404** (no listing) unless a future ADR changes this |

Demo URLs after `cms:demo` (`/about`, `/contact`, `/berita`, `/news/welcome`) remain valid when demo content is installed.

#### 3.5.2 Authentication URLs (frozen)

| URL | Contract |
| --- | --- |
| `/cp` | Login (GET/POST) |
| `/cp/password-change` | Forced password change (session) |
| `/cp/password-reset` | Reset request |
| `/cp/password-reset/verify` | Token verification + new password |
| `/cp/admin-recovery` | Break-glass recovery (unchanged behavior) |
| `/logout` | Idempotent logout |

#### 3.5.3 Admin URLs

All existing `/admin/*` routes **must** continue to work. V2 adds **additive** routes only (e.g. `/admin/users/*`). No removal or semantic change of existing admin routes in v2.0.0.

#### 3.5.4 Upgrade path

Existing installations upgrade from `v1.1.6` (or later V1 tag) to `v2.0.0` via:

1. Backup database and uploads (client procedure)
2. `git checkout v2.0.0`
3. `composer install --no-dev`
4. `php spark migrate` (if migrations ship with V2 CORE)
5. **No** `cms:install` on existing databases

---

### 3.6 Data compatibility

V2 **must** preserve without destructive transformation:

- Pages and page translations
- Posts and post translations
- Categories, tags, post_categories, post_tags
- Media assets and storage keys
- Menu items (PRIMARY / FOOTER)
- Settings (`codeigniter4/settings` keys)
- Shield users and identities
- Audit logs and revisions
- Scheduled actions
- URL redirects

**Theme 2026** content payloads (`custom-page`, `custom-post` schemas) **must** remain renderable after upgrade. New theme fields are optional additions to manifests, not breaking changes to stored JSON.

No destructive data migration in v2.0.0 CORE without a separate Approved ADR.

---

### 3.7 Database rules

| Rule | Requirement |
| --- | --- |
| Schema changes | CI4 migrations only |
| Style | Additive preferred; reversible `down()` where practical |
| Production | No raw `ALTER` outside migrations |
| Upgrades | `php spark migrate` — never `cms:install` for existing sites |
| Destructive changes | Require explicit Approved ADR |
| **ADR-027** | Creates **no** migration |

Expected V2 CORE database impact:

- **P0-1 (user management):** Prefer **no schema change** (Shield `users` + PII columns exist). Optional additive columns (e.g. display label) require migration in V2-003 only if justified.
- **P0-2 (reset email):** **No** schema change expected
- **P0-3 (password policy):** **No** schema change |

---

### 3.8 Security contract

V2 **must** preserve all V1 security controls listed in ADR-026:

- CSRF (global; `logout` except)
- Auth throttling on login, reset, recovery
- Shield session authentication
- Force-reset filter on `/admin/*`
- Permission-gated admin routes
- Encrypted PII + HMAC lookup hashes
- Opaque password-reset responses
- No password/secret leakage in logs, audit, or HTML
- Append-only audit logging

#### 3.8.1 User management security acceptance

| Check | Requirement |
| --- | --- |
| Authorization | `user.manage` on every mutating action |
| Privilege escalation | Editors/Contributors cannot grant themselves `user.manage` or Admin group |
| Last-admin protection | Cannot deactivate/demote sole Admin |
| PII | Email encrypt + hash; never log decrypted email |
| Audit | Create, activate, deactivate, role change audited without secrets |
| Output | `esc()` on all dynamic view output |

#### 3.8.2 Password reset security acceptance

| Check | Requirement |
| --- | --- |
| Token entropy | `random_bytes` or equivalent |
| Expiration | Bounded cache TTL |
| Single-use | Token invalidated after success |
| Opaque responses | No enumeration |
| Throttling | Unchanged surfaces |
| Email | Link only; HTTPS; no secrets in body |
| Sessions | Invalidate per CONTEXT.md on successful reset (V2-004) |

#### 3.8.3 Password policy security acceptance

| Check | Requirement |
| --- | --- |
| Single policy | Shield validators only |
| All paths | Forced change, reset verify, admin recovery, user-create (if applicable) |
| Tests | Cross-path parity tests in V2-005 |

---

### 3.9 Architecture constraints

#### 3.9.1 KEEP (unchanged stack)

- CodeIgniter 4.7+
- MariaDB / MySQL
- CodeIgniter Shield
- HTMX 2 + Alpine.js 3 (ephemeral UI only)
- Layered architecture: Controller → DTO → Service → Model → Entity → View
- Theme filesystem model (ADR-022) — no `themes` table
- Upload paths: `public/uploads/images/`, `writable/uploads/documents/`
- Public URL model (ADR-016, ADR-017, ADR-024)
- File cache (ADR-009) — no Redis requirement
- CLI scheduler — no queue workers (ADR-011)

#### 3.9.2 DO NOT introduce (v2.0.0)

- SPA / headless API rewrite
- DDD/repository layer rewrite
- Event bus / message queue infrastructure
- Redis or mandatory Docker
- Unnecessary npm frontend build for Admin UI
- New Composer dependencies unless justified in implementation ADR addendum

Additional infrastructure is permitted **only** when a concrete V2 requirement proves it necessary and passes change control (§14).

---

### 3.10 Explicit non-goals (v2.0.0 CORE)

The following are **not** part of v2.0.0 CORE and **must not** be added without change control:

- Public full-text search
- Theme marketplace / upload UI
- SPA / headless API
- Multi-tenant / multi-site
- Traffic / visitor analytics
- Page `PENDING_REVIEW` workflow (Posts already have `PENDING_REVIEW`; Pages do not)
- Browser visual regression CI
- Menu drag-and-drop reorder
- Bulk lifecycle operations
- Dashboard analytics widgets
- Arbitrary new roles beyond admin / editor / contributor
- Queue / event-bus architecture
- Unrelated infrastructure (Redis, Elasticsearch, etc.)

**V1 limitations not in CORE** must not be fixed opportunistically during V2-003/004/005 (e.g. styling `/cp/admin-recovery`, audit filters, tag delete UI).

If a **genuine security vulnerability** is discovered in frozen V1, **stop**, classify separately, and do not patch V1 opportunistically during V2 CORE work without explicit approval.

---

### 3.11 Optional V2 scope (post-core / v2.0.x / v2.1+)

Recorded for planning; **not** mandatory for v2.0.0:

| Feature | Typical task |
| --- | --- |
| Audit filters + pagination | V2-006 |
| Tag lifecycle (deactivate/delete parity) | V2-007 |
| Menu reorder UX | V2-008 |
| Admin recovery UI polish | V2-009 |
| `cms:integrity-check` command | V2-010 |
| Post review queue indicators | V2-011+ |
| Admin content search | Future |
| Internal doc path alignment (`docs/10`, `docs/11`) | Maintenance |

---

### 3.12 Release boundary (v2.0.0)

**`v2.0.0` is ready only when all of the following are true:**

1. P0-1 User management complete and acceptance-tested
2. P0-2 Password reset email delivery complete and acceptance-tested
3. P0-3 Password policy consistency complete and acceptance-tested
4. Full V1 regression suite passes (707+ tests, 0 failures)
5. New V2 tests for P0-1…P0-3 pass
6. Existing data compatibility verified on upgraded database fixture
7. Theme 2026 renders existing content
8. Frozen public and auth URLs verified
9. Security acceptance (§3.8) passes
10. Client documentation updated for new operator capabilities
11. Migration safety verified if any migration ships
12. Release audit (V2 freeze) passes

**P1 optional features are not required** for v2.0.0 tagging.

---

### 3.13 Change control

Any proposal to add a feature to **v2.0.0 CORE** must document answers to:

1. What problem does it solve?
2. Why is it required for v2.0.0 — why not v2.1?
3. What is the database impact?
4. What is the backward compatibility impact?
5. What is the security impact?
6. What is the test impact?
7. What is the operational impact?

No feature enters CORE merely because it is technically easy.

Amendments to this ADR require explicit status change and project approval.

---

## 4. Consequences

### 4.1 Positive

- Clear v2.0.0 scope prevents feature creep
- Operators gain self-service staff onboarding (Editors/Contributors)
- Password recovery becomes production-viable with SMTP
- Security posture improves through unified password validation
- V1 investments (tests, themes, content, URLs) remain valid

### 4.2 Trade-offs

- Single-Admin model remains; no multi-Admin UI in v2.0.0
- Optional UX improvements wait until post-core releases
- V1 `/cp/admin-recovery` styling remains out of CORE scope
- SMTP configuration remains an deployment prerequisite for reset email

### 4.3 Risks

| Risk | Mitigation |
| --- | --- |
| Scope creep into P1 during CORE | Change control + release audit |
| Breaking URL or data compatibility | Frozen contracts + upgrade tests |
| PII regression in user management | ADR-008 compliance + dedicated Service |
| Weaker password on reset/recovery | P0-3 centralized Shield validation |

---

## 5. Implementation sequence

| Task | Status | Deliverable |
| --- | --- | --- |
| **V2-001** | Done | Planning analysis |
| **V2-002** | Done | ADR-027 (this document) |
| **V2-003** | Pending | User management UI (P0-1) |
| **V2-004** | Pending | Password reset email (P0-2) |
| **V2-005** | Pending | Password policy consistency (P0-3) |
| **V2-006+** | Pending | Optional P1 features |
| **V2 release** | Pending | Tag `v2.0.0` after V2-003…V2-005 pass |

**v2.0.0 release only after V2-003 through V2-005 pass** release boundary checks (§3.12).

Dependencies:

- V2-004 may proceed in parallel with V2-003 only if no shared code conflicts; V2-005 should follow or align with V2-004 touch points on `PasswordResetController`
- V2-006+ **must not** start until ADR-027 is Accepted and v2.0.0 CORE scope is unchanged

---

## 6. Acceptance criteria (v2.0.0 CORE)

### 6.1 P0-1 — User management

- [ ] Admin with `user.manage` can list, create, edit, activate, deactivate Editor/Contributor accounts
- [ ] Cannot create second Admin via UI
- [ ] Cannot deactivate sole Admin
- [ ] Email stored per ADR-008; not shown in list UIs unauthorized
- [ ] Audit events for create/activate/deactivate/role change without secrets
- [ ] Editors/Contributors denied access to user management routes
- [ ] Feature tests cover authorization and last-admin protection

### 6.2 P0-2 — Password reset email

- [ ] SMTP-configured environment sends reset link email
- [ ] Opaque responses preserved
- [ ] Throttling preserved
- [ ] Token single-use and TTL preserved
- [ ] Successful reset clears `force_reset` where applicable
- [ ] Sessions invalidated per CONTEXT.md
- [ ] No password or token in logs

### 6.3 P0-3 — Password policy

- [ ] `passwords()->check()` (or shared wrapper) on forced change, reset verify, admin recovery
- [ ] Tests prove weak password rejected on all paths
- [ ] No duplicated policy rules outside Shield config

### 6.4 Regression

- [ ] Full PHPUnit suite 0 failures
- [ ] Frozen URLs verified
- [ ] Theme 2026 smoke render
- [ ] `composer validate --strict` passes
- [ ] Client docs updated

---

## 7. Related documents

| Document | Relevance |
| --- | --- |
| V2-001 planning result | Input analysis |
| FINAL-003 V1 freeze audit | Frozen baseline |
| `CONTEXT.md` | Single Admin; PII; account lifecycle |
| `.cursorrules` | Implementation standards |
| `README.md` | Release semantics v1.1.6 / v1.1.2 |
| `docs/client/ADMIN-USER-GUIDE.md` | Operator workflow to update after P0-1 |
| ADR-001 | Username auth; single Admin |
| ADR-008 | PII encryption |
| ADR-019 | Audit trail |
| ADR-026 | Security hardening |
| ADR-016, ADR-017, ADR-024 | Public URL contracts |

---

## 8. Revision history

| Date | Change |
| --- | --- |
| 2026-08-31 | Initial Accepted version — V2-002 charter |
