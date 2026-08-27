# Rancangan Sistem — Sistem Stok & Serah Terima Barang Divisi Logistik

**Versi:** 1.1 (Revisi — role disederhanakan, export dihapus)
**Tanggal:** 27 Agustus 2026
**Referensi Stack:** Diselaraskan dengan `RANCANGAN_SISTEM_REKAPAN_BARANG_FINAL.md` (Sistem Rekapan Barang Event)

**Stack Teknologi:**
- **Frontend:** HTML5 + Tailwind CSS (via CDN) + Inter Google Font + Vanilla JavaScript + `heic2any` (Client-side HEIC Converter)
- **Backend:** PHP Native 8.x (Hostinger / Apache / LiteSpeed Optimized)
- **Database:** MySQL / MariaDB via PDO Prepared Statements
- **Image Engine:** Hybrid Multi-Tier Pipeline (Client JS `heic2any` → Server `Imagick` → Python CLI `pillow-heif` → Server `GD WebP`)

---

## 1. Ringkasan Sistem

Sistem Stok & Serah Terima Barang Logistik adalah aplikasi berbasis web untuk mencatat **stok barang** dan **arus keluar barang** pada divisi logistik yang beranggotakan **1 Admin** dan beberapa **Pekerja Logistik**.

Sistem ini berbasis **inventaris bersama (shared stock)**: seluruh anggota mengakses katalog barang yang sama, dan setiap laporan serah terima **otomatis memotong stok** barang terkait secara realtime, sehingga Admin selalu tahu sisa stok tanpa harus rekap manual.

**Konsep inti (dari studi kasus):**
> Admin input barang "Kursi" stok 50 → salah satu anggota logistik (bisa Admin sendiri atau Pekerja) menyerahkan 10 kursi ke seseorang → yang menyerahkan melapor lewat web: pilih barang, jumlah, tulis nama penerima secara bebas, upload bukti foto → sistem otomatis mengurangi stok kursi menjadi 40 → riwayat tersimpan dan langsung terlihat di dashboard Admin.

Sistem dilengkapi proteksi **Single Active Session**, **Inactivity Timeout**, **Account Lockout Brute-Force Protection**, **DB Transaction (BEGIN...COMMIT)** untuk mencegah race condition saat stok berkurang bersamaan, serta **validasi anti stok minus**.

---

## 2. Role & Matriks Hak Akses

Sistem hanya punya **2 role**: `admin` dan `pekerja`. Tidak ada tingkatan "superadmin" terpisah — Admin di sini berperan sebagai **CO (Coordinator) yang tetap ikut bekerja di lapangan**, termasuk menyerahkan barang seperti anggota lain. Semua akun `pekerja` **setara** — tidak ada perbedaan hak akses antar pekerja, perbedaan hanya nama masing-masing orang.

| Fitur / Modul | Admin | Pekerja |
|---|:---:|:---:|
| Login & Logout | ✅ | ✅ |
| Kelola Akun Pekerja (Buat, Reset Password, Unlock, Reset Sesi) | ✅ | ❌ |
| Master Barang (Tambah, Edit, Hapus, Upload Foto, Set Stok Awal) | ✅ | ❌ (hanya lihat) |
| Tambah Stok Masuk (Restock barang lama) | ✅ | ❌ |
| Katalog Barang & Cek Sisa Stok | ✅ | ✅ |
| Form Lapor Serah Terima Barang (+ Bukti Foto) | ✅ | ✅ |
| Otomatisasi Potong Stok Saat Transaksi Dikonfirmasi | Sistem | Sistem |
| Riwayat Transaksi Seluruh Anggota (Audit Log) | ✅ (semua) | ✅ (transaksi milik sendiri saja) |
| Filter Riwayat (tanggal, nama barang, nama pelapor) | ✅ | ✅ (terbatas pada data sendiri) |
| Dashboard Stok Realtime | ✅ | ✅ (view katalog) |
| Single Active Session (1 Akun = 1 Device) | ✅ | ✅ |
| Inactivity Auto-Logout | ✅ | ✅ |

