# ADR-021 — Scheduled Publish/Unpublish Contract

**Status:** Accepted  
**Date:** 2026-08-25  
**Task:** Phase 4 / Task 4.12B

## 1. Status

Accepted for SMITE CMS V1.

This ADR is **documentation-only**. It does not create migrations, Spark commands, cron configuration, Services, Controllers, routes, views, tests, or AuthGroups changes.

It closes the Task 4.12A blocker: ADR-006 defines the scheduler architecture, but schema, timezone, OCC, cancellation, authorization, PENDING_REVIEW, and reuse of `publish()` after ADR-020 were not implementable.

It **reaffirms** ADR-006 (two-phase lease, Spark cron, no queue), ADR-011 (no workers), ADR-019 (OCC / revision / audit vocabulary), and ADR-020 (interactive Archive). It does **not** supersede those documents. Where this ADR binds an ambiguity, the binding is labeled **NEW**.

## 2. Classification Key

| Label | Meaning |
| --- | --- |
| EXPLICIT SOURCE FACT | Written in DOC/ADR |
| STRONGLY IMPLIED | Necessary consequence of those facts |
| NEW | V1 binding of an ambiguity |
| DEFERRED | Out of V1 |

## 3. Decision Summary

| Decision | Classification | Accepted V1 result |
| --- | --- | --- |
| Action types | EXPLICIT (CONTEXT; DOC-04 §24–25; ADR-006) | `PUBLISH`, `UNPUBLISH` only |
| Resources | EXPLICIT | Pages and Posts |
| Scheduled archive | EXPLICIT (ADR-020 deferred) | **Out of V1** |
| Table | EXPLICIT (ADR-006; DOC-08) | `scheduled_actions` (22nd table when implemented) |
| Execution | EXPLICIT (ADR-006; ADR-011; DOC-11) | `php spark cms:scheduled-content`; cron `* * * * *`; no HTTP; no queue |
| Timezone | **NEW** (binds Site timezone vs `appTimezone` UTC) | User-facing = Site timezone; storage/comparison = UTC |
| Create-time past `execute_at` | **NEW** | Reject `execute_at` strictly before UTC now; equal-to-now is due |
| Catch-up | EXPLICIT (ADR-006) | Execution: `execute_at <= UTC now` |
| Multiple schedules | **NEW** (uniqueness from ADR-006) | Multiple future rows allowed; no supersede |
| OCC | **NEW** | Do **not** store schedule-time `lock_version`; execute against current live version; mismatch → SKIPPED `LOCK_VERSION_CONFLICT` (not HTTP 409, not reschedule) |
| Scheduled publish of ARCHIVED/TRASH | EXPLICIT (DOC-04 §27; ADR-006) | SKIPPED; does **not** call interactive `publish()` for those states |
| PENDING_REVIEW | **NEW** | SKIPPED `TARGET_PENDING_REVIEW` |
| Cache | EXPLICIT (ADR-006) over DOC-02 diagram | **Post-commit** only |
| Create/cancel permission | **NEW** | Reuse `page.publish` / `page.unpublish` / `post.publish` / `post.unpublish`; no new permission |
| Cancel | **NEW** | PENDING only; no content mutation; no revision; no `lock_version` bump |
| Success audit | STRONGLY IMPLIED (ADR-019) | `*_PUBLISHED` / `*_UNPUBLISHED` only |
| Skip/fail/cancel audit | **NEW** | Persist on `scheduled_actions` only; **no** new AuditEvent; **no** misuse of `*_RESTORED` / `*_ARCHIVED` |
| Executor actor | EXPLICIT (ADR-019 `actor_id` NULL = system) | Execution audit `actor_id` NULL; do **not** invent a Shield user |
| `publish()` reuse | **NEW** | Wrapper + pre-validation; never raw `publish()` on ARCHIVED |

## 4. V1 Scope

### In

```text
Page  PUBLISH    (when current state is eligible — see §9)
Page  UNPUBLISH  (PUBLISHED → UNPUBLISHED)
Post  PUBLISH
Post  UNPUBLISH
```

### Out of V1

- scheduled ARCHIVE, TRASH, restore-from-trash, revision restore, autosave
- recurring schedules, calendar UI, drag/drop, reschedule-in-place
- queue/worker execution, public scheduling API
- per-user timezone, notifications, retry dashboard, analytics
- new Shield permissions (`page.schedule`, `schedule.*`)

Interactive Archive remains ADR-020. Interactive `publish()` may still do `ARCHIVED → PUBLISHED`. The **scheduler** must not.

## 5. `scheduled_actions` DDL (MariaDB)

