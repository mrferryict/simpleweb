DOC-04 — SMITE CMS Content & Publishing

Document Version: 0.2.0
Status: Approved — Content & Publishing
Last Updated: 21 August 2026

1. Purpose

This document defines the editorial lifecycle and publication behavior for Pages and Posts.

It covers:

content states;

role-based editorial workflow;

explicit save;

auto-save;

revisions;

Trash and restore;

permanent deletion;

scheduled publish/unpublish;

slug changes and redirects;

optimistic concurrency;

cache invalidation;

publication validation.

Database schema details remain governed by 02-Domain-Model.md and 08-Technical-Architecture.md.

2. Publishing Principles

PUB-001 — Explicit State

Every Page/Post SHALL have an explicit editorial state.

Public rendering SHALL NOT infer editorial state from timestamps or missing fields.

PUB-002 — Persisted State Is Authoritative

The public website only renders the persisted current state.

Public rendering SHALL NOT perform scheduled state transitions.

PUB-003 — State Changes Are Auditable

Meaningful editorial state changes SHALL generate Audit Events.

Examples:

POST_PUBLISHED
POST_UNPUBLISHED
POST_ARCHIVED
POST_RESTORED
PAGE_PUBLISHED
PAGE_UNPUBLISHED

Routine auto-save requests SHALL NOT generate an audit event for every request.

3. Page Lifecycle

Page supports:

DRAFT
PUBLISHED
UNPUBLISHED
ARCHIVED
TRASH

Conceptual lifecycle:

DRAFT
  │
  ▼
PUBLISHED
  ├── UNPUBLISHED
  └── ARCHIVED

Applicable State
  │
  ▼
TRASH

Restore does not always mean DRAFT.

The previous valid editorial state is restored where current conditions still permit it.

4. Post Lifecycle

Post supports:

DRAFT
PENDING REVIEW
PUBLISHED
UNPUBLISHED
ARCHIVED
TRASH

Contributor workflow:

Contributor
  ↓
DRAFT
  ↓
PENDING REVIEW
  ↓
Editor Review
  ├── PUBLISHED
  └── DRAFT

Editor and Admin may publish directly according to authorization.

Editor does not require Admin approval for publication in V1.

5. Draft

Draft is content that is not publicly published.

Draft:

is not publicly visible;

may be edited by authorized users;

may have revision history;

may have scheduled publishing;

may be moved to Trash;

supports auto-save.

6. Pending Review

PENDING REVIEW is primarily used for Contributor workflow.

Editor may:

publish;

return the content to Draft;

edit the content before publication.

Contributor may monitor the state of their submitted content according to authorization.

7. Published

Published content:

is publicly visible;

may appear in public listings;

may be included in sitemap eligibility;

may be targeted by navigation;

is subject to public caching.

Published content may be:

Published
  ├── Updated
  ├── Unpublished
  ├── Archived
  └── Trashed

8. Editing Published Content

V1 uses Direct Live on Explicit Save.

When an authorized Admin or Editor explicitly saves changes to a published Page/Post:

the new content becomes the current live content;

a new Revision Snapshot is created;

affected public cache entries are invalidated;

the operation is audited according to the audit policy.

The content remains PUBLISHED.

V1 does not implement a separate draft branch for ordinary edits to already-published content.

9. Published Content Auto-save

Auto-save for Published content SHALL NOT modify the live public content.

During editing of a Published Page/Post:

Published Current State
        │
        ├── remains live
        │
        └── Auto-save
              ↓
        recoverable draft state

The auto-saved state may be represented as an autosave revision/snapshot, distinguished from the current live revision.

The live content changes only when the user explicitly performs the applicable Save/Update action.

This rule prevents an automatic browser request from unintentionally changing production content.

10. Auto-save

Auto-save applies to Draft editing and to temporary edits of Published content.

Requirements:

only runs when unsaved changes exist;

does not change publication state;

does not generate audit noise for every request;

preserves recoverable editor state;

reports failure without discarding local content.

Timing:

Change detected
  ↓
approximately 60 seconds without further change
  ↓
Auto-save

Safety interval:

Continuous dirty state
  ↓
at least once every 5 minutes
  ↓
forced Auto-save

No request is sent when no unsaved changes exist.

11. Auto-save and Session Expiration

If an auto-save request encounters an expired session:

HTMX request
  ↓
Session invalid
  ↓
HX-Redirect: /cp

The browser must perform a full-page redirect.

The application SHALL NOT replace the HTMX target with the login page.

The editor UI should preserve local unsaved content where technically possible.

12. Revision Model

Pages and Posts use immutable full-snapshot revisions.

A revision may include:

title
content_payload
categories
tags
manual_author
featured_media_id
SEO metadata
publication-related editorial values

