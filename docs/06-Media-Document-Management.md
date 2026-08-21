DOC-06 — SMITE CMS Media & Document Management

Document Version: 0.2.0
Status: Approved — Media & Document Management
Last Updated: 21 August 2026

1. Purpose

This document defines Media Library, image processing, document management, storage, public access, Media references, and Media lifecycle behavior for SMITE CMS V1.

The design prioritizes:

security;

low storage usage;

shared-hosting compatibility;

predictable maintenance;

reusable Media Assets;

minimal external dependencies.

2. Media Architecture Principles

MEDIA-001 — Centralized Media Library

SMITE CMS provides one reusable Media Library for content media.

V1 supports:

IMAGE
DOCUMENT

MEDIA-002 — Content Media vs Theme Assets

Media Library is for content media.

Theme static assets such as:

CSS
JavaScript
icons
fonts
theme images

are developer-owned Theme Assets and are not managed through the Media Library.

MEDIA-003 — Secure by Default

All uploads are untrusted input.

No uploaded file may become executable merely because it was accepted by the upload process.

MEDIA-004 — Reusable Assets

Every Media Asset has its own identity so that it can be reused by multiple Pages/Posts until removed or replaced.

3. Media Entity

Conceptually:

MediaAsset
├── Image metadata
└── Document metadata

A MediaAsset may include:

id
type
title
description
storage_identity
mime_type
file_size
status
uploaded_by
created_at
updated_at
deleted_at

The exact database schema is defined in 08-Technical-Architecture.md.

4. Media Types

V1 supports:

IMAGE
DOCUMENT

V1 does not require:

VIDEO
AUDIO
ARCHIVE

Additional media types require explicit requirements.

5. Media Lifecycle

Media lifecycle is separate from Page/Post editorial lifecycle.

V1:

ACTIVE
  ↓
TRASH
  ↓
RESTORE → ACTIVE

TRASH
  ↓
Permanent Delete

Media does not use:

DRAFT
PUBLISHED
UNPUBLISHED
ARCHIVED

as its primary lifecycle.

Document public availability is determined by document/content publication rules.

6. Media Ownership

Conceptual permissions:

Action

Admin

Editor

Contributor

Upload image

✓

✓

✓

Upload document

✓

✓

✓

Edit metadata

✓

✓

Own/permitted

Trash media

✓

✓

Own/permitted

Restore

✓

✓

Own/permitted

Permanent delete

✓

—

—

Actual authorization is governed by 03-Authorization-Security.md.

7. Media Library

Authorized users may:

browse Media;

filter by media type;

inspect metadata;

reuse existing Media;

upload new Media;

trash permitted Media;

restore permitted Media.

V1 does not require sophisticated search infrastructure.

Media listings SHALL be paginated.

8. Image Upload Pipeline

Upload
  ↓
Authentication / Authorization
  ↓
CSRF validation
  ↓
File validation
  ↓
Image signature inspection
  ↓
Dimension validation
  ↓
Image Profile selection
  ↓
Resize
  ↓
Optimize
  ↓
Store processed image
  ↓
Create MediaAsset
  ↓
Discard original

If processing fails, no partially valid MediaAsset may become active.

9. Image Processing Driver

V1 image processing SHALL use the PHP GD extension (ext-gd) through CodeIgniter 4's Image Manipulation service.

Conceptually:

\Config\Services::image('gd')

GD is the default image-processing driver for:

resizing;

image manipulation;

supported format conversion;

optimization steps compatible with the deployed PHP/CI4 environment.

SMITE CMS does not require ImageMagick CLI, GraphicsMagick, Node.js workers, or another external image-processing service for V1.

The production environment must provide ext-gd.

If a hosting environment lacks required GD capabilities, image-processing functionality must fail safely rather than silently storing an invalid or oversized original.

10. Image Validation

Every uploaded image SHALL be validated for:

allowed MIME type;

allowed extension;

valid image signature;

valid image structure;

file size;

width;

height;

applicable aspect ratio.

Extension alone is never sufficient validation.

11. Image Profiles

Image requirements are defined by developer-controlled Image Profiles.

Examples:

Hero
Featured
Thumbnail
OpenGraph

An Image Profile may define:

minimum_width
minimum_height
maximum_width
maximum_height
maximum_file_size
allowed_formats
aspect_ratio
output_format

Exact dimensions belong to the active Theme/content contract.

12. Minimum Image Dimensions

If an uploaded image is below the minimum required dimensions for its selected Image Profile:

Upload → REJECT

The original image must not be silently upscaled solely to satisfy the minimum requirement.

The user should receive a clear validation message.

13. Maximum Image Dimensions

If an image exceeds the maximum dimensions:

Upload
  ↓
Resize down
  ↓
