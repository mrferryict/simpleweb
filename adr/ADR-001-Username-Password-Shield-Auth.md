ADR-001 — Username & Password Authentication with CodeIgniter Shield
Status: Accepted

Date: 2026-08-21

Context
CodeIgniter Shield secara bawaan mendukung autentikasi berbasis email atau multi-identifier. Namun, spesifikasi SMITE CMS V1 (CONTEXT.md & 00-Project-Charter.md) secara eksplisit menetapkan bahwa login Control Panel menggunakan kombinasi username + password.

Di saat yang sama, alamat email pengguna tetap wajib dicatat untuk pemulihan akun (password reset) dan notifikasi, namun harus dilindungi sebagai data pribadi sensitif (PII) menggunakan enkripsi pada tingkat database, bukan disimpan sebagai teks terbuka (plaintext).

Decision
Authentication Framework & Mechanism:

Menggunakan CodeIgniter Shield (Session Authenticator) sebagai engine autentikasi utama.

Menetapkan kolom username sebagai satu-satunya kredensial login publik di samping password.

Username Invariants:

Username SHALL be normalized (trimmed dan lowercase) before uniqueness validation and persistence.

Password Management:

Password is handled exclusively by CodeIgniter Shield and is never stored or manipulated by application code as plaintext.

Email PII Handling:

Email is not a login identifier.

Email is stored and processed according to the PII encryption strategy defined in ADR-008.

The authoritative database fields are:

email_ciphertext

email_lookup_hash

All email writes SHALL pass through the PII service.

Email lookup SHALL use the normalized HMAC lookup hash and SHALL NOT require decrypting stored email ciphertext.

Authentication Entry Point & Routing:

Titik masuk login publik menggunakan rute khusus /cp.

Rute Control Panel yang terproteksi berada di bawah prefix /admin/*.

Single Admin & Account Lifecycle Invariants:

The system SHALL maintain exactly one Admin account.

The system SHALL reject any operation that would result in zero Admin accounts or more than one Admin account (tidak bisa menambah Admin baru, tidak bisa menghapus, menonaktifkan, atau mempromosikan role lain jika Admin sudah ada).

Admin cannot permanently delete or deactivate itself.

User deactivation uses active = false, bukan physical deletion.

Consequences
Positif:

Alur login sederhana, aman, dan selaras dengan kebutuhan administratif CMS.

Tetap memanfaatkan seluruh fitur native Shield (Session management, Groups & Permissions, Throttling, Password Hashing).

Single source of truth untuk enkripsi PII terisolasi rapi di ADR-008.

Konsekuensi / Trade-off:

View login dan workflow otentikasi default Shield disesuaikan agar hanya membaca dan memvalidasi username.

Compliance / Implementation Rules
Filter otentikasi Shield wajib dipasang terpusat pada route group /admin/*.

Form login di /cp dilarang mengekspos apakah username terdaftar saat autentikasi gagal (pesan kegagalan harus generik: "Kredensial tidak valid").

Service layer wajib menolak operasi mutasi yang melanggar invariant single-Admin sebelum query database dieksekusi.

References
CONTEXT.md (Section 2 & 3: Mandatory Decisions & Explicit Global Override)

docs/00-Project-Charter.md

docs/01-Product-Requirements.md (REQ-AUTH-001 s/d REQ-AUTH-012)

docs/03-Authorization-Security.md (SEC-AUTH-001 s/d SEC-AUTH-012)

docs/08-Technical-Architecture.md (Section 25 & 45)

docs/adr/ADR-008-Sodium-PII-Key-Separation.md