> Fitur export ke Excel/Word **tidak diikutkan** dalam rancangan ini — Admin cukup memantau lewat dashboard & riwayat transaksi di web.

### 2.1 Admin
- Mengelola **Master Barang**: nama barang, deskripsi, stok awal, foto barang (multi-foto, mendukung HEIC/PNG/JPG).
- Menambah **stok masuk** (restock) untuk barang yang sudah ada, dicatat sebagai log tersendiri (bukan transaksi serah terima).
- Mengelola akun pekerja (buat akun, reset password, unlock akun terkunci, reset sesi paksa).
- Memantau **Dashboard Stok Realtime** — melihat sisa stok tiap barang, dan siapa yang terakhir melakukan transaksi.
- Melihat **seluruh riwayat serah terima** dari semua anggota (termasuk transaksinya sendiri), dengan filter tanggal / nama barang / nama pelapor.
- **Ikut mengisi Form Lapor Serah Terima** — karena Admin juga turun langsung menyerahkan barang, bukan hanya mengawasi.

### 2.2 Pekerja Logistik
- Semua akun pekerja punya hak akses yang sama persis — tidak ada pembagian tugas/level di sistem, hanya beda nama pemilik akun.
- Melihat **Katalog Barang**: daftar barang, foto, dan sisa stok saat ini, untuk memastikan ketersediaan sebelum menyerahkan barang.
- Mengisi **Form Lapor Serah Terima**:
  - Pilih barang yang diserahkan.
  - Masukkan jumlah barang.
  - **Tulis nama penerima secara bebas** (teks bebas, bukan pilih dari daftar akun — penerima bisa siapa saja, internal maupun eksternal).
  - Upload **bukti foto** penyerahan (wajib, mendukung multi-foto).
  - Catatan opsional (kondisi barang, tujuan/acara, dsb).
- Sistem **otomatis menolak** jika jumlah yang dilaporkan melebihi stok yang tersedia saat itu.
- Sistem **otomatis memotong stok** begitu laporan berhasil dikirim (dalam satu DB transaction bersama insert log).
- Melihat **riwayat pribadi** — transaksi yang pernah dilaporkan oleh akun tersebut.

---

## 3. Model Data & Skema Database (MySQL / MariaDB)

Database bernama `logistik_barang`. Seluruh tabel menggunakan engine **InnoDB** dengan `utf8mb4_unicode_ci`, konsisten dengan sistem event sebelumnya.

### 3.1 DDL Database (`schema.sql`)

```sql
CREATE DATABASE IF NOT EXISTS `logistik_barang` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `logistik_barang`;

-- 1. Tabel Users (hanya 2 role: admin & pekerja, semua pekerja setara)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(150) NOT NULL,
  `username` VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','pekerja') NOT NULL,
  `session_token` VARCHAR(255) NULL,
  `last_activity_at` DATETIME NULL,
  `failed_login_count` INT DEFAULT 0,
  `locked_until` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Barang (Inventaris Bersama)
CREATE TABLE `barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_barang` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT NULL,
  `stok_awal` INT NOT NULL DEFAULT 0,
  `stok_saat_ini` INT NOT NULL DEFAULT 0,
  `dibuat_oleh` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `chk_stok_non_negatif` CHECK (`stok_saat_ini` >= 0),
  CONSTRAINT `fk_barang_dibuat_oleh` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Foto Master Barang
CREATE TABLE `foto_barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `format_asli` VARCHAR(10),
  `nama_file_server` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_foto_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Transaksi (Log Serah Terima)
