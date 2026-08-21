DOC-03 — SMITE CMS Authorization & Security

Document Version: 0.2.0
Status: Approved — Authorization & Security
Last Updated: 21 August 2026

1. Purpose

This document defines the security boundary for SMITE CMS V1, including:

authentication;

authorization;

role and permission model;

session security;

password recovery;

Admin recovery key;

CSRF protection;

brute-force protection;

PII protection;

upload security;

Rich Text sanitization;

audit security events;

public versus Control Panel security boundaries.

This document complements .cursorrules, CONTEXT.md, 00-Project-Charter.md, 01-Product-Requirements.md, and 02-Domain-Model.md.

2. Security Principles

SEC-001 — Secure by Default

All state-changing endpoints and actions are protected by default until authorization explicitly permits them.

SEC-002 — Server-Side Authorization

Hiding a UI menu is not a security mechanism.

Every protected action SHALL perform server-side authorization, including direct requests to URLs under /admin/*.

SEC-003 — Least Privilege

Each role receives only the permissions required for its responsibilities.

SEC-004 — No Security by Obscurity

/cp is an authentication entry point, not a security boundary.

SEC-005 — Secrets Never Enter Source Control

Passwords, recovery keys, encryption keys, SMTP credentials, API keys, and other secrets SHALL only originate from protected environment/configuration sources.

3. Authentication

SMITE CMS uses CodeIgniter Shield for authentication.

3.1 Login

Primary credentials:

username
password

Authentication entry point:

/cp

After successful authentication:

/admin/*

3.2 Public Website

The public website is unauthenticated in V1.

Examples:

/
/about
/news
/news/example

4. User Roles

V1 has three roles:

ADMIN
EDITOR
CONTRIBUTOR

4.1 Admin

Exactly one Admin exists.

Admin has full system authority, including:

user management;

Site Settings;

Menu management;

Pages;

Posts;

Categories;

Tags;

Media;

Theme activation;

publishing;

revision restoration;

audit inspection;

permanent content deletion.

Admin cannot delete the Admin account itself.

4.2 Editor

Editor may:

create and edit Posts;

edit any Post;

publish Posts directly;

unpublish Posts;

archive Posts;

review Contributor submissions;

manage Categories and Tags;

manage permitted Media;

create and edit Pages within developer-defined Template/Schema boundaries;

restore permitted revisions.

Editor cannot:

modify Site Settings;

enable Themes;

activate Themes;

manage user accounts;

permanently delete content.

4.3 Contributor

Contributor may:

create Posts;

edit own Posts;

save Drafts;

submit Posts for review;

manage permitted own media.

Contributor cannot:

publish;

manage Pages;

manage Categories;

modify Site Settings;

activate Themes;

edit Posts owned by another user;

permanently delete content.

5. Permission Model

Roles SHALL not be the only authorization abstraction in application code.

Recommended permission families:

site.manage

menu.manage

page.create
page.edit
page.publish
page.unpublish
page.archive
page.restore
page.trash

post.create
post.edit_own
post.edit_any
post.submit_review
post.review
post.publish
post.unpublish
post.archive
post.restore
post.trash

category.manage
tag.manage

media.upload
media.edit
media.delete
media.restore

user.manage

theme.preview
theme.activate

audit.view

content.permanent_delete

Exact CodeIgniter Shield groups/permissions are implementation details.

6. Authorization Rules

AUTHZ-001 — Contributor Ownership

Contributor may edit only Posts within the allowed ownership scope, which for V1 means Posts created by that Contributor.

AUTHZ-002 — Editor Override

Editor may edit Posts belonging to Contributors or other Editors.

AUTHZ-003 — Admin Override

Admin has full content authority.

AUTHZ-004 — Permanent Deletion

Only Admin may permanently delete content.

AUTHZ-005 — Theme Activation

Only Admin may activate an ENABLED Theme.

Changing a Theme from DRAFT to ENABLED is developer-controlled and is not an Admin permission.

AUTHZ-006 — Site Settings

Only Admin may modify Site Settings.

AUTHZ-007 — Audit Access

Admin may view the Audit Trail.

Editor access to Audit Trail is not part of V1 unless explicitly added as a requirement.

7. Admin Bootstrap

A fresh installation SHALL create exactly one Admin through a controlled bootstrap process.

Initial credentials SHALL not be hard-coded into application source code or committed to Git.

The initial Admin credentials must be changed at first successful login.

Minimum bootstrap data:

username
email
password
role = ADMIN
active = true

8. Admin Recovery Key

The environment secret:

skey="..."

is used as an emergency Admin recovery mechanism.

The recovery key SHALL:

exist only in environment/configuration;

never be stored in the database;

never be logged;

never be displayed;

never be committed to Git;

be rate-limited;

generate an audit event.

8.1 Recovery Flow

/cp
  ↓
Admin Recovery
  ↓
Enter recovery key
  ↓
Rate limit
  ↓
Constant-time key comparison
  ↓
Set new password
  ↓
Invalidate existing sessions
  ↓
Audit event

8.2 Constant-Time Comparison

Recovery key verification SHALL use a constant-time comparison such as:

hash_equals($configuredKey, $providedKey)

The input key must never be written to logs.

A leaked recovery key SHALL be considered equivalent to compromise of the Admin authentication credential.

9. Password Policy

Password handling is delegated to CodeIgniter Shield.

Requirements:

plaintext passwords are never stored;

passwords are never displayed;

Admin may reset another user's password but cannot retrieve it;

password changes invalidate existing sessions;

password reset events are audited.

A strong password policy SHALL be enforced through Shield/application configuration.

10. Session Management

10.1 Session Timeout

Authenticated sessions SHALL expire after the configured inactivity period.

10.2 Normal Requests

For a normal browser request with an expired session, the application redirects to:

/cp

10.3 HTMX Requests

If an authenticated HTMX request reaches an expired/invalid session, the server SHALL NOT return a normal 302 /cp that could cause the login page to be swapped into a fragment.

When the request contains:

HX-Request: true

the authentication/session response SHALL use:

HX-Redirect: /cp

or an equivalent centralized full-window redirect mechanism.

This behavior SHALL be implemented centrally in the authentication/filter/response layer rather than duplicated across Controllers.

10.4 Session Invalidation

Sessions SHALL be invalidated when:

password is changed;

password is reset;

user is deactivated;

emergency Admin recovery completes.

11. CSRF Protection

CSRF protection is mandatory for state-changing browser requests.

11.1 HTMX CSRF Strategy

SMITE CMS SHALL use a standardized session-backed CSRF strategy with:

X-CSRF-TOKEN

for HTMX state-changing requests.

The application SHALL not implement a custom CSRF algorithm.

11.2 Token Synchronization

If CSRF token regeneration is enabled, the application SHALL keep the client-side token synchronized with the server-side token after regeneration.

The exact synchronization mechanism may use response metadata/events or another CI4-compatible mechanism, but the invariant is:

A subsequent valid HTMX request must not fail merely because it reused a stale CSRF token generated before the previous successful request.

11.3 Logout

Logout follows the idempotency and CSRF exception rules defined by the global .cursorrules.

12. Draft Auto-save Security

Auto-save uses authenticated, authorized endpoints.

Each request SHALL enforce:

valid session;

role/ownership authorization;

CSRF protection;

Content Schema validation.

Auto-save must never bypass normal authorization.

If the session expires, the request is handled using the centralized HTMX session-expiry behavior.

An auto-save failure must not destroy unsaved client-side content.

13. Brute-Force Protection

Server-side throttling SHALL protect at least:

login
password-reset request
password-reset verification
Admin recovery

CodeIgniter Throttler should be used unless an approved ADR specifies otherwise.

Authentication failures should not reveal whether a username or email exists.

14. Input Validation

All external input is untrusted.

Server-side validation is mandatory for:

username;

email;

slug;

title;

Content Items;

Categories;

Tags;

URLs;

YouTube URLs;

scheduling dates/times;

menu targets;

image uploads;

document uploads.

Client-side validation is only a usability aid.

15. Output Encoding

Dynamic output SHALL be escaped unless it is intentionally sanitized HTML.

Default:

<?= esc($value) ?>

Raw output requires an explicit security decision.

16. Rich Text Security

RICH_TEXT is HTML-producing content and SHALL be sanitized server-side.

An explicit allowlist of HTML elements and attributes is required.

Dangerous constructs SHALL be rejected, including:

<script>
onclick
onerror
javascript:
arbitrary iframe

The exact allowlist and sanitizer implementation are defined in 08-Technical-Architecture.md.

Client-side Quill.js behavior is never the security boundary.

17. YouTube URL Security

YOUTUBE_URL accepts only supported YouTube URL forms.

The system extracts and validates a safe video identifier.

Arbitrary HTML/iframe input is prohibited.

Rendering is controlled by the Theme/application.

18. File Upload Security

All uploads are untrusted.

Upload processing SHALL enforce:

authentication/authorization;

CSRF protection;

MIME validation;

extension validation;

file signature/content validation;

maximum file size;

image dimensions where applicable;

safe generated filenames;

path traversal protection;

non-executable storage;

explicit allowed MIME types.

Uploaded filenames SHALL never determine storage paths.

19. Image Security

Image processing flow:

Upload
  ↓
Authorization
  ↓
Validation
  ↓
Image inspection
  ↓
Dimension validation
  ↓
Resize
  ↓
Optimize
  ↓
Store processed image
  ↓
Discard original

Image processing follows developer-defined Image Profiles.

Image output must not be executable.

20. Document Security

Documents SHALL be stored outside the public web root.

Conceptual location:

writable/uploads/documents/

A document is never public merely because its physical file exists.

Public download flow:

GET /download/...
  ↓
Resolve document identity
  ↓
Validate current document/content state
  ↓
Validate public-download permission
  ↓
Check file existence
  ↓
Stream file

Draft, unpublished, inactive, and trashed documents SHALL NOT be publicly downloadable.

21. Media Deletion Protection

Before permanent MediaAsset deletion, the system SHALL check:

direct relational references
+
Page Content Payload references
+
Post Content Payload references

If a Media Asset is still referenced:

Permanent Delete → REJECT

The user should be shown where the asset is currently used.

22. PII Protection

User email is PII.

SMITE CMS follows the PII requirements of the global .cursorrules.

22.1 Email Storage

The authoritative stored email values are:

email_ciphertext
email_lookup_hash

email_ciphertext contains encrypted email data at rest.

email_lookup_hash is an HMAC-SHA256 value of the normalized email for deterministic lookup and uniqueness support.

22.2 Email Normalization

Before lookup hashing:

trim
lowercase

The same normalization policy SHALL be used consistently for creation, update, recovery lookup, and uniqueness checks.

22.3 Key Separation

Email encryption and email lookup hashing SHALL use separate environment secrets:

EMAIL_ENCRYPTION_KEY=...
EMAIL_LOOKUP_HMAC_KEY=...

The same secret SHALL NOT be reused for both purposes.

22.4 PII Encryption Service

Encryption/decryption SHALL be centralized behind a dedicated PII/Encryption Service.

Application code should not implement ad-hoc encryption/decryption in individual Controllers or Models.

The abstraction SHALL allow controlled future key rotation.

22.5 Logging

Decrypted PII, encryption keys, HMAC keys, and sensitive recovery data SHALL never be written to logs.

Normal list views should avoid exposing unnecessary personal data.

23. Secret Management

Secrets include:

database credentials
skey
EMAIL_ENCRYPTION_KEY
EMAIL_LOOKUP_HMAC_KEY
SMTP credentials
third-party API keys

Secrets SHALL never be:

committed;

logged;

returned in responses;

embedded in frontend JavaScript;

placed under public web directories.

24. URL Security

Public URL handling SHALL:

normalize paths;

reject path traversal;

prevent route ambiguity;

enforce global uniqueness;

respect reserved historical URLs.

Application URL parsing should use CodeIgniter's URI/routing mechanisms rather than ad-hoc string parsing.

25. Audit Security Events

Security-sensitive actions SHALL be audited.

Examples:

LOGIN
LOGIN_FAILED
LOGOUT
PASSWORD_CHANGED
PASSWORD_RESET
ADMIN_RECOVERY
USER_CREATED
USER_ACTIVATED
USER_DEACTIVATED

Audit records SHALL:

identify the actor where applicable;

include the timestamp;

identify event type;

be immutable;

never contain plaintext passwords or secrets.

26. Content Audit Events

Meaningful content state changes should be audited:

POST_CREATED
POST_UPDATED
POST_SUBMITTED_FOR_REVIEW
POST_PUBLISHED
POST_UNPUBLISHED
POST_ARCHIVED
POST_TRASHED
POST_RESTORED
POST_PERMANENTLY_DELETED

PAGE_CREATED
PAGE_UPDATED
PAGE_PUBLISHED
PAGE_UNPUBLISHED
PAGE_ARCHIVED
PAGE_TRASHED
PAGE_RESTORED
PAGE_PERMANENTLY_DELETED

THEME_ACTIVATED
SETTING_UPDATED
MENU_UPDATED
MEDIA_UPLOADED
MEDIA_TRASHED
REVISION_RESTORED

Routine auto-save requests SHALL NOT flood the Audit Trail.

27. Audit Immutability

Audit records cannot be:

edited;

manually altered;

deleted through normal CMS UI.

Any future retention/deletion mechanism requires a new approved security requirement and ADR.

28. Error Disclosure

Production errors SHALL NOT expose:

stack traces;

SQL statements;

filesystem paths;

environment variables;

API credentials;

unnecessary internal identifiers;

personal data.

Detailed diagnostic information belongs in controlled server logs.

29. Authentication Boundary

Authentication entry point:

/cp

Authenticated Control Panel routes:

/admin/*

Public routes remain unauthenticated in V1.

The application SHALL NOT rely on /cp obscurity as an authorization mechanism.

30. Security Headers

Production responses should provide appropriate security headers, including where compatible with the application:

Content-Security-Policy;

X-Content-Type-Options;

Referrer-Policy;

appropriate frame restrictions;

Strict-Transport-Security when HTTPS is fully enforced.

Exact header configuration belongs to the technical/deployment architecture.

31. HTTPS

Production authentication, Control Panel, password recovery, and document download SHALL operate over HTTPS.

HTTP-to-HTTPS enforcement belongs to production infrastructure/application configuration.

32. Dependency Security

Third-party packages must be minimal.

Every package must have a concrete requirement.

Before adding a package, assess:

security maintenance;

compatibility with CI4/PHP;

shared-hosting compatibility;

upgrade/maintenance burden;

whether native PHP/CodeIgniter functionality can safely solve the requirement.

33. Security Testing Requirements

Security tests SHALL cover at minimum:

role authorization;

direct URL access to forbidden /admin/* routes;

inactive user authentication;

session expiration;

HTMX session expiration;

password reset;

Admin recovery;

constant-time recovery-key comparison;

brute-force throttling;

CSRF;

HTMX CSRF token synchronization;

XSS through RICH_TEXT;

malicious URL schemes;

malicious file uploads;

document access control;

media dependency checks;

reserved URL collision;

audit immutability;

PII encryption and lookup hashing.

34. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

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
REQ-SEO-*
REQ-SCHED-*
REQ-AUDIT-*
REQ-REV-*
REQ-NFR-*

Security implementation SHALL preserve all mandatory invariants defined by those documents.