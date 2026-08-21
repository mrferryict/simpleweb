ADR-008 — Sodium PII Encryption & Lookup Key Separation

Status: Accepted

Date: 2026-08-21

Context

Sesuai CONTEXT.md, 00-Project-Charter.md, dan 03-Authorization-Security.md, alamat email pengguna dalam SMITE CMS dikategorikan sebagai Personally Identifiable Information (PII) yang wajib dilindungi. Sistem tidak mengizinkan penyimpanan email dalam bentuk teks terbuka (plaintext) pada database.

Di saat yang sama, aplikasi membutuhkan:

Kemampuan memulihkan email asli untuk pengiriman email reset password dan notifikasi administratif.

Kemampuan melakukan pencarian akun secara cepat dan penegakan keunikan (unique constraint) tanpa harus mendeskripsi seluruh baris database pengguna.

Kepatuhan terhadap prinsip pemisahan kunci (key separation) dan kesiapan rotasi kunci di masa depan tanpa dependensi library eksternal yang berat.

Decision

Cryptographic Engine (ext-sodium):

Menggunakan extension bawaan PHP 8.5 ext-sodium yang diisolasi di dalam App\Services\Security\PiiCipherService.

Menolak penambahan library kriptografi pihak ketiga di luar core PHP.

Authoritative Database Schema:

Tabel pengguna (users / Shield identity) menggunakan dua kolom khusus:

email_ciphertext (TEXT NOT NULL): Menyimpan payload terenkripsi yang dapat didekripsi kembali.

email_lookup_hash (VARCHAR(64) NOT NULL): Menyimpan hash deterministik untuk index dan unique lookup. Diberi indeks UNIQUE(email_lookup_hash).

Hex Representation & Fail-Fast Config Validation:

Kunci rahasia pada file .env wajib direpresentasikan sebagai string 64-karakter hexadecimal printable:

EMAIL_ENCRYPTION_KEY=<64 hex chars>

EMAIL_LOOKUP_HMAC_KEY=<64 hex chars>

PiiCipherService melakukan konversi menggunakan hex2bin() dan menjalankan fail-fast startup validation:

Memastikan kedua kunci terdefinisi dan merupakan hex yang valid.

Memastikan panjang binary hasil decode tepat 32 bytes (SODIUM_CRYPTO_SECRETBOX_KEYBYTES).

Memastikan kedua kunci memiliki nilai yang berbeda (EMAIL_ENCRYPTION_KEY !== EMAIL_LOOKUP_HMAC_KEY).

Jika konfigurasi tidak valid, aplikasi menolak boot (fail-fast) tanpa membocorkan nilai kunci ke log atau exception message.

Encryption Protocol (Confidentiality):

Menggunakan algoritma terotentikasi XSalsa20-Poly1305 via sodium_crypto_secretbox():

Nonce dibuat menggunakan CSPRNG: $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); (tepat 24 bytes).

Ciphertext dihasilkan: $ciphertext = sodium_crypto_secretbox($email, $nonce, $encryptionKey);.

Format penyimpanan: $nonce . $ciphertext yang di-encode ke Base64 URL-safe string.

Deterministic Lookup Hash Protocol (Integrity & Lookup):

Menggunakan algoritma HMAC-SHA256:

$lookupHash = hash_hmac('sha256', strtolower(trim($email)), $lookupHmacKey);

Menghasilkan string hexadecimal 64-karakter yang disimpan pada email_lookup_hash.

Normalization vs Canonical Email Policy:

Normalisasi (strtolower(trim($rawEmail))) digunakan secara konsisten untuk kalkulasi email_lookup_hash pada registrasi, update profil, recovery lookup, dan pemeriksaan keunikan.

Nilai email asli hasil dekripsi digunakan untuk keperluan pengiriman pesan (SMTP).

Future-Safe Key Rotation Policy:

V1 menggunakan single active key configuration.

Desain diisolasi di dalam PiiCipherService sehingga rotasi kunci di masa depan wajib menggunakan strategi versi kunci yang eksplisit (explicit key-version strategy) dan dilarang mengganti kunci secara diam-diam tanpa migrasi data terenkripsi.

Zero Plaintext Logging Rule:

Plaintext email hasil dekripsi hanya berada di memori aplikasi selama alur eksekusi SMTP atau form edit profil pengguna aktif.

Dilarang mencatat plaintext email ke file log, audit trail, query log, atau response exception error.

Consequences

Positif:

Perlindungan PII berstandar industri menggunakan ext-sodium tanpa dependensi eksternal.

Pencarian user berbasis email berjalan cepat melalui indexed exact lookup dengan kompleksitas logaritmik B-Tree tanpa perlu full database decryption scan.

Representasi hex di .env mencegah ambiguitas encoding pada berbagai environment server.

Konsekuensi / Trade-off:

Pencarian wildcard (misal LIKE '%@sekolah.sch.id') tidak dapat dilakukan langsung di database; pencarian harus berbasis exact match email yang dinormalisasi.

Compliance / Implementation Rules

Seluruh operasi baca/tulis data email wajib melalui method PiiCipherService::encrypt(), PiiCipherService::decrypt(), atau PiiCipherService::getLookupHash().

Dilarang memanggil fungsi enkripsi atau hashing secara manual di dalam Controller atau Model.

Unit test wajib memverifikasi bahwa variasi huruf besar/kecil menghasilkan email_lookup_hash yang identik dan nonce selalu acak (panjang 24 bytes) untuk setiap enkripsi baru.

References

CONTEXT.md (Section 3: PII Security & Encryption Baseline)

docs/01-Product-Requirements.md (REQ-AUTH-007)

docs/03-Authorization-Security.md (SEC-PII-001 s/d SEC-PII-003)

docs/08-Technical-Architecture.md (Section 45)

docs/adr/ADR-001-Username-Password-Shield-Auth.md