Optimize
  ↓
Store processed image

Aspect ratio must be preserved unless the Image Profile explicitly defines controlled cropping.

Arbitrary distortion is not allowed.

14. Image Optimization

Processed images should be optimized to reduce storage and bandwidth.

V1 should favor broadly supported optimized formats.

WebP may be used as an optimized output where supported by the deployed environment and selected Image Profile.

AVIF is not required for V1.

15. Original Image Retention

The original uploaded image SHALL NOT be retained after successful processing unless an explicit future requirement requires master/original retention.

Normal flow:

Original Upload
  ↓
Processed Output
  ↓
Store Processed Output
  ↓
Delete Original

16. Generated Storage Identity

Uploaded filenames must never determine permanent storage identity.

The system SHALL generate a safe internal storage identifier.

Conceptual separation:

original_filename
    = user-facing metadata

storage_key
    = application-generated identity

This protects against:

path traversal;

collisions;

unsafe characters;

executable filename attacks.

17. Image URL and Storage Separation

Physical storage paths are not public identity.

Conceptually:

MediaAsset ID
  ↓
Storage service
  ↓
Current public representation

This allows storage implementation changes without changing content references.

18. Image Public Access

Publicly renderable Images may use controlled public asset URLs.

Only active Media Assets may resolve through the normal public image representation.

A trashed Media Asset must not continue to render through normal content paths.

19. Document Types

V1 supports:

PDF
DOC
DOCX
XLS
XLSX
PPT
PPTX

The exact MIME/extension allowlist is explicit and security-reviewed.

Additional formats require an approved requirement.

20. Document Security

Documents SHALL:

be validated by MIME;

be validated by extension;

be validated by file signature where technically applicable;

use generated storage identities;

be stored outside the public web root;

never be executable;

only be downloadable when public-access conditions are satisfied.

21. Document Storage

Documents SHALL be stored outside:

public/

Conceptual location:

writable/
└── uploads/
    └── documents/

The exact deployment path may vary, but documents must remain outside the directly accessible public web root.

22. Controlled Document Download

Public document download uses an application endpoint.

Conceptual flow:

GET /download/document/{public_identifier}
          ↓
Resolve Document
          ↓
Check current state
          ↓
Check public-download rule
          ↓
Check filesystem object
          ↓
Stream / download

The raw physical storage URL must never be exposed.

23. Public Document Identifier

Public document URLs SHALL NOT expose the internal auto-increment MediaAsset ID.

The public identifier SHALL be a cryptographically random, non-sequential token generated when the document is created.

Recommended length:

16–32 characters

Conceptually:

/download/document/{download_hash}

or an equivalent random public token.

The token:

is unique;

is not derived from the auto-increment database ID;

is safe for URL use;

is generated server-side;

is never based on the original filename.

This reduces predictable enumeration of document download URLs.

24. Document Public Access Rule

A document is publicly downloadable only when:

Document = ACTIVE
AND
its owning/reference content state permits public access

Draft, unpublished, trashed, or otherwise non-public content must not make its documents publicly downloadable.

A non-public document should return an appropriate not-found/forbidden response without exposing its physical storage path.

25. Document Download Memory Safety

Document downloads SHALL use CodeIgniter's response download facilities rather than loading the entire document into PHP memory.

Conceptually:

return $this->response->download($filePath, null);

The implementation must use streaming/chunked delivery appropriate to the deployed CI4 environment.

The application must not use:

file_get_contents($filePath)

to load the entire document into memory before sending it.

This is required to avoid memory exhaustion on shared hosting for files in the 10–25 MB range and beyond.

26. Document Download Security

The download service must prevent:

path traversal;

arbitrary file access;

directory traversal;

filename guessing;

access to unrelated writable files;

direct filesystem path exposure.

The request supplies only the controlled public identifier. The application resolves that identifier to the authoritative storage key.

27. Document Content Item

DOCUMENT is a supported Content Item type.

Conceptual usage:

$content['home']['brochure']

The rendered URL is generated by the application rather than stored as an arbitrary filesystem URL.

28. Media Metadata

Media metadata may include:

title
description
original_filename
mime_type
file_size
width
height
storage_key
media_type
uploaded_by
created_at
updated_at
status
deleted_at

A public document additionally has a non-sequential download identifier.

For Documents, dimensions may be null.

For Images, width/height should be recorded after successful processing.

29. Metadata Editing

Authorized Admin/Editor users may edit descriptive metadata such as:

title
description
alt text where applicable

They SHALL NOT manually change authoritative technical properties such as:

mime_type
storage_key
file signature
download_hash

Technical properties are generated or validated by the application.

30. Alt Text Resolution

Alt text resolution SHALL follow this priority:

1. Content Payload contextual alt text
       ↓
