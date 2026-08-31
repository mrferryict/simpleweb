# SMITE CMS — Control Panel

This document describes the **Admin Control Panel** introduced and polished in TH-006 through TH-010. It is a client-facing overview, not a feature manual for every screen.

## Public site vs Control Panel

| URL | Purpose |
|---|---|
| `/` | Public website (Theme 2026 when active) |
| `/cp` | Login entry point (Shield) |
| `/admin` | Control Panel dashboard (after authentication) |
| `/admin/*` | Authenticated administration routes |

Sign in at `/cp`. After a successful login, open `/admin` for the dashboard and use the sidebar to reach other areas.

Do not confuse the **public Theme** (`app/Views/themes/2026/`, `public/themes/2026/`) with the **Admin UI** (`app/Views/admin/`, `public/assets/admin/css/`). They are separate presentation layers.

## Main areas

Navigation is a single sidebar (desktop) or menu (mobile) with permission-gated items where noted.

| Area | Route | Purpose |
|---|---|---|
| **Dashboard** | `/admin` | Starting point; module shortcuts (no analytics widgets) |
| **Pages** | `/admin/pages` | Website pages, slugs, publication workflow |
| **Posts** | `/admin/posts` | News/articles, authors, categories, tags |
| **Categories** | `/admin/categories` | Post categories (flat taxonomy) |
| **Tags** | `/admin/tags` | Post tags |
| **Media** | `/admin/media` | Uploaded images and documents |
| **Menus** | `/admin/menus` | Primary and Footer navigation items |
| **Settings** | `/admin/settings` | Site identity, localization, SEO defaults |
| **Themes** | `/admin/themes` | Enabled themes; activate and preview (`theme.activate`) |
| **Audit** | `/admin/audit` | Read-only administrative event history (`audit.view`) |

### How the areas relate

- **Content** — Pages, Posts, and Media are where editorial work happens.
- **Taxonomy** — Categories and Tags organize Posts.
- **Navigation** — Menus define configured navigation items (Primary / Footer locations).
- **Configuration** — Settings and Themes control site-wide behavior and appearance.
- **Compliance** — Audit records operational events; it is not a dashboard and does not expose secrets or raw metadata.

### Posts — Author field

When creating or editing a **Post**, the form includes a required **Author (public)** field. Enter the name displayed to visitors on the published article (for example, a staff member or department name).

This value is stored as the Post's public author label. It is **not** automatically taken from the logged-in Admin account — you enter it manually for each Post according to your editorial policy.

## Shared UI (TH-006–TH-010)

All Control Panel screens share:

- `admin/layouts/main.php` — header, sidebar, skip link, CSRF meta, asset loading
- `admin-shell.css` — layout, navigation, buttons, focus states
- `admin-content.css` — tables, forms, empty states, badges, responsive rules

Common patterns:

- Page title + short lead text on list and form screens
- `admin-table` with horizontal scroll on narrow viewports
- `admin-empty-state` when a list has no rows
- `flash_messages` partial for success, error, and validation alerts
- Status badges for editorial states (Pages/Posts); separate type badges for Media

## Permissions (unchanged)

Authorization is enforced by routes and filters, not by the views alone. Examples:

- **Themes** nav link — `theme.activate`
- **Audit** nav link — `audit.view`
- **Settings** — `site.manage`
- **Menus** — `menu.manage`

Editors and contributors see subsets of content areas per the existing AuthGroups matrix (DOC-03).

## Operational notes

- **Theme Preview** (`/admin/preview/theme/...`) is separate from the active public theme and does not change live site caching by itself.
- **Audit** has no filters, pagination, or detail view in V1 — only a recent-event list.
- **Dashboard** does not show fabricated statistics or activity feeds.

## TH-006–TH-010 completion checklist

Verified by automated tests and view inspection (TH-011). Browser visual QA was not available in the development environment.

| Task | Scope | Status |
|---|---|---|
| TH-006 | Dashboard + shared Admin shell | PASS |
| TH-007 | Pages + Posts UI | PASS |
| TH-008 | Categories + Tags + Media UI | PASS |
| TH-009 | Menus + Settings UI | PASS |
| TH-010 | Themes + Audit UI | PASS |

| Verification | Status |
|---|---|
| Admin Control Panel visual coverage (10 areas) | PASS |
| Shared shell integration | PASS |
| Navigation + permission gating | PASS |
| Authorization preservation | PASS |
| CSRF preservation | PASS |
| Responsive CSS (code review) | PASS |
| Accessibility basics (semantic HTML, labels, focus) | PASS |
| Full PHPUnit suite | PASS (697 tests / 3118 assertions) |
| Public Theme 2026 separation | PASS |
| Routes / Services / schema unchanged by TH-006–TH-010 | PASS |

### Verification method legend

| Label | Meaning |
|---|---|
| Verified locally | Inspected in workspace source and tests |
| Verified by automated tests | PHPUnit feature/unit coverage |
| Not available in current environment | Real-browser visual QA |

## Related documentation

- [FIRST-RUN.md](FIRST-RUN.md) — post-install flow including `/cp` login
- [INSTALLATION.md](INSTALLATION.md) — server setup and `cms:install`
- [CONFIGURATION.md](CONFIGURATION.md) — `.env` and throttle configuration

## Optional future work (out of scope for TH-006–TH-011)

These are not implemented in V1 and are listed only as possible follow-ups:

- Audit filters, pagination, or detail view
- Drag-and-drop menu ordering
- Dashboard analytics or activity widgets
- Theme installation/upload marketplace
- Browser-based visual regression testing
