# SMITE CMS — Theme Development Guide

**Audience:** Maintainer / developer  
**Companion document:** [05-Theme-Template-Architecture.md](05-Theme-Template-Architecture.md) (full architecture — read that first for principles and constraints)

This is a practical quick-start for creating a new Theme (for example **Theme 2027**) based on the existing **Theme 2026** reference implementation.

---

## 1. Theme locations

A Theme spans three directories keyed by the same **theme id** (for example `2026`):

| Path | Purpose |
|---|---|
| `app/Views/themes/{theme-id}/` | PHP templates, partials, and `ThemeManifest.php` |
| `resources/themes/{theme-id}/` | CSS **source** (Tailwind input) |
| `public/themes/{theme-id}/` | Compiled static assets served to browsers |

Theme 2026 example:

```text
app/Views/themes/2026/
├── ThemeManifest.php
├── templates/
│   ├── home.php
│   ├── custom-page.php
│   └── custom-post.php
└── _partials/
    ├── head.php
    ├── seo_head.php
    ├── site_header.php
    ├── site_nav.php
    ├── site_footer.php
    └── locale_notice.php

resources/themes/2026/css/app.input.css

public/themes/2026/css/app.css
```

---

## 2. Key files and their roles

### `ThemeManifest.php`

Developer-owned metadata and **content field schema** for each template. The CMS reads this file to:

- identify the Theme (`id`, `name`, `version`, `author`);
- expose template choices and field definitions in the Control Panel;
- validate Page/Post content against declared field types.

The manifest `id` must match the directory name (`2026`, `2027`, etc.).

### `templates/home.php`

Renders the public homepage (`GET /`) when this Theme is active and no dedicated homepage Page overrides it.

### `templates/custom-page.php`

Renders a **Page** assigned the `custom-page` template.

### `templates/custom-post.php`

Renders a **Post** assigned the `custom-post` template.

### `_partials/`

Reusable layout fragments (header, footer, navigation, `<head>`, SEO tags). Templates include partials — they are not routed directly.

### CSS source vs compiled CSS

| Layer | Location | Role |
|---|---|---|
| Source | `resources/themes/{theme-id}/css/app.input.css` | Tailwind v4 input; edited during development |
| Compiled | `public/themes/{theme-id}/css/app.css` | Static file served in production; commit after build |

PHP templates reference the compiled public asset path (for example via the Theme asset helper documented in architecture docs). Production hosting does **not** require Node.js — the compiled CSS is the release artifact.

---

## 3. Relationship summary

```text
ThemeManifest.php     →  defines templates + CMS field schema
templates/*.php       →  PHP presentation for each template type
_partials/            →  shared layout fragments
app.input.css         →  Tailwind source (@source scans app/Views/themes/{id})
app.css (public)      →  compiled CSS committed for deployment
Config\Theme          →  developer registry of ENABLED theme ids (deploy-time)
Settings (Admin)      →  persisted ACTIVE theme after activation in /admin/themes
```

**Discovery:** Themes are discovered from `app/Views/themes/{theme-id}/` when a valid `ThemeManifest.php` exists.

**Enabled:** A discovered Theme is only **ENABLED** when its id appears in `Config\Theme::$enabledThemeIds` (developer deploy step).

**Active:** The live site uses the Theme marked ACTIVE in Settings (set via **Themes** in the Control Panel after preview/verification).

---

## 4. Workflow — create Theme 2027 from Theme 2026

### Step 1 — Copy Theme 2026 structure

Copy all three directories and rename `2026` → `2027`:

```text
app/Views/themes/2026/      →  app/Views/themes/2027/
resources/themes/2026/      →  resources/themes/2027/
public/themes/2026/         →  public/themes/2027/
```

### Step 2 — Assign a new theme id

Use a stable, lowercase identifier (for example `2027`). It must be consistent across all three paths and inside `ThemeManifest.php`.

### Step 3 — Update `ThemeManifest.php`

- Set `'id' => '2027'`
- Update `name`, `version`, `author` as appropriate
- Adjust `templates` and field schemas only when the new Theme requires different CMS fields

### Step 4 — Create or update templates

Edit `templates/home.php`, `custom-page.php`, and `custom-post.php` under `app/Views/themes/2027/`. Keep template keys aligned with manifest `templates` keys.

### Step 5 — Create or update partials

Adjust `_partials/` for header, footer, navigation, and head markup.

### Step 6 — Create or update CSS source

Edit `resources/themes/2027/css/app.input.css`. Ensure the `@source` directive points at your new view directory:

```css
@source "../../../app/Views/themes/2027";
```

### Step 7 — Build CSS (developer workstation)

On a machine with the Tailwind standalone CLI available, from the project root:

```bash
tailwindcss -i resources/themes/2027/css/app.input.css \
  -o public/themes/2027/css/app.css --minify
```

Commit the compiled `public/themes/2027/css/app.css`. If the CLI is unavailable, you may edit the compiled CSS directly (as noted in Theme 2026's input file header) — prefer rebuilding when possible.

### Step 8 — Register as ENABLED (developer deploy)

Add `'2027'` to `Config\Theme::$enabledThemeIds` and deploy. Without this step the Theme may be discovered but not activatable.

### Step 9 — Verify discovery

After deployment, open **Themes** (`/admin/themes`). The new Theme should appear with lifecycle state **ENABLED** (not merely discovered as DRAFT).

### Step 10 — Preview

Use the Control Panel preview action for Theme 2027 before activation. Confirm homepage, Page, and Post rendering.

### Step 11 — Activate only after verification

Activate Theme 2027 from `/admin/themes` only after preview and smoke tests pass. Activation updates persisted Settings; rollback is available by re-activating Theme 2026.

---

## 5. Verification checklist

- [ ] Manifest `id` matches directory name
- [ ] All three theme paths exist (`app/Views`, `resources`, `public`)
- [ ] Compiled `public/themes/{id}/css/app.css` is present and committed
- [ ] Theme id added to `Config\Theme::$enabledThemeIds`
- [ ] `/admin/themes` lists the Theme as ENABLED
- [ ] Preview renders `/`, a sample Page, and a sample Post
- [ ] Activation succeeds; public site reflects new Theme

---

## 6. What clients should not do

Clients select and activate **existing** Themes in the Control Panel. They do not create Themes, edit `ThemeManifest.php`, or modify files under `app/Views/themes/`.

Custom visual design for a client is **developer/maintainer work** — typically fork Theme 2026 as described above.

---

## 7. Further reading

- [05-Theme-Template-Architecture.md](05-Theme-Template-Architecture.md) — full THEME-* rules, field types, preview/activation
- [08-Technical-Architecture.md](08-Technical-Architecture.md) — ThemeService and asset pipeline overview
- [DEVELOPER-CLIENT-DEPLOYMENT.md](DEVELOPER-CLIENT-DEPLOYMENT.md) — client onboarding (Theme 2026 is the default deployment baseline)