**NEW.** Merges ADR-006 execution columns with DOC-02 observability (`attempts`, `last_error`, `failed_at`, `created_at`, `updated_at`) and a persistable creator (`created_by`).

### 5.1 Columns

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | `BIGINT UNSIGNED` | NO | AUTO_INCREMENT | Primary key (DOC-02 §31) |
| `target_type` | `VARCHAR(16)` | NO | — | `page` or `post` (same tokens as `revisions.resource_type`) |
| `target_id` | `BIGINT UNSIGNED` | NO | — | Page or Post id; **no** polymorphic FK |
| `action_type` | `VARCHAR(16)` | NO | — | `PUBLISH` or `UNPUBLISH` |
| `execute_at` | `DATETIME` | NO | — | **UTC**, second precision |
| `status` | `VARCHAR(20)` | NO | `PENDING` | See §5.3 |
| `claimed_at` | `DATETIME` | YES | NULL | UTC; set on claim |
| `lease_until` | `DATETIME` | YES | NULL | UTC; claim + 5 minutes |
| `processed_at` | `DATETIME` | YES | NULL | UTC; set on terminal success/skip/cancel-complete/fail |
| `result_code` | `VARCHAR(50)` | YES | NULL | §14 |
| `result_message` | `TEXT` | YES | NULL | Human-readable; **no PII**, no secrets, no filesystem paths |
| `attempts` | `INT UNSIGNED` | NO | `0` | Incremented on each claim (including lease reclaim) |
| `last_error` | `TEXT` | YES | NULL | Last execution error text; same redaction rules as `result_message` |
| `failed_at` | `DATETIME` | YES | NULL | UTC; set when status becomes `FAILED` |
| `created_by` | `INT UNSIGNED` | YES | NULL | Shield `users.id`; schedule **creator**; ON DELETE SET NULL |
| `created_at` | `DATETIME` | NO | — | UTC |
| `updated_at` | `DATETIME` | NO | — | UTC |
| `pending_guard` | `TINYINT` | YES | generated | Technical column — §5.4 |

No `cancelled_at` column. Cancellation is `status = CANCELLED` plus `processed_at` / `result_code`.

No stored `lock_version`. OCC uses the live row at execution (§10).

### 5.2 Allowed enumerations

`target_type`: `page`, `post`  
`action_type`: `PUBLISH`, `UNPUBLISH`  
`status`: `PENDING`, `PROCESSING`, `PROCESSED`, `SKIPPED`, `CANCELLED`, `FAILED`

These are ScheduledAction processing states, **not** Page/Post editorial states (DOC-08 §39).

### 5.3 Indexes and keys

```text
PRIMARY KEY (id)
UNIQUE KEY uq_scheduled_pending (target_type, target_id, action_type, execute_at, pending_guard)
INDEX idx_due (status, execute_at)
INDEX idx_target (target_type, target_id)
INDEX idx_created_by (created_by)
```

Optional FK: `created_by` → `users.id` `ON DELETE SET NULL`.

No FK from `target_id` to `pages`/`posts` (polymorphic `target_type`).

### 5.4 Unique PENDING strategy (MariaDB)

ADR-006 uniqueness applies **while PENDING**. MariaDB has no PostgreSQL-style partial unique index.

**NEW technical column** `pending_guard`:

```sql
pending_guard TINYINT
  GENERATED ALWAYS AS (IF(`status` = 'PENDING', 1, NULL)) STORED
```

Unique key:

```text
(target_type, target_id, action_type, execute_at, pending_guard)
```

MariaDB unique indexes treat `NULL` as distinct. Non-PENDING rows have `pending_guard = NULL` and do not collide. Two PENDING rows with the same tuple are rejected.

Implementation MUST use this generated column. Do not rely on application-only uniqueness.

### 5.5 Expected table count after implementation

Current: **21** tables. After Task 4.12C: **22** tables (`scheduled_actions` added). No schema in this ADR task.

## 6. Creator vs executor

| Role | Identity | Audit |
| --- | --- | --- |
| Schedule **creator** | Authenticated Shield user; stored in `created_by` | Not a content mutation; no `*_PUBLISHED` at create time |
| Schedule **executor** | Spark process `cms:scheduled-content` | On successful content mutation: `audit_logs.actor_id = NULL` (ADR-019 “NULL = system”) |

- Do **not** create a fake Shield user.
- Execution MUST **not** impersonate `created_by` for `PageService`/`PostService` `can()` checks (cron has no session). Authorization is enforced at **create/cancel** time.
- `created_by` MAY become NULL if the user row is deleted (`ON DELETE SET NULL`). Historical `scheduled_actions` rows remain.
- `result_message` / `last_error` MUST NOT contain usernames, emails, or other PII.

