-- ===================================================
-- Seed Data Initial: logistik_barang
-- Akun Default & Data Master Barang Awal
-- ===================================================

USE `logistik_barang`;

-- 1. Insert Initial Users (Hanya 2 Akun Admin)
-- Password Admin 1 (admin_logistik): Admin#12345
-- Password Admin 2 (admin_lapangan): Admin#12345
INSERT INTO `users` (`id`, `nama`, `username`, `password_hash`, `role`) VALUES
(1, 'Admin Logistik (CO)', 'admin_logistik', '$2y$12$M.WmlpVcba4Bc0pa1VSOguWVxLrLb6EuCRvD2PfGe9quvnubhmgBG', 'admin'),
(2, 'Admin Lapangan 2', 'admin_lapangan', '$2y$12$M.WmlpVcba4Bc0pa1VSOguWVxLrLb6EuCRvD2PfGe9quvnubhmgBG', 'admin')
ON DUPLICATE KEY UPDATE `nama` = VALUES(`nama`), `role` = VALUES(`role`);

-- 2. Insert Initial Master Barang (Inventaris Bersama)
INSERT INTO `barang` (`id`, `nama_barang`, `deskripsi`, `satuan`, `stok_awal`, `stok_saat_ini`, `dibuat_oleh`) VALUES
(1, 'Kursi Lipat Futura Red', 'Kursi lipat besi stainles busa merah untuk acara & rapat.', 'unit', 50, 50, 1),
(2, 'Meja Lipat HPL White 120x60', 'Meja lipat portable lapis HPL putih rangka alumunium.', 'unit', 20, 20, 1),
(3, 'Sound System Portable 15 Inch', 'Speaker aktif portable dengan 2 mic wireless + bluetooth.', 'set', 5, 5, 1),
(4, 'Kabel Roll Stop Kontak 50 Meter', 'Kabel gulung outdoor heavy duty 4 lubang stop kontak.', 'unit', 10, 10, 1),
(5, 'Backdrop Stand Display 3x2m', 'Stand lipat kerangka alumunium untuk banner backdrop.', 'set', 4, 4, 1)
ON DUPLICATE KEY UPDATE `nama_barang` = VALUES(`nama_barang`);

-- 3. Initial Audit Log Entry
INSERT INTO `audit_log` (`user_id`, `aksi`, `detail`, `ip_address`) VALUES
(1, 'SYSTEM_INIT', 'Sistem Logistik Barang berhasil di-seed dengan data awal.', '127.0.0.1');
