ADR-004 — Native PHP Content Schema Validation Engine

Status: Accepted

Date: 2026-08-21

Context

SMITE CMS menyimpan data dinamis halaman dan artikel dalam kolom JSON terstruktur (content_payload). Untuk menjaga integritas data tanpa bergantung pada pustaka eksternal pihak ketiga (seperti justinrainbow/json-schema), sistem membutuhkan engine validasi berbasis PHP 8.5 native yang membaca kontrak skema dari ThemeManifest.php.

Engine ini harus mampu memvalidasi 7 tipe data skalar, struktur bounded Repeatable Blocks, memastikan field wajib (required) terpenuhi, serta menolak data submitted baru yang tidak sah tanpa merusak data warisan (legacy fields) saat terjadi pergantian tema.

Decision

Validation Engine Implementation:

Menggunakan class native PHP 8.5: App\Services\Content\ContentSchemaValidator.

Menolak penambahan library eksternal JSON Schema validator untuk menjaga dependensi tetap minimal (zero third-party dependency).

Supported Scalar Field Types & Rules:

TEXT: String satu baris, otomatis di-trim, validasi max_length.

TEXTAREA: String multi-baris polos tanpa formatting HTML.

RICH_TEXT: String HTML terformat yang lolos sanitasi server-side allowlist.

IMAGE: Integer media_id yang valid pada media_assets, bertipe IMAGE, berstatus ACTIVE, dan sesuai dengan profil gambar tema aktif. Validator dilarang melakukan image processing/resizing.

YOUTUBE_URL: String URL YouTube valid yang dinormalisasi menjadi video ID aman.

URL: String valid dengan protokol http:// atau https:// (menolak skema javascript:, data:).

DOCUMENT: Integer media_id dari media_assets bertipe DOCUMENT dan berstatus ACTIVE. Kebijakan otorisasi unduhan publik tetap dikelola oleh DocumentService / PublishingService.

Repeatable Block Bounds & Validation:

Repeatable Block didefinisikan dengan batas jelas: min_items dan max_items.

Validator memverifikasi bahwa jumlah item array berada dalam rentang [min_items, max_items].

Setiap elemen di dalam Repeatable Block divalidasi secara rekursif terhadap sub-skema yang didefinisikan di ThemeManifest.php.

Submitted vs Legacy Unknown Fields Handling:

New Submitted Unknown Fields: Input form yang mengirimkan key baru yang tidak terdaftar pada skema aktif saat ini wajib ditolak (hard rejection dengan HTTP 422).

Legacy Unknown Fields Preservation: Data lama di content_payload yang berasal dari tema sebelumnya namun tidak dikenal oleh tema aktif saat ini wajib dipertahankan secara utuh saat operasi update (safe merge persistence), sesuai prinsip non-destruktif pada ADR-002.

Separation of Sanitization & Validation:

Alur pemrosesan RICH_TEXT: raw input → server-side sanitizer → ContentSchemaValidator → persist.

Validator tidak bertindak sebagai parser/sanitizer HTML, melainkan memastikan bahwa field telah melewati sanitasi sebelum validasi skema final.

Consequences

Positif:

Eksekusi sangat cepat, memanfaatkan fitur native PHP 8.5 (match expressions, strict typing).

Bebas dari risiko dependency breaking changes atau kerentanan package pihak ketiga.

Form input divalidasi secara konsisten tanpa merusak data saat berganti tema.

Konsekuensi / Trade-off:

Penambahan tipe field baru di masa depan memerlukan pembaruan matcher pada ContentSchemaValidator di kode aplikasi.

Compliance / Implementation Rules

PageService dan PostService wajib memanggil ContentSchemaValidator::validate() sebelum melakukan operasi INSERT atau UPDATE pada content_payload.

Operasi update pada service layer wajib menggabungkan (merge) data legacy yang tidak tersentuh form agar tidak terhapus dari JSON database.

Validasi wajib mengembalikan daftar error terperinci per-field (field-level errors) agar antarmuka Control Panel dapat menampilkan pesan kesalahan yang spesifik kepada pengguna.

References

docs/01-Product-Requirements.md (REQ-CONT-001 s/d REQ-CONT-012)

docs/02-Domain-Model.md (Section 12, 13, 14, 15)

docs/05-Theme-Template-Architecture.md (Section 8, 9, 10, 11)

docs/08-Technical-Architecture.md (Section 19)

docs/adr/ADR-002-Single-Active-Theme-Manifest.md