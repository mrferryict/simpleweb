DOC-07 — SMITE CMS Localization, URL & SEO

Document Version: 0.2.0
Status: Approved — Localization, URL & SEO
Last Updated: 21 August 2026

1. Purpose

This document defines:

Primary and Secondary Language behavior;

Translation and fallback;

localized public URLs;

slug and reserved route rules;

historical redirects;

canonical URLs;

hreflang;

sitemap;

robots.txt;

SEO metadata;

preview indexing protection;

third-party analytics integration boundaries.

The document preserves the global URL uniqueness and developer-controlled routing principles established by the Project Charter and Domain Model.

2. Language Model

LOC-001 — Primary Language

Every website SHALL have exactly one Primary Language.

The Primary Language is required.

LOC-002 — Secondary Language

The website MAY have one Secondary Language.

The Secondary Language is optional.

V1 supports at most:

Primary
Secondary

There is no arbitrary third/fourth language in V1.

LOC-003 — Supported Languages

Language codes SHALL use stable language identifiers such as:

id
en

Supported language choices SHALL come from an application-controlled list rather than arbitrary Admin input.

3. Language URL Strategy

SMITE CMS V1 SHALL use:

Primary Root + Secondary Prefix

Example:

Primary:
https://example.com/about

Secondary:
https://example.com/en/about-us

The Primary Language uses the natural root namespace.

The Secondary Language uses a locale prefix.

This strategy applies to localized public Page/Post URLs and any other localized public resource explicitly supported by the routing architecture.

4. Language Prefix Rules

The active Secondary Language code becomes a reserved route prefix.

Example:

Secondary Language = en

then:

/en/...

is reserved for the Secondary Language namespace.

If the Primary Language is changed in a future configuration update, routing configuration must be validated so that locale prefixes do not conflict with existing reserved routes or public content.

5. Reserved Language Prefixes

Active locale codes used as route prefixes SHALL be part of the Reserved System Slug/Route set.

Example:

/en

must not be claimed by a Page, Post, Category, or other public resource as an equivalent root path.

This prevents routing ambiguity.

6. Translation Entities

Pages and Posts use dedicated Translation entities:

Page
└── PageTranslation

Post
└── PostTranslation

Primary Translation is required for publishable content.

Secondary Translation is optional.

7. Translation Completeness

A Page/Post does not require a translation in every configured language.

Example:

Page
├── ID ✓
└── EN —

is valid.

If a visitor requests the Secondary Language and a translation is missing, the application uses Primary Language content according to the fallback rules below.

8. Language Fallback

Resolution order:

Requested Secondary Language
        ↓
Translation exists?
   ├── YES → render Secondary
   └── NO  → render Primary fallback

Fallback is deterministic and implemented in the application/service layer.

Fallback SHALL NOT automatically create a missing Translation record.

9. Localized Content Scope

Localization may apply to:

Page title;

Page Content Payload;

Post title;

Post excerpt;

Post content;

Post Content Payload;

SEO metadata;

contextual Media alt text;

other Content Schema fields explicitly marked translatable.

Not every Site Setting is translatable.

10. Global Site Settings Localization

Site Settings SHALL distinguish:

Global/non-translatable settings

Conceptually:

locale = NULL

Examples:

timezone
smtp configuration
logo reference
system preferences

Localized settings

Conceptually:

locale = id
locale = en

Examples:

site_title
footer_text
site_description
default_meta_description

The technical implementation may use a locale column on the Site Settings storage model.

The logical uniqueness rule SHALL be:

setting_key + locale

with only one global (NULL) record per setting key.

The exact database uniqueness implementation is defined in 08-Technical-Architecture.md.

11. Language Switching

A Theme may provide a language switcher.

The switcher should link to the equivalent localized resource when a real translation exists.

Example:

ID:
 /tentang-kami

EN:
 /en/about-us

When no Secondary translation exists, the UI should not pretend that a translated resource exists.

12. Localized Slugs

Pages and Posts MAY have localized slugs.

Example:

Primary:
 /tentang-kami

Secondary:
 /en/about-us

