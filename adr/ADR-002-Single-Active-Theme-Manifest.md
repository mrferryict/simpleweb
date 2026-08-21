ADR-002 — Single Active Theme & Theme Manifest Architecture

Status: Accepted

Date: 2026-08-21

Context

SMITE CMS dirancang sebagai CMS dengan presentasi yang dikendalikan oleh developer (developer-owned presentation), bukan visual page builder. Admin memiliki wewenang untuk mengaktifkan tema yang tersedia dan mengisi konten dinamis sesuai skema yang telah ditentukan developer.

Sistem membutuhkan mekanisme deklarasi tema yang deterministik, penegakan satu tema aktif pada satu waktu, serta jaminan bahwa pergantian tema tidak merusak (prune) data konten yang sudah ada di database.

Decision

Single Active Theme Invariant:

A valid installed SMITE CMS SHALL maintain exactly one ACTIVE Theme.

Theme activation SHALL be atomic and SHALL NOT leave the system with zero or multiple ACTIVE Themes.

Theme Lifecycle & Authority:

Tema mendukung tiga state: DRAFT, ENABLED, dan ACTIVE.

Perubahan status DRAFT → ENABLED dikendalikan mutlak oleh deployment/konfigurasi developer.

Admin hanya dapat melihat, melakukan preview, dan mengaktifkan tema yang berstatus ENABLED.

Theme Manifest Implementation:

Setiap tema wajib menyertakan file manifest resmi berupa file PHP terstruktur: ThemeManifest.php.

ThemeManifest.php SHALL return one structured PHP array conforming to the SMITE CMS Theme Manifest contract.

Manifest mendefinisikan metadata tema (id, name, version, author), daftar templates yang tersedia, dan Content Schema untuk masing-masing template.

Format PHP array dipilih karena sederhana, native terhadap runtime PHP, mudah divalidasi oleh ContentSchemaValidator, tidak membutuhkan parser konfigurasi tambahan, dan mudah dikontrol melalui Git.

Mandatory Custom Page Template:

Setiap tema wajib menyediakan tepat satu template bernama custom-page sebagai template serbaguna default.

Non-Destructive Content Schema Switching:

Pergantian tema aktif dilarang menghapus atau memangkas (pruning) data lama di kolom content_payload (pages / posts).

Data payload yang tidak terpakai oleh tema baru tetap dipertahankan secara utuh untuk menjaga kompatibilitas jika tema sebelumnya diaktifkan kembali.

Template tema wajib menerapkan pola rendering defensif (menggunakan fallback helper dan pemeriksaan key) agar tidak menimbulkan error saat membaca payload dari skema yang berbeda.

Theme Preview Security & Headers:

Preview tema yang berstatus ENABLED hanya dapat diakses oleh Admin yang terautentikasi.

Request preview wajib melewati (bypass) application cache, tidak menyimpan cache publik baru, dan menyertakan response header:

Cache-Control: no-store, no-cache, must-revalidate

Pragma: no-cache

X-Robots-Tag: noindex, nofollow, noarchive

Consequences

Positif:

Kontrak antara developer dan Admin terisolasi tegas; Admin tidak dapat merusak arsitektur HTML/layout.

Deklarasi via PHP array murni (ThemeManifest.php) cepat, mudah dipelihara, dan bebas dependensi eksternal.

Data konten aman dari kehilangan saat Admin berganti-ganti tema.

Konsekuensi / Trade-off:

Developer bertanggung jawab menjaga kompatibilitas template custom-page dan Content Schema pada setiap tema yang dibuat berstatus ENABLED.

Compliance / Implementation Rules

Authority Boundary: Developer/deployment mengontrol transisi DRAFT → ENABLED; Admin mengontrol transisi ENABLED → ACTIVE. Keduanya wajib lolos validasi manifest dan pengecekan keberadaan template custom-page.

Atomic Activation Flow:

Mulai database transaction.

Validasi kelayakan tema kandidat (ENABLED, manifest valid, template lengkap).

Ubah status tema kandidat menjadi ACTIVE.

Ubah status tema aktif sebelumnya menjadi non-aktif.

Commit database transaction.

Bersihkan/invalidasi cache presentasi publik secara menyeluruh (post-commit invalidation).

References

docs/00-Project-Charter.md (Section 3.2, 3.3, 6)

docs/01-Product-Requirements.md (REQ-THEME-001 s/d REQ-THEME-009)

docs/02-Domain-Model.md (Section 16, 17, 30)

docs/05-Theme-Template-Architecture.md

docs/08-Technical-Architecture.md (Section 20 & 53)