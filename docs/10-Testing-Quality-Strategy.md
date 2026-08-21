DOC-10 — SMITE CMS Testing & Quality Strategy

Document Version: 0.2.0
Status: Approved — Testing & Quality Strategy
Last Updated: 21 August 2026

1. Purpose

This document defines the testing strategy, test harness, quality gates, regression policy, and release-readiness rules for SMITE CMS V1.

The strategy is intentionally pragmatic:

PHPUnit is the primary test framework;

CodeIgniter testing utilities are used where appropriate;

Shield testing helpers are used for authenticated feature tests;

browser automation is reserved for workflows where browser behavior materially matters;

test isolation is mandatory;

security testing is part of functional correctness.

2. Quality Principles

QA-001 — Test Behavior, Not Implementation

Tests verify required behavior rather than arbitrary internal implementation details.

Good:

Contributor cannot publish a Post.

Less useful:

Controller calls hasPermission().

QA-002 — Security Is Functional Correctness

A feature is considered incorrect when the business behavior works but authorization or security is wrong.

QA-003 — Regression Protection

Every important discovered bug must receive a regression test reproducing the failure.

QA-004 — Deterministic Tests

Tests must be:

deterministic;

isolated;

repeatable;

independent of execution order;

safe to run repeatedly.

QA-005 — No Test Suppression

A failing test must not be bypassed, deleted, weakened, or ignored merely to obtain a green build.

3. Testing Layers

SMITE CMS uses:

Unit Test
Integration Test
Feature / HTTP Test
Database Test
Security Test
CLI Test
Browser Test
Regression Test

Recommended pyramid:

           Browser
          /-------\
         Feature
        /---------\
      Integration
     /-------------\
         Unit

Most tests should live at Unit, Service/Integration, and Feature levels.

4. PHPUnit Baseline

PHPUnit is the primary test runner.

The repository SHALL expose one standard command:

composer test

or the explicitly documented equivalent:

./vendor/bin/phpunit

CodeIgniter 4 provides built-in PHPUnit support and test utilities. urlCodeIgniter Testing Documentationhttps://codeigniter.com/user_guide/testing/overview.html

5. Test Authentication Harness

Authenticated tests must not repeatedly perform the real login form workflow unless the login workflow itself is being tested.

The project SHALL provide a helper trait such as:

App\Tests\Support\Traits\AuthenticatesTestUser

Recommended helper API:

$this->actingAsAdmin();
$this->actingAsEditor();
$this->actingAsContributor();
$this->actingAsGuest();

For HTTP Feature Tests, the helper should use CodeIgniter Shield's official AuthenticationTesting facility where practical.

Shield provides AuthenticationTesting::actingAs() specifically for authenticated HTTP tests. citeturn134343search0

The project helper is therefore a thin test convenience layer, not a replacement authentication mechanism.

6. Test User Fixtures

The authentication helper SHALL use deterministic test users with known roles/permissions.

At minimum:

Admin Test User
Editor Test User
Contributor Test User
Guest / unauthenticated

Test users must be created in the isolated test database.

Tests must not depend on development or production accounts.

7. Database Test Harness

Database tests SHALL use a dedicated CodeIgniter test database group.

The test database must be isolated from development/production data.

CodeIgniter's DatabaseTestTrait is the standard testing utility for database-backed tests. citeturn192217search1

Feature tests that touch the database should use both:

DatabaseTestTrait
FeatureTestTrait

as appropriate. citeturn192217search2

8. Database Isolation Policy

Database tests must leave the test database in a known clean state.

The preferred strategy is:

Test bootstrap
  ↓
Migrations/schema preparation
  ↓
Each test
  ↓
Controlled database state
  ↓
Rollback/cleanup

Where the database test harness uses transaction rollback, it must ensure that each test method cannot leak mutations into the next test.

The exact DatabaseTestTrait settings and transaction strategy SHALL follow the installed CI4 version's supported test-harness behavior.

Do not hard-code assumptions about internal test trait properties that differ between CI4 versions.

9. Test Database Refresh Policy

The suite should avoid rebuilding the complete schema before every single test when a faster supported transaction/staging strategy provides equivalent isolation.

The default optimization target is:

Prepare schema once per test run
+
Isolate each test method

