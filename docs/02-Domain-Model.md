# DOC-02 — SMITE CMS Domain Model

**Document Version:** 0.2.0  
**Status:** Draft Reviewed  
**Last Updated:** 21 August 2026

## 1. Purpose

This document defines the conceptual domain model of SMITE CMS V1.

It establishes domain boundaries, core entities, ownership, relationships, lifecycle concepts, and domain invariants. It intentionally does not define the final database schema, exact indexes, migrations, or implementation classes.

---

## 2. Domain Boundary

```text
SMITE CMS
├── Identity & Access
├── Site Configuration
├── Navigation
├── Content
│   ├── Pages
│   ├── Posts
│   ├── Categories
│   └── Tags
├── Content Schema
│   ├── Content Items
│   └── Repeatable Blocks
├── Theme / Templates
├── Media
│   ├── Images
│   └── Documents
├── Localization
├── Publishing & Scheduling
├── Revision
├── Audit
└── SEO / URL
```

All domains belong to one CodeIgniter 4 application and one MariaDB database. V1 is single-organization and single-website.

---

## 3. Core Entity Map

```text
User
 ├── Post
 └── AuditLog

Page
 ├── PageTranslation
 ├── Template
 ├── Revision
 └── AuditLog

Post
 ├── PostTranslation
 ├── Category
 ├── Tag
 ├── MediaAsset
 ├── Revision
 └── AuditLog

Theme
 └── Template
      └── Content Schema
           ├── Content Field
           └── Repeatable Block

MediaAsset
 ├── Image
 └── Document

SiteSetting
Menu
MenuItem
URLRedirect
ScheduledAction
AuditLog
Revision
```

This is a conceptual model, not a final relational schema.

---

## 4. Identity & Access

### User

`User` represents an authenticated Control Panel account.

Roles:

```text
ADMIN
EDITOR
CONTRIBUTOR
```

Invariants:

- Exactly one Admin exists.
- At least one active Editor exists.
- Contributor is optional.
- Username is unique.
- Email is unique.
- Inactive users cannot authenticate.
- Users are not physically deleted through normal CMS operation.

---

## 5. Site Configuration

### SiteSetting

Represents website-level configuration such as:

```text
site_title
logo
favicon
footer_text
site_description
contact_information
social_links
primary_language
secondary_language
timezone
default_meta_title
default_meta_description
```

No `organization_id` or `tenant_id` is required in V1.

---

## 6. Navigation

### Menu

V1 supports:

```text
PRIMARY
FOOTER
```

### MenuItem

A MenuItem may target:

- Page;
- Post Category;
- External URL.

Maximum hierarchy:

```text
Parent
└── Child
```

Deeper nesting is invalid.

Menu items have explicit ordering and active/inactive state.

---

## 7. Content Domain

Page and Post are separate domain entities.

They SHALL NOT be combined into a single polymorphic content table.

Shared behavior should be implemented through reusable application services where genuinely appropriate.

---

## 8. Page

A Page represents a structured public website page.

Conceptually:

```text
Page
├── identity
├── hierarchy
├── template binding
├── status
├── publication
├── localization
├── URL
├── SEO
└── content
```

Pages support a maximum depth of two levels.

```text
Level 1
└── Parent Page

Level 2
└── Child Page
```

A third level is invalid.

---

## 9. Post

A Post represents an editorial publication.

Conceptually:

```text
Post
├── identity
├── editorial state
├── publication
├── manual author
├── categories
├── tags
├── featured media
├── localization
├── SEO
└── revisions
```

Manual Author is distinct from the system user.

```text
Manual author: John Doe
Created by: editor01
Published by: adminweb
```

---

## 10. Categories and Tags

A Post may have multiple Categories:

```text
Post ←→ Category
```

Categories are flat.

A Post may have multiple optional Tags:

```text
Post ←→ Tag
```

Tags are flat and non-hierarchical.

---

## 11. Content Schema

The Content Schema defines what Admin and Editor users can edit.

```text
Theme
  ↓
Template
  ↓
Content Schema
  ├── Content Field
  └── Repeatable Block
```

The schema is developer-controlled. Admin does not create arbitrary fields.

### Content Field

V1 types:

```text
TEXT
TEXTAREA
RICH_TEXT
IMAGE
YOUTUBE_URL
URL
DOCUMENT
```

Fields may be required or optional and may define defaults.

### Repeatable Block

A bounded developer-defined collection of fields.

