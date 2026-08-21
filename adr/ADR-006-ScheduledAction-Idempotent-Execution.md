ADR-006 — ScheduledAction Idempotent Execution & Lifecycle

Status: Accepted

Date: 2026-08-21

Context

SMITE CMS mendukung publikasi terjadwal (scheduled publish) dan penurunan tayang terjadwal (scheduled unpublish) untuk Page dan Post. Karena CMS ditargetkan untuk shared hosting tanpa background worker persisten atau Redis, eksekusi jadwal mengandalkan cron sistem operasi yang memanggil CI4 Spark command per menit (php spark cms:scheduled-content).

Sistem membutuhkan jaminan bahwa eksekusi jadwal bersifat idempoten, tahan terhadap crash worker (lease recovery), tidak menahan lock database terlalu lama, mematuhi optimistic concurrency control (OCC), aman dari eksekusi ganda, serta mencatat status transisi secara terstruktur dan atomik bersama event audit.

Decision

Dedicated ScheduledAction Entity & Schema:

Jadwal tindakan disimpan dalam tabel tersendiri: scheduled_actions.

Struktur data inti:

id: BIGINT UNSIGNED AUTO_INCREMENT

target_type: VARCHAR(50) (misal: page, post)

target_id: BIGINT UNSIGNED

action_type: VARCHAR(50) (misal: publish, unpublish)

execute_at: DATETIME

status: VARCHAR(20) (PENDING, PROCESSING, PROCESSED, SKIPPED, CANCELLED, FAILED)

claimed_at: DATETIME NULL

lease_until: DATETIME NULL

processed_at: DATETIME NULL

result_code: VARCHAR(50) NULL (kode hasil terstruktur)

result_message: TEXT NULL

Uniqueness Invariant: Sistem mencegah duplikasi tindakan pending dengan memastikan kombinasi unik untuk (target_type, target_id, action_type, execute_at) pada tindakan berstatus PENDING.

Decoupled Two-Phase Transaction & Lease Management:

Untuk mencegah penahanan database lock terlalu lama, eksekusi dipisah menjadi dua transaksi:

Transaction 1 (Claim Phase):

Mengklaim record yang jatuh tempo atau yang ditinggalkan (abandoned processing):

SELECT * FROM scheduled_actions
WHERE (status = 'PENDING' AND execute_at <= :now)
   OR (status = 'PROCESSING' AND lease_until <= :now)
ORDER BY execute_at ASC
LIMIT 50
FOR UPDATE;

 - Mengubah status menjadi `PROCESSING`, mengisi `claimed_at = :now`, dan menetapkan batas waktu sewa pendek (`lease_until = :now + 5 minutes`).
 - Segera lakukan `COMMIT` untuk melepas row lock antrean.

Execution & Validation: Memproses setiap item antrean secara terisolasi di memori aplikasi.

Transaction 2 (State Mutation & Atomic Audit Phase):

Membuka transaksi untuk menerapkan perubahan pada entitas target.

Mengubah status target, mencatat revisi, memperbarui scheduled_actions menjadi PROCESSED (atau SKIPPED/FAILED), dan menyisipkan record ke audit_logs dalam satu transaksi database yang sama.

Lakukan COMMIT.

Post-Commit Phase: Menginvalidasi cache presentasi publik dan memperbarui status sitemap hanya setelah commit transaksi berhasil.

Target State Pre-Validation & Structured Result Codes:

Sebelum menerapkan mutasi status, scheduler memvalidasi kondisi aktual target:

Jika target TRASH → status SKIPPED, result_code = 'TARGET_TRASHED'.

Jika target ARCHIVED → status SKIPPED, result_code = 'TARGET_ARCHIVED'.

Jika target sudah berada pada status yang dituju (misal sudah PUBLISHED) → status SKIPPED, result_code = 'TARGET_ALREADY_PUBLISHED'.

Jika data target hilang → status SKIPPED, result_code = 'TARGET_MISSING'.

Jika terjadi kegagalan validasi skema/sistem → status FAILED, result_code = 'VALIDATION_FAILED'.

Optimistic Concurrency Control (OCC) Compliance:

Scheduler tunduk pada mekanisme lock_version dari ADR-005.

Mutasi status konten dilakukan secara kondisional:

UPDATE posts
SET status = :target_status, lock_version = lock_version + 1, updated_at = :now
WHERE id = :id AND lock_version = :current_version;

Jika affected rows sama dengan 0 (menandakan ada editor yang baru saja mengupdate konten secara bersamaan):

Transaksi dibatalkan (rollback).

Tindakan ditandai sebagai SKIPPED atau dijadwalkan ulang dengan result_code = 'LOCK_VERSION_CONFLICT'.

Late Catch-up & Single Transition Invariant:

Evaluasi waktu menggunakan execute_at <= :now, menjamin seluruh aksi yang tertunda akibat downtime cron dieksekusi berurutan saat cron kembali aktif.

Setiap record scheduled_actions dijamin hanya menghasilkan tepat satu kali transisi status konten yang sukses.

Consequences

Positif:

Penjadwalan tangguh, bebas dari antrean gantung (crash-resilient) berkat mekanisme lease_until.

Lock database diminimalkan sehingga tidak mengganggu operasi baca/tulis Control Panel lainnya.

Audit trail, status tindakan, dan mutasi konten konsisten 100% (bebas anomali state-without-audit).

Status eksekusi terstruktur memudahkan audit diagnostik dan CLI maintenance.

Konsekuensi / Trade-off:

Implementasi worker membutuhkan loop pemrosesan dua fase (claim loop lalu execution loop).

Compliance / Implementation Rules

Spark command cms:scheduled-content dilarang memegang lock tabel sepanjang siklus pemrosesan konten.

Invalidasi cache wajib dieksekusi di luar transaksi database (post-commit).

Setiap transisi status wajib menyertakan result_code baku saat status akhir bukan PROCESSED.

References

docs/01-Product-Requirements.md (REQ-SCHED-001 s/d REQ-SCHED-006)

docs/02-Domain-Model.md (Section 25, 30)

docs/04-Content-Publishing.md (Section 22, 23, 24, 25, 26)

docs/08-Technical-Architecture.md (Section 37 & 38)

docs/adr/ADR-005-Revision-Autosave-Concurrency.md