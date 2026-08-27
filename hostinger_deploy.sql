-- ===================================================
-- Full Hostinger Deployment SQL File
-- Sistem Stok & Serah Terima Barang Logistik
-- Petunjuk: Import file ini langsung ke phpMyAdmin database Hostinger Anda
-- ===================================================

-- 1. Tabel Users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(150) NOT NULL,
  `username` VARCHAR(100) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','pekerja') NOT NULL DEFAULT 'pekerja',
  `session_token` VARCHAR(255) NULL,
  `last_activity_at` DATETIME NULL,
  `failed_login_count` INT DEFAULT 0,
  `locked_until` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabel Barang (Inventaris Bersama)
CREATE TABLE IF NOT EXISTS `barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_barang` VARCHAR(255) NOT NULL,
  `deskripsi` TEXT NULL,
  `satuan` VARCHAR(20) NOT NULL DEFAULT 'pcs',
  `stok_awal` INT NOT NULL DEFAULT 0,
  `stok_saat_ini` INT NOT NULL DEFAULT 0,
  `dibuat_oleh` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `chk_stok_non_negatif` CHECK (`stok_saat_ini` >= 0),
  CONSTRAINT `fk_barang_dibuat_oleh` FOREIGN KEY (`dibuat_oleh`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabel Foto Master Barang
CREATE TABLE IF NOT EXISTS `foto_barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `format_asli` VARCHAR(10) NULL,
  `nama_file_server` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_foto_barang` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabel Transaksi (Log Serah Terima)
CREATE TABLE IF NOT EXISTS `transaksi` (
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
CREATE TABLE IF NOT EXISTS `foto_transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaksi_id` INT NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `format_asli` VARCHAR(10) NULL,
  `nama_file_server` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_foto_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabel Restock (Stok Masuk, dikelola Admin)
CREATE TABLE IF NOT EXISTS `restock_log` (
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
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NULL,
  `aksi` VARCHAR(100) NOT NULL,
  `detail` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================
-- SEED DATA AWAL (Akun Default & Barang Master)
-- ===================================================

-- Akun Default:
-- Admin: admin_logistik / Admin#12345
-- Pekerja: pekerja_budi / Pekerja#12345
INSERT INTO `users` (`id`, `nama`, `username`, `password_hash`, `role`) VALUES
(1, 'Admin Logistik (CO)', 'admin_logistik', '$2y$12$M.WmlpVcba4Bc0pa1VSOguWVxLrLb6EuCRvD2PfGe9quvnubhmgBG', 'admin'),
(2, 'Budi Santoso', 'pekerja_budi', '$2y$12$i5zxHuVXKoCdgI56Ej6wju929vPwBk72KkrYq87D9ZJLYltDCQcau', 'pekerja'),
(3, 'Siti Rahma', 'pekerja_siti', '$2y$12$i5zxHuVXKoCdgI56Ej6wju929vPwBk72KkrYq87D9ZJLYltDCQcau', 'pekerja')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`);

INSERT INTO `barang` (`id`, `nama_barang`, `deskripsi`, `satuan`, `stok_awal`, `stok_saat_ini`, `dibuat_oleh`) VALUES
(1, 'Kursi Lipat Futura Red', 'Kursi lipat besi stainles busa merah untuk acara & rapat.', 'unit', 50, 50, 1),
(2, 'Meja Lipat HPL White 120x60', 'Meja lipat portable lapis HPL putih rangka alumunium.', 'unit', 20, 20, 1),
(3, 'Sound System Portable 15 Inch', 'Speaker aktif portable dengan 2 mic wireless + bluetooth.', 'set', 5, 5, 1),
(4, 'Kabel Roll Stop Kontak 50 Meter', 'Kabel gulung outdoor heavy duty 4 lubang stop kontak.', 'unit', 10, 10, 1),
(5, 'Backdrop Stand Display 3x2m', 'Stand lipat kerangka alumunium untuk banner backdrop.', 'set', 4, 4, 1)
ON DUPLICATE KEY UPDATE `nama_barang` = VALUES(`nama_barang`);

INSERT INTO `audit_log` (`user_id`, `aksi`, `detail`, `ip_address`) VALUES
(1, 'SYSTEM_INIT', 'Sistem Logistik Barang berhasil di-seed di Hostinger.', '127.0.0.1');
