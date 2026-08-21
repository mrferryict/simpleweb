DOC-05 — SMITE CMS Theme & Template Architecture

Document Version: 0.2.0
Status: Approved — Theme & Template Architecture
Last Updated: 21 August 2026

1. Purpose

This document defines how Themes, Templates, Content Schemas, Theme Manifests, Preview, activation, assets, and compatibility operate in SMITE CMS V1.

The core principle is:

Developer owns presentation architecture; Admin owns content and Theme selection.

2. Theme Architecture Principles

THEME-001 — Developer Controlled Presentation

A Theme is a developer-created presentation package.

The Theme controls:

layout;

HTML structure;

header;

navigation markup;

footer;

components;

assets;

page templates;

content schemas;

presentation-level behavior.

Admin does not edit Theme source.

THEME-002 — CMS Is Not a Website Builder

SMITE CMS does not provide a visual page builder.

Admin cannot:

create arbitrary layouts;

create arbitrary HTML sections;

create Templates;

create arbitrary Content Fields;

modify Header HTML;

modify Footer HTML;

drag and drop arbitrary presentation blocks.

3. Theme Entity

A Theme is a presentation package with a stable developer-controlled identifier.

Conceptual structure:

Theme
├── Manifest
├── Layout
├── Header
├── Navigation
├── Footer
├── Components
├── Assets
├── Page Templates
└── Content Schemas

Example Theme identifiers:

classic
modern
corporate

Theme identifiers are developer-controlled.

4. Theme Lifecycle

Theme states:

DRAFT
ENABLED
ACTIVE

Lifecycle:

Developer creates Theme
        ↓
      DRAFT
        ↓
Developer enables Theme
        ↓
     ENABLED
        ↓
Admin activates Theme
        ↓
      ACTIVE

Admin cannot perform:

DRAFT → ENABLED

Only developer-controlled deployment/configuration may enable a Theme.

5. Single Active Theme

Only one Theme may be ACTIVE at any time.

Example:

Theme A = ACTIVE
Theme B = ENABLED

If Admin activates Theme B:

Theme A → inactive
Theme B → ACTIVE

Theme activation applies globally to the website.

6. Theme Activation Boundary

Admin may:

View available Themes
Preview enabled Theme
Activate enabled Theme

Admin may not:

Create Theme
Edit Theme source
Enable Theme
Upload arbitrary Theme package
Modify Theme Manifest
Modify Content Schema definitions
Modify Template source

Theme source is always supplied by developer-controlled code/deployment.

7. Required custom-page Template

Every Theme SHALL provide exactly one:

custom-page

template.

custom-page is the general-purpose Page Template used when a Page does not require another developer-defined template.

Example:

Theme Classic
├── home
├── news-list
├── article
└── custom-page

Additional Templates are optional.

8. Theme Manifest

Every Theme SHALL contain exactly one official Theme Manifest.

The Manifest is the developer-controlled single source of truth for Theme metadata and presentation contracts.

The exact file format is an implementation decision, but V1 should support a developer-maintained manifest such as:

ThemeManifest.php

or an equivalent structured configuration file.

The Manifest SHALL define, at minimum:

id
name
version
templates
custom-page requirement
Content Schemas
Repeatable Block limits
Media Profile requirements

Theme lifecycle availability such as DRAFT and ENABLED is controlled by developer deployment/configuration and SHALL NOT be editable by Admin through the Manifest.

The manifest is used by the application to:

discover available Templates;

expose Content Schema fields in the Control Panel;

validate Content Payload;

validate Theme compatibility;

identify required Media Profiles.

9. Template Ownership

A Template belongs to a Theme.

Developer controls:

Template source;

HTML;

CSS classes;

component structure;

Content Schema;

validation rules;

Media Profile requirements;

presentation behavior.

Admin controls only the content values exposed by the Template.

10. Template Contract

Every Template has a Content Contract.

Conceptually:

Theme
  ↓
Template
  ↓
Content Schema
  ↓
Allowed Content Fields

Example:

Template: homepage

hero_title
hero_image
hero_description
hero_slides
cta_title
cta_url

Admin sees these as controlled editing fields rather than as a schema editor.

11. Content Schema

A Content Schema is a developer-defined contract describing what Admin/Editor may edit.

A schema may define:

key
label
type
required
default
validation
media_profile

For Repeatable Blocks:

minimum_items
maximum_items
fields
ordering

Content Schema definitions are owned by the Theme Manifest/developer code.

12. Scalar Content Fields

V1 supports:

TEXT
TEXTAREA
RICH_TEXT
IMAGE
YOUTUBE_URL
URL
DOCUMENT

Field type determines:

form control;

server-side validation;

