ADR-012 — Dedicated Branch Framework Upgrade Policy & Deprecation Governance

Status: Accepted

Date: 2026-08-21

Context

Sebagai aplikasi CMS yang dirancang untuk dipelihara jangka panjang oleh solo developer, SMITE CMS akan menghadapi siklus pembaruan berkala pada runtime PHP (PHP 8.5 → 8.6 → 9.x), framework CodeIgniter (CI 4.7+), CodeIgniter Shield, dan dependensi pustaka terkait.

Pembaruan framework, runtime, maupun patch keamanan yang dicampur dengan penambahan fitur bisnis baru berisiko tinggi memicu regresi tersembunyi, merusak integritas database, dan mempersulit penelusuran akar masalah (root cause analysis).

Sistem membutuhkan tata kelola pembaruan (upgrade governance) yang disiplin: pemisahan kelas branch khusus, larangan penambahan fitur secara bersamaan, toleransi nol terhadap peringatan deprecation internal, serta prosedur verifikasi berkala terhadap integritas sistem.

Decision

Dedicated Maintenance Branch Classes:

Setiap aktivitas pemeliharaan wajib diisolasi pada kelas branch Git khusus:

upgrade/*: Untuk pembaruan versi mayor/minor pada PHP runtime, CodeIgniter framework, atau CodeIgniter Shield (misal: upgrade/ci4-4.8, upgrade/php-8.6).

security/*: Untuk penerapan patch kerentanan keamanan atau perbaikan darurat (misal: security/shield-auth-patch, security/cve-remediation).

Zero Feature Addition Rule: Kedua kelas branch tersebut dilarang keras memuat penambahan fitur baru, perubahan skema bisnis baru, atau refaktor arsitektur yang tidak terkait langsung dengan kompatibilitas pembaruan.

Deprecation & Warning Governance:

Selama pengujian pada lingkungan lokal/WSL2, tingkat pelaporan error PHP wajib diatur maksimal (E_ALL).

Project-Introduced Deprecations (Hard Blocker): Seluruh Deprecation Warning yang dipicu oleh kode aplikasi internal (app/) wajib diselesaikan hingga nol (zero-tolerance) sebelum branch di-merge ke branch utama (main).

External/Environment Warnings (Review & Mitigate): Peringatan dari dependensi vendor pihak ketiga atau lingkungan server ditinjau secara eksplisit; tidak otomatis memblokir rilis kecuali mengindikasikan potensi ketidakstabilan atau cacat keamanan.

Strict Release & Verification Gate:

Branch upgrade/* atau security/* hanya boleh di-merge setelah memenuhi seluruh acceptance gate berikut:

Security Audit: composer audit bebas dari kerentanan berkategori High atau Critical yang belum dimitigasi (unmitigated) pada set dependensi yang dideploy.

Automated Test Suite: Seluruh rangkaian pengujian (composer test) lulus 100% (green).

Database Migration on Sanitized Data: Uji coba migrasi database berjalan sukses pada salinan data realistis yang telah disanitasi dari data sensitif/PII (sanitized production-like copy).

Smoke Testing: Verifikasi fungsionalitas kritis (autentikasi, publikasi konten, pemrosesan media GD, eksekusi scheduler CLI, unduhan token dokumen, dan rendering dwibahasa).

Integrity Inspection: Menjalankan cms:integrity-check pasca-migrasi lokal.

Non-Destructive Database Evolution (Expand-Migrate-Contract):

Jika pembaruan memerlukan evolusi skema database:

Dilarang mengubah file migrasi yang sudah pernah diterapkan di production.

Terapkan pola Expand → Migrate Data → Contract: tambahkan struktur baru via migrasi baru, migrasikan data secara aman, alihkan kode aplikasi, baru kemudian hapus kolom/tabel usang pada rilis berikutnya.

Operational Verification via cms:integrity-check:

Menyediakan Spark CLI command non-destruktif:

php spark cms:integrity-check

Berfungsi sebagai alat inspeksi operasional (dry-run auditor) untuk mendeteksi anomali:

File fisik di writable/uploads/ yang tidak memiliki record di media_assets.

Record media_assets yang file fisiknya hilang di disk.

Record url_redirects yang mengarah ke target rute mati/404.

Tindakan scheduled_actions yang berstatus FAILED.

Batas Kewenangan: Command ini adalah alat verifikasi operasional dan bukan pengganti automated tests. Command dilarang melakukan mutasi atau penghapusan file/data otomatis.

Scheduled Security & Operational Hygiene:

Rutinitas pemeliharaan berkala (minimal setiap 3 bulan):

Menjalankan composer audit dan meninjau patch dependensi.

Memeriksa keterpisahan kunci dan format kunci rahasia PII (EMAIL_ENCRYPTION_KEY & EMAIL_LOOKUP_HMAC_KEY).

Melakukan uji coba pemulihan cadangan database (test restore) ke lingkungan lokal.

Consequences

Positif:

Risiko regresi dan downtime saat upgrade framework/PHP ditekan hingga mendekati nol.

Riwayat Git jelas dan mudah di-rollback jika terjadi kendala pada rilis upgrade.

Utang teknis (technical debt) tidak menumpuk karena peringatan deprecation langsung ditangani saat transisi versi.

Integritas data historis (audit logs, revisi, redirect, media references) terjamin selama evolusi sistem.

Konsekuensi / Trade-off:

Membutuhkan disiplin branch terpisah dan waktu pengujian tambahan sebelum dependensi baru dapat digunakan pada branch utama.

Compliance / Implementation Rules

Pull request / merge dari branch upgrade/* dan security/* wajib menyertakan laporan pengujian penuh (composer test pass, zero internal deprecation).

File composer.lock wajib selalu diperbarui dan di-commit bersamaan dengan perubahan composer.json.

Command cms:integrity-check dilarang memuat logika mutasi atau penghapusan data database/filesystem.

References

docs/00-Project-Charter.md (Section 8)

docs/08-Technical-Architecture.md (Section 60 & 63)

docs/10-Testing-Quality-Strategy.md (Section 44, 48, 50)

docs/11-Deployment-Operations.md (Section 41)

docs/12-Maintenance-Upgrade-Guide.md (Section 1, 7, 8, 10, 11, 41)