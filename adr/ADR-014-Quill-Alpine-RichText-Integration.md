ADR-014 — Quill.js, Alpine.js Bridge & Server-Side Rich Text Sanitization

Status: Accepted

Date: 2026-08-21

Context

Dalam skema konten SMITE CMS, tipe field RICH_TEXT digunakan untuk mengedit konten berformat (seperti artikel berita dan teks halaman kustom). Antarmuka Control Panel membutuhkan editor WYSIWYG yang ringan, tidak membebani frontend dengan bundle framework yang besar, serta terintegrasi mulus dengan ekosistem Alpine.js dan HTMX (REQ-UX-001 s/d REQ-UX-003).

Di sisi keamanan, browser client merupakan lingkungan yang tidak dapat dipercaya (untrusted environment). Output HTML dari editor JavaScript berisiko disisipi serangan Cross-Site Scripting (XSS), tag iframe berbahaya, atau skema URL berbahaya (javascript:, data:).

Selain itu, SMITE CMS bukan visual page builder; kebutuhan media dan video telah ditangani oleh field khusus (IMAGE dan YOUTUBE_URL). Sistem membutuhkan batas tanggung jawab yang tegas: editor JavaScript hanya bertindak sebagai alat bantu antarmuka pengguna (UX tool), sementara sanitasi keamanan HTML merupakan wewenang mutlak server-side sebelum data divalidasi dan disimpan ke database.

Decision

Frontend Editor Engine (UX Tool Only):

Menggunakan pustaka terfokus Quill 2.x sebagai komponen editor UI untuk field RICH_TEXT.

Menolak WYSIWYG editor berat yang berbasis framework SPA (React/Vue) atau editor yang memerlukan runtime backend Node.js.

Quill is NOT a Security Boundary: Output dari Quill tidak pernah dianggap aman semata-mata karena dihasilkan oleh antarmuka editor.

Canonical Persistence Format (Sanitized HTML String):

Format penyimpanan kanonikal untuk field RICH_TEXT di dalam kolom database content_payload adalah string HTML yang telah disanitasi (sanitized HTML string), bukan format internal Quill Delta / JSON.

Hal ini menyederhanakan rendering template tema, pembuatan snapshot revisi, inspeksi SEO, dan proses migrasi data.

Alpine.js Reactive Component Bridge & Single Instance Invariant:

Inisialisasi dan sinkronisasi data Quill diintegrasikan melalui komponen Alpine.js (x-data="quillEditor"):

<div x-data="quillEditor({ initialContent: '...' })" class="w-full">
  <div x-ref="editorContainer" class="min-h-[250px]"></div>
  <input
    type="hidden"
    name="content_payload[body]"
    :value="content"
    x-ref="hiddenInput"
  />
</div>

Lifecycle & Instance Invariant:

Setiap DOM root komponen editor hanya boleh memiliki paling banyak satu instance Quill aktif (at most once per DOM root).

Alpine menginisialisasi Quill instance pada x-init dan mendengarkan event text-change untuk memperbarui variabel reaktif content serta nilai hidden input.

Saat HTMX melakukan fragment replacement (hx-swap), Alpine menangani cleanup dan re-initialization secara terisolasi tanpa menimbulkan duplikasi instance editor atau memory leak.

Toolbar & Sanitizer Strict Alignment:

Prinsip: Kemampuan toolbar editor tidak boleh melebihi allowlist sanitizer server (Editor capabilities must never exceed the server sanitizer allowlist).

Toolbar Allowlist: Dibatasi hanya pada elemen teks struktural dasar: headings (h1–h4), paragraph, format teks (strong, em, u), hyperlink (a), list (ul, ol), blockquote, dan line break (br).

Media/Embed Prohibition in RICH_TEXT: Field RICH_TEXT dilarang memuat arbitrary image uploads, arbitrary iframes, atau embed tags. Kebutuhan gambar dan video YouTube wajib menggunakan field skema terpisah (IMAGE dan YOUTUBE_URL).

Authoritative Server-Side HTML Sanitizer (App\Services\Security\RichTextSanitizer):

Seluruh pemrosesan RICH_TEXT wajib melalui service keamanan terpusat: App\Services\Security\RichTextSanitizer.

Sanitizer menerapkan allowlist ketat berbasis konfigurasi server:

Allowed Tags: <h1>, <h2>, <h3>, <h4>, <p>, <strong>, <em>, <u>, <a>, <ul>, <ol>, <li>, <blockquote>, <br>.

Prohibited Elements: <script>, <style>, <iframe>, <object>, <embed>, <img>, <form>, <input>, serta seluruh inline event handler (onclick, onerror, dll.).

Protocol Whitelist: Tag <a> hanya mengizinkan protokol http://, https://, dan mailto:. Protokol javascript:, data:, dan vbscript: wajib dibuang.

Universal Ingestion Sanitization (Zero Unsanitized Ingestion):

Sanitasi server-side wajib dieksekusi pada seluruh titik masuk data sebelum validasi skema dan penyimpanan permanen:

Create / Update: Raw Input → RichTextSanitizer → ContentSchemaValidator → Persist.

Draft Auto-save: Raw Input → RichTextSanitizer → ContentSchemaValidator → Autosave Snapshot.

Restore Revision: Historical Snapshot → RichTextSanitizer → ContentSchemaValidator → OCC Verification → Persist.

Data Import / Migration: Seluruh data teks berformat dari sumber luar wajib melewati RichTextSanitizer.

Consequences

Positif:

Pengalaman pengguna (UX) responsif, ringan, dan terintegrasi mulus dengan alur form HTMX dan Alpine.js.

Perlindungan mutlak terhadap serangan XSS dan injeksi konten berbahaya pada level server.

Format penyimpanan HTML string bersih membuat rendering tema publik dan snapshot revisi sangat sederhana dan efisien.

Menghindari bloat fitur page-builder yang tidak terkontrol di dalam field teks.

Konsekuensi / Trade-off:

Fitur styling dan formatting di toolbar editor dibatasi secara ketat sesuai kontrak allowlist sanitizer.

Compliance / Implementation Rules

Dilarang menyimpan payload RICH_TEXT ke database atau tabel revisions tanpa melewati RichTextSanitizer.

Inisialisasi Quill wajib dibungkus dalam komponen Alpine.js dan menyinkronkan data ke hidden input.

Unit/Security tests wajib menguji injeksi tag <script>, tag <img>, atribut onerror, iframe, dan skema URL javascript: untuk memastikan sanitizer membersihkan elemen tersebut dari payload akhir.

References

docs/01-Product-Requirements.md (REQ-CONT-003, REQ-UX-001, REQ-UX-003)

docs/03-Authorization-Security.md (SEC-INP-001 s/d SEC-INP-004)

docs/08-Technical-Architecture.md (Section 30, 31, 32)

docs/10-Testing-Quality-Strategy.md (Section 15)

docs/adr/ADR-004-Native-Content-Schema-Validator.md

docs/adr/ADR-005-Revision-Autosave-Concurrency.md

docs/adr/ADR-013-Standard-Layered-CI4-Architecture.md