2. MediaAsset default alt/title metadata
       ↓
3. Empty string: alt=""

Example:

<img
    src="<?= esc($imageUrl) ?>"
    alt="<?= esc($resolvedAlt) ?>"
>

An empty alt="" is valid for decorative imagery and prevents screen readers from reading an unintended filename or technical identifier.

31. Media References

Dynamic Content Payload references Media Assets by:

media_id

Example:

{
  "hero_image": {
    "media_id": 42,
    "alt": "Main building"
  }
}

media_id is authoritative.

Physical storage paths and generated URLs are not duplicated as authoritative references inside Content Payload.

32. Direct Relational Media References

High-value fixed relationships may use direct relational references.

Example:

posts.featured_image_id

This provides stronger referential integrity and efficient querying.

Dynamic schema-controlled media references inside JSON remain application-validated.

33. Media Dependency Checking

Before permanent Media deletion, the system SHALL check:

Direct relational references
        +
Page Content Payloads
        +
Post Content Payloads

Conceptually:

Request Permanent Delete
          ↓
Authorization
          ↓
Dependency Check
          ↓
Referenced?
    ├── YES → REJECT
    └── NO  → Continue

Where practical, the UI should identify the affected content.

34. Media Replacement

Replacing a Media Asset SHALL create/use a new MediaAsset identity.

It must not mutate a shared MediaAsset in-place if doing so would silently change every content reference.

Example:

Old Image
media_id = 42

Replace
  ↓
New Image
media_id = 57

Content references are explicitly changed from 42 to 57.

35. Media Trash

Normal Media deletion moves the asset to Trash.

ACTIVE
  ↓
TRASH

with:

deleted_at = timestamp

Trashed Media:

is excluded from normal Media Library results;

is not rendered publicly;

cannot be newly assigned to content;

can be restored if authorized;

can be permanently deleted by Admin only.

36. Media Restore

Restore:

TRASH
  ↓
RESTORE
  ↓
ACTIVE

Restore does not change existing content references.

A restored MediaAsset becomes available again to its references, subject to normal content/publication rules.

37. Permanent Media Delete

Only Admin may permanently delete Media.

Permanent deletion SHALL be rejected if the MediaAsset is still referenced.

After dependency checks succeed, permanent deletion may remove:

the MediaAsset record;

processed physical files;

related technical metadata.

Permanent deletion must be safely auditable.

38. Upload Failure Handling

If any processing step fails:

Validation failure
Processing failure
Storage failure
Database failure

the system must:

avoid creating a partially valid MediaAsset;

clean up temporary files;

return a clear user-facing error;

log safe operational diagnostics;

never expose internal filesystem paths.

39. Temporary Upload Files

Temporary processing files should be stored outside public directories.

Temporary files must:

use generated names;

have restricted permissions;

be removed after successful or failed processing;

never become Media Library assets by accident.

40. Concurrent Upload Protection

Media creation must be safe under concurrent uploads.

Generated storage identities must avoid collisions.

Two uploads with identical original filenames must still receive distinct MediaAsset/storage identities.

41. Media Cache

Public images may be cached according to public cache policy.

Changing or trashing a MediaAsset should invalidate relevant cached references where required.

The implementation should prefer targeted cache invalidation over invalidating the entire public site.

42. Document Cache and Delivery

Document downloads are served through controlled application endpoints and should not rely on long-lived public HTML caching.

Access checks must occur on the download request.

Response headers should be configured appropriately for the document's download behavior.

43. Media Library Performance

Media Library lists SHALL be paginated.

Do not load every Media Asset into one Control Panel page.

Filtering should use indexed metadata where practical.

Grid/list thumbnails should use optimized image representations rather than large images.

44. Security Logging

Media security events may include:

MEDIA_UPLOADED
MEDIA_METADATA_UPDATED
MEDIA_TRASHED
MEDIA_RESTORED
MEDIA_PERMANENTLY_DELETED
DOCUMENT_DOWNLOAD_DENIED

Routine successful public image requests must not generate database audit rows.

45. Future Extensions

V1 intentionally does not implement:

video library;

audio library;

object storage requirement;

CDN requirement;

mandatory antivirus service integration;

image AI tagging;

OCR;

online document editing;

media collections/albums;

sophisticated media search.

Future capabilities require explicit requirements.

46. Traceability

This document derives from:

00-Project-Charter.md;

01-Product-Requirements.md;

02-Domain-Model.md;

03-Authorization-Security.md;

05-Theme-Template-Architecture.md.

Primary requirement groups:

REQ-MEDIA-*
REQ-DOC-*
REQ-CONT-*
REQ-THEME-*
REQ-NFR-*

All implementation must preserve the security, storage, lifecycle, and dependency invariants defined here.