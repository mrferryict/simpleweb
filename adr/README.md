Architecture Decision Records (ADR) Index

Last Updated: 2026-08-21

Directory: /docs/adr/

Total Records: 14 (All Accepted)

Dokumen ini merupakan indeks resmi seluruh keputusan arsitektural (Architecture Decision Records) untuk SMITE CMS V1. Setiap ADR mendefinisikan keputusan teknis yang bersifat mengikat dan menjadi pelengkap dari dokumen spesifikasi inti (DOC-00 s/d DOC-12).

Master ADR Registry

ADR ID

Judul Keputusan & File

Status

Domain

Referensi Dokumen Inti

Hubungan / Keterkaitan ADR

ADR-001

ADR-001-Username-Password-Shield-Auth.md

Accepted

Identity & Auth

DOC-01, DOC-03, DOC-08

ADR-008 (PII Encryption)

ADR-002

ADR-002-Single-Active-Theme-Manifest.md

Accepted

Theme System

DOC-02, DOC-05, DOC-08

ADR-004, ADR-009, ADR-010, ADR-013

ADR-003

ADR-003-Bilingual-Routing-Strategy-B.md

Accepted

Routing & SEO

DOC-01, DOC-02, DOC-07, DOC-08

ADR-009 (Locale Caching), ADR-013

ADR-004

ADR-004-Native-Content-Schema-Validator.md

Accepted

Content Core

DOC-01, DOC-02, DOC-05, DOC-08

ADR-002 (Manifest Contract), ADR-005, ADR-014

ADR-005

ADR-005-Revision-Autosave-Concurrency.md

Accepted

Editorial & OCC

DOC-01, DOC-02, DOC-04, DOC-08

ADR-004, ADR-006 (OCC in Scheduling), ADR-014

ADR-006

ADR-006-ScheduledAction-Idempotent-Execution.md

Accepted

Scheduling

DOC-01, DOC-02, DOC-04, DOC-08

ADR-005 (OCC Integration), ADR-009, ADR-011

ADR-007

ADR-007-Media-Storage-Download-Token.md

Accepted

Media & Storage

DOC-01, DOC-02, DOC-06, DOC-08

ADR-004 (Media/Doc Fields), ADR-011

ADR-008

ADR-008-Sodium-PII-Key-Separation.md

Accepted

Security & Crypto

DOC-01, DOC-03, DOC-08

ADR-001 (Shield User Email)

ADR-009

ADR-009-Shared-Hosting-File-Cache.md

Accepted

Performance

DOC-01, DOC-04, DOC-05, DOC-08

ADR-002, ADR-003, ADR-006, ADR-011

ADR-010

ADR-010-Standalone-Tailwind-CLI-Build.md

Accepted

Frontend / Styling

DOC-00, DOC-05, DOC-08, DOC-11

ADR-002, ADR-013 (Asset Segregation)

ADR-011

ADR-011-No-Queue-Shared-Hosting-Baseline.md

Accepted

Infrastructure

DOC-00, DOC-01, DOC-06, DOC-08, DOC-11

ADR-006, ADR-007, ADR-009

ADR-012

ADR-012-Dedicated-Branch-Upgrade-Policy.md

Accepted

Maintenance

DOC-00, DOC-08, DOC-10, DOC-11, DOC-12

Semua ADR (Tata kelola rilis & upgrade)

ADR-013

ADR-013-Standard-Layered-CI4-Architecture.md

Accepted

Architecture

DOC-00, DOC-05, DOC-08, DOC-09

ADR-002, ADR-004, ADR-007, ADR-010

ADR-014

ADR-014-Quill-Alpine-RichText-Integration.md

Accepted

UI & Security

DOC-01, DOC-03, DOC-08, DOC-10

ADR-004 (Validator), ADR-005, ADR-013

Pemetaan Berdasarkan Kluster Domain

1. Security, Identity & PII Boundary

ADR-001: Login tunggal username + password via Shield Session Authenticator di /cp.

ADR-008: Enkripsi email_ciphertext (Sodium XSalsa20-Poly1305) & email_lookup_hash (HMAC-SHA256) dengan key terpisah di .env.

ADR-014: Sanitasi HTML server-side allowlist (RichTextSanitizer) sebagai batas keamanan mutlak sebelum persistensi.

2. Content Lifecycle, Concurrency & Presentation Contract

ADR-002: Format ThemeManifest.php murni array PHP, single active theme, template wajib custom-page, dan preservasi payload legacy non-destruktif.

ADR-004: Engine validasi skema 7 tipe skalar + Repeatable Blocks native PHP 8.5 tanpa dependensi library JSON Schema eksternal.

ADR-005: Snapshot revisi JSON lengkap, isolasi auto-save (is_autosave = 1), OCC lock_version (HTTP 409), dan proteksi konten PUBLISHED saat drafting.

ADR-006: Transaksi dua fase (lease_until), idempoten, penanganan late catch-up, dan audit otomatis pada scheduled_actions.

3. Assets, URL Namespace & SEO Engine

ADR-003: Routing dwibahasa Strategy B (Primary di root /slug, Secondary di /en/slug), resolusi deterministik fallback canonical, dan hreflang kondisional.

ADR-007: Pemisahan fisik gambar di public/uploads/images/ (master size via ext-gd), dokumen privat di writable/uploads/documents/ via 32-char crypto token stream, dan dependency checking sebelum penghapusan.

4. Infrastructure, Performance & Project Governance

ADR-009: Caching filesystem FileHandler native CI4 4.7+ dengan targeted cache invalidation (deleteMatching) dan isolasi total pada Theme Preview.

ADR-010: Kompilasi CSS statis via standalone Tailwind CLI per tema/admin, bebas Node.js di server produksi.

ADR-011: Batasan mutlak ketiadaan persistent queue/worker/Redis/Docker di shared hosting; synchronous workload dibatasi secara terukur.

ADR-012: Kebijakan branch terisolasi (upgrade/* dan security/*), toleransi zero deprecation internal, dan verifikasi non-destruktif via cms:integrity-check.

ADR-013: Struktur layered standar CI4 (Controller → Service → Model), penolakan HMVC app/Modules/, dan penempatan PHP views tema di luar web root publik (app/Views/themes/).

Governance

ADR berstatus Accepted bersifat mengikat terhadap implementasi SMITE CMS V1.

Perubahan terhadap keputusan yang telah Accepted harus:

melalui review terhadap dokumen dan ADR yang terdampak;

menghasilkan ADR revisi atau ADR pengganti bila keputusan arsitektural berubah;

memperbarui hubungan/traceability pada indeks ini.

ADR tidak boleh diubah secara diam-diam hanya untuk menyesuaikan implementation detail. Jika keputusan dasarnya berubah, perubahan tersebut harus terlihat dalam history ADR.