Each localized public URL must independently satisfy:

global URL uniqueness;

reserved route rules;

valid slug format;

historical redirect rules.

13. Public URL Namespace

All current public URLs share one global namespace.

Potential owners include:

Page
Post
Category
Other explicitly defined public resources

No two active public resources may claim the same public path.

14. URL Structure Ownership

Developer controls route structure.

Admin/Editor controls content slugs only within the route structure already defined by the application.

Admin cannot create arbitrary routes.

Example:

Developer route:
 /news/{slug}

Admin enters:

annual-meeting

Result:

/news/annual-meeting

For Secondary Language:

/en/news/annual-meeting

where that localized route is supported by the application.

15. Reserved System Routes

The application reserves paths such as:

/cp
/admin
/download
/sitemap.xml
/robots.txt

and active locale prefixes such as:

/en

Content slugs must not conflict with these routes.

The exact reserved-route registry belongs to 08-Technical-Architecture.md.

16. Current URL Uniqueness

A Page/Post/Category cannot claim a current public URL already used by another public resource.

Validation MUST consider:

Current public URLs
+
Active historical redirect URLs
+
Reserved system routes
+
Active locale prefixes

The database/application implementation must preserve this invariant atomically.

17. Historical URLs

When a published Page/Post slug changes:

/about

to:

/company

the system SHALL retain:

/about → /company
HTTP 301

The previous URL remains reserved while the redirect is active.

18. Localized Historical URLs

Localized URL changes follow the same redirect rules.

Example:

/en/about

changes to:

/en/about-us

then:

/en/about → /en/about-us
HTTP 301

Historical localized paths remain reserved while their redirect is active.

19. Redirect Ownership

Historical redirect records represent previous public URL ownership.

Conceptual attributes:

source_path
target_path
http_code
resource_type
resource_id
locale
active
created_at

The default HTTP code for editorial slug changes is:

301

20. Redirect Chain Prevention

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

Exact redirect normalization belongs to the URL service/technical architecture.

21. Slug Change Atomicity

A slug change and its historical redirect creation SHALL be treated as one logical operation.

The system must not persist:

new slug without redirect

or:

redirect without successful new slug

unless the redirect is explicitly being managed by an authorized maintenance operation.

22. Canonical URL

Every public Page/Post should have a deterministic canonical URL.

If an explicit canonical URL is not configured, the system generates the canonical from the current public URL.

Canonical URLs must use valid HTTP/HTTPS URLs.

23. Localized Canonical URL

When a real Secondary translation exists:

/en/about-us

should have its own canonical URL:

https://example.com/en/about-us

Primary and Secondary localized pages should canonically reference themselves unless a specific SEO rule dictates otherwise.

24. Canonical During Secondary-Language Fallback

If a visitor requests a Secondary-language URL but the actual Secondary Translation does not exist:

/en/news/prestasi-sekolah

the application may render Primary Language fallback content.

However:

the page SHALL NOT claim an English translation exists;

the canonical URL SHALL point to the corresponding Primary Language URL;

the page SHALL NOT emit an hreflang="en" alternate for a translation that does not exist.

Example:

Requested:
 /en/news/prestasi-sekolah

Fallback content:
 /news/prestasi-sekolah

Canonical:

<link
    rel="canonical"
    href="https://example.com/news/prestasi-sekolah"
/>

This prevents the fallback URL from being treated as an independent translated document.

25. SEO Metadata

Pages and Posts support:

meta_title
meta_description
canonical_url
og_image

Metadata resolution:

Page/Post specific
        ↓
Site localized default
        ↓
Site global default where applicable
        ↓
Deterministic generated fallback

The exact fallback order for each field is defined by the SEO service implementation.

26. hreflang

Public localized content SHALL emit hreflang alternates when a real translation exists.

Example:

<link
    rel="alternate"
    hreflang="id"
    href="https://example.com/tentang-kami"
/>

<link
    rel="alternate"
    hreflang="en"
    href="https://example.com/en/about-us"
/>

<link
    rel="alternate"
    hreflang="x-default"
    href="https://example.com/tentang-kami"