normalization;

rendering behavior;

security constraints;

media requirements where applicable.

13. Repeatable Blocks

Themes may define bounded Repeatable Blocks.

Example:

hero_slides
├── image
├── title
├── description
├── button_text
├── button_url
└── display_interval

The developer defines:

minimum_items
maximum_items
field definitions
ordering capability

Admin may:

Add
Edit
Remove
Reorder

within those limits.

Admin may not:

Add Field
Change Field Type
Change Validation
Change Maximum

Repeatable Blocks SHALL NOT become a general-purpose page builder.

14. Content Payload Validation

Content Payload must be validated against the active Template/Content Schema before persistence.

Flow:

Admin Input
    ↓
Authorization
    ↓
Theme Manifest
    ↓
Template Schema
    ↓
Schema Validation
    ↓
Normalized Content
    ↓
Persistence

Content Payload is not arbitrary JSON.

15. Template and Page Relationship

Pages use Templates exposed by the active Theme.

Conceptually:

Active Theme
    ↓
Available Templates
    ↓
Page Template Selection

Example:

Page:
About
template = custom-page

The active Theme provides the corresponding Template.

16. Theme Switching and Data Preservation

Theme activation is global, but Theme switching SHALL be non-destructive to stored content.

When Theme A contains:

hero_slides

and Theme B contains:

single_banner

activating Theme B SHALL NOT remove, prune, rewrite, or truncate the old hero_slides data stored in the Page/Post Content Payload.

Stored content remains available for future Theme changes.

If Theme A is activated again later, its previous compatible content data remains available.

The active Theme determines what content is currently understood/rendered; it does not determine what historical content data is physically retained.

17. Defensive Template Rendering

Templates SHALL render Content Payload defensively.

A Template must not assume every optional field always exists.

Conceptual behavior:

theme_content('hero_title', $default)

or equivalent safe access.

Templates SHALL avoid producing runtime warnings/errors such as:

Undefined array key
Undefined index

when an optional field is absent or a different Theme/Schema is active.

Required fields must still be validated by the application before publication.

18. Theme Compatibility

A Theme must not become ENABLED if deployment would make existing public Pages impossible to render safely without an approved compatibility/migration plan.

Compatibility includes at minimum:

custom-page exists
required files/components exist
manifest loads
schema definitions load
required rendering contract is available
existing content can be rendered safely

The exact automated compatibility checks belong to the technical/testing documents.

19. Theme Preview

Admin may preview an ENABLED Theme without activating it.

Preview is performed per Page using actual Page content.

Conceptual route:

/preview/theme/{theme}/{page}

The exact route and authorization mechanism are implementation decisions.

20. Preview Security and Cache Isolation

Theme Preview:

is Admin-only;

does not change the ACTIVE Theme;

does not alter persisted public content;

does not create public cache entries;

does not expose draft presentation to anonymous visitors.

Preview responses SHALL send:

Cache-Control: no-store, no-cache, must-revalidate
Pragma: no-cache

Preview SHALL bypass application/public content caches.

Preview output SHALL NOT be written into normal public cache storage.

21. Preview with Real Content

Preview uses actual Page content:

Admin
  ↓
Select Page
  ↓
Select ENABLED Theme
  ↓
Preview
  ↓
Render actual Page Content
using candidate Theme

This allows the Admin to evaluate a future Theme without changing production presentation.

22. Preview and Published State

Preview may render content according to Admin authorization even when the underlying content is:

Draft
Published
Unpublished
Archived

Preview does not change the content state.

If unsaved editor changes must be previewed, the application may use an explicitly persisted draft/autosave snapshot according to the content publishing policy.

Preview SHALL NOT silently persist editorial changes.

23. Theme Activation

Only Admin may activate an ENABLED Theme.

Activation flow:

Admin
  ↓
Select ENABLED Theme
  ↓
Compatibility Check
  ↓
Begin Transaction
  ↓
Activate new Theme
  ↓
Deactivate previous Theme
  ↓
Invalidate public presentation cache
  ↓
Audit THEME_ACTIVATED
  ↓
Commit

If activation fails, the previous active Theme must remain active.

24. Theme Deactivation

V1 does not provide an independent Deactivate Theme action.

Activating another Theme automatically replaces the previous active Theme.

The website must not be left without an ACTIVE Theme as the result of a normal activation operation.

25. Theme Source Updates

Theme source is a developer-controlled code/deployment artifact.

V1 does not provide database-level Theme version history.

When developers update Theme source:

Theme source update
       ↓
Deployment
       ↓
New Theme behavior

Git is the version-control mechanism for Theme source.

Theme Manifest version is metadata for the deployed Theme and compatibility tracking; it is not a database-level revision history system.