Revision is separate from Audit Trail.

A Revision answers:

What did the content look like?

Audit answers:

What action happened, by whom, and when?

13. Explicit Save vs Auto-save

Explicit Save

Explicit actions such as:

Save Draft
Update
Publish
Unpublish
Archive

are meaningful editorial actions.

The appropriate current state/revision is persisted and affected cache entries are invalidated.

Auto-save

Auto-save is recovery-oriented persistence.

It:

does not publish;

does not unpublish;

does not archive;

does not modify the live state of Published content;

does not create an audit event for every save.

14. Optimistic Concurrency

SMITE CMS SHALL prevent one editing session from silently overwriting another editing session.

Pages and Posts SHALL use a monotonically increasing lock_version or equivalent optimistic concurrency token.

Example:

Browser A
lock_version = 5

Browser B
lock_version = 5

Browser A saves
→ current version becomes 6

Browser B saves with version 5
→ conflict

Conceptual update:

UPDATE posts
SET
    ...,
    lock_version = lock_version + 1
WHERE id = :id
  AND lock_version = :submitted_version;

If no row is affected, the server SHALL treat the request as a concurrency conflict.

Recommended HTTP response:

409 Conflict

The UI must inform the user that the content changed in another session and must not silently discard the user's unsaved changes.

The exact CI4 implementation belongs to 08-Technical-Architecture.md.

15. Unpublished

UNPUBLISHED means content remains in the Control Panel but is not currently public.

Flow:

PUBLISHED
  ↓
UNPUBLISHED

Unpublish SHALL:

change persisted state;

generate an Audit Event;

invalidate affected public caches;

remove the content from public listing/sitemap eligibility where applicable.

Content may later be published again.

16. Archived

ARCHIVED indicates editorial retirement without deleting the content.

Archived content:

is not part of normal public listings;

is not treated as current published content;

remains available to authorized Control Panel users;

may be restored or republished according to authorization.

Pages and Posts both support Archived.

17. Trash

Trash represents logical deletion.

For publishable content:

status = TRASH
deleted_at = timestamp

Trashed content:

is not public;

is excluded from normal editorial queries;

cannot be published while in Trash;

may be restored;

may be permanently deleted by Admin.

18. Restore

Restore SHALL determine the previous valid editorial state.

Example:

PUBLISHED
  ↓
TRASH
  ↓
RESTORE
  ↓
PUBLISHED

or:

PENDING REVIEW
  ↓
TRASH
  ↓
RESTORE
  ↓
PENDING REVIEW

Restore SHALL NOT blindly return all content to Draft.

If the previous public state is no longer valid under current conditions, restore must choose a safe non-public state.

For example, if a publication schedule has already expired and the content is no longer valid for publication, the Service may restore it to ARCHIVED or DRAFT according to the applicable business rule.

Restore SHALL create a new current revision/state and an Audit Event.

Historical revisions remain immutable.

19. Permanent Delete

Permanent deletion is destructive and Admin-only.

Before permanent deletion:

authorization must pass;

dependencies must be checked;

related cleanup must be safe;

multi-entity changes must use an appropriate transaction;

the action must be audited.

Permanent deletion is never the default editorial operation.

20. Page Publishing Validation

Before a Page becomes PUBLISHED, the system SHALL validate:

required Content Items;

Template availability;

Content Schema compliance;

slug;

global URL uniqueness;

Page hierarchy;

required dependencies;

applicable SEO constraints.

If validation fails, publication is rejected.

21. Post Publishing Validation

Before a Post becomes PUBLISHED, the system SHALL validate:

required editorial fields;

slug;

global URL uniqueness;

Categories;

Tags where provided;

Featured Media where provided;

Content Schema;

required localization state;

applicable SEO constraints.

Contributor cannot publish directly.

Editor and Admin may publish according to authorization.

22. Required Content Validation

If a Template/Content Schema defines a field as required, that field must contain a valid value before publication.

Example:

hero_title       REQUIRED
hero_image       REQUIRED
description      OPTIONAL
video            OPTIONAL

If hero_image is missing:

Publish → REJECT

Validation is server-side.

23. Theme/Schema Compatibility

Theme and Template schemas are developer-controlled.

Existing Page/Post content must remain compatible with the active Theme before the Theme is enabled/activated.

V1 does not implement database-level Theme versioning.

A developer deployment that changes a schema must preserve compatibility or provide an explicit migration/compatibility plan before activation.

24. Scheduled Publishing

Pages and Posts support a scheduled publication action.

Conceptual flow:

Cron
  ↓
CI4 Spark command
  ↓
Due ScheduledAction
  ↓
Validate current target state
  ↓