Example:

```text
hero_slides[]
├── image
├── title
├── description
├── button_text
├── button_url
└── display_interval
```

Developer controls minimum/maximum items, field definitions, and ordering capability.

Admin may add, edit, remove, and reorder items within those limits.

Repeatable Blocks SHALL NOT become a general-purpose page builder.

---

## 12. Content Payload

SMITE CMS uses a hybrid content model.

Core identity, state, relationships, and indexed data remain relational.

Dynamic content values are stored as schema-validated JSON in the corresponding Translation entity.

```text
Page
└── PageTranslation
     └── content_payload

Post
└── PostTranslation
     └── content_payload
```

Example:

```json
{
  "hero_title": "Welcome",
  "hero_image": {
    "media_id": 42,
    "alt": "Main Building"
  },
  "hero_slides": [
    {
      "image": {
        "media_id": 43,
        "alt": "Campus"
      },
      "title": "Our Campus",
      "description": "A modern learning environment.",
      "button_text": "Learn More",
      "button_url": "/about",
      "display_interval": 5
    }
  ]
}
```

JSON is not free-form storage. Every payload SHALL conform to the developer-defined Theme/Template Content Schema before persistence.

---

## 13. Theme and Template

### Theme

States:

```text
DRAFT
ENABLED
ACTIVE
```

Only developer-controlled deployment/configuration can transition DRAFT to ENABLED.

Admin may activate an ENABLED Theme.

Only one Theme may be ACTIVE.

### Template

Every Theme SHALL provide exactly one:

```text
custom-page
```

template.

Additional templates are optional and developer-defined.

Admin selects from templates provided by the active Theme and cannot modify template source.

---

## 14. Theme Preview

Admin may preview an ENABLED Theme without activating it.

Preview is performed per Page using actual Page content.

Preview SHALL NOT alter:

- active Theme;
- published content;
- public URL state;
- scheduled actions.

Exact preview routing is a technical implementation decision.

---

## 15. Localization

Pages and Posts use dedicated Translation entities:

```text
Page
└── PageTranslation

Post
└── PostTranslation
```

Primary Language is mandatory.

Secondary Language is optional.

The Primary Language translation MUST exist for publishable content.

Fallback:

```text
Requested Language
  ├── translation exists → use it
  └── missing → use Primary Language
```

Fallback is deterministic and belongs to the application/service layer.

---

## 16. Media

### MediaAsset

Represents a managed uploaded asset.

Types:

```text
IMAGE
DOCUMENT
```

Media has its own lifecycle and is not forced into the Page/Post editorial state machine.

### Image

Images are processed using developer-defined profiles such as:

```text
Hero
Featured
Thumbnail
OpenGraph
```

Images below required minimum dimensions are rejected.

Images above configured maximum dimensions are resized.

The original upload is removed after successful processing according to the approved media-processing policy.

### Document

V1 supports PDF and selected common office document formats.

Documents SHALL be stored outside the public web root and served through a controlled download endpoint.

Only documents whose current content state permits public download may be served.

---

## 17. Media References

Dynamic Content Payload references Media Assets using `media_id`.

```json
{
  "hero_image": {
    "media_id": 42,
    "alt": "Main Building"
  }
}
```

`media_id` is authoritative.

Physical storage paths or public URLs SHALL NOT be authoritative values inside Content Payload.

The Media service resolves:

```text
media_id
  ↓
MediaAsset
  ↓
current storage/public representation
```

`alt` may remain in Content Payload because it can be contextual and localized.

---

## 18. Media Referential Integrity

JSON references cannot be protected by ordinary relational foreign keys.

Therefore application-level dependency checking is required.

Before permanent deletion:

```text
MediaService
  ↓
DependencyChecker
  ├── direct relational references
  ├── Page Content Payloads
  └── Post Content Payloads
```

If a Media Asset remains referenced, permanent deletion SHALL be rejected.

The user should be informed where the asset is being used.

Media paths are never treated as a substitute for the authoritative `media_id`.

---

## 19. Media Lifecycle

Media uses a separate lifecycle:

```text
ACTIVE
  ↓
TRASH
```

with:

```text
deleted_at
```

Media does not use Page/Post states such as Draft, Published, Unpublished, or Archived.

---

## 20. URL Domain

Public URLs belong to one global namespace.

Current public URLs may be owned by:

```text
Page
Post
Category
```

and future explicitly defined public resources.

No two active public resources may claim the same path.

