DOC-08 — SMITE CMS Technical Architecture

Document Version: 0.2.0
Status: Approved — Technical Architecture
Last Updated: 21 August 2026

1. Purpose

This document defines the technical architecture used to implement SMITE CMS V1.

The architecture prioritizes:

security;

maintainability;

upgradeability;

performance;

simplicity;

shared-hosting compatibility;

minimal third-party dependencies;

clear separation of responsibilities;

testability;

controlled extensibility.

Architecture must not become more complex than the V1 product requires.

2. Technology Baseline

Backend

PHP 8.5+
CodeIgniter 4.7+
MariaDB
CodeIgniter Shield

Frontend

Semantic HTML5
Tailwind CSS 4
Alpine.js
HTMX
Quill 2.x

The project does not use:

jQuery
React
Vue
Angular
SPA framework

Infrastructure

Development:

Windows 11 Pro
    ↓
WSL2
    ↓
Ubuntu 24.04 LTS
    ↓
/var/www/html/simpleweb

Production target:

Shared Hosting
├── cPanel / hPanel
├── PHP
├── MariaDB
├── Cron
└── SMTP

V1 does not require:

Docker
Redis
RabbitMQ
Persistent queue workers

3. Architecture Goals

The application architecture SHALL:

Keep Controllers thin.

Keep business logic in Services.

Keep database access in Models.

Keep transport data in DTOs where useful.

Keep domain data in Entities.

Keep presentation logic in Views/Theme templates.

Keep security rules centralized.

Keep framework coupling limited to appropriate application boundaries.

Avoid premature abstraction.

Prefer official CodeIgniter/PHP capabilities.

4. Global Rule Precedence

Implementation decisions follow:

1. System/developer instructions
2. Repository .cursorrules
3. CONTEXT.md
4. Approved project documentation
5. Approved ADRs
6. Explicit implementation request

When CONTEXT.md deliberately specializes a global .cursorrules requirement, that exception is authoritative for SMITE CMS and must remain documented.

SMITE CMS intentionally uses:

username + password

as the V1 login identifier.

Global PII and security requirements remain mandatory.

5. Application Architecture

SMITE CMS uses a layered monolith with domain-oriented namespaces.

The system has one:

CodeIgniter application
database
deployment
Git repository
release cycle

It does not use microservices or nested HMVC modules.

Domain responsibilities are organized through namespaces inside the standard CodeIgniter project structure.

6. Standard CodeIgniter Project Structure

Recommended structure:

app/
├── Config/
├── Controllers/
│   ├── Admin/
│   ├── Auth/
│   └── Public/
├── Database/
│   ├── Migrations/
│   └── Seeds/
├── DTO/
├── Entities/
├── Filters/
├── Helpers/
├── Models/
├── Services/
│   ├── Content/
│   ├── Publishing/
│   ├── Media/
│   └── Security/
├── Views/
│   ├── admin/
│   └── public/
└── Commands/

Example Controllers:

Controllers/Admin/PageController.php
Controllers/Admin/PostController.php
Controllers/Admin/MediaController.php
Controllers/Admin/SettingController.php

Controllers/Auth/AuthController.php
Controllers/Auth/RecoveryController.php

Controllers/Public/HomeController.php
Controllers/Public/ContentController.php
Controllers/Public/DownloadController.php

Example Services:

Services/Content/PageService.php
Services/Content/PostService.php
Services/Content/ContentSchemaValidator.php

Services/Publishing/PublishingService.php
Services/Publishing/SchedulingService.php

Services/Media/MediaService.php
Services/Media/ImageProcessor.php
Services/Media/DependencyChecker.php

Services/Security/PiiCipherService.php
Services/Security/AuditService.php

This structure uses standard CodeIgniter 4 autoloading and avoids custom nested-module infrastructure.

7. Controller Responsibility

Controllers are thin HTTP orchestrators.

A Controller SHALL:

receive HTTP input;

invoke authentication/authorization boundaries;

perform request-level validation;

construct DTOs when useful;

invoke a Service;

convert Service results into HTTP responses;

select full-page or HTMX fragment responses.

Controllers SHALL NOT contain:

complex business rules;

large multi-table workflows;

publication state machines;

filesystem lifecycle orchestration;

direct business-level SQL decision logic.

8. Service Layer

Services own business behavior.