-- Catatan: penyerah = akun yang login & melapor (admin ATAU pekerja, karena admin juga ikut kerja lapangan)
--          penerima = teks bebas yang ditulis pelapor di form, BUKAN dipilih dari daftar akun
CREATE TABLE `transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `penyerah_id` INT NOT NULL COMMENT 'Akun (admin/pekerja) yang menyerahkan & melapor',
  `nama_penerima` VARCHAR(150) NOT NULL COMMENT 'Ditulis bebas oleh pelapor di form',
  `jumlah` INT NOT NULL,
  `stok_sebelum` INT NOT NULL,
  `stok_sesudah` INT NOT NULL,
  `catatan` TEXT NULL,
  `waktu_transaksi` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `chk_jumlah_positif` CHECK (`jumlah` > 0),
  CONSTRAINT `fk_transaksi_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transaksi_penyerah` FOREIGN KEY (`penyerah_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabel Bukti Foto Transaksi
CREATE TABLE `foto_transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaksi_id` INT NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `format_asli` VARCHAR(10),
  `nama_file_server` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_foto_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Restock (Stok Masuk, dikelola Admin)
CREATE TABLE `restock_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `jumlah_tambahan` INT NOT NULL,
  `stok_sebelum` INT NOT NULL,
  `stok_sesudah` INT NOT NULL,
  `dicatat_oleh` INT NOT NULL,
  `catatan` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_restock_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_restock_user` FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabel Audit Log (Umum)
CREATE TABLE `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `aksi` VARCHAR(100) NOT NULL,
  `detail` TEXT NULL,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> **Catatan:** `stok_sebelum` / `stok_sesudah` disimpan langsung di tiap baris `transaksi` dan `restock_log` agar riwayat tetap akurat dan bisa diaudit meski `barang.stok_saat_ini` terus berubah.

### 3.2 Kenapa Bukan `kuantitas VARCHAR` seperti Sistem Event?

Pada sistem event, `kuantitas` sengaja dibuat `VARCHAR` karena bebas format teks ("150 Pcs", "10 Unit"). Pada sistem logistik ini, kuantitas **harus berupa angka murni (`INT`)** karena dipakai langsung untuk operasi pengurangan/penambahan stok otomatis. Jika unit barang perlu ditampilkan (pcs, dus, buah), tambahkan kolom `satuan VARCHAR(20)` di tabel `barang` — kolom ini hanya label tampilan, tidak memengaruhi kalkulasi.

---

## 4. Alur Transaksi Serah Terima (Inti Sistem)

Form serah terima **bisa diisi oleh Admin maupun Pekerja** — keduanya sama-sama bisa jadi pihak yang menyerahkan barang.

### 4.1 Endpoint `serah_terima/lapor.php` (dapat diakses admin & pekerja)

```
1. Validasi CSRF token & sesi aktif (role = admin ATAU pekerja)
2. Validasi input: barang_id, jumlah (harus > 0), nama_penerima (teks bebas, wajib diisi, min 2 karakter)
3. Upload & proses bukti foto (lihat §5 Pipeline Upload) → dapatkan path file final
4. BEGIN TRANSACTION
   a. SELECT stok_saat_ini FROM barang WHERE id = :barang_id FOR UPDATE   -- row lock, cegah race condition
   b. Jika jumlah > stok_saat_ini → ROLLBACK, kembalikan error "Stok tidak mencukupi (sisa: X)"
   c. UPDATE barang SET stok_saat_ini = stok_saat_ini - :jumlah WHERE id = :barang_id
   d. INSERT INTO transaksi (barang_id, penyerah_id, nama_penerima, jumlah,
      stok_sebelum, stok_sesudah, catatan, waktu_transaksi)
   e. INSERT INTO foto_transaksi (transaksi_id, file_path, ...) untuk tiap file bukti
   f. INSERT INTO audit_log (aksi = 'serah_terima_barang', detail = ...)
