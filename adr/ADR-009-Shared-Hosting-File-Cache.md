ADR-009 — Shared-Hosting-Compatible File Cache & Targeted Invalidation

Status: Accepted

Date: 2026-08-21

Context

SMITE CMS membutuhkan mekanisme caching untuk mempercepat waktu respon halaman publik (homepage, halaman konten, daftar berita, menu navigasi, dan pengaturan situs). Karena target operasional utama adalah shared hosting (cPanel/Hostinger) dan VPS mandiri tanpa dependensi daemon tambahan, penggunaan Redis atau Memcached dilarang untuk V1 (REQ-CACHE-003 & REQ-NFR-005).

Sistem membutuhkan driver cache bawaan yang stabil di sistem berkas (filesystem), konvensi penamaan key yang menyertakan konteks tema aktif, strategi invalidasi terarah (targeted invalidation) dengan dependensi fan-out yang terukur pasca-commit database, pemanfaatan kapabilitas native CI4 4.7+ (deleteMatching), serta jaminan isolasi penuh pada fitur Theme Preview.

Decision

Cache Driver & Storage:

Menggunakan driver bawaan CodeIgniter 4: \CodeIgniter\Cache\Handlers\FileHandler.

File cache disimpan secara lokal di dalam direktori writable/cache/.

Menolak dependensi Redis, Memcached, atau daemon memori eksternal untuk V1.

Deterministic & Theme-Aware Key Namespacing:

Cache key distandarisasi menggunakan format terstruktur berbasis domain dan tema:

site:settings:{locale}: Konfigurasi situs untuk bahasa terkait.

nav:menu:{theme_id}:{location}: Struktur navigasi publik.

content:page:{theme_id}:{locale}:{slug}: Data halaman publik hasil resolusi penuh (public-resolved renderable data).

content:post:{theme_id}:{locale}:{slug}: Data artikel berita publik hasil resolusi penuh.

content:category:{theme_id}:{locale}:{slug}:page_{page}: Daftar artikel per kategori terpaginasi.

theme:active: Metadata dan manifest tema yang sedang aktif.

Public Resolution Caching Scope:

Yang disimpan dalam cache adalah objek data siap-render setelah melalui DB query, localization fallback, resolusi URL Media, resolusi SEO, dan template schema mapping.

Cache bukan authoritative storage dan tidak menggantikan database.

Targeted Invalidation & Dependency Fan-out:

Invalidation wajib dipicu pasca-commit transaksi database (post-commit invalidation) menggunakan abstraction CacheService::deleteMatching('prefix:*') yang memanfaatkan kapabilitas CacheInterface::deleteMatching() CI4 4.7+.

Dependency Fan-out Rules:

Update Post: Menghapus cache detail artikel, seluruh cache listing kategori terkait, cache homepage/listing agregasi yang dipengaruhi, dan cache sitemap jika sitemap dicache.

Update Page: Menghapus cache detail halaman dan cache homepage jika halaman tersebut adalah homepage atau mempengaruhi aggregate content.

Update Site Settings / Menu: Menghapus namespace settings/menu yang terdampak.

Theme Activation: Menghapus seluruh namespace presentasi publik (content:*, nav:*, theme:active).

Prohibition: Dilarang memanggil $cache->clean() untuk mutasi konten editorial rutin. Method tersebut hanya diizinkan untuk deployment, reset manual, atau prosedur recovery/maintenance.

TTL as Safety Net, Not Correctness Driver:

Invalidasi eksplisit adalah correctness mechanism utama.

TTL (Time-To-Live) hanya menjadi safety net jika invalidasi gagal.

Nilai TTL default dikonfigurasi dalam rentang yang sesuai hasil benchmark V1, misalnya 1–24 jam.

Theme Preview Cache Isolation:

Request Theme Preview (/admin/preview/...) wajib:

melewati seluruh pembacaan dan penulisan cache aplikasi;

menyertakan:

Cache-Control: no-store, no-cache, must-revalidate

Pragma: no-cache

X-Robots-Tag: noindex, nofollow, noarchive

tidak mencemari atau menimpa cache halaman publik.

Filesystem Safety & Graceful Fallback:

Jika file cache korup atau tidak dapat dibaca, sistem harus mengambil data segar dari database dan meregenerasi cache tanpa fatal exception ke pengunjung publik.

Cache corruption must never make public content unavailable when the underlying database data is healthy.

Consequences

Positif:

Kompatibel dengan shared hosting tanpa server background process.

Response time cepat karena public resolution pipeline lengkap hanya diproses saat cache miss.

Konteks theme_id pada key mencegah rendering stale saat terjadi peralihan tema.

Invalidation terarah menjaga efisiensi I/O disk dan konsistensi data di seluruh titik tampilan publik.

Konsekuensi / Trade-off:

deleteMatching() pada File Cache memerlukan operasi filesystem pada namespace terkait.

Karena V1 menggunakan file cache, performa I/O dapat lebih rendah dibanding cache in-memory seperti Redis.

Compliance / Implementation Rules

Service Layer (PostService, PageService, SettingService, ThemeService) wajib memicu invalidasi cache relevan hanya setelah database transaction berhasil commit.

Cache key untuk public-rendered content wajib mempertahankan konteks theme_id.

Dilarang menambahkan package adapter cache eksternal.

Unit/Feature test wajib memverifikasi fan-out invalidation untuk perubahan Post/Page.

Theme Preview tidak boleh membaca atau menulis cache public/application.

$cache->clean() bukan bagian dari jalur editorial normal.

References

docs/01-Product-Requirements.md (REQ-CACHE-001 s/d REQ-CACHE-004, REQ-NFR-005)

docs/04-Content-Publishing.md (Section 30)

docs/05-Theme-Template-Architecture.md (Section 28)

docs/08-Technical-Architecture.md (Section 39, 40, 41)

docs/adr/ADR-002-Single-Active-Theme-Manifest.md