If a test intentionally requires migration behavior, that test may use a fresh database/schema lifecycle instead.

10. Filesystem Sandbox

Media and Document tests SHALL NOT use the real production-style upload directories.

Use an isolated test filesystem such as:

writable/testing/

or an equivalent virtual filesystem approach where appropriate.

Each test must clean up:

temporary uploads;

processed images;

generated documents;

test storage identities.

No test may leave files in:

writable/uploads/images/
writable/uploads/documents/

unless those paths are themselves explicitly redirected to a test-specific sandbox.

11. Media Test Isolation

For Media tests:

setUp
  ↓
Create sandbox
  ↓
Run test
  ↓
tearDown
  ↓
Delete all sandbox files

Cleanup must happen even when the test fails.

Filesystem cleanup errors should fail the test harness rather than silently leaving artifacts.

12. Time Control

Time-sensitive tests SHALL use CodeIgniter's Time testing facility.

Example:

use CodeIgniter\I18n\Time;

Time::setTestNow('2026-08-21 08:00:00');

After each test:

Time::setTestNow();

This prevents scheduler tests from depending on the machine clock.

CodeIgniter documents Time::setTestNow() specifically for deterministic testing of time-dependent logic. citeturn192217search0

13. Scheduler Time Tests

Scheduler tests must explicitly control time.

Example:

execute_at = 08:00
test time  = 07:59
→ action not due

test time  = 08:00
→ action due

test time  = 08:15
→ overdue action due

This must cover:

scheduled publish;

scheduled unpublish;

catch-up;

skipped action;

cancelled action;

already-processed action.

14. CLI Command Tests

CI4 Spark commands SHALL be tested using CI4's CLI testing utilities where practical.

Important commands include:

php spark cms:install
php spark cms:scheduled-content

Tests should verify:

exit behavior;

output;

database effects;

idempotency;

error behavior;

safe handling of invalid input.

CodeIgniter provides dedicated CLI testing support including MockInputOutput for commands requiring input. citeturn192217search12

15. Unit Tests

Unit tests cover isolated logic that does not require the full application stack.

Examples:

ContentSchemaValidator
Slug normalization
SEO fallback
Localization fallback
Alt text resolution
State transition rules
URL normalization
PII normalization

16. Service Tests

Services are a primary testing target.

High-value Services:

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
PiiCipherService
AuditService

17. Database Integration Tests

Database integration tests verify:

migrations;

foreign keys;

unique constraints;

indexes;

relationship behavior;

transactions;

locking/concurrency behavior;

query correctness.

18. HTTP / Feature Tests

Feature tests exercise one complete HTTP use case.

Examples:

POST /cp
POST /admin/posts
POST /admin/posts/123/publish
POST /admin/posts/123/autosave
POST /admin/themes/activate
GET /download/document/{token}

Feature tests should verify:

authentication;

authorization;

validation;

response status;

response headers;

database changes;

audit events;

cache invalidation where applicable.

19. Authentication Test Matrix

Minimum:

valid credentials
invalid username
invalid password
inactive user
session expiry
logout
password change
password reset
Admin recovery
brute-force throttling

20. Authorization Test Matrix

Each protected capability should be tested for:

Admin
Editor
Contributor
Guest

Example:

Action

Admin

Editor

Contributor

Guest

Publish Post

✓

✓

✗

✗

Edit Any Post

✓

✓

✗

✗

Edit Own Post

✓

✓

✓

✗

Manage Users

✓

✗

✗

✗

Activate Theme

✓

✗

✗

✗

Permanent Delete

✓

✗

✗

✗

Server-side authorization must be tested independently of UI navigation visibility.

21. Session Expiration Tests

Test:

Normal request + expired session
HTMX request + expired session

Expected normal response:

redirect /cp

Expected HTMX behavior:

HX-Redirect: /cp

The login page must never be swapped into an HTMX fragment target.

22. CSRF Tests

Test:

missing token
invalid token
stale token
valid token
HTMX mutation
token regeneration

The test suite must verify that token regeneration does not make subsequent valid HTMX requests fail because the client is holding an outdated token.

23. Brute-force Tests

Authentication/recovery throttling must be tested for:

login
password reset
Admin recovery

Verify:

repeated attempts become throttled;

valid users are not permanently locked out by a test artifact;