Examples:

PageService
PostService
PublishingService
SchedulingService
RevisionService
SlugService
MediaService
DocumentService
ThemeService
LocalizationService
SEOService
AuditService
PiiCipherService

Services may:

enforce business rules;

coordinate multiple Models;

validate domain conditions;

own database transaction boundaries;

call infrastructure abstractions;

orchestrate state transitions.

Business logic should not be distributed randomly between Controllers and Models.

9. Service Namespace Boundaries

Business logic is grouped by domain-oriented namespaces:

App\Services\Content
App\Services\Publishing
App\Services\Media
App\Services\Security

Controllers depend on Services.

Services may depend on Models, Entities, DTOs, and dedicated infrastructure helpers.

A Service should not directly render HTML.

10. Model Responsibility

Models handle database interaction.

Models SHALL:

define allowed fields;

define casts/return types where appropriate;

provide focused query methods;

use CI4 Model/Query Builder capabilities.

Models SHALL NOT become large workflow engines.

Complex publication/revision/deletion workflows belong in Services.

11. Entity Responsibility

Entities represent typed application/domain data.

Entities should contain:

typed properties;

safe data representation;

simple value normalization where appropriate;

simple invariants that naturally belong to the data object.

Entities SHALL NOT contain large multi-step business workflows.

12. DTO Strategy

DTOs are immutable typed transport objects.

Use DTOs when:

request payloads are non-trivial;

a Service receives structured business input;

several fields form one operation;

strong typing improves correctness.

Do not create DTO classes for every trivial scalar parameter.

Recommended examples:

PageDTO
PostDTO
MediaUploadDTO
ScheduleDTO
ThemeActivationDTO

13. Request Flow

Normal request:

HTTP Request
    ↓
Route
    ↓
Authentication / Filter
    ↓
Controller
    ↓
Authorization
    ↓
Request Validation
    ↓
DTO
    ↓
Service
    ↓
Model / Database
    ↓
Entity / Result
    ↓
View / Response

14. Database Architecture

MariaDB is the authoritative V1 database.

All schema changes SHALL use CodeIgniter migrations.

No manual production schema alteration is part of the normal workflow.

Database name is environment-specific and must not be hard-coded.

15. Primary Keys

Default internal primary key:

BIGINT UNSIGNED AUTO_INCREMENT

Public URLs use:

slug
localized path
random public token

rather than sequential internal IDs.

Public document download identifiers are separate random tokens as defined in 06-Media-Document-Management.md.

16. Database Naming

Use:

snake_case
plural table names
singular Entity names

Examples:

pages
posts
page_translations
post_translations
media_assets
scheduled_actions
url_redirects
audit_logs
revisions
site_settings
menus
menu_items

Exact table definitions are maintained through migrations.

17. Foreign Keys

Use relational foreign keys whenever practical.

Examples:

page_translations.page_id → pages.id
post_translations.post_id → posts.id
posts.featured_image_id → media_assets.id

Dynamic media_id references inside JSON cannot use ordinary relational foreign keys and therefore require application-level dependency checks.

18. Indexing Strategy

Indexes SHALL reflect actual application query patterns.

Expected indexed data includes:

status
slug
locale
published_at
scheduled_at / execute_at
created_at
updated_at
deleted_at
lock_version
download_hash
email_lookup_hash

Composite indexes should be used where they match real query patterns.

Do not create indexes solely because a field may theoretically be searched someday.

19. Content Storage Architecture

V1 uses a hybrid content model:

Relational state
        +
Schema-validated JSON payload

For Pages:

page_translations
├── page_id
├── locale
├── title
├── content_payload
└── SEO-related values

For Posts:

post_translations
├── post_id
├── locale
├── title
├── content_payload
└── SEO-related values

Core relational attributes remain relational.

Dynamic Theme-defined values remain in content_payload.

20. Content Schema Validation

Content Payload validation is performed in:

App\Services\Content\ContentSchemaValidator

Flow:

Request
  ↓
Authorization
  ↓
Resolve Theme
  ↓
Resolve Template
  ↓
Load Theme Manifest
  ↓
Resolve Content Schema
  ↓
Validate Payload
  ↓
Normalize Values
  ↓
Persist JSON

V1 SHALL use a custom native PHP validator rather than a general-purpose JSON Schema package.

The validator should use strict typing and explicit rules for:

TEXT
TEXTAREA
RICH_TEXT
IMAGE
YOUTUBE_URL
URL
DOCUMENT
Repeatable Blocks

Unknown fields SHALL be rejected unless explicitly allowed by the Theme Manifest.

21. Theme Manifest Loading

Theme Manifest is developer-controlled configuration.

The system must validate the Manifest when:

Theme is discovered;

Theme is enabled;

Theme is previewed;

Theme is activated;

Content Schema is required.

Malformed or incompatible Manifest data must prevent Theme activation.

A malformed candidate Theme must not corrupt the active production Theme.

22. Published Content Query Strategy

Public queries must explicitly request public content.

Prefer database filtering such as:

status = PUBLISHED

plus all applicable visibility rules.

Do not fetch large sets of Draft/Trash/Unpublished content and filter them in PHP.

Public rendering must avoid loading non-public content unnecessarily.

23. Query Performance

Follow:

pagination for unbounded Control Panel lists;

no N+1 queries;

explicit SELECT columns where practical;

eager loading for known relationships;

targeted cache usage;

minimal repeated Site Setting queries;

no unnecessary asynchronous requests.

24. Public Rendering Pipeline

Public Request
   ↓
Route Resolution
   ↓
Locale Resolution
   ↓
Resolve Current Public Resource
   ↓
Load Active Theme
   ↓
Resolve Template
   ↓
Load Content Schema
   ↓
Load Translation / Content
   ↓
Load Required Media
   ↓
Resolve SEO
   ↓
Render Theme
   ↓
HTTP Response

Public rendering SHALL never perform hidden editorial state transitions.

25. Control Panel Pipeline