---

## 21. URL Redirect

`URLRedirect` represents historical public URLs.

Example:

```text
/about
  ↓ 301
/tentang-kami
```

Conceptual attributes:

```text
source_path
target_path
http_code
resource_type
resource_id
active
created_at
```

An active historical URL remains reserved and cannot be assigned to a new public resource.

The exact database enforcement strategy is deferred.

---

## 22. Publishing Lifecycle

### Page

```text
DRAFT
  ↓
PUBLISHED
  ├── UNPUBLISHED
  └── ARCHIVED
```

### Post

```text
DRAFT
  ↓
PENDING REVIEW
  ↓
PUBLISHED
  ├── UNPUBLISHED
  └── ARCHIVED
```

Deletion from applicable states:

```text
TRASH
```

Only Admin may permanently delete content.

---

## 23. Trash and Restore

Publishable content uses:

```text
status
deleted_at
```

When content enters Trash:

```text
status = TRASH
deleted_at = timestamp
```

Restore SHALL NOT always force the item to Draft.

The system preserves enough state history to determine the previous valid editorial state.

Example:

```text
PUBLISHED
  ↓
TRASH
  ↓
RESTORE
  ↓
PUBLISHED
```

or:

```text
PENDING REVIEW
  ↓
TRASH
  ↓
RESTORE
  ↓
PENDING REVIEW
```

Restore SHALL NOT automatically make content public if its previous public state is no longer valid because of current scheduling, authorization, dependency, or other domain conditions.

---

## 24. Revision

Pages and Posts maintain immutable full-snapshot revisions.

A revision may contain:

```text
title
content_payload
SEO metadata
categories
tags
manual author
featured media reference
other relevant editorial state
```

A revision is self-contained enough to represent the relevant content state at the time it was created.

Restore flow:

```text
Historical Revision
  ↓
Validate
  ↓
Create New Current State
  ↓
Create New Revision
  ↓
Audit RESTORE
```

Existing historical revisions are never modified.

---

## 25. Revision vs Audit

```text
Revision
  = What the content looked like

AuditLog
  = What action happened, by whom, and when
```

Auto-save may create or update recoverable draft/revision state but SHALL NOT create an audit event for every automatic save.

Meaningful actions such as publication, unpublication, archive, restore, and deletion remain auditable.

---

## 26. Audit

AuditLog records meaningful system and security events.

Examples:

```text
USER_LOGIN
USER_DEACTIVATED
POST_CREATED
POST_UPDATED
POST_SUBMITTED_FOR_REVIEW
POST_PUBLISHED
POST_UNPUBLISHED
POST_ARCHIVED
PAGE_PUBLISHED
THEME_ACTIVATED
SETTING_CHANGED
REVISION_RESTORED
PASSWORD_RESET
```

Audit history is immutable.

System-generated events identify the relevant system process or event source.

---

## 27. Scheduling

Scheduling represents future actions, not additional content states.

V1 actions:

```text
PUBLISH
UNPUBLISH
```

A dedicated `ScheduledAction` entity records the target, action, execution time, and processing state.

Conceptual attributes:

```text
id
target_type
target_id
action
execute_at
processed_at
attempts
last_error
failed_at
created_at
updated_at
```

Scheduled Actions support catch-up after cron downtime and idempotent processing.

Equivalent duplicate actions should be prevented through an appropriate uniqueness/idempotency mechanism.

---

## 28. Scheduler Transaction Principle

The scheduler processes due actions transactionally:

```text
START TRANSACTION
  ↓
Lock due action
  ↓
Verify still pending
  ↓
Apply state transition
  ↓
Mark action processed
  ↓
Create audit event
  ↓
Invalidate affected cache
  ↓
COMMIT
```

Concurrent scheduler executions SHALL NOT apply the same action multiple times.

The scheduler is invoked by a CI4 Spark command through cron.

---

## 29. Entity Ownership

| Entity | Authority / Owner |
|---|---|
| User | Admin |
| SiteSetting | Admin |
| Menu | Admin |
| MenuItem | Admin |
| Page | Admin / Editor |
| PageTranslation | Admin / Editor |
| Post | Admin / Editor / Contributor |
| PostTranslation | Admin / Editor / Contributor according to Post permissions |
| Category | Admin / Editor |
| Tag | Admin / Editor |
| MediaAsset | Role-controlled |
| Theme | Developer |
| Template | Developer |
| Content Schema | Developer |
| Content Value | Admin / Editor according to content permissions |
| Revision | System-generated |
| AuditLog | System-generated |
| URLRedirect | System-managed / authorized editorial operation |
| ScheduledAction | System-generated from authorized scheduling |