error messages do not reveal account existence.

24. Admin Recovery Tests

Test:

valid skey
invalid skey
malformed input
rate-limited attempts
session invalidation
audit event
constant-time comparison path

The recovery secret must never appear in test failure output or test logs.

25. PII Cryptography Tests

Encryption Round Trip

plaintext
  ↓
encrypt
  ↓
ciphertext
  ↓
decrypt
  ↓
original plaintext

Lookup Hash

Normalized equivalent emails must produce the same lookup hash.

Example:

Ferry@example.com
 ferry@example.com
FERRY@EXAMPLE.COM

must normalize consistently according to the approved policy.

Key Separation

Tests should verify configuration rejects accidental reuse of the encryption and lookup-HMAC keys where practical.

26. Content Schema Tests

Every supported Content Field type requires:

valid value;

missing required value;

invalid value;

wrong type;

unexpected field;

default value;

optional value.

Types:

TEXT
TEXTAREA
RICH_TEXT
IMAGE
YOUTUBE_URL
URL
DOCUMENT

Repeatable Block tests cover:

minimum
maximum
too many items
too few items
invalid child field
unknown child field
ordering

27. Rich Text Security Tests

Malicious content examples:

<script>
<img onerror=...>
<a href="javascript:...">
arbitrary iframe
dangerous attributes

Expected:

unsafe content removed or rejected

Safe allowed formatting must remain functional.

28. URL and Slug Tests

Test:

valid slug
invalid slug
duplicate current URL
duplicate historical URL
reserved system route
reserved locale prefix
slug change
redirect creation
redirect collision
redirect chain normalization

Global URL namespace must remain unique.

29. Localization Tests

Test:

Primary translation exists
Secondary translation exists
Secondary translation missing
Primary fallback
localized slug
localized canonical
hreflang
x-default

Special case:

/en/page
Secondary translation missing

must:

render Primary fallback;

canonicalize to Primary URL;

not emit false hreflang="en".

30. Publishing State Machine Tests

Page

Draft → Published
Published → Unpublished
Published → Archived
Unpublished → Published
Archived → Published where authorized
Applicable state → Trash
Trash → previous valid state

Post

Draft → Pending Review
Pending Review → Published
Pending Review → Draft
Published → Unpublished
Published → Archived
Applicable state → Trash
Trash → previous valid state

Invalid transitions must fail safely.

31. Published Edit Tests

Critical behavior:

Published Post
  ↓
Edit
  ↓
Auto-save

Expected:

live content unchanged

Then:

Explicit Update

Expected:

live content changed
new revision created
affected cache invalidated
appropriate audit event

32. Auto-save Tests

Test:

dirty = false

must produce no auto-save.

Test:

change detected
↓
approximately 60 seconds idle

produces auto-save.

Continuous dirty state must trigger the five-minute safety interval.

Auto-save failures must not destroy local unsaved content.

Test failures include:

session expired
validation failure
network failure
409 conflict

33. Revision Tests

Test:

Revision creation;

immutable history;

restore;

restore creates a new current state/revision;

historical revision is unchanged;

revision snapshot is self-contained;

auto-save revision;

Published-content autosave revision.

34. Optimistic Concurrency Tests

Scenario:

Initial lock_version = 5

Browser A:

save version 5 → success → 6

Browser B:

save version 5 → 409 Conflict

Expected:

no silent overwrite;

database retains Browser A state;

Browser B receives conflict information.

35. Theme Tests

Test:

valid Theme Manifest
malformed Theme Manifest
missing custom-page
missing required Template
missing Content Schema
Theme DRAFT cannot activate
Theme ENABLED can activate
Theme replacement
non-destructive Theme switching

Theme switching must never prune old Content Payload data.

36. Theme Preview Tests

Verify:

Admin only
candidate Theme must be ENABLED
no public cache
Cache-Control no-store
Pragma no-cache
no Theme activation
no content mutation

37. Media Tests

Test:

valid image
invalid image
too-small image
too-large image
GD resize
optimization
original removal
same filename collision
concurrent upload
metadata update
Trash
Restore
permanent delete

38. Media Dependency Tests

A MediaAsset referenced by:

posts.featured_image_id
Page Content Payload
Post Content Payload

must fail permanent deletion.