Apply state transition
  ↓
Create Audit Event
  ↓
Invalidate cache

Public rendering does not perform the transition.

25. Scheduled Unpublishing

Pages and Posts support scheduled unpublishing.

Conceptual flow:

PUBLISHED
  ↓
ScheduledAction
  ↓
UNPUBLISHED

Scheduled unpublishing must be:

idempotent;

audited;

cache-invalidating.

26. Scheduler Catch-up

The scheduler SHALL process actions where:

execute_at <= current_time

rather than requiring an exact timestamp match.

If cron is unavailable during the intended execution time, the next successful scheduler run SHALL process overdue actions.

27. Scheduled Action State Validation

The scheduler SHALL NOT blindly execute a ScheduledAction.

Before execution it must validate the current state of the target entity.

Examples:

Scheduled Publish
Target = Post #123

If the Post is already:

PUBLISHED

the action should be marked SKIPPED or otherwise resolved as already satisfied.

If the target is:

TRASH
ARCHIVED

the action should normally be marked SKIPPED/CANCELLED rather than forcing publication.

SKIPPED and CANCELLED are ScheduledAction processing states, not Page/Post editorial states.

All non-standard scheduling outcomes should be recorded in the Audit Trail.

28. Duplicate Scheduled Actions

Equivalent pending scheduled actions should be prevented through an application/database idempotency strategy.

Example:

Target: Post #123
Action: PUBLISH
Execute At: 2026-09-01 08:00

Repeated form submissions must not create uncontrolled duplicates.

29. Slug Changes

Admin/Editor may change a Page/Post slug.

When a published URL changes:

/about

to:

/company

the application SHALL create:

/about → /company
HTTP 301

The old public path remains reserved while its redirect remains active.

The slug change and redirect creation should be atomic.

30. Slug Validation

A new slug SHALL:

use the permitted slug format;

be unique in the global public URL namespace;

not match an active reserved redirect;

not conflict with another current public resource;

not conflict with reserved system routes.

31. Redirect Chains

The system should avoid unnecessary redirect chains.

Example:

/about
  ↓
/company
  ↓
/company-profile

Preferred normalized behavior:

/about → /company-profile
/company → /company-profile

The exact redirect normalization mechanism is defined in the URL/technical architecture.

32. Publishing and Cache

Meaningful public-state changes SHALL invalidate affected caches.

Examples:

Publish Post
  ↓
Invalidate Post cache
  ↓
Invalidate relevant Category cache
  ↓
Invalidate affected Homepage/listing cache

Cache invalidation must account for content relationships and public presentation dependencies.

33. Publishing and Sitemap

When content becomes public:

sitemap eligibility is recalculated;

sitemap cache is invalidated if cached;

public URL becomes renderable.

When content becomes Unpublished, Archived, or Trash:

it is removed from normal sitemap eligibility;

it is not treated as publicly renderable content.

34. Publishing and Menu

A Menu Item that targets content which is no longer public must not produce a broken public link.

For V1, public navigation rendering should omit or safely handle links to non-public content.

The precise UI fallback is defined in the Navigation/Theme implementation.

35. Publishing Errors

Publishing errors must be understandable and actionable.

Example:

Cannot publish:
- Hero image is required.
- Slug is already in use.
- Category no longer exists.
- Selected document is inactive.

Field-level validation errors should be returned where applicable.

A generic error such as Validation failed is insufficient when specific field information is available.

36. Workflow Ownership

Action

Admin

Editor

Contributor

Create Draft Post

✓

✓

✓

Edit Own Post

✓

✓

✓

Edit Any Post

✓

✓

—

Submit Review

✓

✓

✓

Review Contributor Post

✓

✓

—

Publish Post

✓

✓

—

Unpublish Post

✓

✓

—

Archive Post

✓

✓

—

Restore Post

✓

✓

Own/allowed

Permanently Delete

✓

—

—

Create Page

✓

✓

—

Edit Page

✓

✓

—

Publish Page

✓

✓

—

Manage Categories

✓

✓

—

Manage Tags

✓

✓

—

Actual authorization is enforced by 03-Authorization-Security.md.

37. Future Workflow Extensions

V1 intentionally does not include:

multi-step approval chains;

Admin approval for Editor publishing;

external approval workflows;

content comments;

editorial assignments;

complex content collaboration.

These require new approved requirements before implementation.

38. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

03-Authorization-Security.md.

Primary requirement groups:

REQ-PAGE-*
REQ-POST-*
REQ-CONT-*
REQ-THEME-*
REQ-SEO-*
REQ-SCHED-*
REQ-AUDIT-*
REQ-REV-*
REQ-UX-*

All implementations must preserve the invariants defined by those documents.