DOC-01 — SMITE CMS Product Requirements

Document Version: 0.2.0
Status: Approved — Product Requirements
Last Updated: 20 August 2026

1. Purpose

This document defines the functional and non-functional requirements for SMITE CMS V1.

Each requirement has a stable identifier using the following convention:

REQ-DOMAIN-xxx

Implementation tasks should reference one or more requirement IDs.

2. Requirement ID Domains

Domain

Prefix

Scope

Authentication & Users

REQ-AUTH

Login, users, sessions, recovery

Site Settings

REQ-SITE

Website configuration

Navigation

REQ-MENU

Menus and menu items

Pages

REQ-PAGE

Page management

Posts

REQ-POST

Editorial posts

Categories

REQ-CAT

Post categories

Tags

REQ-TAG

Post tags

Content

REQ-CONT

Content Items and Repeatable Blocks

Themes

REQ-THEME

Themes and templates

Media

REQ-MEDIA

Image and media management

Documents

REQ-DOC

Public downloadable documents

Localization

REQ-LOC

Primary and secondary language

SEO

REQ-SEO

Metadata, sitemap, redirects

Scheduling

REQ-SCHED

Scheduled publishing

Audit

REQ-AUDIT

Immutable audit trail

Revision

REQ-REV

Revision history

Cache

REQ-CACHE

Public/application caching

Control Panel UX

REQ-UX

Dashboard and editor UX

Non-Functional

REQ-NFR

Security, performance, maintainability

Scope

REQ-SCOPE

Scope control

3. Authentication & User Requirements

REQ-AUTH-001 — Admin Account

SMITE CMS SHALL maintain exactly one Admin account.

The Admin account:

cannot be permanently deleted;

may change its username;

may change its email;

may change its password;

has full system authority.

REQ-AUTH-002 — Editor Accounts

The system SHALL support multiple Editor accounts.

At least one active Editor must exist.

REQ-AUTH-003 — Contributor Accounts

The system SHALL support zero or more Contributor accounts.

Contributor is optional.

REQ-AUTH-004 — User Deactivation

Users SHALL be deactivated using an active state rather than permanently deleted.

Inactive users SHALL NOT be able to authenticate.

Existing sessions of a deactivated user SHALL become invalid.

REQ-AUTH-005 — Login

Control Panel authentication SHALL use:

username
password

Authentication entry point:

/cp

After successful authentication:

/admin/*

REQ-AUTH-006 — Username

Username SHALL be:

required;

unique;

normalized to lowercase;

trimmed;

validated server-side.

REQ-AUTH-007 — Email

Email SHALL be:

required;

unique;

stored for password recovery and future notifications;

treated as PII.

Email SHALL NOT be the V1 login identifier.

REQ-AUTH-008 — Password Reset

Password reset SHALL support:

Email-based password recovery.

Admin-initiated reset.

Admin SHALL never be able to view another user's existing password.

REQ-AUTH-009 — Admin Recovery

The Admin SHALL have an emergency recovery mechanism using the environment secret:

skey

The recovery secret SHALL:

never be stored in the database;

never be logged;

never be displayed;

never be committed to Git;

be rate-limited;

generate an audit event.

REQ-AUTH-010 — Session Timeout

The Control Panel SHALL expire inactive sessions according to the configured session lifetime.

After expiration, subsequent authenticated requests SHALL be rejected.

The frontend SHALL detect the expired session and redirect the user to:

/cp

The user must not remain apparently inside /admin/* while requests silently fail.

REQ-AUTH-011 — Session Invalidation

Changing or resetting a password SHALL invalidate existing sessions for that account.

Deactivating a user SHALL invalidate existing sessions.

REQ-AUTH-012 — Brute Force Protection

Authentication and recovery endpoints SHALL be protected against brute-force attempts using server-side throttling.

4. Site Settings Requirements

REQ-SITE-001 — Site Configuration

Admin SHALL be able to configure general website settings.

Initial settings include:

Site title.

Logo.

Favicon.

Footer text.

Site description.

Contact information.

Social media links.

Default SEO metadata.

Primary language.

Secondary language.

Timezone.

REQ-SITE-002 — Admin Only

Site Settings SHALL only be manageable by Admin.

Editor and Contributor SHALL NOT modify Site Settings.

REQ-SITE-003 — Settings Validation

All settings SHALL be validated server-side.

REQ-SITE-004 — Settings Cache

Frequently accessed public settings SHALL be cacheable.

Settings changes SHALL invalidate the affected cache.

5. Menu Requirements

REQ-MENU-001 — Menu Locations

V1 SHALL support:

Primary
Footer

REQ-MENU-002 — Menu Hierarchy

Menus SHALL support a maximum of two levels.

Parent
└── Child

Deeper nesting SHALL be rejected.

REQ-MENU-003 — Menu Targets

A Menu Item SHALL be able to target:

Page.

Post Category.

External URL.

REQ-MENU-004 — Menu State

Menu Items SHALL support active/inactive state.

REQ-MENU-005 — Menu Ordering

Menu Items SHALL support explicit ordering.

REQ-MENU-006 — Menu Ownership

Admin SHALL have full Menu management.

Editor SHALL NOT manage the site's Menu structure.

The HTML structure and visual presentation of the Menu remain Theme-controlled.

6. Page Requirements

REQ-PAGE-001 — Page Creation

Admin and authorized Editor users SHALL be able to create Pages.

REQ-PAGE-002 — Page Hierarchy

Pages SHALL support a maximum depth of two levels.

REQ-PAGE-003 — Page Status

Page status SHALL support:

Draft
Published
Unpublished
Archived
Trash

REQ-PAGE-004 — Page Scheduling

Pages SHALL support:

Scheduled publish.

Scheduled unpublish.

REQ-PAGE-005 — Page Slug

Page slug SHALL:

be editable by authorized Admin/Editor users;

be normalized;

be unique within the global public URL namespace;

be validated server-side.

REQ-PAGE-006 — Global URL Uniqueness

A Page SHALL NOT be allowed to create a public URL that conflicts with another public resource, including Posts and other reserved public URLs.

REQ-PAGE-007 — Page Template

A Page SHALL use a developer-defined template available from the active Theme.

Admin SHALL NOT create templates.

REQ-PAGE-008 — Custom Page

Every Theme SHALL provide exactly one custom-page template.

Admin SHALL be able to create a Page using custom-page.

REQ-PAGE-009 — Content Area Boundary

Admin and Editor SHALL only manage content exposed by the selected template's Content Schema.

They SHALL NOT modify:

Header.

Navigation markup.

Footer.

Global layout.

Template HTML.

REQ-PAGE-010 — Page Revision

Page changes SHALL create revision history.

REQ-PAGE-011 — Page Restore

Authorized users SHALL be able to restore a previous Page revision.

Revision restoration SHALL be audited.

REQ-PAGE-012 — Page Deletion

Deleting a Page SHALL move it to Trash.

Only Admin SHALL be allowed to permanently delete a Page.

7. Post Requirements

REQ-POST-001 — Post Creation

Admin, Editor, and Contributor SHALL be able to create Posts according to their role.

REQ-POST-002 — Post Editing

Contributor:

edit own Post

Editor:

edit any Post

Admin:

edit any Post

REQ-POST-003 — Post Status

Post SHALL support:

Draft
Pending Review
Published
Unpublished
Archived
Trash

REQ-POST-004 — Contributor Review

Contributor Posts SHALL support:

Draft
   ↓
Pending Review
   ↓
Editor review

Editor may:

publish;

return to Draft;

edit the Post before publication.

REQ-POST-005 — Editor Publishing

Editor SHALL be able to publish Posts directly without Admin approval.

REQ-POST-006 — Post Author

Post SHALL contain a public-facing manual author field.

The displayed author is independent from the system user who created or uploaded the Post.

Example:

Written by: John Doe

Audit history separately records the actual system user who created, edited, or published the content.

REQ-POST-007 — Categories

A Post SHALL support multiple Categories.

REQ-POST-008 — Tags

A Post SHALL support multiple Tags.

Tags are optional.

REQ-POST-009 — Featured Image

A Post MAY have a Featured Image.

The image SHALL use a developer-defined media profile.

REQ-POST-010 — Post Revision

Post changes SHALL create revision history.

REQ-POST-011 — Post Restore

Authorized users SHALL be able to restore previous Post revisions.

Restoration SHALL be audited.

REQ-POST-012 — Post Scheduling

Posts SHALL support:

Scheduled publish.

Scheduled unpublish.

REQ-POST-013 — Post Deletion

Deleting a Post SHALL move it to Trash.

Only Admin SHALL permanently delete a Post.

8. Category Requirements

REQ-CAT-001 — Flat Categories

Categories SHALL be flat.

No parent/child Category hierarchy is permitted.

REQ-CAT-002 — Category Management

Admin and Editor SHALL be able to:

create;

edit;

deactivate;

restore Categories according to authorization.

REQ-CAT-003 — Category Deletion Safety

A Category still referenced by Posts SHALL require a safe replacement strategy before permanent removal.

The preferred UX is to require the operator to select a replacement Category.

9. Tag Requirements

REQ-TAG-001 — Flat Tags

Tags SHALL be flat.

REQ-TAG-002 — Tag Management

Admin and Editor SHALL be able to manage Tags.

REQ-TAG-003 — Optional Tags

Tags SHALL remain optional for Posts.

10. Content Item Requirements

REQ-CONT-001 — Named Slots

Content Items SHALL be developer-defined named slots known by the selected Page Template.

Example:

<?= $content['home']['hero_title'] ?>

Admin SHALL only edit values exposed by the schema.

REQ-CONT-002 — No Arbitrary Fields

Admin SHALL NOT create arbitrary Content Item fields.

REQ-CONT-003 — V1 Content Types

V1 SHALL support:

TEXT
TEXTAREA
RICH_TEXT
IMAGE
YOUTUBE_URL
URL
DOCUMENT

REQ-CONT-004 — Required Fields

A Content Schema SHALL be able to mark an Item as:

required
optional

REQ-CONT-005 — Default Values

A Content Schema MAY provide a default value.

REQ-CONT-006 — Validation

Content Items SHALL be validated according to their schema and type.

REQ-CONT-007 — Rich Text

RICH_TEXT SHALL be sanitized server-side using an explicit allowlist.

Client-side editor behavior SHALL NOT be treated as a security boundary.

REQ-CONT-008 — YouTube URL

YOUTUBE_URL SHALL accept supported YouTube URL formats and normalize them to a safe video identifier.

Arbitrary iframe HTML SHALL NOT be accepted.

REQ-CONT-009 — Repeatable Blocks

A Content Schema MAY define a Repeatable Block containing a predefined collection of Content Items.

Example:

hero_slides[]
├── image
├── title
├── description
├── button_text
├── button_url
└── display_interval

REQ-CONT-010 — Repeatable Block Limits

Each Repeatable Block SHALL define developer-controlled minimum and maximum item counts where applicable.

Admin SHALL NOT exceed the configured maximum.

REQ-CONT-011 — Repeatable Block Structure

The fields within each Repeatable Block SHALL be developer-defined.

Admin SHALL NOT add arbitrary fields to a Repeatable Block.

REQ-CONT-012 — Repeatable Block Ordering

Where enabled by the schema, Admin and authorized Editor users MAY reorder Repeatable Block items.

11. Theme Requirements

REQ-THEME-001 — Developer Controlled

Themes SHALL be created and maintained by developers.

Admin cannot create or edit Theme source.

REQ-THEME-002 — Theme States

Themes SHALL support:

DRAFT
ENABLED
ACTIVE

REQ-THEME-003 — Developer Enablement

Only developer-controlled deployment/configuration can change a Theme from DRAFT to ENABLED.

Admin SHALL NOT enable a Theme.

REQ-THEME-004 — Theme Activation

Admin SHALL be able to activate an ENABLED Theme.

REQ-THEME-005 — Single Active Theme

Only one Theme SHALL be ACTIVE at a time.

Activating another Theme SHALL deactivate the previous Theme.

REQ-THEME-006 — Required Custom Page

Every Theme SHALL provide exactly one custom-page template.

REQ-THEME-007 — Additional Templates

A Theme MAY provide additional developer-defined templates.

REQ-THEME-008 — Theme Preview

Admin SHALL be able to preview an ENABLED Theme without changing the active Theme.

Preview SHALL be performed per Page using the Page's actual content data.

REQ-THEME-009 — Presentation Boundary

Admin SHALL NOT modify:

Theme source.

Header.

Navigation markup.

Footer.

Layout.

Template structure.

Content Schema definition.

12. Media Requirements

REQ-MEDIA-001 — Media Library

The CMS SHALL provide a reusable Media Library.

REQ-MEDIA-002 — Image Processing

Uploaded images SHALL be:

Validated.

Checked for dimensions.

Resized if necessary.

Optimized.

Stored in the required output format.

Removed in original form after successful processing.

REQ-MEDIA-003 — Image Profiles

Image dimensions SHALL be determined by developer-defined usage profiles.

Examples:

Hero
Featured
Thumbnail
OpenGraph

REQ-MEDIA-004 — Minimum Dimensions

An image SHALL be rejected when it is below the minimum dimensions required by its selected profile.

REQ-MEDIA-005 — Maximum Dimensions

An image larger than the maximum dimensions SHALL be resized.

REQ-MEDIA-006 — Media Replacement

Replacing a media asset SHALL create/use a new Media Asset rather than mutating a shared asset unexpectedly.

REQ-MEDIA-007 — Media Authorization

Contributor, Editor, and Admin media permissions SHALL be role-controlled.

13. Document Requirements

REQ-DOC-001 — Document Types

V1 SHALL support:

PDF.

Selected common office document formats.

The exact MIME/extension allowlist SHALL be defined in the technical architecture/security documentation.

REQ-DOC-002 — Document Content Item

DOCUMENT SHALL be a supported Content Item type.

REQ-DOC-003 — Public Download

Only published/active documents SHALL be publicly downloadable.

REQ-DOC-004 — Private Storage

Draft, inactive, unpublished, and trashed documents SHALL NOT expose publicly accessible storage URLs.

Documents SHALL be stored outside the public web root and served through a controlled download endpoint that validates the current document/content state before delivery.

REQ-DOC-005 — Download Authorization

The public download endpoint SHALL resolve the document through its application identity and verify that its current state permits public download before serving the file.

14. Localization Requirements

REQ-LOC-001 — Primary Language

Primary Language SHALL be required.

REQ-LOC-002 — Secondary Language

Secondary Language SHALL be optional.

REQ-LOC-003 — Localized Pages

Pages SHALL support localized content.

REQ-LOC-004 — Localized Posts

Posts SHALL support localized content.

REQ-LOC-005 — Fallback

Missing Secondary Language content SHALL fall back to Primary Language.

REQ-LOC-006 — Language Configuration

Admin SHALL be able to configure Primary and Secondary Language from Site Settings.

15. SEO Requirements

REQ-SEO-001 — Metadata

Pages and Posts SHALL support:

Meta title.

Meta description.

Canonical URL.

Open Graph image.

REQ-SEO-002 — Site Defaults

Site-level SEO defaults SHALL be available through Site Settings.

REQ-SEO-003 — Sitemap

The public website SHALL expose:

/sitemap.xml

Only publicly renderable content SHALL be included.

REQ-SEO-004 — Robots

The public website SHALL expose:

/robots.txt

REQ-SEO-005 — Slug History

When a published Page/Post slug changes, the previous public URL SHALL be retained as redirect history.

The preferred redirect is HTTP 301.

REQ-SEO-006 — Reserved Historical URLs

A historical public URL retained for redirect purposes SHALL remain reserved in the global public URL namespace.

The reserved URL SHALL NOT be assigned to a new Page, Post, or other public resource while its redirect record remains active.

An authorized operation may explicitly remove or override the redirect record according to the URL-management rules.

REQ-SEO-007 — Global URL Namespace

Current public URLs and active historical redirect URLs SHALL share one globally unique namespace.

16. Scheduling Requirements

REQ-SCHED-001 — Scheduler

Scheduling SHALL be executed by a CI4 Spark command invoked by cron.

REQ-SCHED-002 — Idempotency

The scheduler SHALL be safe to execute repeatedly.

REQ-SCHED-003 — Late Jobs

If cron is unavailable when a scheduled action becomes due, the next scheduler execution SHALL process the overdue action.

REQ-SCHED-004 — Audit

Scheduled state changes SHALL generate audit events.

REQ-SCHED-005 — Cache Invalidation

Scheduled state changes SHALL invalidate affected public caches.

REQ-SCHED-006 — State Ownership

The scheduler SHALL update the persisted content state.

Public rendering SHALL read the current persisted state and SHALL NOT silently perform scheduled state transitions.

17. Audit Requirements

REQ-AUDIT-001 — Audit Trail

The system SHALL record security-sensitive and meaningful state-changing operations.

REQ-AUDIT-002 — Actor

Audit events SHALL identify the system user responsible for the action where applicable.

System-generated events SHALL identify the responsible system process or event source.

REQ-AUDIT-003 — Timestamp

Audit events SHALL record when the action occurred.

REQ-AUDIT-004 — Immutable

Audit records SHALL not be editable through the CMS.

REQ-AUDIT-005 — Retention

Audit records SHALL be retained indefinitely for V1.

REQ-AUDIT-006 — Auto-save Noise

Routine draft auto-save operations SHALL NOT generate an audit event for every automatic save.

Meaningful editorial actions such as explicit save, submission, publication, unpublication, archive, restore, and deletion SHALL remain auditable according to the audit policy.

18. Revision Requirements

REQ-REV-001 — Page Revision

Pages SHALL maintain revision history.

REQ-REV-002 — Post Revision

Posts SHALL maintain revision history.

REQ-REV-003 — Draft Auto-save Revision

Draft auto-save SHALL preserve the latest unsaved editorial state in a recoverable form.

REQ-REV-004 — Restore

Authorized users SHALL be able to restore a prior revision.

REQ-REV-005 — Restore Audit

Revision restoration SHALL create an Audit Event.

REQ-REV-006 — Immutable Revision History

Existing revisions SHALL be immutable. Restoring a previous revision SHALL create a new current state/revision rather than modifying the historical snapshot.

19. Caching Requirements

REQ-CACHE-001 — Public Cache

Stable public content SHALL be cacheable.

REQ-CACHE-002 — Cache Invalidation

Content changes SHALL invalidate relevant cache entries.

REQ-CACHE-003 — No Redis Requirement

V1 SHALL NOT require Redis.

The implementation SHALL use a cache mechanism compatible with shared hosting.

REQ-CACHE-004 — Scheduler Cache Invalidation

Scheduled publishing and unpublishing SHALL invalidate affected public caches.

20. Control Panel UX Requirements

REQ-UX-001 — Role-Aware Navigation

Control Panel navigation SHALL only display functionality available to the authenticated role.

REQ-UX-002 — Server Authorization

Hidden navigation SHALL NOT be treated as authorization.

Every protected action SHALL be authorized server-side.

REQ-UX-003 — Session Expiration UX

When a session expires, the user SHALL be redirected to /cp rather than receiving an unexplained application error.

REQ-UX-004 — Draft Auto-save

Draft article editing SHALL support automatic saving of unsaved changes.

The default auto-save behavior SHALL use a change-aware mechanism:

after a change is detected, auto-save SHOULD occur after approximately 60 seconds without further changes;

while unsaved changes remain continuously dirty, the system SHALL perform a safety auto-save at least once every 5 minutes;

no auto-save request SHALL be sent when there are no unsaved changes.

REQ-UX-005 — Unsaved State

The editing UI SHOULD clearly indicate whether there are unsaved changes.

REQ-UX-006 — Auto-save Failure

If auto-save fails, the editor SHALL be informed without discarding the local unsaved content.

The UI SHOULD provide a clear indication that the latest changes have not been persisted.

21. Non-Functional Requirements

REQ-NFR-001 — Security

The application SHALL follow the security requirements defined in 03-Authorization-Security.md and the mandatory global .cursorrules.

REQ-NFR-002 — Performance

The application SHALL:

paginate unbounded lists;

avoid N+1 queries;

select only required columns where practical;

cache stable public data;

optimize images at ingestion;

avoid unnecessary asynchronous requests;

avoid polling where event-driven interaction is sufficient.

REQ-NFR-003 — Maintainability

The application SHALL minimize external dependencies.

REQ-NFR-004 — Upgradeability

Application code SHALL avoid unnecessary framework overrides and coupling to framework internals.

REQ-NFR-005 — Shared Hosting

V1 SHALL operate without:

Docker.

Redis.

RabbitMQ.

Background queue workers.

REQ-NFR-006 — Accessibility

Public pages SHALL use semantic HTML5 and follow practical accessibility best practices.

REQ-NFR-007 — Responsive Design

Public pages SHALL support:

smartphone;

tablet;

laptop/desktop.

REQ-NFR-008 — Browser-Side Libraries

V1 SHALL use:

Tailwind CSS.

Alpine.js.

HTMX.

jQuery SHALL NOT be used.

Additional libraries require explicit justification.

22. Product Scope Guard

REQ-SCOPE-001 — No Unrequested Features

The implementation SHALL NOT introduce features outside this requirements document merely because they are:

common in CMS products;

technically convenient;

easy to implement;

suggested by a library;

implied by a framework.

REQ-SCOPE-002 — Future Features

Potential future functionality SHALL be documented as future scope and SHALL NOT be implemented until explicitly approved.

23. Requirement Traceability

Each implementation task SHOULD reference one or more requirement IDs.

Example:

Implement:
REQ-POST-002
REQ-POST-003
REQ-POST-004
REQ-POST-005

A feature that cannot be mapped to an existing requirement SHALL either:

be explicitly identified as an implementation detail required to satisfy an existing requirement; or

require a new approved requirement before implementation.

24. Requirement Governance

This document defines the approved functional contract for V1.

Changes to an approved requirement SHALL:

Identify the affected requirement ID.

Explain the reason for the change.

Identify affected documents and ADRs.

Update the document version.

Be approved before implementation.

Cursor SHALL NOT silently modify an approved requirement.