/admin/*
   ↓
Session Authentication Filter
   ↓
Route
   ↓
Authorization
   ↓
Controller
   ↓
Service
   ↓
Model
   ↓
View

Control Panel Views do not directly query Models.

26. Authentication

CodeIgniter Shield Session Authenticator is mandatory.

SMITE CMS uses Shield for:

session authentication;

password handling;

groups;

permissions;

authentication filters.

The login identifier remains project-specific:

username + password

Authentication of /admin/* is applied centrally through Shield/session filters.

27. Authorization

Use Shield groups and permissions as the baseline authorization mechanism.

Groups:

admin
editor
contributor

Permissions use explicit domain actions such as:

page.create
page.edit
page.publish
post.create
post.edit_any
post.publish
media.manage
theme.activate

Ownership rules remain application/domain rules:

Contributor → own Post
Editor → any Post
Admin → full authority

28. /cp Authentication Entry

The dedicated authentication/recovery entry point is:

/cp

Authenticated Control Panel routes are:

/admin/*

No conventional public /login route is introduced unless a future dependency explicitly requires it.

29. Session Expiration and HTMX

Centralized authentication/session response handling shall inspect:

HX-Request: true

When an authenticated HTMX request encounters an expired session:

HX-Redirect: /cp

must be used so that the browser performs full-page navigation.

The login page must never be swapped into an ordinary HTMX target.

30. CSRF Architecture

Use CodeIgniter session-backed CSRF protection.

HTMX state-changing requests use:

X-CSRF-TOKEN

The client-side CSRF token must remain synchronized with the server-side token if regeneration is enabled.

The synchronization mechanism must be compatible with the selected CI4 version and covered by integration tests.

31. Rich Text Editor

RICH_TEXT uses Quill 2.x as the V1 editing UI.

Recommended integration pattern:

<div x-data="quillEditor({ content: '...' })">
    <div x-ref="editor" class="min-h-[250px]"></div>
    <input type="hidden"
           name="content_payload[body]"
           :value="content">
</div>

Alpine initializes the editor and synchronizes Quill output with application state.

The editor:

is responsible for user interaction;

does not provide the security boundary;

does not bypass server-side validation;

does not bypass HTML sanitization.

32. Alpine.js

Alpine.js is for ephemeral UI state.

Examples:

modal state
dropdown state
tabs
toggle controls
dirty/unsaved indicator
Quill editor bridge

Alpine is not the authoritative application data layer.

33. HTMX

HTMX is the standard asynchronous interaction mechanism.

Use it for:

inline actions;

modal content loading;

partial updates;

server-driven filters;

pagination;

auto-save;

lightweight Control Panel mutations.

Do not make an action asynchronous merely because HTMX can technically do it.

Normal HTTP is preferred where simpler.

34. HTMX Response Rules

Mutation responses may use:

fragment response
inline validation errors
HX-Redirect
HX-Trigger

Use HX-Redirect for full-page navigation.

Use HTML fragments for localized UI updates.

35. Auto-save HTTP Architecture

Draft and Published-content editor auto-save may use a dedicated endpoint such as:

POST /admin/posts/{id}/autosave

The endpoint:

authenticates;

authorizes;

validates lock_version;

validates Content Schema;

stores recoverable autosave state;

returns current version/revision information.

Auto-save must never invoke publication transitions.

36. Optimistic Concurrency

Pages and Posts use:

lock_version

or equivalent optimistic concurrency token.

Example:

UPDATE posts
SET
    ...,
    lock_version = lock_version + 1
WHERE id = :id
  AND lock_version = :submitted_version;

Affected rows 0 indicates a conflict.

Response:

409 Conflict

The UI must inform the user without silently discarding unsaved changes.

37. Transactions

Services own database transaction boundaries.

Transactions are required for operations such as:

Publish Post
Slug change + redirect creation
Theme activation
Scheduled state transition
Revision restore
Permanent deletion with dependencies
Media creation + metadata persistence

Transactions should be short.

Slow filesystem/network operations should not remain inside database transactions unless required for consistency.

38. Scheduler Architecture

Scheduled content is processed by a CI4 Spark command invoked through cron.

Recommended command:

php spark cms:scheduled-content

Recommended cadence:

every minute

when hosting permits.

Processing:

Find due ScheduledActions
        ↓
Safely lock/claim
        ↓
Validate current target state
        ↓
Apply transition
        ↓
Audit
        ↓
Invalidate cache
        ↓
Mark action processed

The scheduler must be idempotent and support catch-up.

39. ScheduledAction Processing State

ScheduledAction states are separate from Page/Post states.

Recommended states:

PENDING
PROCESSING
PROCESSED
SKIPPED
CANCELLED
FAILED

Page/Post states remain:

DRAFT
PENDING REVIEW
PUBLISHED
UNPUBLISHED
ARCHIVED
TRASH

An already-satisfied scheduled action may be marked SKIPPED.

40. Cache Architecture

V1 uses CodeIgniter File Cache.

Default cache location:

writable/cache/

This provides shared-hosting compatibility without Redis/Memcached.

Potential cached values:

Site Settings
Menus
Published Pages
Published Posts
Category listings
Active Theme metadata
SEO outputs where useful

41. Cache Key Strategy

Cache keys must be deterministic and sufficiently scoped.

Examples:

site:settings
menu:primary
page:{locale}:{path}
post:{locale}:{slug}
theme:active

Exact key naming is implementation detail.

42. Cache Invalidation

Mutations must invalidate affected cache entries.

Examples:

Update Site Setting
    ↓
Invalidate settings cache

Publish Post
    ↓
Invalidate Post cache
Invalidate affected Category/list cache
Invalidate affected homepage/listing cache

Activate Theme
    ↓
Invalidate public presentation caches

Avoid flushing the entire cache for every small mutation unless no targeted strategy is practical.

43. Preview Cache Isolation

Theme Preview SHALL:

Cache-Control: no-store, no-cache, must-revalidate
Pragma: no-cache

and must bypass normal application/public cache.

Preview output must never populate public cache.

44. File Storage Architecture

Local filesystem storage is the V1 default.

Conceptually:

writable/
├── cache/
├── uploads/
│   ├── images/
│   └── documents/
└── ...

Documents remain outside the public web root.

Image public representation is controlled separately.

45. Image Processing

V1 uses:

PHP ext-gd
    ↓
CodeIgniter Image Manipulation

Conceptually:

\Config\Services::image('gd')

Required processing includes:

resize;

validation-compatible manipulation;

supported format conversion;

optimization appropriate to the deployment environment.

No ImageMagick CLI, Node.js worker, or external image-processing service is required in V1.

The production environment must provide the required GD capabilities.

46. Public Document Download

Document download uses CI4 response streaming facilities.

Conceptually:

return $this->response->download($filePath, null);

Do not use:

file_get_contents($filePath)

to load the entire document into PHP memory before sending it.

Public document identifiers are random, non-sequential tokens.

47. PII Architecture

User email storage uses:

email_ciphertext
email_lookup_hash

Encryption and lookup hashing use separate secrets:

EMAIL_ENCRYPTION_KEY=...
EMAIL_LOOKUP_HMAC_KEY=...

A dedicated:

App\Services\Security\PiiCipherService

encapsulates:

encrypt()
decrypt()
lookupHash()

Application code must not perform ad-hoc encryption/decryption in Controllers or Models.

48. PII Cryptography

Email encryption uses PHP Sodium:

sodium_crypto_secretbox()

with:

32-byte key
24-byte random nonce
XSalsa20-Poly1305 authenticated encryption

Stored ciphertext format:

nonce || ciphertext

encoded as Base64 for database-safe storage.

Lookup hash:

hash_hmac(
    'sha256',
    strtolower(trim($email)),
    $lookupKey
)

The normalized email policy must be used consistently for create, update, recovery lookup, and uniqueness checks.

The encryption key and lookup HMAC key must remain separate.

49. Error Handling

Services may throw domain/application exceptions.

HTTP layer translates them into appropriate responses:

400
401
403
404
409
422
500

Production responses must not expose stack traces, SQL, filesystem paths, or secrets.

50. Logging

Logs are operational diagnostics, not a replacement for the database.

Never log:

password
skey
EMAIL_ENCRYPTION_KEY
EMAIL_LOOKUP_HMAC_KEY
plaintext PII
full sensitive request payload

Log safe identifiers and useful operational context.

51. Public URL Resolution

URL resolution is centralized.

Conceptually:

Incoming Request
    ↓
Locale Resolution
    ↓
Reserved Route Check
    ↓
Current URL Lookup
    ↓
Redirect Lookup
    ↓
Page/Post/Category Resolution
    ↓
404 if unresolved

Slug/redirect resolution logic must not be duplicated across Controllers.

52. SEO Rendering

SEO data is resolved by an application Service.

Theme receives normalized values such as:

title
description
canonical
hreflang
og_image

Theme renders the HTML.

Theme templates do not query database tables directly for SEO data.

53. Dependency Policy

Default rule:

Do not add a package unless it solves an approved requirement better than built-in PHP/CodeIgniter capabilities.

Core V1 dependencies include:

CodeIgniter 4
CodeIgniter Shield
Tailwind CSS
Alpine.js
HTMX
Quill 2.x

Additional packages require explicit review and justification.

54. Tailwind CSS Build Strategy

Development may use Tailwind Play CDN for rapid prototyping.

Production SHALL use compiled static CSS.

Recommended local build:

WSL / Development machine
        ↓
Tailwind CLI
        ↓
public/themes/{theme_id}/css/app.css
        ↓
Commit compiled CSS
        ↓
Shared hosting serves static CSS

The production hosting environment does not need Node.js/npm.

Example local build pattern:

npx @tailwindcss/cli \
  -i ./resources/css/app.css \
  -o ./public/themes/{theme_id}/css/app.css \
  --minify

The exact command and input/output paths may be refined in 11-Deployment-Operations.md.

55. Theme Assets

Theme static assets belong under the public Theme asset namespace.

Conceptually:

public/themes/{theme_key}/
├── css/
├── js/
├── images/
└── ...

Templates must use:

theme_asset('css/app.css')

or an equivalent helper/service rather than hard-coded deployment paths.

56. Admin UI Reference

TailAdmin Free is used as the local Control Panel design reference where required by the global project rules.

Reference assets belong under:

reference/tailadmin

and reference/ is not a production dependency.

The reference is for implementation guidance rather than a runtime package.

57. Configuration

Environment-specific values belong in configuration/environment.

Examples:

database credentials
app.baseURL
skey
EMAIL_ENCRYPTION_KEY
EMAIL_LOOKUP_HMAC_KEY
SMTP credentials
upload limits
cache settings
timezone

.env is for environment configuration, not hidden Product Requirements.

Business rules must remain visible in code/documentation.

58. Constants and Enums

Domain states/types should avoid uncontrolled duplicated string literals.

Use centralized PHP enums/value objects/configuration where appropriate for:

PageStatus
PostStatus
ThemeStatus
MediaType
ScheduledActionType
ScheduledActionStatus
ContentFieldType

Avoid excessive abstractions where a simple typed enum or constant is sufficient.

59. Database and Filesystem Consistency

Database transactions cannot automatically roll back filesystem operations.

Media workflows must have explicit cleanup/compensation behavior.

Example:

Process file
  ↓
Store temporary output
  ↓
Persist/validate database state
  ↓
Finalize storage

If any step fails, orphaned temporary files and invalid records must be cleaned up.

60. Testing Architecture

Business logic must be testable without browser automation for every case.

Test categories:

Unit
Integration
Feature / HTTP
Database
Security
Authorization
Regression

High-value Services such as:

PublishingService
SchedulingService
SlugService
RevisionService
ContentSchemaValidator
MediaService
ThemeService

should have focused tests.

61. Security Testing

Security tests SHALL cover at minimum:

authentication
authorization
CSRF
HTMX session expiry
brute-force throttling
SKEY recovery
PII encryption
HMAC lookup
XSS
Rich Text sanitization
malicious uploads
document access
media dependency protection
URL collision
reserved routes
revision restore
optimistic concurrency

62. Performance Testing

Test:

public Page rendering;

Post listings;

Category listings;

Media Library pagination;

scheduler execution;

document downloads;

cache hit/miss behavior;

concurrent editing conflicts.

Optimization must be evidence-driven.

63. Upgradeability

Prefer official CodeIgniter APIs over framework overrides.

Keep business logic separated from framework internals.

Avoid modifying vendor code.

Keep external dependencies minimal.

This reduces PHP/CI4 upgrade risk.

64. No Premature Infrastructure

Do not introduce:

Redis
RabbitMQ
Docker
queue workers
microservices
Kubernetes
object storage

until an approved requirement actually needs them.

The V1 system must remain deployable on the shared-hosting target.

65. Deployment Architecture

Production deployment must support:

Application code
Composer dependencies
Database migrations
Public Theme assets
Writable uploads
Cron
SMTP
Environment variables

The application must not assume infrastructure beyond what the target shared hosting provides.

66. Composer Policy

Composer manages PHP dependencies.

composer.lock SHALL be committed.

Production deployments should install production dependencies only.

Dependency upgrades must be reviewed for:

compatibility;

security;

maintenance burden;

shared-hosting compatibility.

67. Database Migration Policy

Every schema change uses a CI4 Migration.

Workflow:

Create migration
   ↓
Run locally
   ↓
Run tests
   ↓
Review
   ↓
Commit
   ↓
Deploy
   ↓
Run production migration

No manual production schema modification is part of the normal workflow.

68. Seeders

Seeders may provide:

required system defaults;

development fixtures;

controlled bootstrap data.

Production seeders must be idempotent and must never overwrite live content unintentionally.

Admin bootstrap must be safe to rerun or safely detect existing bootstrap state.

69. Backup Boundary

Backup is primarily an infrastructure responsibility.

Production backup must include:

MariaDB database
Media uploads
Document uploads
Required configuration/secrets according to hosting backup policy

Application source is version-controlled by Git.

70. Failure Behavior

The application should degrade predictably.

Examples:

Cache unavailable
    ↓
Fallback to database where practical

Optional analytics unavailable
    ↓
Public page continues rendering

Theme Preview unavailable
    ↓
Production Theme remains unaffected

Critical failures must fail safely without exposing internals.

71. Architecture Decision Boundary

A new decision requires documentation/ADR review if it changes:

database architecture;

security;

permissions;

public URLs;

publishing;

Theme contracts;

third-party dependencies;

deployment;

migration strategy.

72. Cursor Implementation Rule

Before making code changes, Cursor SHALL:

verify project identity;

read .cursorrules;

read CONTEXT.md;

read relevant approved documents;

identify affected REQ-*;

inspect existing code;

implement the smallest correct change;

run relevant tests;

report files changed;

report tests run;

report unresolved issues.

Cursor SHALL NOT create infrastructure merely because the framework supports it.

73. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

03-Authorization-Security.md;

04-Content-Publishing.md;

05-Theme-Template-Architecture.md;

06-Media-Document-Management.md;

07-Localization-URL-SEO.md;

global .cursorrules;

CONTEXT.md.

Primary requirement groups:

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
REQ-CACHE-*
REQ-NFR-*

All implementation must preserve the architectural and security invariants defined by the approved project documents.