ADR-007 — Media Storage, Secure Document Download Token & Image Processing Engine

Status: Accepted

Date: 2026-08-21

Context

SMITE CMS membutuhkan sistem pengelolaan aset media (gambar dan dokumen) yang terpusat, hemat penyimpanan, aman dari eksekusi file berbahaya, dan sepenuhnya kompatibel dengan shared hosting.

Sistem harus membedakan perlakuan antara:

Gambar (Images): Aset presentasi publik yang diakses langsung oleh browser untuk efisiensi rendering, divalidasi dimensinya, dioptimasi ke ukuran master presentation yang terukur, dan dibuang file aslinya untuk menghemat ruang disk.

Dokumen (Documents): Aset privat/unduhan yang berisiko tinggi sehingga wajib disimpan di luar web root publik dan disajikan melalui URL terproteksi tanpa mengekspos identifier database sekuensial.

Di samping itu, sistem membutuhkan aturan integritas referensial yang ketat agar penghapusan aset media tidak merusak tampilan halaman yang sedang aktif.

Decision

Strict Physical Storage Segregation:

Images (Public Assets): Disimpan mutlak pada direktori publik public/uploads/images/ menggunakan nama acak aman (safe generated storage key). Browser menyajikan gambar secara langsung tanpa melewati execution overhead dari PHP Controller.

Documents (Protected Assets): Disimpan mutlak di luar root publik pada writable/uploads/documents/. Web server dilarang melayani akses file langsung ke direktori ini.

Image Processing Pipeline & Bounded Master Size:

Menggunakan extension bawaan PHP GD (ext-gd) melalui wrapper CodeIgniter 4 Image Manipulation service (\Config\Services::image('gd')).

Output Format: Dibatasi pada format yang didukung oleh build GD di server. Format WebP diutamakan, dengan JPEG sebagai fallback resmi yang tervalidasi.

Bounded Master Presentation Size: Gambar diproses dan disimpan pada ukuran master presentation maksimum yang mencukupi kebutuhan peran konten (misal batas lebar master hero/featured). Sistem tidak mengerdilkan ukuran master hanya untuk kebutuhan tema aktif saat upload.

No Automatic Upscaling: Jika tema baru di masa depan membutuhkan dimensi lebih besar dari ukuran master yang tersimpan, sistem dilarang melakukan upscaling otomatis. Operator harus mengunggah aset baru.

Original Discard Policy: File asli unggahan wajib langsung dihapus (discard original upload) setelah pemrosesan master berhasil untuk efisiensi penyimpanan disk.

Cryptographically Secure Download Token & Memory-Safe Streaming:

Dokumen publik tidak menggunakan database auto-increment ID atau pola sekuensial pada URL publik.

Setiap dokumen diberi token unik 32-karakter acak kriptografis: download_token (dihasilkan via bin2hex(random_bytes(16)) dan diberi UNIQUE INDEX di media_assets).

Rute publik unduhan: GET /download/document/{download_token}.

Scope Proteksi: Penggunaan token dan controller download bertujuan mencegah direct filesystem access dan predictable ID enumeration scraping.

Authorization Check: Controller memverifikasi status dokumen adalah ACTIVE dan konten pemiliknya berada pada status publik yang sah sebelum mengalirkan file.

Streaming Protocol: Controller wajib menggunakan native CI4 $this->response->download($filePath, null) dengan chunked buffering. Dilarang menggunakan file_get_contents() yang membaca seluruh file ke memori PHP.

Authoritative Media References in JSON:

Kolom JSON content_payload hanya menyimpan media_id (integer authoritative):

{
  "hero_image": {
    "media_id": 42,
    "alt": "Gedung Utama Sekolah"
  }
}

URL publik dan path aset selalu di-resolve secara dinamis oleh MediaService / Asset Helper saat rendering.

Alt Text Resolution Hierarchy:

Prioritas:

alt kontekstual pada content_payload.

alt / title default dari media_assets.

alt="".

Targeted Dependency Checking (Hard Rejection):

Operasi Permanent Delete pada MediaAsset wajib melalui DependencyChecker.

Pengecekan dependensi wajib dilakukan secara terarah (targeted database queries), dilarang memuat seluruh baris pages/posts ke memori PHP.

Memeriksa foreign key relasional langsung (posts.featured_image_id) dan keberadaan media_id di dalam JSON content_payload (pages dan posts).

Jika aset masih digunakan, operasi hapus permanen wajib ditolak (hard rejection) disertai daftar konten yang menggunakannya.

Non-Destructive Media Replacement:

Mengganti media pada suatu slot konten dilakukan dengan mengaitkan MediaAsset baru atau memilih aset lain dari library, bukan menimpa file fisik MediaAsset lama di disk.

Consequences

Positif:

Kompatibel penuh dengan shared hosting tanpa kebutuhan biner ImageMagick atau Node.js.

Direct rendering gambar cepat karena dilayani langsung oleh web server dari public/uploads/images/.

Dokumen privat terlindungi dari predictable ID enumeration dan aman dari kehabisan memori PHP saat diunduh.

Menghindari dependensi kaku ukuran gambar terhadap tema tertentu saat upload.

Konsekuensi / Trade-off:

Karena file asli dihapus, gambar master tidak dapat di-reprocess ke resolusi yang lebih tinggi dari master awal jika terjadi pergantian tema dengan kebutuhan dimensi lebih besar.

Compliance / Implementation Rules

Direktori dokumen privat wajib berada di writable/uploads/documents/ dan tidak boleh dibuatkan symlink ke public.

DependencyChecker wajib menggunakan query terindeks/terarah untuk mendeteksi media_id pada payload JSON sebelum mengizinkan penghapusan fisik file.

Generator token wajib menggunakan random_bytes() dan dilarang menggunakan fungsi hashing dari timestamp/ID yang dapat ditebak.

References

docs/01-Product-Requirements.md (REQ-MEDIA-001 s/d 007, REQ-DOC-001 s/d 005)

docs/02-Domain-Model.md (Section 18, 19, 20, 29, 30)

docs/06-Media-Document-Management.md

docs/08-Technical-Architecture.md (Section 42, 43, 44)