26. Theme Assets

Theme static assets include:

CSS
JavaScript
icons
theme images
fonts
other static presentation assets

These are developer-owned assets.

Admin does not manage Theme assets through the Content Media Library.

27. Theme Asset Helper

Templates SHALL NOT hard-code static Theme asset paths.

The application SHALL provide a standard helper/service for resolving active Theme assets.

Conceptual examples:

theme_asset('css/app.css')
theme_asset('js/app.js')

or:

theme_url('images/logo.svg')

The helper resolves the asset against the currently active Theme.

Conceptual public structure:

public/themes/{active_theme}/...

The exact filesystem/public URL implementation is defined in 08-Technical-Architecture.md.

28. Header, Navigation, Footer Boundary

Admin/Editor manage navigation data where authorized.

Theme controls the presentation markup:

<header>...</header>
<nav>...</nav>
<main>...</main>
<footer>...</footer>

Admin cannot modify this HTML structure from the Control Panel.

29. Content Section Boundary

A Page Template defines where CMS-managed content is rendered.

Conceptually:

<header>
    ...
</header>

<nav>
    ...
</nav>

<main>
    <section id="content">
        <!-- CMS-managed content -->
    </section>
</main>

<footer>
    ...
</footer>

CMS content is restricted to the Theme-defined content area.

Admin cannot modify the outer layout structure.

30. Template Rendering Contract

Templates receive normalized content data from the application layer.

Example:

$content['home']['hero_title']

Templates SHALL NOT:

query the database directly;

contain business rules;

perform authorization;

bypass Content Schema validation;

treat raw Content Payload as trusted input.

31. Theme and Cache

Public rendering may use cached Theme/content output according to REQ-CACHE-*.

Theme activation SHALL invalidate affected presentation caches.

Theme-dependent cache keys must include sufficient Theme identity to prevent stale output from a previous Theme.

Preview bypasses normal public/application cache behavior.

32. Theme Security

Theme source is trusted developer code.

Content values are untrusted data.

Theme code SHALL:

escape dynamic output;

use sanitized Rich Text;

validate expected Content Schema values;

never trust Admin input;

never embed secrets;

never bypass authorization.

33. Theme JavaScript Policy

Theme frontend SHALL:

use Alpine.js for ephemeral UI state;

use HTMX for server-driven asynchronous interactions;

not use jQuery;

not use SPA frameworks;

minimize third-party libraries.

Additional libraries require explicit justification.

34. Responsive Presentation

Themes SHALL support:

Smartphone
Tablet
Laptop/Desktop

Device-specific presentation remains a Theme responsibility.

Content Schema should not encode device-specific presentation logic unless explicitly required.

35. Accessibility

Themes should use:

semantic HTML5;

meaningful heading hierarchy;

accessible labels;

keyboard-friendly controls;

appropriate image alt handling;

accessible navigation;

visible focus behavior.

Content remains data-driven while accessibility remains presentation-controlled.

36. Theme Development Rules

A developer creating a Theme must:

provide exactly one custom-page;

provide a Theme Manifest;

define Template Content Schemas;

define required/optional fields;

define sensible defaults where useful;

define Image/Media Profiles as required;

define validation expectations;

ensure existing content remains safely renderable;

test public and preview rendering;

preserve compatibility with current data;

document any new required content contract.

37. Theme Activation Safety

Before activation, the system/application should verify at minimum:

Theme = ENABLED
custom-page exists
Manifest loads successfully
required files/components exist
schema definitions load
required rendering contract is valid

The exact automated checks belong to the Testing and Technical Architecture documents.

38. Failure Safety

If an ENABLED Theme cannot be activated safely:

activation must fail;

current ACTIVE Theme must remain active;

no partial activation may occur;

the failure must be visible to Admin;

technical details belong in controlled logs;

no incomplete public state may be produced.

39. Theme Activation Audit

Successful Theme activation SHALL generate:

THEME_ACTIVATED

The audit event should identify:

acting Admin;

previous Theme;

new Theme;

timestamp.

Failed activation is logged according to operational logging policy without exposing secrets.

40. Future Extensions

V1 intentionally does not implement:

visual page builder;

arbitrary Template editor;

Theme marketplace;

multiple simultaneously active Themes;

drag-and-drop layout editor;

arbitrary custom field builder;

database-level Theme version history.

These require explicit future requirements before implementation.

41. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

03-Authorization-Security.md.

Primary requirement groups:

REQ-PAGE-*
REQ-CONT-*
REQ-THEME-*
REQ-MEDIA-*
REQ-CACHE-*
REQ-NFR-*

All implementation must preserve the developer-controlled presentation boundary defined here.