## 7. Timezone

| Concern | Binding |
| --- | --- |
| User-facing / Admin UI | Site timezone (`Site.timezone` / `SettingService`; default `Asia/Jakarta`). **One** site timezone. No per-user TZ. |
| Storage | `execute_at` and all other DATETIME columns on this table are **UTC** |
| Scheduler comparison | `execute_at <= UTC_TIMESTAMP()` (application UTC now, same clock) |
| `Config\App::$appTimezone` | Remains UTC for application clock; **not** a second product timezone |
| Precision | Seconds (`DATETIME`) |
| DST | Convert UI local → UTC at create; store the instant. DST does not rewrite stored UTC |
| Display | Convert UTC → Site timezone for Control Panel |

Do not add another timezone setting. Do not store browser-local timestamps without conversion.

## 8. Past-date vs catch-up

### At create (Control Panel)

- `execute_at` **strictly before** UTC now → **reject** (validation error).
- `execute_at` **equal to** UTC now (same second) → **accept**; row is immediately due.
- No “a few seconds in the past is OK” slop.

### At execution (cron)

Unchanged ADR-006 catch-up: due rows are `status IN (PENDING, abandoned PROCESSING)` and `execute_at <= UTC now`. Downtime does not drop overdue jobs.

Create-time rejection and execution catch-up are different rules. A row created as future that later becomes past because cron was down **is** processed.

## 9. Eligible source states

Scheduler **pre-validates** live Page/Post status **before** calling Service mutation methods. Interactive ADR-020 `publish()` semantics are **not** used for ARCHIVED.

### Scheduled PUBLISH

| Current status | Result |
| --- | --- |
| `DRAFT` | Apply → `PUBLISHED` (`APPLIED`) |
| `UNPUBLISHED` | Apply → `PUBLISHED` (`APPLIED`) |
| `PUBLISHED` | SKIPPED `TARGET_ALREADY_PUBLISHED` |
| `ARCHIVED` | SKIPPED `TARGET_ARCHIVED` |
| `TRASH` | SKIPPED `TARGET_TRASH` |
| `PENDING_REVIEW` | SKIPPED `TARGET_PENDING_REVIEW` |
| Missing row | SKIPPED `TARGET_MISSING` |
| Other/unknown | SKIPPED `INVALID_SOURCE_STATE` |

### Scheduled UNPUBLISH

| Current status | Result |
| --- | --- |
| `PUBLISHED` | Apply → `UNPUBLISHED` (`APPLIED`) |
| `UNPUBLISHED` | SKIPPED `TARGET_ALREADY_UNPUBLISHED` |
| `ARCHIVED` | SKIPPED `TARGET_ARCHIVED` |
| `TRASH` | SKIPPED `TARGET_TRASH` |
| `DRAFT` / `PENDING_REVIEW` / other | SKIPPED `INVALID_SOURCE_STATE` |
| Missing row | SKIPPED `TARGET_MISSING` |

Publish-time schema validation failure after an eligible source → `FAILED` `VALIDATION_FAILED` (ADR-006). Content status must not change.

## 10. OCC

**NEW — option B.**

- Do **not** persist `lock_version` on `scheduled_actions`.
- At execution, lock the live Page/Post row and bump **current** `lock_version` (existing `beginOccMutation` with expected = current, or equivalent conditional UPDATE).
- Concurrent editor **during the same execution transaction** → 0 rows updated → mark ScheduledAction **SKIPPED** `LOCK_VERSION_CONFLICT`. Rollback content mutation. **Do not reschedule. Do not HTTP 409.**
- Edits **after scheduling and before execute_at** increment live `lock_version` but **do not** skip the job. The scheduled **status** transition still applies to the **current** content. That preserves later editorial work (body/title) instead of requiring the page to be frozen at schedule time.

HTTP 409 remains **interactive** Controller behavior only (ADR-019).

## 11. Multiple / conflicting schedules

- Multiple future PENDING rows for the same target **are allowed** if they differ in `action_type` and/or `execute_at`.
- `PUBLISH` and `UNPUBLISH` **may coexist**.
- Same action at **different** `execute_at` **may coexist**.
- Exact duplicate PENDING tuple → unique-key rejection (validation / 409-equivalent domain error at create, not OCC `_conflict`).
- Later rows do **not** supersede earlier rows. Execution order is `execute_at ASC`, then `id ASC`.

Example: publish 10:00, unpublish 12:00, publish 14:00 is valid V1.

## 12. Execution architecture (reaffirm ADR-006)

