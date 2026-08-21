ADR-005 — Revision History, Draft Auto-save & Optimistic Concurrency Control

Status: Accepted

Date: 2026-08-21

Context

Aplikasi editorial membutuhkan mekanisme penyimpanan riwayat revisi yang deterministik, fitur auto-save pada draf untuk mencegah kehilangan pekerjaan pengguna, serta perlindungan konkurensi agar dua editor tidak saling menimpa data secara diam-diam (silent overwrite).

Di saat yang sama, auto-save tidak boleh membanjiri tabel audit trail dengan ribuan log tidak penting, dan dilarang mengubah tampilan konten yang sedang berstatus PUBLISHED ke publik secara prematur.

Decision

Immutable Full-Snapshot Revisions:

Entitas revisions menyimpan snapshot mandiri (self-contained full snapshot) dalam format JSON pada kolom snapshot.

Snapshot mencakup: title, content_payload, categories, tags, manual_author, featured_image_id, dan metadata seo.

Record revisi bersifat mutlak tidak dapat diubah (immutable).

Normal Editorial Revision vs Autosave Snapshot:

Tabel revisions membedakan dua jenis snapshot menggunakan kolom is_autosave (TINYINT(1) DEFAULT 0):

Normal Editorial Revision (is_autosave = 0): Terbentuk saat aksi eksplisit (Save Draft, Update, Publish, Restore). Ditampilkan di riwayat revisi editorial dan dicatat ke audit_logs.

Autosave Snapshot (is_autosave = 1): Terbentuk saat proses auto-save berkala. Berfungsi sebagai salinan kerja yang dapat dipulihkan (recoverable working snapshot), diisolasi dari daftar riwayat revisi standar UI, dan dilarang menghasilkan event audit ke audit_logs.

Auto-save Protocol & Published Content Boundary:

Auto-save diizinkan untuk konten DRAFT maupun draf pembaruan sementara pada konten PUBLISHED (temporary edits of published content).

Integritas Konten Live: Auto-save dilarang mengubah data publik (persisted live state) pada page_translations / post_translations. Data publik hanya diperbarui saat pengguna menekan tombol eksplisit "Update / Simpan Perubahan".

Timing Protocol:

Perubahan terdeteksi → status dirty = true.

Eksekusi auto-save berjalan setelah 60 detik tanpa interaksi lanjutan (debounce).

Batas keamanan maksimal (safety interval): eksekusi dipaksa setidaknya sekali setiap 5 menit selama kondisi dirty = true.

Jika dirty = false, tidak ada request auto-save yang dikirim ke server.

Optimistic Concurrency Control (OCC) & lock_version:

Menggunakan kolom lock_version (INT UNSIGNED DEFAULT 1) pada tabel pages dan posts.

Aturan Kenaikan Versi: Setiap mutasi konten aktif yang berhasil disimpan secara permanen (explicit save/update/publish/restore) wajib menaikkan lock_version = lock_version + 1. Auto-save pada konten PUBLISHED hanya memperbarui snapshot autosave terisolasi dan tidak menaikkan lock_version live record.

Validasi Concurrency: Setiap aksi simpan (termasuk request auto-save) wajib menyertakan lock_version yang sedang dipegang browser.

Query update dieksekusi secara kondisional:

UPDATE posts
SET ..., lock_version = lock_version + 1
WHERE id = :id AND lock_version = :submitted_version;

Jika baris terpengaruh (affected rows) sama dengan 0, server mengembalikan status HTTP 409 Conflict. UI Control Panel memberitahu pengguna dan dilarang menghapus konten lokal yang belum tersimpan.

Deterministic Restore Revision with OCC:

Operasi Restore Revision wajib memvalidasi lock_version aktif saat ini.

Alur restore: Validasi snapshot masa lalu → Verifikasi lock_version → Terapkan data ke state aktif → Naikkan lock_version = lock_version + 1 → Catat revisi baru (is_autosave = 0) → Catat event audit REVISION_RESTORED → Invalidasi cache publik.

Consequences

Positif:

Konten publik PUBLISHED terlindungi mutlak dari publikasi perubahan draf yang belum matang.

Menghilangkan risiko data hilang akibat race condition antar sesi editor maupun tumpang tindih auto-save.

Tabel audit_logs dan daftar revisi editorial tetap bersih dari ribuan rekaman auto-save.

Alur pemulihan revisi aman dari konflik konkuren.

Konsekuensi / Trade-off:

UI Control Panel dan client-side Alpine.js harus menangani response HTTP 409 Conflict secara anggun (graceful conflict resolution UX).

Compliance / Implementation Rules

Endpoint auto-save (POST /admin/posts/{id}/autosave) wajib memvalidasi sesi, permission, CSRF, lock_version, dan Content Schema.

Service layer dilarang melakukan query mutasi data tanpa klausul verifikasi lock_version.

Query riwayat revisi untuk UI editorial wajib memfilter kondisi WHERE is_autosave = 0.

References

docs/01-Product-Requirements.md (REQ-PAGE-010 s/d 012, REQ-POST-010 s/d 013, REQ-REV-001 s/d 004, REQ-UX-004 s/d 006)

docs/02-Domain-Model.md (Section 26, 27, 28)

docs/04-Content-Publishing.md (Section 7, 17, 18, 19, 20)

docs/08-Technical-Architecture.md (Section 34 & 35)