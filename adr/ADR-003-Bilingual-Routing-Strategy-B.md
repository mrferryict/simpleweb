ADR-003 — Bilingual URL Routing Strategy B & Multilingual SEO

Status: Accepted

Date: 2026-08-21

Context

SMITE CMS mendukung arsitektur dwibahasa (Primary Language mandatory, Secondary Language optional). Sistem membutuhkan strategi penamaan URL publik yang ramah SEO, tidak mengotori domain utama dengan prefix bahasa default (/id/), mendukung lokalisasi slug, menjamin keunikan URL global, serta menangani fallback content secara deterministik tanpa menimbulkan ambiguitas dokumen duplikat bagi mesin pencari.

Decision

URL Prefix Strategy (Strategy B):

Primary Language: Menggunakan root URL tanpa prefix bahasa (misal: /tentang-kami, /berita/prestasi-siswa).

Secondary Language: Menggunakan prefix kode bahasa sekunder (misal: /en/about-us, /en/news/student-achievement).

Global URL Namespace & Protection:

Seluruh URL publik berbagi satu namespace keunikan global yang terdiri atas:

Current Public Paths: URL aktif milik Page, Post, dan Category.

Reserved Historical Redirect Paths: URL sumber redirect lama yang masih aktif.

Reserved System Paths: Rute internal sistem (/cp, /admin, /sitemap.xml, /robots.txt, /download) serta semua kode bahasa aktif (id, en).

Validasi pembuatan/perubahan slug baru wajib menolak tabrakan terhadap ketiga kategori di atas.

Localized Slugs & Deterministic Fallback:

Entitas translasi menyimpan slug terlokalisasi (page_translations.slug, post_translations.slug).

Jika pengunjung mengakses URL bahasa sekunder yang translasi fisiknya belum ada:

Path fallback diturunkan dari rute publik bahasa utama (misal: /en/tentang-kami).

Resolver mencari resource bahasa utama yang bersesuaian dan merender konten bahasa utama (Primary Language fallback).

Tag <link rel="canonical"> wajib mengarah ke URL bahasa utama (misal: https://example.com/tentang-kami).

Fallback URL bukan merupakan translasi baru dan tidak membuat Translation record secara otomatis.

Strict Multilingual SEO & hreflang Policy:

Tag hreflang hanya dirender jika translasi bahasa tersebut benar-benar ada, berstatus PUBLISHED, dan memiliki URL publik aktif.

Tag <link rel="alternate" hreflang="x-default"> wajib selalu mengarah ke URL bahasa utama.

Translasi berstatus DRAFT, UNPUBLISHED, ARCHIVED, TRASH, atau berstatus fallback dilarang memancarkan tag hreflang.

Fallback-only Secondary URL tidak diperlakukan sebagai dokumen terjemahan independen.

Atomic & Chain-Flattened Redirects:

Perubahan slug pada konten PUBLISHED secara otomatis dan atomik mencatat record HTTP 301 di url_redirects.

URL target redirect wajib selalu menunjuk langsung ke URL publik final saat ini (flattened destination), bukan merujuk ke record redirect historis lainnya.

URL lama berstatus reserved selama entri redirect aktif.

Redirect localized wajib mempertahankan locale prefix yang sesuai.

Separation of Concerns in Routing:

LocaleFilter hanya bertugas mendeteksi prefix bahasa pada URL dan mengatur konteks locale aplikasi.

LocaleFilter dilarang melakukan pengecekan eksistensi konten atau resolusi resource.

Resolusi Page/Post/Category menjadi tanggung jawab URL/Content Resolver.

Sitemap Generation Rules:

Endpoint /sitemap.xml hanya mendaftarkan URL publik dari entitas translasi yang nyata dan berstatus PUBLISHED.

URL bahasa sekunder yang hanya berstatus fallback dilarang didaftarkan sebagai entri sitemap independen.

Setiap localized URL yang masuk sitemap harus merupakan current public URL yang benar-benar dapat dirender secara publik.

Consequences

Positif:

URL bahasa utama bersih, natural, dan teroptimasi untuk audiens lokal.

Resolusi fallback bersifat deterministik tanpa membuat Translation record palsu.

Redirect chain dieliminasi otomatis sejak penulisan ke database.

Routing locale, resource resolution, dan SEO rendering memiliki separation of concerns yang jelas.

Konsekuensi / Trade-off:

URL Resolver membutuhkan logika resolusi bertingkat (cek translasi sekunder → fallback ke resource primer).

Sistem harus menjaga global URL namespace lintas current public paths, historical redirects, dan reserved system paths.

Compliance / Implementation Rules

SlugService wajib memvalidasi ketiadaan tabrakan URL terhadap seluruh global URL namespace dalam satu transaksi database.

LocaleFilter tidak boleh melakukan content/resource lookup.

View/SEO renderer wajib memverifikasi status publikasi translasi sebelum menyuntikkan tag hreflang.

Generator sitemap wajib mengabaikan resource sekunder yang tidak memiliki baris translasi PUBLISHED.

Fallback-only Secondary URL tidak boleh menjadi canonical URL dan tidak boleh menjadi sitemap entry independen.

References

docs/01-Product-Requirements.md (REQ-LOC-001 s/d REQ-LOC-006, REQ-SEO-001 s/d REQ-SEO-007)

docs/02-Domain-Model.md (Section 21, 22, 23)

docs/07-Localization-URL-SEO.md

docs/08-Technical-Architecture.md (Section 48 & 49)