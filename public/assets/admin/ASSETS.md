# Control Panel Vendored Frontend Assets

**Status:** Baseline contract (Phase 3 / Task 3.5A + 3.5 + 3.6)  
**Authority:** ADR-010 (delivery / hosting), ADR-014 (Alpine + Quill roles), DOC-03 §11 (HTMX CSRF)  
**Last updated:** 2026-08-22

This directory holds **pinned, Git-committed static assets** for the SMITE CMS Control Panel.

Production and development use the **same** files. There is **no** runtime CDN dependency and **no** Node.js/npm/Vite requirement on production hosting.

Application PHP / Views / Services must not be placed under `public/`.

## Delivery mechanism

| Rule | Decision |
|------|----------|
| Mechanism | Vendored static files under `public/assets/admin/` |
| CDN (production) | Forbidden for Alpine.js, Quill JS/CSS, and HTMX |
| Node.js on production | Not required / not used |
| package.json / Vite / Webpack | Not introduced for Control Panel boot |
| Update policy | Replace pinned files from official sources below, update this manifest, commit |

## Pinned versions (current contract)

| Asset | Version | License | Official source | Destination | Build |
|-------|---------|---------|-----------------|-------------|-------|
| Alpine.js | **3.16.2** | MIT | npm `alpinejs@3.16.2` → `dist/cdn.min.js` | `public/assets/admin/js/alpine.min.js` | minified CDN build |
| Quill | **2.0.3** | BSD-3-Clause | npm `quill@2.0.3` → `dist/quill.js` | `public/assets/admin/js/quill.min.js` | browser bundle (package has no separate `.min.js`) |
| Quill Snow CSS | **2.0.3** | BSD-3-Clause | npm `quill@2.0.3` → `dist/quill.snow.css` | `public/assets/admin/css/quill.snow.css` | theme CSS |
| HTMX | **2.0.10** | 0BSD | npm `htmx.org@2.0.10` → `dist/htmx.min.js` | `public/assets/admin/js/htmx.min.js` | minified browser build |

### Application bridge (project-owned, not third-party)

| Asset | Destination | Notes |
|-------|-------------|-------|
| `quillEditor` Alpine bridge | `public/assets/admin/js/quill-editor.js` | ADR-014 bridge; not a third-party package |
| Quill editor layout helpers | `public/assets/admin/css/quill-editor.css` | fallback visibility helpers only |
| HTMX CSRF sync | `public/assets/admin/js/htmx-csrf.js` | DOC-03 §11 / `.cursorrules` §4.5; reads meta token + `X-CSRF-TOKEN` response header |

Major-version constraints from project docs:

- Alpine.js **3.x** (`.cursorrules` / CONTEXT.md)
- Quill **2.x** (ADR-014)
- HTMX **2.x** (`.cursorrules` / DOC-03)

## SHA-256 checksums (acquired 2026-08-22)

```
c13d85e590cf4c91959aeaaef1ba755344e911d55c5ec56f472ed2894e2b4684  js/alpine.min.js
f6157c72ac9b3f51cdead426335688a027b12405d9d6a4daadd38a676b2d7ff2  js/quill.min.js
1c7948cd13aa92fac6390319bc1e5e461823da171519d3a768db56164f871636  css/quill.snow.css
71ea67185bfa8c98c39d31717c6fce5d852370fcdfd129db4543774d3145c0de  js/htmx.min.js
```

## Provenance expectations

Before committing binary/text vendor files:

1. Obtain artifacts only from the official npm package dists listed above (or the matching upstream GitHub release asset for the same version).
2. Confirm license metadata matches MIT (Alpine), BSD-3-Clause (Quill), and 0BSD (HTMX).
3. Update SHA-256 checksums in this file when binaries change.
4. Do not copy files from unknown mirrors or unversioned CDN URLs without pinning.

## Related paths

- Tailwind Control Panel CSS (separate ADR-010 build): `public/assets/admin/css/admin.css`
- Public theme static assets: `public/themes/{theme_id}/` (not used for Alpine/Quill/HTMX)
