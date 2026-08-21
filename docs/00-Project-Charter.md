DOC-00 --- SMITE CMS Project Charter

Document Version: 0.2.0
Status: Approved --- Project Charter
Last Updated: 20 August 2026

1. Product Identity

SMITE CMS is a lightweight, secure, maintainable Content Management
System for one organization and one public website.

SMITE CMS is not a website builder.

The presentation layer is controlled by the developer. Administrators
manage content and configuration inside boundaries established by the
active Theme.

2. Product Goals

Provide simple and safe content management.

Allow Admin and Editor users to publish website content without
editing application code.

Keep presentation architecture under developer control.

Provide a clean CodeIgniter 4 architecture.

Keep third-party dependencies minimal.

Support shared-hosting deployment without Docker, Redis, or a queue
requirement.

Provide strong auditability, revision history, and predictable
publishing.

Keep the architecture extensible for future modules without
implementing those modules prematurely.

3. Core Principles

3.1 CMS, Not Website Builder

Admins cannot create arbitrary layouts, HTML structures, templates,
components, or content fields.

3.2 Developer Owns Presentation

The developer owns:

Theme files.

HTML structure.

Header.

Navigation markup.

Footer.

Page templates.

Content schemas.

CSS/JavaScript architecture.

Public route structure.

3.3 Role-Based Responsibility

Admin

Admin owns system configuration and has full content authority,
including:

Site settings.

Menu management.

Users.

Pages.

Posts.

Categories.

Tags.

Media.

Theme activation.

Publishing.

Audit inspection.

Permanent content deletion.

There is exactly one Admin.

Editor

Editor has delegated editorial authority, including:

Creating and editing Posts.

Editing any Post.

Managing Categories and Tags.

Managing permitted Media.

Managing Pages within developer-defined template/content boundaries.

Publishing without Admin approval.

Reviewing Contributor submissions.

Unpublishing and archiving content.

Editor cannot modify system-level Site Settings, enable Themes, or
permanently delete content.

Contributor

Contributor has limited editorial authority:

Create Posts.

Edit own Posts.

Save drafts.

Submit Posts for review.

Manage permitted own media.

Contributor cannot publish, manage Pages, manage Categories, modify Site
Settings, activate Themes, edit another user's Post, or permanently
delete content.

3.4 Cursor Must Not Invent Features

Cursor must not implement a feature merely because it is technically
easy, common, or convenient.

A feature must exist in the requirements or be explicitly requested.

3.5 Minimal Dependencies

Prefer CodeIgniter and PHP built-ins. Add third-party packages only when
they solve a concrete requirement and the dependency is justified in an
ADR.

4. Product Boundary

4.1 In Scope

Authentication and Control Panel.

Admin, Editor, Contributor roles.

Site settings.

Menu management.

Page management.

Post management.

Categories.

Tags.

Media Library.

Images.

Public downloadable documents.

Themes and developer-defined templates.

Revision history.

Audit trail.

Scheduled publishing/unpublishing.

Basic SEO.

Primary/secondary language foundation.

Public website rendering.

Caching.

Security and rate limiting.

4.2 Out of Scope for V1

Membership.

Ecommerce.

Comments.

Internal analytics engine.

Full website/page builder.

Arbitrary custom fields.

Arbitrary HTML layout editor.

Complex workflow requiring Admin approval for Editor publishing.

Search.

Queue infrastructure.

Redis requirement.

Docker requirement.

Multi-tenant operation.

Multi-organization operation.

These may become future modules/features without being implemented now.

5. Deployment Boundary

Development

Windows 11 Pro.

WSL Ubuntu 24.04 LTS.

CodeIgniter 4.

Cursor AI.

Project workspace: /var/www/html/simpleweb.

Production Target

Shared hosting with cPanel/hPanel or equivalent.

PHP 8.5+ where available.

MariaDB.

Cron.

SMTP/email capability may be used for password recovery.

No Docker dependency.

6. System Invariants

One installation manages one organization and one website.

Exactly one Admin account exists as the system administrator.

At least one Editor must exist.

Contributor is optional.

Only one Theme can be ACTIVE at a time.

Every Theme must provide exactly one custom-page template. A Theme
may contain additional developer-defined templates.

Public routes are developer-controlled.

Public URLs must be globally unique.

User accounts are deactivated rather than permanently deleted.

Content deletion uses Trash/soft-delete before permanent deletion.

Only Admin may permanently delete content.

Audit history is immutable.

Revision history is mandatory for editable content.

Editors may publish permitted content without Admin approval.

Contributor Posts require Editor review before publication.

Primary Language is required.

Secondary Language is optional.

Missing secondary-language content falls back to Primary Language.

Scheduled publishing/unpublishing is performed by an idempotent CI4
Spark command invoked by cron.

Public rendering reads the current persisted content state; it does
not perform hidden state transitions merely because a scheduled
timestamp has passed.

Theme activation changes the active presentation Theme globally.

Admin cannot modify Theme source, template source, header markup,
navigation markup, or footer markup.

Content schemas are developer-controlled; Admin cannot create
arbitrary Content Item fields.

Public document downloads are allowed only for published/active
documents.

7. Scheduling Principle

Scheduling is a state-management concern, not a presentation-layer
concern.

The scheduler is responsible for:

Detecting due scheduled actions.

Changing the persisted content state.

Recording the appropriate audit event.

Invalidating affected caches.

Being safe to run repeatedly.

Catching up jobs that became due while cron was unavailable.

The public frontend is responsible only for rendering the current valid
persisted state.

This separation keeps publishing behavior deterministic, auditable,
cacheable, and easy to test.

8. Presentation Principle

SMITE CMS separates Theme, Page Template, and Content
Schema.

Theme
├── Layout
├── Header
├── Navigation
├── Footer
├── Components
├── Assets
├── Page Templates
└── Content Schemas

Every Theme must provide:

custom-page

as its required general-purpose Page template.

Additional templates are optional and are defined only by the developer.

The Admin selects and activates Themes; the Admin does not create or
modify presentation structures.

9. Localization Principle

SMITE CMS establishes multilingual infrastructure from V1 without
requiring every content item to have a translation.

Primary Language
        │
        └── Required

Secondary Language
        │
        └── Optional

When secondary-language content does not exist, the Primary Language is
used as fallback.

Localization applies to Pages and Posts and must be designed into the
content model from the beginning.

10. Project Governance

This Project Charter is the highest-level product definition for SMITE
CMS.

Implementation must follow:

Global engineering rules in .cursorrules.

Project-specific rules in CONTEXT.md.

This Project Charter.

Detailed Product Requirements.

Domain and technical architecture documents.

Approved Architecture Decision Records.

A material architectural change requires documentation review before
implementation.

Cursor must not silently change product scope, data model, security
model, authorization model, public URL behavior, publishing behavior,
Theme contract, or deployment architecture.