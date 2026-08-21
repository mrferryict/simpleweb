ADR-010 — Standalone Tailwind CSS v4 Build Strategy

Status: Accepted

Date: 2026-08-21

Context

SMITE CMS menggunakan Tailwind CSS 4 sebagai sistem styling untuk frontend tema publik dan Control Panel (REQ-NFR-008). Di lingkungan produksi, website tidak boleh bergantung pada runtime browser compiler (Play CDN) karena Play CDN ditujukan khusus untuk development/prototyping, bukan untuk aset produksi yang berperforma tinggi dan stabil (DOC-08 Section 51).

Di sisi lain, server shared hosting target (Hostinger, cPanel) tidak memiliki runtime Node.js, npm, atau asset pipeline background worker. Sistem membutuhkan strategi kompilasi CSS yang terisolasi di workstation developer (WSL2 Ubuntu) dengan hasil build statis terisolasi per-tema yang siap disajikan langsung oleh web server tanpa dependensi build di server live.

Decision

Standalone Build Tooling:

Menggunakan Standalone Tailwind CLI Executable (tailwindcss standalone binary) pada workstation developer (WSL2 Ubuntu 24.04 LTS).

Server Produksi Bebas Node.js: Server hosting target tidak memerlukan dan tidak menjalankan Node.js, npm, npx, Vite, atau Webpack. Server hanya bertugas menyajikan file .css statis dari direktori publik.

Dual Build Targets & Theme Isolation:

Proses build Tailwind dipisahkan secara tegas menjadi dua target terisolasi:

Public Themes: Setiap tema memiliki entry point dan output CSS mandiri (public/themes/{theme_id}/css/app.css). Build tema dilarang mencampur styling antar tema yang berbeda.

Control Panel: Memiliki entry point dan output CSS tersendiri (public/assets/admin/css/admin.css). Control Panel bukan merupakan bagian dari tema publik.

Source Scanning Contract:

Setiap proses build Tailwind wajib mengonfigurasi pemindaian (content scanning) terhadap seluruh file PHP view, template, layout, dan partials yang relevan dengan target:

Theme build: memindai app/Views/themes/{theme_id}/, public/themes/{theme_id}/, dan helper tema terkait.

Admin build: memindai app/Views/admin/, komponen UI Control Panel, dan layout TailAdmin.

Asset Helper Separation:

Tema Publik: Menggunakan helper resmi theme_asset('css/app.css') yang me-resolve path ke /themes/{active_theme_id}/css/app.css sesuai tema yang sedang ACTIVE.

Control Panel: Menggunakan asset helper khusus admin atau absolute path /assets/admin/css/admin.css. Helper theme_asset() dilarang digunakan di antarmuka Control Panel.

Release Artifact & Git Discipline:

File CSS hasil kompilasi minifikasi (--minify) wajib di-commit ke repositori Git sebagai bagian dari release artifact resmi.

Deployment ke shared hosting menyajikan file statis tersebut secara langsung.

Production CDN Prohibition:

Production templates SHALL NOT load Tailwind through any runtime CDN/Play CDN mechanism (termasuk skrip runtime @tailwindcss/browser).

Penggunaan Play CDN hanya diizinkan untuk keperluan eksplorasi dan prototyping cepat lokal, dan kode aplikasi dilarang bergantung pada CDN tersebut agar UI dapat berfungsi.

Consequences

Positif:

Waktu muat halaman publik sangat cepat karena browser langsung menerima file CSS statis yang terminifikasi.

Menjaga lingkungan produksi tetap murni LAMP/LNMP klasik (hanya PHP + MariaDB).

Isolasi tema terjaga penuh; aktivasi tema baru tidak menyebabkan benturan styling (style leaking).

Workstation developer tidak membutuhkan manajemen dependensi node_modules yang besar berkat standalone binary.

Konsekuensi / Trade-off:

Developer wajib menjalankan perintah build Tailwind lokal sebelum melakukan commit perubahan tampilan tema atau Control Panel ke Git.

Compliance / Implementation Rules

View template yang ditujukan untuk rilis produksi dilarang memuat tag script compiler runtime Tailwind.

Script build atau Makefile/Composer script lokal wajib memisahkan build perintah untuk tema publik dan Control Panel.

CI/Deployment verification wajib memeriksa keberadaan file fisik CSS di public/themes/{theme_id}/css/app.css dan public/assets/admin/css/admin.css sebelum proses rilis dinyatakan lengkap.

References

docs/00-Project-Charter.md (Section 4)

docs/01-Product-Requirements.md (REQ-NFR-005, REQ-NFR-008)

docs/05-Theme-Template-Architecture.md (Section 24, 30)

docs/08-Technical-Architecture.md (Section 51 & 53)

docs/11-Deployment-Operations.md (Section 2, 8)