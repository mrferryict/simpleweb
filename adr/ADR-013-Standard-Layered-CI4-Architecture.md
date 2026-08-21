ADR-013 — Standard Layered CodeIgniter 4 Directory Architecture

Status: Accepted

Date: 2026-08-21

Context

Dalam membangun aplikasi modular monolith menggunakan CodeIgniter 4.7+, terdapat dua pendekatan struktur direktori yang umum:

Nested Modular / HMVC (app/Modules/Content/...): Mengelompokkan Controller, Service, Model, Entity, dan View ke dalam folder modul yang terisolasi secara horizontal.

Standard Layered Directory (app/Controllers/, app/Services/, app/Models/, dll.): Menggunakan struktur direktori bawaan CodeIgniter 4 dan mengelompokkan domain fungsional menggunakan sub-namespace logis secara vertikal.

Untuk proyek yang dikelola oleh solo developer, struktur nested modular yang terlalu dalam berisiko menimbulkan over-engineering, memperumit konfigurasi autoloader PSR-4 di Config/Autoload.php, menyulitkan pelacakan alur data, serta menambah friksi pemeliharaan. Selain itu, sistem memerlukan batasan fisik yang tegas agar file template PHP tema publik tidak pernah berada di dalam web root publik.

Decision

Standard Layered Structure Baseline:

Menggunakan struktur direktori layer standar CodeIgniter 4 sebagai arsitektur resmi aplikasi:

app/
├── Config/
├── Controllers/
│   ├── Admin/       (PageController, PostController, MediaController, SettingController)
│   ├── Auth/        (AuthController, RecoveryController)
│   └── Site/        (HomeController, ContentController, DownloadController)
│
├── Services/
│   ├── Content/     (PageService, PostService, ContentSchemaValidator)
│   ├── Publishing/  (PublishingService, SchedulingService)
│   ├── Media/       (MediaService, ImageProcessor, DependencyChecker)
│   ├── Security/    (PiiCipherService, AuditService)
│   └── Localization/(LocaleResolver, SlugService, SeoService)
│
├── Models/          (PageModel, PostModel, MediaModel, RedirectModel, SettingModel)
├── Entities/        (Page, Post, MediaAsset, Redirect, AuditLog)
├── DTO/             (PageDTO, PostDTO, MediaUploadDTO)
├── Filters/         (LocaleFilter, AdminAuthFilter, HtmxSessionFilter)
├── Commands/        (ScheduledContentCommand, InstallCommand, IntegrityCheckCommand)
├── Helpers/         (theme_helper, asset_helper)
│
└── Views/
    ├── admin/       (Layouts, partials, dan komponen TailAdmin)
    └── themes/
        ├── default/ (Layouts, templates, partials, custom-page)
        └── ...

Strict Segregation of Theme Views & Static Assets:

PHP Theme Templates (app/Views/themes/{theme_id}/): Seluruh file PHP view, layout, dan partials tema publik wajib berada di dalam direktori app/Views/themes/ (di luar root web publik).

Developer Ownership Invariant: Direktori app/Views/themes/{theme_id}/ merupakan aset milik developer (developer-owned) dan dilarang dapat diedit oleh Admin melalui antarmuka CMS.

Static Theme Assets (public/themes/{theme_id}/): File statis publik (CSS hasil compile, JavaScript, gambar statis tema, fonts) ditempatkan di direktori web root public/themes/{theme_id}/.

Explicit Rejection of Nested HMVC (app/Modules/):

SMITE CMS V1 secara eksplisit menolak struktur nested app/Modules/.

Pemisahan tanggung jawab bisnis dilakukan murni melalui sub-namespace Service Layer, bukan pemisahan fisik folder MVC terpisah.

Uni-directional Layer Dependency (Controller → Service → Model):

Controllers: Bertindak sebagai thin orchestrators. Bertugas menerima HTTP input, memvalidasi form/CSRF dasar, mengonstruksi DTO, memanggil Service, dan mengembalikan View fragment (HTMX) atau redirect response. Controller dilarang memanggil Model secara langsung untuk operasi bisnis.

Services: Pemilik utama business logic dan transaction boundaries. Bertanggung jawab atas koordinasi model, penegakan state machine, validasi skema konten, pemanggilan kriptografi, dan pemicu invalidasi cache.

Models: Berfokus pada interaksi Query Builder dan operasi tabel MariaDB. Model dilarang memanggil Service lain (no circular dependency).

Entity & DTO Roles:

DTOs: Bertanggung jawab sebagai objek transfer data yang diketik secara ketat (strictly-typed) dan immutable di mana memungkinkan.

Entities: Representasi data domain/aplikasi yang typed, dengan mutasi data dikendalikan secara tertib melalui Service Layer (tidak dipaksakan immutable pada level objek CI4 Entity).

Consequences

Positif:

Memanfaatkan autoloader native CI4 secara optimal tanpa konfigurasi namespace kustom yang rapuh.

Navigasi kode cepat dan intuitif bagi solo developer.

File template PHP tema publik terlindungi dari eksekusi langsung karena berada di luar direktori public/.

Batasan tanggung jawab antar layer (Controller → Service → Model) sangat tegas dan mempermudah pengujian modular.

Konsekuensi / Trade-off:

Penambahan fungsionalitas baru memerlukan pembuatan file di beberapa direktori layer yang berbeda (Controllers/Admin/, Services/Content/, Models/), namun konsistensi alur data tetap terjaga.

Compliance / Implementation Rules

Dilarang membuat direktori app/Modules/ atau mendaftarkan namespace modular kustom di Config/Autoload.php.

Controller wajib mendelegasikan seluruh alur mutasi dan pembacaan bisnis ke Service Layer terkait.

Template PHP tema publik dilarang ditempatkan di dalam folder public/themes/.

Model dilarang memanggil Service lain.

Admin tidak dapat mengubah file PHP Theme template melalui CMS.

References

docs/00-Project-Charter.md (Section 4 & 5)

docs/05-Theme-Template-Architecture.md (Section 3, 24, 30)

docs/08-Technical-Architecture.md (Section 4, 6, 7, 8, 9, 10, 11)

docs/09-Implementation-Blueprint.md (Section 14)

docs/adr/ADR-002-Single-Active-Theme-Manifest.md

docs/adr/ADR-010-Standalone-Tailwind-CLI-Build.md