An unreferenced MediaAsset may be permanently deleted by an authorized Admin.

39. Alt Text Tests

Resolution must follow:

Content Payload contextual alt
    ↓
MediaAsset default alt/title
    ↓
alt=""

Test every combination.

40. Document Download Tests

Test:

valid public document
invalid token
non-public document
trashed document
unpublished content
missing physical file
path traversal attempt
sequential ID enumeration attempt
random token failure

Expected non-public behavior must not disclose physical storage paths.

41. Document Streaming Tests

Verify that large document downloads use CI4 download/streaming facilities rather than loading the entire file into PHP memory.

The test suite should guard against accidental introduction of:

file_get_contents($filePath)

for whole-document buffering.

42. Scheduler Tests

Test:

due action
future action
overdue action
already processed action
duplicate action
target moved to Trash
target already Published
target Archived
failed action

Expected processing states:

PENDING
PROCESSING
PROCESSED
SKIPPED
CANCELLED
FAILED

Scheduler execution must be idempotent.

43. Scheduler Catch-up Test

Example:

Scheduled publish:
08:00

Cron execution:
08:15

Expected:

content becomes Published
audit event created
cache invalidated
action marked processed

44. Cache Tests

Test:

cache hit
cache miss
cache invalidation
stale cache prevention
Theme activation invalidation
Preview bypass
scheduler invalidation
cache fallback where supported

45. SEO Tests

Test:

title
description
canonical
OG image
hreflang
x-default
sitemap
robots.txt
noindex preview

Unpublished/Archived/Trash content must not accidentally enter sitemap output.

46. Accessibility and HTML Tests

Public templates should be tested for:

semantic HTML5
heading hierarchy
image alt
form labels
keyboard-accessible controls

Automated accessibility tooling may be added when justified, but V1 does not require a large accessibility stack.

47. Responsive UI Tests

Public website checks should cover:

smartphone
tablet
laptop/desktop

Focus on:

navigation;

image scaling;

typography;

forms;

menus;

content blocks.

Not every browser/OS combination requires a dedicated automated test.

48. Browser Test Scope

Browser automation should be limited to workflows where browser behavior is material:

login
role-aware dashboard
session timeout
HTMX session redirect
Post creation
auto-save
publish/update
concurrency conflict
Media upload
Document download
Theme preview
Theme activation
language switch

Business rules remain primarily covered by Service/Feature tests.

49. Test Data

Test data must be deterministic.

Factories/fixtures should be used where useful.

Tests must not depend on:

production data
developer local database state
external internet services
real SMTP delivery
third-party analytics

External integrations should be mocked/stubbed unless their real behavior is explicitly the subject of an integration test.

50. Test Filesystem

All media/document test artifacts must live in a test-specific sandbox.

The test harness must remove them automatically during teardown.

Filesystem cleanup must be part of the test lifecycle, not a manual developer responsibility.

51. Test Database Strategy

A dedicated test database configuration SHALL be used.

Conceptually:

app/Config/Database.php
    ↓
test database group

CI4 provides a dedicated database test configuration pattern for safe isolation. urlCI4 Database Testing Documentationhttps://codeigniter.com/user_guide/testing/database.html

Database tests must never point at development or production databases.

52. Test Time Reset

Every test that changes test time must restore the normal clock:

Time::setTestNow();

in teardown or equivalent cleanup.

Failure to reset test time can create order-dependent failures.

53. Test Naming

Names describe behavior:

test_contributor_cannot_publish_post()
test_published_post_autosave_does_not_change_live_content()
test_slug_change_creates_reserved_301_redirect()
test_missing_secondary_translation_uses_primary_fallback()

Avoid vague names such as:

test_post_works()
test_controller_works()

54. Test Isolation

Each test should be independent.

Do not rely on test execution order.

Use the approved database staging/rollback mechanism and filesystem cleanup.

55. Static and Quality Checks

Where configured, the quality gate may include:

PHPUnit
PHP syntax checks
static analysis
linting
migration verification
Composer validation
Composer audit

Only justified, maintained tools should be added.

56. Composer and Dependency Checks

Before release:

composer validate
composer audit

and the full test suite must pass.

Dependency vulnerabilities must be reviewed before release.

57. Full Test Suite

The repository SHALL provide:

