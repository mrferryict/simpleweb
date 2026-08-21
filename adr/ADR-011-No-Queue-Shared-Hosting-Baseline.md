ADR-011 — No-Queue Shared Hosting Architectural Baseline

Status: Accepted

Date: 2026-08-21

Context

Aplikasi CMS sering kali memperkenalkan infrastruktur antrean latar belakang (background queues) seperti Redis, RabbitMQ, Beanstalkd, Supervisor workers, atau container Docker untuk menangani tugas asinkron (seperti pengiriman email, resize gambar, dan publikasi terjadwal).

Namun, sasaran operasional SMITE CMS V1 adalah lingkungan shared hosting standar (Hostinger, cPanel, hPanel) serta single-box VPS Ubuntu tanpa keharusan mengelola daemon proses persisten (CONTEXT.md, 00-Project-Charter.md, 01-Product-Requirements.md).

Sistem membutuhkan batasan arsitektur yang tegas (architectural guardrails) agar seluruh proses latar belakang, pengiriman pesan, pemrosesan media, dan manajemen sesi bekerja secara deterministik di atas infrastruktur server standar tanpa membebani runtime server atau menambahkan dependensi infrastruktur yang berlebihan.

Decision

No Persistent Application Queue/Worker Infrastructure:

SMITE CMS V1 tidak menggunakan dan melarang penambahan persistent application queue worker, Redis daemon, RabbitMQ, Docker container, atau Celery-like supervisor.

Infrastruktur yang Diizinkan: Web server (Nginx/Apache), PHP-FPM / runtime PHP standar, MariaDB database, dan cron sistem operasi.

Seluruh alur kerja sistem dirancang untuk berjalan di atas siklus request-response HTTP standar PHP atau Spark CLI yang dipicu oleh cron berkala.

Persisted Scheduling State Machine (Not a General Queue):

Tugas terjadwal (publikasi/take-down artikel) dieksekusi melalui CI4 Spark Command:

php spark cms:scheduled-content

Dipicu oleh cron sistem operasi per menit (* * * * *).

scheduled_actions adalah persisted scheduling state machine berbasis MariaDB dengan kontrol leasing transaksi (ADR-006), bukan general-purpose asynchronous message queue broker.

Bounded Synchronous Work Principle:

Ketiadaan antrean latar belakang tidak berarti beban synchronous boleh tanpa batas (no queue does not mean unlimited synchronous work). Seluruh operasi sinkron wajib memiliki batasan beban yang ketat (bounded workload):

Image Upload: Dibatasi oleh dimensi maksimum input dan ukuran file maksimum aplikasi. Pemrosesan GD wajib dibatasi oleh budget resource PHP (memory dan execution limits); jika budget tidak terpenuhi, sistem menolak atau membatalkan operasi secara aman (fail safely).

Direct SMTP: Pengiriman email pemulihan kata sandi dilakukan secara sinkron dengan timeout koneksi/baca yang dibatasi secara eksplisit melalui konfigurasi environment guna mencegah request blocking tanpa batas.

Scheduler Batching: Eksekusi dibatasi secara terukur (misal LIMIT 50 per putaran cron).

Admin Operations: Seluruh operasi data masal wajib terpaginasi (bounded pagination).

Failure-Safe Synchronous Email:

Kegagalan jaringan atau timeout SMTP saat pemulihan kata sandi tidak boleh melempar unhandled HTTP 500 error ke pengguna.

Service wajib menangkap error koneksi secara anggun, mencatat log operasional diagnostik tanpa membocorkan secret/PII, dan mengembalikan respon kegagalan yang aman ke antarmuka pengguna tanpa meninggalkan state token yang ambigu.

V1 tidak mengimplementasikan pengiriman email massal/newsletter.

Filesystem-Backed Baseline State Management:

Session pengguna menggunakan native session handler berbasis sistem berkas lokal (writable/session/) sebagai baseline bawaan.

Caching aplikasi dikelola oleh FileHandler di writable/cache/ (ADR-009).

V1 tidak memerlukan dan melarang ketergantungan terhadap distributed session/cache storage seperti Redis.

Consequences

Positif:

Portabilitas maksimal: aplikasi dapat langsung dipasang dan dijalankan di shared hosting PHP 8.5 tanpa konfigurasi daemon tambahan.

Biaya operasional dan overhead pemeliharaan nol (tidak ada worker process yang perlu dimonitor atau di-restart saat crash).

Arsitektur ramping, minim titik kegagalan (fewer moving parts), dan mudah di-debug oleh solo developer.

Konsekuensi / Trade-off:

Pengiriman email saat reset password menambahkan sedikit latensi pada response time HTTP sesuai responsivitas server SMTP.

Unggahan gambar berukuran raksasa dibatasi secara ketat oleh validasi aplikasi sebelum menyentuh resource limit PHP.

Compliance / Implementation Rules

Dilarang menambahkan package antrean (seperti codeigniter4/queue atau dependensi AMQP/Redis) ke dalam composer.json.

Dilarang mengasumsikan adanya worker yang berjalan terus menerus di background (long-running worker process).

Setiap operasi sinkron (SMTP, pemrosesan media) wajib menerapkan batasan beban (bounded workload) dan proteksi error yang aman (fail-safe handling).

References

CONTEXT.md (Section 2: Mandatory Baseline Decisions)

docs/00-Project-Charter.md (Section 4 & 5)

docs/01-Product-Requirements.md (REQ-NFR-005, REQ-SCOPE-001)

docs/06-Media-Document-Management.md (Section 7, 8, 12)

docs/08-Technical-Architecture.md (Section 2 & 61)

docs/11-Deployment-Operations.md (Section 2 & 11)

docs/adr/ADR-006-ScheduledAction-Idempotent-Execution.md

docs/adr/ADR-009-Shared-Hosting-File-Cache.md