/>

Rules:

each actual available language may have an alternate;

missing translations must not produce fake alternate URLs;

x-default points to the Primary Language representation by default;

alternates must reference valid public URLs.

27. hreflang for Missing Secondary Translation

When the Secondary Translation does not exist:

Primary translation exists
Secondary translation missing

the page SHALL NOT emit:

hreflang="en"

for a nonexistent translation.

The Primary URL may emit its normal self/available-language alternates and x-default.

A fallback /en/... URL is not considered an actual English translation.

28. Sitemap

The public website SHALL expose:

/sitemap.xml

The sitemap contains only currently public, indexable URLs.

Excluded:

Draft
Pending Review
Unpublished
Archived
Trash

unless a future explicit SEO requirement changes these rules.

29. Localized Sitemap

When localized translations exist, each public localized URL may be represented in the sitemap architecture.

The sitemap implementation must remain consistent with:

localized canonical URLs;

available translations;

hreflang;

current publication state.

Fallback-only Secondary URLs should not be treated as independent translated URLs.

30. robots.txt

SMITE CMS SHALL expose:

/robots.txt

The response is generated from controlled application/deployment configuration.

Admin cannot inject executable content or arbitrary malformed directives through normal Site Settings.

31. Preview SEO Protection

Theme Preview and content preview must not become indexable.

Preview responses should include:

X-Robots-Tag: noindex, nofollow, noarchive

and appropriate cache-control headers defined by 05-Theme-Template-Architecture.md.

Preview remains protected by authentication and authorization.

32. Unpublished/Archived/Trash SEO

When content becomes:

UNPUBLISHED
ARCHIVED
TRASH

it is removed from:

normal public rendering;

sitemap eligibility;

normal indexable URL set.

If no redirect exists for the previous URL, the public request should resolve to the appropriate not-found behavior.

33. Removed Content and Redirects

For a removed public resource:

With active historical redirect

301 Redirect

Without redirect

404 Not Found

No stale content should remain publicly renderable solely because an old URL is known.

34. SEO and Theme Responsibility

Application/service layer provides normalized SEO data.

Theme controls output such as:

<title>...</title>
<meta name="description" ...>
<link rel="canonical" ...>
<link rel="alternate" hreflang="..." ...>
<meta property="og:image" ...>

Theme must not query database tables directly for SEO metadata.

35. Structured Data

V1 does not provide a generic arbitrary Schema.org/JSON-LD builder.

Developer-defined Theme templates may emit structured data from trusted/validated application data.

Arbitrary user-provided JSON-LD scripts are not permitted.

36. Analytics

V1 does not implement a proprietary analytics engine.

The architecture may provide a controlled integration point for third-party analytics.

No analytics provider is mandatory.

No visitor tracking mechanism may be added without explicit approval.

37. External URL Security

Content Item type URL must accept only valid HTTP/HTTPS URLs unless a future requirement explicitly permits another scheme.

The system must reject dangerous schemes including:

javascript:
data:
vbscript:

38. Slug Rules

Slugs SHALL:

be normalized;

be lowercase;

use predictable separator rules;

avoid unsupported special characters;

remain stable until explicitly changed;

be unique in the global public namespace;

not conflict with reserved system routes;

not conflict with active locale prefixes;

not conflict with active historical redirects.

The exact normalization algorithm belongs to the technical implementation.

39. Language Configuration Changes

Changing Primary or Secondary Language is a high-impact configuration change because it can affect:

URL routing;

locale prefixes;

canonical URLs;

hreflang;

sitemap;

fallback behavior.

Such changes must be validated before becoming active.

The system must not allow a language configuration that causes route collisions.

40. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

03-Authorization-Security.md;

04-Content-Publishing.md;

05-Theme-Template-Architecture.md.

Primary requirement groups:

REQ-LOC-*
REQ-SEO-*
REQ-PAGE-*
REQ-POST-*
REQ-NFR-*

All implementation must preserve the global URL uniqueness, localization fallback, reserved-route, canonical, and SEO invariants defined here.