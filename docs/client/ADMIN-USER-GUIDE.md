# SMITE CMS — Admin User Guide

**Versi panduan:** V2.0.0 CORE (user management, reset password email, kebijakan password Shield)

Panduan ini ditujukan untuk **Admin website**, **Editor konten**, dan **staf/operator** yang mengelola isi website melalui Control Panel SMITE CMS.

Anda **tidak** perlu memahami PHP, Git, Composer, SSH, atau database untuk pekerjaan sehari-hari di panduan ini.

Untuk instalasi server, update software CMS, backup, dan konfigurasi teknis, gunakan dokumentasi terpisah (lihat [Dokumentasi lain](#21-dokumentasi-lain)).

---

## 1. Tentang Panduan Ini

### Siapa yang menggunakan panduan ini?

| Peran | Kegiatan umum |
|---|---|
| **Admin** | Semua area Control Panel (Settings, Themes, Audit, konten, media, menu) |
| **Editor** | Pages, Posts, kategori/tag, media, publikasi |
| **Contributor** | Membuat Draft Post, mengedit Post milik sendiri, mengirim untuk review, media terbatas |

Peran ditetapkan oleh Admin melalui menu **Users** di Control Panel (V2). Editor dan Contributor tidak dapat mengelola akun pengguna.

### Dua jenis “update” — jangan dicampur

#### Update isi website (tugas operator)

Contoh:

- membuat atau mengubah **Page**
- membuat atau mengubah **Post/berita**
- mengunggah **gambar** atau **dokumen**
- mengatur **menu** navigasi
- mengubah **Settings** (nama situs, locale, SEO default)

Dilakukan melalui:

```text
https://your-domain.example/cp  →  login  →  /admin
```

**Tidak membutuhkan Git** dan tidak mengubah kode aplikasi.

#### Update software CMS (tugas developer/server administrator)

Contoh: memasang versi CMS baru (`v1.1.4` → versi berikutnya).

Dilakukan oleh developer melalui prosedur deployment. Lihat [UPDATE.md](UPDATE.md).

Operator **tidak** melakukan update software CMS dari Control Panel.

---

## 2. Memahami Control Panel

| URL | Fungsi |
|---|---|
| `/` | Website publik (tampilan pengunjung) |
| `/cp` | Halaman login Control Panel |
| `/admin` | Dashboard Control Panel (setelah login) |
| `/admin/...` | Area pengelolaan (Pages, Posts, Media, dll.) |

Tampilan **website publik** (Theme 2026) berbeda dari **tampilan Control Panel** (admin). Operator mengelola konten di `/admin`; pengunjung melihat hasilnya di `/`.

Menu sisi (sidebar) di Control Panel:

- Dashboard
- Pages
- Posts
- Categories
- Tags
- Media
- Menus
- Settings
- Users *(hanya Admin dengan permission user.manage)*
- Themes *(hanya jika akun Anda punya permission)*
- Audit *(hanya jika akun Anda punya permission)*

Beberapa menu tetap terlihat di navigasi, tetapi akses ke halaman tertentu dibatasi oleh permission. Jika halaman menolak akses, hubungi Admin.

---

## 3. Login

### Alamat login

```text
https://your-domain.example/cp
```

Ganti `your-domain.example` dengan domain website Anda.

### Langkah login normal

1. Buka `/cp`.
2. Masukkan **username**.
3. Masukkan **password**.
4. Klik tombol login.
5. Setelah berhasil, Anda masuk ke **Dashboard** di `/admin`.

### Login pertama setelah instalasi (wajib ganti password)

Jika akun Admin baru dibuat melalui `cms:install`, sistem mewajibkan pergantian password sebelum Control Panel dapat digunakan sepenuhnya.

```text
/cp
  ↓ login dengan password awal instalasi
/cp/password-change
  ↓ isi password saat ini + password baru + konfirmasi
/admin
```

Selama password belum diganti:

- Anda **tidak** bisa membuka `/admin` atau area seperti `/admin/pages`.
- Sistem mengarahkan Anda kembali ke **Change your password**.

Setelah password berhasil diganti, gunakan **password baru** untuk login berikutnya. Password awal instalasi tidak boleh dipakai lagi.

### Logout

Gunakan tautan **Logout** di Control Panel, atau buka `/logout`.

Pada komputer bersama, selalu logout setelah selesai.

### Lupa password

Jika Anda lupa password dan server sudah dikonfigurasi dengan SMTP:

1. Buka `/cp/password-reset`.
2. Masukkan **email** yang terdaftar pada akun Anda.
3. Klik **Request reset**.
4. Periksa kotak masuk email Anda (termasuk folder spam).
5. Buka tautan reset dalam email.
6. Di halaman **Set new password**, masukkan password baru dan **konfirmasi password**.
7. Setelah berhasil, kembali ke `/cp` dan login dengan password baru.

Pesan setelah permintaan reset selalu sama, baik email dikenali maupun tidak — ini melindungi privasi akun.

Jika email tidak sampai, hubungi administrator server (SMTP mungkin belum dikonfigurasi).

---

## 4. Dashboard

Buka: `/admin`

Dashboard menampilkan:

- **Welcome back, {username}** — sapaan dengan username akun Anda.
- **Quick actions** (jika permission mengizinkan), misalnya:
  - Create Page
  - Create Post
  - Open Media
  - Open Settings
- **Kartu modul** yang mengelompokkan area: Content, Media, Site, Configuration, Appearance, Security.

Dashboard **bukan** dashboard analitik. V1 **tidak** menampilkan statistik kunjungan, grafik, atau feed aktivitas palsu.

Gunakan kartu atau menu sisi untuk masuk ke area yang Anda butuhkan.

---

## 5. Pages

### Apa itu Page?

**Page** digunakan untuk konten website yang relatif permanen, misalnya halaman Tentang Kami, Kontak, Profil, Layanan, dan sejenisnya.

### Membuka Pages

Menu **Pages** → `/admin/pages`

Daftar menampilkan: ID, Title, Slug, Locale, Status, Template, Updated, dan Actions.

Gunakan tab **Active** / **Trash** untuk melihat halaman aktif atau yang sudah dibuang.

### Membuat Page baru

1. Klik **Create Page**.
2. Isi form (bagian **Basics**):
   - **Title** *(wajib)* — judul halaman.
   - **Slug** *(wajib)* — bagian URL publik (huruf kecil, tanpa spasi; contoh: `tentang-kami`).
   - **Locale** *(wajib)* — `id` atau `en`. Untuk konten utama Indonesia, pilih **`id`**.
   - **Template** — V1 dengan Theme 2026 aktif menggunakan **`custom-page`**.
   - **Parent page** — opsional; untuk struktur hierarki internal (bukan URL bersarang).
3. Isi bagian **SEO** (opsional):
   - Meta title
   - Meta description
   - Canonical URL override
   - OG image media ID (ID media gambar dari Media Library)
4. Isi bagian **Content** sesuai field template Theme aktif. Pada Theme 2026, field yang tersedia antara lain:
   - Hero Title
   - Hero Description
   - Body *(rich text)*
   - Hero Image *(pilih dari Media)*
   - Video URL
   - CTA URL
   - Attachment *(dokumen)*
   - Hero Slides *(daftar slide, maksimal 5 item)*
5. Klik **Create page**.

Page baru disimpan sebagai **Draft** sampai Anda mempublikasikannya.

### Mengedit Page

Dari daftar Pages, klik **Edit**.

Pada halaman edit Anda dapat:

- mengubah field form;
- menggunakan **Save draft** (autosave) saat mengedit;
- membuka **Revision history** jika permission mengizinkan;
- menggunakan tombol lifecycle: **Publish**, **Unpublish**, **Archive** (jika permission mengizinkan);
- menjadwalkan publish/unpublish (**Scheduled publish / unpublish**) jika permission mengizinkan dan hosting sudah menjalankan scheduler.

Klik **Update page** untuk menyimpan perubahan form.

### URL publik Page

| Locale | Format URL |
|---|---|
| Primary (`id`) | `https://your-domain.example/{slug}` |
| Secondary (`en`) | `https://your-domain.example/en/{slug}` |

Contoh: slug `tentang-kami` dengan locale `id` → `/tentang-kami`.

Page harus berstatus **Published** agar dapat diakses pengunjung (kecuali preview internal).

### Menghapus Page

- **Trash** — memindahkan ke Tong Sampah (dapat dipulihkan).
- Dari view **Trash**, gunakan **Restore** atau **Permanent Delete** (hanya jika authorized).

---

## 6. Posts

### Perbedaan Page dan Post

| | Page | Post |
|---|---|---|
| Fungsi | Halaman permanen | Artikel/berita |
| URL publik | `/{slug}` atau `/en/{slug}` | `/news/{slug}` atau `/en/news/{slug}` |

### Membuka Posts

Menu **Posts** → `/admin/posts`

### Membuat Post baru

1. Klik **Create Post** (atau **New post**).
2. Isi **Basics**:
   - **Title** *(wajib)*
   - **Slug** *(wajib)* — digunakan di URL `/news/{slug}`
   - **Locale** *(wajib)*
   - **Author (public)** *(wajib)* — nama penulis yang ditampilkan ke pengunjung. **Diisi manual** oleh operator; tidak diambil otomatis dari akun login Anda.
3. Pilih **Categories** dan **Tags** (opsional).
4. Isi **SEO** (opsional): meta title, meta description, canonical URL, OG image media ID.
5. Isi **Content** — pada Theme 2026, field utama Post adalah **Body** *(rich text)*.
6. Klik **Create post**.

### Workflow editorial Post

Status yang tersedia:

| Status | Arti singkat |
|---|---|
| **DRAFT** | Belum dipublikasikan |
| **PENDING_REVIEW** | Menunggu review Editor/Admin |
| **PUBLISHED** | Tampil di website |
| **UNPUBLISHED** | Sengaja tidak dipublikasikan |
| **ARCHIVED** | Diarsipkan |
| **TRASH** | Dibuang (dapat dipulihkan) |

Tombol lifecycle (tergantung peran):

- **Publish** — Admin/Editor
- **Unpublish**, **Archive**
- **Submit for Review** — Contributor
- **Publish** / **Return for Revision** — Editor/Admin saat review

### URL publik Post

```text
https://your-domain.example/news/{slug}
```

Contoh generik: `/news/selamat-datang` (bukan `/news` saja).

**Penting:** `/news` **bukan** halaman arsip berita di V1. Hanya URL per artikel `/news/{slug}` yang valid.

---

## 7. Categories

Menu **Categories** → `/admin/categories`

**Category** mengelompokkan Post. Kategori V1 **datar** (tidak hierarkis).

### Membuat Category

1. Klik **Create Category**.
2. Isi **Name** dan **Slug**.
3. Simpan.

### Mengelola Category

- **Edit** — ubah nama/slug.
- **Deactivate** — kategori tidak aktif; tidak bisa dipakai pada Post baru, tetapi tetap terlihat di daftar.
- **Restore** — mengaktifkan kembali kategori yang dinonaktifkan.

---

## 8. Tags

Menu **Tags** → `/admin/tags`

**Tag** memberi label tambahan pada Post (lebih fleksibel dari Category).

### Membuat dan mengedit Tag

1. Klik **Create Tag**.
2. Isi **Name** dan **Slug**.
3. Simpan.

V1 menyediakan **Create** dan **Edit** untuk Tag. Tidak ada tombol deactivate/delete di daftar Tag.

---

## 9. Media

Menu **Media** → `/admin/media`

Media Library menyimpan file yang dipakai di Pages dan Posts.

### Jenis file

| Jenis | Penyimpanan (di server) | Akses pengunjung |
|---|---|---|
| **Gambar** | `public/uploads/images/` | URL publik `/uploads/images/...` |
| **Dokumen** | `writable/uploads/documents/` | Unduh melalui tautan aman aplikasi (bukan file langsung di folder publik) |

Operator **tidak** perlu mengelola folder server secara manual.

### Format yang didukung (V1)

**Gambar:** JPEG, PNG, WebP, GIF — maksimum sekitar **5 MB** per file (dan mengikuti profil gambar Theme aktif).

**Dokumen:** PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX — maksimum sekitar **15 MB** per file.

File berbahaya (misalnya `.php`, `.exe`, `.html`, `.svg`) **ditolak**.

### Mengunggah media

1. Klik **Upload Media**.
2. Pilih file.
3. (Opsional) isi Title, Alt text, Description.
4. Klik **Upload**.

### Mengelola media

Dari daftar **Active**:

- **Edit** — ubah metadata.
- **View** — buka gambar di tab baru.
- **Download** — untuk dokumen.
- **Trash** — pindahkan ke Tong Sampah.

Dari **Trash**:

- **Restore**
- **Delete permanently** *(hanya jika authorized)*

### Memakai media di konten

Pada field **Hero Image**, **Attachment**, atau field gambar/dokumen lain di form Page/Post, gunakan **Media Picker** untuk memilih file yang sudah diunggah.

---

## 10. Menus

Menu **Menus** → `/admin/menus`

Menu V1 memiliki dua **location**:

- **PRIMARY** — navigasi utama (header)
- **FOOTER** — navigasi footer

Setiap location mendukung **dua tingkat** (induk + anak).

### Membuat menu item

1. Klik **New menu item**.
2. **Placement:**
   - **Location** — PRIMARY atau FOOTER
   - **Parent** — kosongkan untuk level atas, atau pilih induk untuk submenu
3. **Menu label** — teks yang tampil di navigasi.
4. **Destination type:**
   - **Page** — isi **Page ID** (angka ID dari daftar Pages)
   - **Post Category** — isi **Post Category ID**
   - **External URL** — isi URL lengkap `https://...`
5. **Display order** — angka urutan (lebih kecil biasanya lebih dulu).
6. Centang **Active** agar item tampil di website.
7. Klik **Create menu item**.

### Contoh struktur *(ilustrasi)*

```text
PRIMARY
├── Home          → Page atau URL eksternal
├── About         → Page #...
├── News          → Page #... (misalnya halaman landing berita)
└── Contact       → Page #...
```

### Page dibuat ≠ otomatis muncul di menu

Membuat Page **tidak** menambahkannya ke navigasi secara otomatis. Anda harus membuat **menu item** yang menunjuk ke Page tersebut (atau URL lain).

---

## 11. Settings

Menu **Settings** → `/admin/settings` *(permission: site.manage — biasanya Admin)*

### Site identity

- **Site name** *(wajib)*
- **Site description**
- **Contact email** *(wajib)*

### Localization

- **Default locale** — default `id`
- **Primary locale** — locale utama konten publik
- **Secondary locale** — `en` atau **Disabled**
- **Timezone** — contoh `Asia/Jakarta`

Pastikan **locale** pada Page/Post selaras dengan pengaturan ini agar URL publik benar.

### SEO defaults

Default meta title dan meta description untuk locale `id` dan `en`, dipakai ketika konten tidak mengisi SEO sendiri.

Klik **Save settings** untuk menyimpan.

---

## 12. Themes

Menu **Themes** → `/admin/themes` *(permission: theme.activate — biasanya Admin)*

**Theme** menentukan tampilan **website publik**, bukan tampilan Control Panel.

Pada daftar theme **ENABLED**:

- lihat theme mana yang **Active**
- gunakan **Preview: {judul Page}** untuk melihat preview tanpa mengaktifkan
- gunakan **Activate** untuk mengganti theme aktif

**Theme 2026** adalah theme default V1.

> Jangan mengganti theme aktif tanpa memahami dampaknya terhadap tampilan seluruh website.

Membuat theme baru adalah pekerjaan **developer**. Lihat `docs/05-Theme-Development-Guide.md` (bukan panduan operator).

---

## 13. Audit

Menu **Audit** → `/admin/audit` *(permission: audit.view — biasanya Admin)*

**Audit Trail** adalah catatan aktivitas administratif **read-only** untuk kepatuhan.

Kolom yang ditampilkan:

- When (waktu)
- Event
- Actor
- Resource
- Revision

V1 menampilkan daftar peristiwa terbaru. **Tidak ada** filter, pagination, atau halaman detail di V1.

Audit **tidak** menampilkan password, token, atau secret.

---

## 14. Managing Users

Menu **Users** → `/admin/users` *(permission: `user.manage` — hanya Admin)*

Fitur ini memungkinkan Admin mengelola akun staf **Editor** dan **Contributor** tanpa bantuan developer/CLI.

### Siapa yang dapat mengelola pengguna?

Hanya akun **Admin** dengan permission `user.manage`. Editor dan Contributor **tidak** melihat menu Users dan **tidak** dapat membuka halaman manajemen pengguna.

### Membuat akun staf

1. Buka **Users** → klik **Create User**.
2. Isi **Username** (huruf kecil, angka, titik; 3–30 karakter).
3. Isi **Email** (disimpan terenkripsi di database).
4. Pilih **Role**: **Editor** atau **Contributor** (default: Contributor).
5. Centang **Active** jika akun harus bisa login segera.
6. Tetapkan **password awal**; pengguna akan diminta mengganti password saat login pertama (sama seperti Admin setelah `cms:install`).
7. Klik **Create user**.

### Mengedit akun staf

1. Di daftar Users, klik **Edit** pada baris pengguna.
2. Anda dapat mengubah email, role (Editor ↔ Contributor), dan status aktif.
3. **Password tidak diubah** dari form ini — pengguna mengganti password melalui `/cp/password-change` setelah login, atau melalui alur reset password jika tersedia.

### Aktifkan / nonaktifkan akun

- **Deactivate** — akun tidak dapat login; data akun tetap ada.
- **Activate** — mengaktifkan kembali akun yang dinonaktifkan.

Perubahan status dicatat di Audit Trail (`USER_ACTIVATED` / `USER_DEACTIVATED`).

### Batasan peran (single-Admin)

SMITE CMS mengharuskan **tepat satu akun Admin** yang dapat digunakan:

- Form **tidak** menawarkan peran Admin untuk akun baru.
- Akun Editor/Contributor **tidak dapat dipromosikan** menjadi Admin dari UI ini.
- Akun Admin **tidak dapat dinonaktifkan** jika ia satu-satunya Admin.
- Peran Admin **tidak dapat diubah** melalui form edit.

Jika database berisi lebih dari satu Admin (data lama), sistem menampilkan peringatan di halaman Users tetapi **tidak** memperbaiki data secara otomatis.

### Password

- Password awal ditetapkan Admin saat membuat akun.
- Semua pengaturan password (instalasi, pergantian wajib, reset, pembuatan akun staf) mengikuti **kebijakan Shield** yang sama — aturan teknis ada di konfigurasi server (`app/Config/Auth.php`), bukan aturan terpisah di aplikasi.
- Password **tidak boleh** mengandung bagian username atau email Anda (validator Shield).
- Password **tidak** ditampilkan kembali di halaman setelah submit, **tidak** dicatat di Audit Trail, dan **tidak** muncul di daftar pengguna.

---

## 15. Content Lifecycle

### Page

```text
DRAFT
  → PUBLISHED        (tombol Publish)
  → UNPUBLISHED      (tombol Unpublish)
  → ARCHIVED         (tombol Archive)
  → TRASH            (tombol Trash)
  → Restore          (dari Trash)
  → Permanent Delete (dari Trash, jika authorized)
```

### Post

```text
DRAFT
  → PENDING_REVIEW   (Submit for Review — Contributor)
  → PUBLISHED        (Publish — Editor/Admin, atau Review & Publish)
  → UNPUBLISHED / ARCHIVED / TRASH
  → Restore / Permanent Delete (sesuai permission)
```

Tombol yang Anda lihat bergantung pada **status saat ini** dan **peran/permission** akun.

### Revisi dan jadwal

- **Revision history** — simpan riwayat perubahan; dapat dipulihkan jika authorized.
- **Scheduled publish / unpublish** — jadwalkan publikasi; membutuhkan cron server yang menjalankan `php spark cms:scheduled-content` (diatur administrator server).

---

## 16. Permission — jika tombol tidak muncul

| Peran | Ringkasan akses |
|---|---|
| **Admin** | Semua area termasuk Settings, Themes, Audit |
| **Editor** | Pages, Posts (termasuk publish/review), kategori, tag, media |
| **Contributor** | Draft Post milik sendiri, submit review, media terbatas; **tidak** bisa publish |

Jika menu atau tombol tertentu tidak tersedia, akun Anda mungkin tidak memiliki permission. Hubungi Admin atau developer — **bukan** dengan mengubah kode.

V1 **tidak** menyediakan UI manajemen user/role di Control Panel.

---

## 17. Practical Recipes

### Recipe A — Membuat halaman “Tentang Kami”

1. **Pages** → **Create Page**
2. Title: `Tentang Kami`
3. Slug: `tentang-kami`
4. Locale: `id`
5. Isi Body/Hero sesuai kebutuhan
6. (Opsional) isi SEO
7. **Create page**
8. Buka halaman edit → klik **Publish**
9. Buka URL publik: `/tentang-kami`
10. **Menus** → buat item PRIMARY yang menunjuk ke Page ID halaman ini

### Recipe B — Membuat berita

1. **Categories** → buat kategori jika perlu
2. **Posts** → **Create Post**
3. Title, Slug, Locale `id`, **Author (public)** diisi manual
4. Pilih Category/Tags
5. Isi Body
6. **Create post** → **Publish**
7. Buka `/news/{slug}` (contoh: `/news/pengumuman-baru`)

### Recipe C — Upload gambar dan pakai di Page

1. **Media** → **Upload Media** → pilih JPEG/PNG/WebP/GIF
2. **Pages** → edit Page → field **Hero Image** → buka Media Picker → pilih gambar
3. **Update page** → **Publish**
4. Periksa tampilan di website publik

### Recipe D — Menambahkan Page ke menu utama

1. Catat **ID** Page dari daftar Pages
2. **Menus** → **New menu item**
3. Location: **PRIMARY**
4. Destination type: **Page**
5. Page ID: isi angka ID
6. Label: misalnya `Tentang Kami`
7. Atur **Display order**, centang **Active**
8. **Create menu item**

### Recipe E — Mengubah nama dan deskripsi website

1. **Settings** → ubah **Site name** dan **Site description**
2. **Save settings**
3. Buka `/` untuk melihat perubahan pada landing page Theme 2026

---

## 18. Demo / Starter Content

Instalasi baru (`cms:install`) menghasilkan CMS siap pakai dengan Theme 2026, **tanpa** Page atau Post.

Developer/server administrator **dapat** (opsional) menjalankan:

```bash
php spark cms:demo
```

Perintah ini menambahkan konten contoh:

| URL | Isi |
|---|---|
| `/about` | Page About |
| `/contact` | Page Contact |
| `/berita` | Page landing berita |
| `/news/welcome` | Post contoh |

**Operator tidak menjalankan perintah ini** dalam pekerjaan sehari-hari. Setelah demo content ada, kelola seperti konten biasa melalui Control Panel.

---

## 19. Common Mistakes

### “Saya membuat Page tetapi tidak muncul di menu”

Membuat Page tidak otomatis menambah menu. Buat **menu item** di **Menus** yang menunjuk ke Page tersebut.

### “Saya membuat Post tetapi URL-nya bukan `/news`”

URL Post adalah `/news/{slug}`, bukan `/news`. `/news` sendiri bukan halaman arsip di V1.

### “Page/Post tidak tampil di website”

Periksa:

- status harus **Published**
- **locale** konten harus sesuai primary locale situs
- slug benar

### “Saya tidak melihat tombol Publish / Settings / Themes”

Kemungkinan permission atau peran akun. Contributor tidak bisa publish. Hanya Admin tertentu yang mengelola Settings/Themes.

### “Saya ingin mengubah tampilan website”

Gunakan **Themes** (dengan hati-hati) atau minta developer membuat penyesuaian theme. Jangan mengedit file aplikasi.

### “Saya ingin meng-update CMS”

Itu update **software**, bukan update konten. Lihat [UPDATE.md](UPDATE.md) dan hubungi developer.

---

## 20. Security

- Jangan membagikan password Control Panel.
- Gunakan password kuat; ganti password awal instalasi segera.
- Jangan menempelkan secret (API key, password database) ke dalam konten website.
- Berikan akses Admin hanya kepada orang yang memang membutuhkan.
- Selalu gunakan **HTTPS** saat login.
- Jangan mengunggah file executable (`.php`, `.exe`, dll.).
- Logout setelah menggunakan Control Panel di komputer bersama.

---

## 21. Jika Membutuhkan Bantuan Developer

Hubungi developer/server administrator untuk:

- instalasi awal, domain, HTTPS, database
- update versi CMS
- backup dan restore
- konfigurasi email (SMTP)
- penjadwalan cron (`cms:scheduled-content`)
- theme kustom atau perubahan tampilan besar
- penambahan akun staff atau perubahan peran

---

## 22. Dokumentasi Lain

| Dokumen | Untuk siapa |
|---|---|
| [ADMIN-USER-GUIDE.md](ADMIN-USER-GUIDE.md) | **Operator** — panduan ini |
| [ADMIN-CONTROL-PANEL.md](ADMIN-CONTROL-PANEL.md) | Ringkasan area Control Panel |
| [FIRST-RUN.md](FIRST-RUN.md) | Login pertama dan setup awal |
| [INSTALLATION.md](INSTALLATION.md) | Instalasi server |
| [CONFIGURATION.md](CONFIGURATION.md) | Konfigurasi `.env` |
| [UPDATE.md](UPDATE.md) | Update software CMS |
| [BACKUP-RESTORE.md](BACKUP-RESTORE.md) | Backup database dan upload |
| [PRODUCTION-CHECKLIST.md](PRODUCTION-CHECKLIST.md) | Checklist go-live |
| [DEVELOPER-CLIENT-DEPLOYMENT.md](../DEVELOPER-CLIENT-DEPLOYMENT.md) | SOP developer onboarding klien |
| [05-Theme-Development-Guide.md](../05-Theme-Development-Guide.md) | Developer — membuat theme baru |