Command: `php spark cms:scheduled-content`  
Cron: `* * * * *` (DOC-11). No public route. No queue (ADR-011).

1. **Claim transaction:** `SELECT … FOR UPDATE` due rows  
   `WHERE (status = 'PENDING' AND execute_at <= :utcNow) OR (status = 'PROCESSING' AND lease_until <= :utcNow)`  
   `ORDER BY execute_at ASC, id ASC`  
   `LIMIT 50`  
   Set `status = PROCESSING`, `claimed_at = :utcNow`, `lease_until = :utcNow + 5 minutes`, increment `attempts`. **COMMIT** (release queue locks).
2. **Per item, isolated mutation transaction:** pre-validate → OCC bump → status mutation → `recordEditorialFromLive` → set ScheduledAction `PROCESSED`/`SKIPPED`/`FAILED` + `result_code` → **COMMIT**.
3. **Post-commit:** invalidate affected **public** cache only if content status actually changed to/from PUBLISHED (`APPLIED`). Do not invalidate on SKIPPED/FAILED/CANCELLED.

Spark MUST NOT hold the claim-table lock for the duration of content work (ADR-006).

## 13. Idempotency and failure

| Situation | Behavior |
| --- | --- |
| Duplicate cron while first still holds lease | Second claim does not take the same PENDING/leased row |
| `PROCESSING` lease expired | Reclaim as due; increment `attempts`; re-validate live state |
| Content already in target state on reclaim | SKIPPED `TARGET_ALREADY_*` (no second transition) |
| Crash after content COMMIT but before ScheduledAction update | Reclaim + already-satisfied skip. **At-least-once claim, at-most-one successful content transition** |
| Mutation transaction failure | Rollback content; set `FAILED` `EXECUTION_ERROR`; `failed_at`; `last_error` |
| FAILED automatic retry | **None.** ADR-006 lease reclaim is crash recovery, not FAILED retry |
| Manual retry UI | **DEFERRED** |
| `attempts` | Count of claims; no failure threshold that auto-retries FAILED |

Do not claim exactly-once end-to-end if the process dies between two commits; uniqueness of **successful content transition** is preserved by re-validation.

## 14. Result codes

| Code | When |
| --- | --- |
| `APPLIED` | Successful PUBLISH or UNPUBLISH |
| `TARGET_TRASH` | Skip: TRASH |
| `TARGET_ARCHIVED` | Skip: ARCHIVED |
| `TARGET_PENDING_REVIEW` | Skip: PENDING_REVIEW on PUBLISH |
| `TARGET_ALREADY_PUBLISHED` | Skip: already PUBLISHED on PUBLISH |
| `TARGET_ALREADY_UNPUBLISHED` | Skip: already UNPUBLISHED on UNPUBLISH |
| `TARGET_MISSING` | Skip: target row gone |
| `INVALID_SOURCE_STATE` | Skip: other ineligible editorial state |
| `LOCK_VERSION_CONFLICT` | Skip: OCC miss during execution |
| `VALIDATION_FAILED` | Fail: publish schema/locale validation |
| `CANCELLED` | User cancelled PENDING |
| `EXECUTION_ERROR` | Fail: unexpected error |

`result_code` is **not** an AuditEvent.

## 15. Revision

Successful `APPLIED` PUBLISH/UNPUBLISH MUST call existing `RevisionService::recordEditorialFromLive(...)` (same snapshots as interactive publish/unpublish).

SKIPPED, FAILED, and CANCELLED MUST NOT create editorial or autosave revisions.

## 16. Audit

| Outcome | `audit_logs` |
| --- | --- |
| APPLIED PUBLISH | `PAGE_PUBLISHED` / `POST_PUBLISHED`; `actor_id` NULL |
| APPLIED UNPUBLISH | `PAGE_UNPUBLISHED` / `POST_UNPUBLISHED`; `actor_id` NULL |
| SKIPPED / FAILED / CANCELLED / LOCK_VERSION_CONFLICT | **No** audit event in V1 |
| Create schedule | **No** audit event |

Do **not** add `*_SCHEDULED` / `*_CANCELLED` AuditEvent values in the implementation task unless a later ADR accepts them.

Do **not** reuse `*_RESTORED` or `*_ARCHIVED`.

**Limitation (accepted):** skip/fail/cancel observability lives on `scheduled_actions` (`status`, `result_code`, `result_message`). DOC-04’s wish to audit non-standard outcomes is not expressible in the current enum without new events; V1 does not add them here.

## 17. Authorization

No new permissions. AuthGroups is unchanged.