5. COMMIT TRANSACTION
6. Response sukses → tampilkan konfirmasi + sisa stok terbaru
```

**Poin kritis:**
- `SELECT ... FOR UPDATE` mengunci baris barang selama transaksi berlangsung, sehingga jika dua orang melapor bersamaan untuk barang yang sama, transaksi kedua akan menunggu transaksi pertama selesai — mencegah stok minus akibat race condition.
- Validasi stok dilakukan **di dalam** transaction (bukan sebelum), agar data yang dicek benar-benar data terkini saat lock diambil.
- Jika upload foto gagal di tengah proses, seluruh transaction di-ROLLBACK agar tidak ada transaksi "setengah jadi".
- Endpoint ini **satu jenis form untuk semua role** — tidak ada form terpisah untuk admin vs pekerja, karena admin juga ikut menyerahkan barang.

### 4.2 Endpoint `admin/restock.php` (Stok Masuk, khusus Admin)

Alur sejenis tapi lebih sederhana (tidak perlu validasi stok minus, karena menambah):
```
BEGIN TRANSACTION
  UPDATE barang SET stok_saat_ini = stok_saat_ini + :jumlah_tambahan
  INSERT INTO restock_log (...)
COMMIT TRANSACTION
```

---

## 5. Pipeline Multi-Upload & Konversi Gambar (Hybrid 4-Tier)

Sama persis dengan sistem event, dipakai baik untuk **foto master barang** maupun **foto bukti serah terima**:

```
[User Input File (HEIC / PNG / JPG)]
       │
       ├── Tier 1 (Client JS): Preview & Konversi HEIC via heic2any.js di Browser
       │
       └── Form Submit ke PHP Server (upload_helper.php)
              │
              ├── Validasi Ukuran (Max 10MB)
              ├── Validasi Filename Scrutiny (Anti Dangerous/Double Ext)
              ├── Validasi Magic Bytes (finfo_file MIME Check)
              ├── Validasi PHP Script Payload Regex Check inside Images
              │
              ├── Process HEIC (Server Fallback Pipeline):
              │     ├── Tier 2: Imagick PHP Extension (`setImageFormat('webp')`)
              │     ├── Tier 3: Python CLI (`scripts/convert_heic.py` via `pillow-heif`)
              │     └── Tier 4: GD Library Fallback (`imagewebp`)
              │
              └── Simpan File Akhir ke `/uploads/barang/{uuid}.webp` atau `/uploads/transaksi/{uuid}.webp`