composer test

as the canonical project test command, unless explicitly superseded by repository documentation.

The command must run all required Unit, Integration, Feature, and other automated tests appropriate to the current project state.

58. Warning and Deprecation Policy

A Phase completion gate fails when the implementation introduces relevant:

PHP 8.5 deprecation
CodeIgniter deprecation
incorrect API usage warning
security warning

that indicates an unsafe or future-incompatible implementation.

Environment-only warnings outside the application's control must still be reviewed, but need not automatically block a release.

The project must not knowingly accumulate deprecations introduced by its own code.

59. Coverage Policy

SMITE CMS does not require an arbitrary global percentage.

Coverage is risk-based.

Highest priority:

Authentication
Authorization
Publishing
Scheduling
Revision
URL/Redirect
Content Validation
Media Security
Document Access
PII Encryption

Critical security/business paths must have direct tests.

60. Regression Test Policy

Every important defect follows:

Bug reproduced
   ↓
Regression test added
   ↓
Fix implemented
   ↓
Regression test passes
   ↓
Full suite passes

The regression test remains unless the underlying requirement is explicitly removed.

61. Phase Quality Gate

Before a Phase is complete:

Focused tests pass
        +
Integration tests pass
        +
Full test suite passes
        +
Required security checks pass
        +
No blocking regression
        +
No project-introduced deprecation
        +
Acceptance criteria pass

A single required test failure means:

Phase Gate = FAILED

62. Release Candidate Gate

Before V1 release candidate:

Full test suite
+
Security suite
+
Migration test
+
Fresh install test
+
Upgrade/migration test
+
Shared-hosting dry run
+
Backup/restore test

All blocking findings must be resolved.

63. Fresh Installation Test

A release candidate must be tested from a clean environment:

php spark cms:install

Expected:

migrations complete
Shield configuration complete
default settings created
single Admin created
application accessible
/cp accessible

The installer must be idempotent.

64. Upgrade Test

With an existing database containing content:

Existing V1 data
    ↓
New application version
    ↓
Migrations
    ↓
Tests

the system must preserve:

Pages;

Posts;

Media;

Documents;

Revisions;

Audit history;

Settings;

Theme compatibility.

No migration may silently delete user data without explicit approved migration behavior.

65. Production Readiness Checklist

[ ] all required tests pass
[ ] no blocking security issue
[ ] no project-introduced PHP/CI4 deprecation
[ ] composer audit reviewed
[ ] clean install tested
[ ] upgrade tested
[ ] cron tested
[ ] SMTP tested
[ ] document download tested
[ ] image processing tested
[ ] Theme activation tested
[ ] localization tested
[ ] sitemap tested
[ ] robots tested
[ ] backup tested
[ ] restore tested

66. Quality Ownership

The developer is responsible for:

understanding test failures before resolving them;

maintaining meaningful tests;

preventing test bypass;

maintaining regression protection;

keeping quality gates aligned with approved requirements.

AI-generated tests are not automatically considered sufficient.

Tests must be reviewed for:

correctness;

meaningful assertions;

adequate edge coverage;

alignment with actual business behavior.

67. Cursor Testing Rule

For each implementation task, Cursor should report:

Requirements implemented
Files changed
Tests added/changed
Tests executed
Test result
Known warnings
Known limitations

Cursor must not report completion merely because code compiles.

Cursor must not suppress or ignore a failing required test to obtain a green result.

68. References

The following official documentation is the implementation reference for the approved test harness:

CodeIgniter 4 Testing Overview

CodeIgniter 4 Database Testing

CodeIgniter 4 HTTP Feature Testing

CodeIgniter 4 Time Testing

CodeIgniter 4 CLI Testing

CodeIgniter Shield Testing

CodeIgniter Shield Authentication

CodeIgniter Shield Controller Filters

The exact URLs are maintained as implementation references in the project documentation/ADR process.

69. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

03-Authorization-Security.md;

04-Content-Publishing.md;

05-Theme-Template-Architecture.md;

06-Media-Document-Management.md;

07-Localization-URL-SEO.md;

08-Technical-Architecture.md;

09-Implementation-Blueprint.md.

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
REQ-UX-*
REQ-NFR-*

Quality gates defined here are mandatory for implementation completion and V1 release readiness.