| Action | Permission | Ownership |
| --- | --- | --- |
| Create Page PUBLISH schedule | `page.publish` | Pages have no Contributor ownership rule |
| Create Page UNPUBLISH schedule | `page.unpublish` | same |
| Create Post PUBLISH schedule | `post.publish` | Must also be allowed to act on that Post (`post.edit_any` or own + `post.edit_own` as existing write rules) |
| Create Post UNPUBLISH schedule | `post.unpublish` | same |
| Cancel PENDING | Same permission as the row’s `action_type` | Same ownership as create |

Contributor has neither publish nor unpublish → **cannot** schedule or cancel publish/unpublish. Do not broaden Contributor.

Create/cancel are future **POST** Control Panel routes (CSRF, session, group, permission). Not GET. Not public.

## 18. Cancellation

- Only `PENDING` may be cancelled. PROCESSING/terminal states cannot.
- Sets `status = CANCELLED`, `result_code = CANCELLED`, `processed_at = UTC now`.
- Does **not** change Page/Post status, `lock_version`, or revisions.
- Not audited (see §16).
- Same authorization as create for that action type. Any authorized actor may cancel, not only `created_by`.

## 19. Service reuse (implementation constraint)

Do **not** call `PageService::publish()` / `PostService::publish()` unless pre-validation has already established DRAFT or UNPUBLISHED.

A scheduler wrapper SHALL:

1. Authorize only at human create/cancel (HTTP). Spark execution uses `actor = null` and MUST still apply §9 guards (null actor currently skips `can()` — that is acceptable **only** because Spark is CLI-only).
2. Apply §9 / §10 before mutation.
3. Map Service `_conflict` → ScheduledAction SKIPPED `LOCK_VERSION_CONFLICT`.
4. Map publish validation errors → FAILED `VALIDATION_FAILED`.

Do not change interactive `publish()` / `unpublish()` / `archive()` contracts.

## 20. Admin UI contract (not implemented here)

On Page/Post **edit** (not a calendar):

- Action: PUBLISH and/or UNPUBLISH controls appear when the actor has the matching create permission. Execution still applies §9 (invalid live states become SKIPPED, not a UI-only rule).
- `execute_at` datetime field; label the **Site timezone**.
- Validate past UTC instants per §8.
- List PENDING (and optionally recent terminal) schedules for this resource: action, execute_at (site TZ), status, result_code/message, cancel.
- Cancel: POST + CSRF + `lock_version` is **not** required (cancel does not mutate content OCC).
- No recurring UI, no reschedule-in-place (cancel + create).

## 21. Public visibility

Unchanged ADR-016 / ADR-017: only `PUBLISHED` is public. Public rendering never reads or executes `scheduled_actions`. No public scheduler URL.

## 22. Security

- Shield session + group on future Admin schedule/cancel routes
- CSRF on those POSTs; no new CSRF exception
- Spark command is CLI/cron only; not an HTTP controller
- No secrets, PII, or filesystem paths in schedule rows
- Target/action cannot be supplied by unauthenticated requests

## 23. Deferred

- Scheduled archive / trash / restore / revision restore / autosave
- Recurring schedules; calendar; drag/drop; in-place reschedule
- Queues, public API, notifications, per-user timezone
- FAILED automatic retry; retry dashboard; skip/fail AuditEvent vocabulary
- Cache architecture changes beyond post-commit invalidation of existing keys

## 24. Consequences

- Task 4.12C can migrate `scheduled_actions`, wrap publish/unpublish, add Spark `cms:scheduled-content`, and add Admin POST schedule/cancel without a further schema ADR.
- Interactive ADR-020 republish of ARCHIVED remains; cron will not republish archived content.
- Site timezone remains the only product TZ; UTC is the storage clock.

## 25. References

- CONTEXT.md
- docs/01-Product-Requirements.md (REQ-SCHED-001–006, REQ-AUDIT-002)
- docs/02-Domain-Model.md §5, §27–30
- docs/03-Authorization-Security.md §5, §14
- docs/04-Content-Publishing.md §24–28
- docs/07-Localization-URL-SEO.md (timezone as global setting)
- docs/08-Technical-Architecture.md §16–18, §37–39
- docs/09-Implementation-Blueprint.md Phase 8 / publishing order
- docs/11-Deployment-Operations.md §13–14, §39
- adr/ADR-005-Revision-Autosave-Concurrency.md
- adr/ADR-006-ScheduledAction-Idempotent-Execution.md
- adr/ADR-011-No-Queue-Shared-Hosting-Baseline.md
- adr/ADR-019-Revision-Audit-OCC-and-Autosave-Foundation.md
- adr/ADR-020-Page-Post-Archive-Lifecycle-Contract.md