```

Aturan keamanan file upload identik dengan sistem event: batas 10MB, generasi nama UUID v4, `.htaccess` `php_flag engine off` di folder `/uploads/`, dsb.

---

## 6. Alur Autentikasi & Keamanan Sesi

Diadopsi 1:1 dari sistem event, dengan 2 role (`admin`, `pekerja`):

- **Account Lockout**: 5x gagal login berturut-turut → akun terkunci 15 menit, tercatat di `audit_log`.
- **Single Active Session**: 1 akun hanya bisa aktif di 1 device; token 64-karakter hex disimpan di `users.session_token`.
- **Inactivity Timeout**: auto-logout setelah periode tidak aktif (rekomendasi: 15-30 menit, sesuaikan karena tim ini bekerja mobile/lapangan).
- **Heartbeat Ping** (`/auth/ping.php`) menjaga sesi tetap hidup selama halaman dibuka.
- Redirect kompatibel LiteSpeed/Hostinger (`response_success_redirect()`) untuk mencegah cookie drop.

---

## 7. Proteksi Keamanan Aplikasi

1. **SQL Injection**: 100% PDO Prepared Statements.
2. **XSS**: seluruh output di-escape via helper `e($val)` (`htmlspecialchars`).
3. **CSRF**: token tersembunyi di setiap form POST, divalidasi per submit.
4. **Race Condition pada Stok**: `SELECT ... FOR UPDATE` + `BEGIN...COMMIT` (§4.1) — ini fokus keamanan utama sistem, karena isu paling kritis di sini adalah **integritas angka stok**, bukan isolasi data antar akun.
5. **Validasi Stok Minus**: ditolak di level backend sebelum `UPDATE` dijalankan, bukan hanya validasi di frontend.
6. **Upload File**: pipeline sama seperti §5, mencegah webshell/polyglot file.

---

## 8. Struktur File & Folder Project

```
logistikbarang/
├── RANCANGAN_SISTEM_LOGISTIK_BARANG.md   # Dokumen rancangan ini
├── schema.sql                            # DDL Database
├── seed.sql                              # Akun admin default
│
├── config/
│   ├── database.php                      # Koneksi PDO
│   ├── helpers.php                       # XSS, Sesi, Redirect, Audit Log
│   ├── csrf.php                          # CSRF Generator & Validator
│   └── upload_helper.php                 # UUID, Multi-Upload, HEIC Converter
│
├── public/
│   ├── .htaccess
│   ├── index.php                         # Entry & Role Router
│   │
│   ├── auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   └── ping.php
│   │
│   ├── middleware/
│   │   └── auth.php                      # Inactivity Timeout & Role Protection
│   │
│   ├── katalog.php                       # Lihat barang & sisa stok (admin & pekerja)
│   │
│   ├── serah_terima/
│   │   ├── lapor.php                     # Form lapor + proses transaksi (§4.1) — admin & pekerja
│   │   └── riwayat_saya.php              # Riwayat transaksi pribadi (admin & pekerja)
│   │
│   ├── admin/
│   │   ├── dashboard.php                 # Dashboard stok realtime
│   │   ├── master_barang.php             # Tambah/Edit/Hapus barang
│   │   ├── restock.php                   # Tambah stok masuk (§4.2)
│   │   ├── kelola_akun.php               # Kelola akun pekerja
│   │   └── riwayat_transaksi.php         # Semua riwayat + filter
│   │
│   └── uploads/
│       ├── barang/
│       ├── transaksi/
│       └── .htaccess                     # php_flag engine off
│
├── scripts/
│   └── convert_heic.py
│
└── vendor/
```

---

## 9. Panduan Konfigurasi & Deployment

Identik dengan sistem event: PHP 8.1+/8.2/8.3, ekstensi `pdo_mysql`, `gd`, `fileinfo`, `imagick` (opsional), `zip`, `xml`. Import `schema.sql` lalu `seed.sql` untuk akun Admin awal (username `admin_logistik`, password default segera diganti setelah login pertama).

---

## 10. Perbedaan Utama vs Sistem Rekapan Barang Event

| Aspek | Sistem Rekapan Barang Event | Sistem Logistik (Baru) |
|---|---|---|
| Model Data Inti | Barang milik 1 pekerjaan/event | Barang = inventaris bersama seluruh divisi |
| Role | Superadmin, Admin, Pekerja (3 level) | Admin, Pekerja (2 level, semua pekerja setara) |
| Siapa Bisa Serah Terima | Hanya pekerja | Admin dan pekerja sama-sama bisa (admin ikut kerja lapangan) |
| Kuantitas | `VARCHAR` bebas teks | `INT` murni (dipakai kalkulasi otomatis) |
| Penerima Barang | — | Ditulis bebas oleh pelapor, tanpa dipilih dari daftar akun |
| Operasi Utama | Input data barang statis | Transaksi serah terima yang mengubah stok |
| Risiko Keamanan Khusus | Anti-IDOR antar pekerjaan | Race condition saat potong stok bersamaan |
| Isolasi Data | Ketat per akun pekerja | Katalog & stok terbuka untuk semua anggota |
| Export Laporan | Ada (Excel/Word) | Tidak ada — cukup dashboard & riwayat di web |
| Bukti Foto | Foto barang saja | Foto barang (master) + foto bukti serah terima |

---

*Dokumen rancangan ini disusun sebagai baseline desain sebelum implementasi di Antigravity — lihat `PROMPT_ANTIGRAVITY_SISTEM_LOGISTIK.md` untuk prompt build-nya.*