---

## 30. Domain Invariants

1. Exactly one Admin exists.
2. At least one active Editor exists.
3. Only one Theme is ACTIVE.
4. Every Theme has exactly one `custom-page` template.
5. Page hierarchy cannot exceed two levels.
6. Menu hierarchy cannot exceed two levels.
7. Categories are flat.
8. Tags are flat.
9. A Post may have multiple Categories.
10. A Post may have multiple Tags.
11. Public URLs are globally unique.
12. Historical redirect URLs remain reserved while active.
13. A Contributor can edit only own Posts.
14. An Editor can edit any Post.
15. An Editor can publish without Admin approval.
16. Contributor publication requires Editor review.
17. Only Admin can permanently delete content.
18. User accounts are deactivated rather than normally deleted.
19. Draft/Unpublished documents are not publicly downloadable.
20. Content Schema is developer-controlled.
21. Repeatable Blocks are bounded by developer-defined limits.
22. Revision history is immutable.
23. Audit history is immutable.
24. Scheduler state transitions are idempotent.
25. Primary Language translation must exist.
26. Secondary Language translation is optional.
27. Missing Secondary Language content falls back to Primary Language.
28. A referenced Media Asset cannot be permanently deleted.
29. `media_id` is the authoritative Media reference inside Content Payload.
30. Physical Media paths are not authoritative Content Payload values.
31. JSON Content Payload must conform to its developer-defined Content Schema.
32. Public rendering does not perform hidden scheduled state transitions.

---

## 31. Primary Key Strategy

All internal database entities SHALL use:

```text
BIGINT UNSIGNED AUTO_INCREMENT
```

as the default internal primary-key strategy.

Rationale:

- compact indexes;
- efficient joins;
- suitable for MariaDB/InnoDB;
- appropriate for a single-organization CMS;
- no distributed-ID requirement exists in V1.

Internal numeric IDs SHALL NOT be treated as public URLs.

Public resources use semantic paths/slugs or explicitly defined public identifiers.

---

## 32. Soft Delete Strategy

Publishable entities use:

```text
status
deleted_at
```

This provides explicit Trash state, timestamped deletion, compatibility with application-level soft-delete patterns, and future retention/purge capability.

Permanent deletion is a privileged Admin operation.

User accounts are not permanently deleted through normal CMS operation.

---

## 33. Deferred Technical Decisions

The following are intentionally deferred:

- exact table names and column definitions;
- exact foreign-key definitions;
- exact indexes;
- exact JSON validation mechanism;
- exact Content Schema code representation;
- exact revision snapshot serialization;
- exact localization fallback implementation;
- exact media storage naming;
- exact file-processing libraries;
- exact URL uniqueness enforcement;
- exact scheduler locking strategy;
- exact transaction boundaries;
- exact CI4 Model/Entity/Service class structure.

These SHALL be defined in the appropriate technical document or approved ADR.

---

## 34. Architectural Principles Derived from the Domain Model

1. Prefer entity-driven models over polymorphic mega-tables.
2. Keep Page and Post persistence separate.
3. Reuse application services where behavior is genuinely shared.
4. Keep dynamic content flexible through schema-validated JSON.
5. Keep relational data relational where integrity and indexing matter.
6. Keep localization explicit through Translation entities.
7. Keep Revision and Audit separate.
8. Treat URLs as a globally governed namespace.
9. Treat Scheduled Actions as explicit domain records.
10. Keep Media lifecycle separate from editorial content lifecycle.
11. Do not turn Repeatable Blocks into a general-purpose page builder.
12. Do not introduce database structures solely because they are technically convenient.
13. Do not introduce features outside approved Product Requirements.

---

## 35. Traceability

This document derives primarily from:

- `00-Project-Charter.md`
- `01-Product-Requirements.md`

Relevant requirement groups include:

```text
REQ-AUTH-*
REQ-PAGE-*
REQ-POST-*
REQ-CONT-*
REQ-THEME-*
REQ-MEDIA-*
REQ-DOC-*
REQ-LOC-*
REQ-SEO-*
REQ-SCHED-*
REQ-AUDIT-*
REQ-REV-*
```

Later technical documents SHALL preserve the domain invariants defined here.
