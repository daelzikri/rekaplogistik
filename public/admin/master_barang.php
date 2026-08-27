<?php
/**
 * Master Barang Management (Admin Only)
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/upload_helper.php';
require_once __DIR__ . '/../../config/csrf.php';

$user = require_role(['admin']);
$db = get_db_connection();

$error = null;
$success = null;

// Handle Form Submissions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $namaBarang = trim($_POST['nama_barang'] ?? '');
        $deskripsi  = trim($_POST['deskripsi'] ?? '');
        $satuan     = trim($_POST['satuan'] ?? 'pcs');
        $stokAwal   = (int)($_POST['stok_awal'] ?? 0);

        if (empty($namaBarang)) {
            set_flash_message('error', 'Nama barang wajib diisi.');
        } elseif ($stokAwal < 0) {
            set_flash_message('error', 'Stok awal tidak boleh bernilai negatif.');
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("INSERT INTO barang (nama_barang, deskripsi, satuan, stok_awal, stok_saat_ini, dibuat_oleh) VALUES (:nama, :desk, :satuan, :awal, :saat_ini, :user_id)");
                $stmt->execute([
                    'nama'     => $namaBarang,
                    'desk'     => $deskripsi,
                    'satuan'   => $satuan ?: 'pcs',
                    'awal'     => $stokAwal,
                    'saat_ini' => $stokAwal,
                    'user_id'  => $user['id']
                ]);
                $barangId = $db->lastInsertId();

                // Process Multiple Photos if uploaded
                if (!empty($_FILES['foto_barang']['name'][0])) {
                    $uploadedFiles = process_multiple_image_uploads($_FILES['foto_barang'], 'barang');
                    $fStmt = $db->prepare("INSERT INTO foto_barang (barang_id, file_path, format_asli, nama_file_server) VALUES (:b_id, :path, :fmt, :name)");
                    foreach ($uploadedFiles as $f) {
                        $fStmt->execute([
                            'b_id' => $barangId,
                            'path' => $f['file_path'],
                            'fmt'  => $f['format_asli'],
                            'name' => $f['nama_file_server']
                        ]);
                    }
                }

                write_audit_log($db, $user['id'], 'TAMBAH_BARANG', "Menambahkan barang baru: '{$namaBarang}' dengan stok awal {$stokAwal} {$satuan}.");
                $db->commit();
                set_flash_message('success', "Barang '{$namaBarang}' berhasil ditambahkan.");
                header('Location: ' . base_url('public/admin/master_barang.php'));
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                set_flash_message('error', 'Gagal menambah barang: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'edit') {
        $barangId   = (int)($_POST['barang_id'] ?? 0);
        $namaBarang = trim($_POST['nama_barang'] ?? '');
        $deskripsi  = trim($_POST['deskripsi'] ?? '');
        $satuan     = trim($_POST['satuan'] ?? 'pcs');

        if ($barangId <= 0 || empty($namaBarang)) {
            set_flash_message('error', 'Data barang tidak valid.');
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare("UPDATE barang SET nama_barang = :nama, deskripsi = :desk, satuan = :satuan WHERE id = :id");
                $stmt->execute([
                    'nama'   => $namaBarang,
                    'desk'   => $deskripsi,
                    'satuan' => $satuan ?: 'pcs',
                    'id'     => $barangId
                ]);

                // Upload additional photos if provided
                if (!empty($_FILES['foto_barang_baru']['name'][0])) {
                    $uploadedFiles = process_multiple_image_uploads($_FILES['foto_barang_baru'], 'barang');
                    $fStmt = $db->prepare("INSERT INTO foto_barang (barang_id, file_path, format_asli, nama_file_server) VALUES (:b_id, :path, :fmt, :name)");
                    foreach ($uploadedFiles as $f) {
                        $fStmt->execute([
                            'b_id' => $barangId,
                            'path' => $f['file_path'],
                            'fmt'  => $f['format_asli'],
                            'name' => $f['nama_file_server']
                        ]);
                    }
                }

                write_audit_log($db, $user['id'], 'EDIT_BARANG', "Mengubah data barang (ID: {$barangId}): '{$namaBarang}'.");
                $db->commit();
                set_flash_message('success', "Data barang '{$namaBarang}' berhasil diperbarui.");
                header('Location: ' . base_url('public/admin/master_barang.php'));
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                set_flash_message('error', 'Gagal memperbarui barang: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'hapus') {
        $barangId = (int)($_POST['barang_id'] ?? 0);
        if ($barangId > 0) {
            try {
                // Check if referenced in transactions or restock
                $cStmt = $db->prepare("SELECT (SELECT COUNT(*) FROM transaksi WHERE barang_id = :id1) + (SELECT COUNT(*) FROM restock_log WHERE barang_id = :id2) AS total_ref");
                $cStmt->execute(['id1' => $barangId, 'id2' => $barangId]);
                $refCount = (int)$cStmt->fetchColumn();

                if ($refCount > 0) {
                    set_flash_message('error', 'Barang ini tidak dapat dihapus karena sudah memiliki riwayat transaksi/restock. Silakan ubah deskripsi atau nama jika tidak lagi digunakan.');
                } else {
                    $db->beginTransaction();

                    // Get photos to delete from disk
                    $pStmt = $db->prepare("SELECT file_path FROM foto_barang WHERE barang_id = :id");
                    $pStmt->execute(['id' => $barangId]);
                    $photos = $pStmt->fetchAll();

                    // Delete item record (Cascade deletes foto_barang)
                    $bStmt = $db->prepare("DELETE FROM barang WHERE id = :id");
                    $bStmt->execute(['id' => $barangId]);

                    // Remove physical image files
                    foreach ($photos as $p) {
                        $fullPath = __DIR__ . '/../' . $p['file_path'];
                        if (file_exists($fullPath)) @unlink($fullPath);
                    }

                    write_audit_log($db, $user['id'], 'HAPUS_BARANG', "Menghapus barang (ID: {$barangId}).");
                    $db->commit();
                    set_flash_message('success', 'Barang berhasil dihapus.');
                }
                header('Location: ' . base_url('public/admin/master_barang.php'));
                exit;
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                set_flash_message('error', 'Gagal menghapus barang: ' . $e->getMessage());
            }
        }
    } elseif ($action === 'hapus_foto') {
        $fotoId = (int)($_POST['foto_id'] ?? 0);
        if ($fotoId > 0) {
            $fStmt = $db->prepare("SELECT file_path, barang_id FROM foto_barang WHERE id = :id");
            $fStmt->execute(['id' => $fotoId]);
            $foto = $fStmt->fetch();
            if ($foto) {
                $dStmt = $db->prepare("DELETE FROM foto_barang WHERE id = :id");
                $dStmt->execute(['id' => $fotoId]);

                $fullPath = __DIR__ . '/../' . $foto['file_path'];
                if (file_exists($fullPath)) @unlink($fullPath);

                write_audit_log($db, $user['id'], 'HAPUS_FOTO_BARANG', "Menghapus foto master barang (Foto ID: {$fotoId}).");
                set_flash_message('success', 'Foto master barang berhasil dihapus.');
            }
            header('Location: ' . base_url('public/admin/master_barang.php'));
            exit;
        }
    }
}

// Fetch Search & Data List
$search = trim($_GET['q'] ?? '');
$querySql = "
    SELECT b.*, u.nama AS pembuat,
           (SELECT COUNT(*) FROM foto_barang WHERE barang_id = b.id) AS total_foto,
           (SELECT file_path FROM foto_barang WHERE barang_id = b.id ORDER BY id ASC LIMIT 1) AS foto_utama
    FROM barang b
    LEFT JOIN users u ON b.dibuat_oleh = u.id
";
$params = [];

if ($search !== '') {
    $querySql .= " WHERE b.nama_barang LIKE :q OR b.deskripsi LIKE :q OR b.satuan LIKE :q";
    $params['q'] = "%{$search}%";
}

$querySql .= " ORDER BY b.id DESC";

$stmt = $db->prepare($querySql);
$stmt->execute($params);
$barangList = $stmt->fetchAll();

// Fetch all photos mapped by barang_id for modals
$allPhotosStmt = $db->query("SELECT id, barang_id, file_path, format_asli FROM foto_barang ORDER BY id ASC");
$allPhotos = [];
foreach ($allPhotosStmt->fetchAll() as $ph) {
    $allPhotos[$ph['barang_id']][] = $ph;
}

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Barang - Admin Logistik</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- heic2any for client-side HEIC conversion -->
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
</head>
<body class="min-h-full bg-slate-950 text-slate-100 flex flex-col font-sans">

    <!-- Top Navigation Bar -->
    <header class="bg-slate-900/90 backdrop-blur-md border-b border-slate-800 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo & Brand -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-lg shadow-indigo-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-base font-bold text-white tracking-wide">Logistik System</span>
                        <span class="hidden sm:inline-block ml-2 px-2 py-0.5 text-[10px] font-semibold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md">Admin Portal</span>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                    <a href="<?= base_url('public/admin/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Dashboard</a>
                    <a href="<?= base_url('public/admin/master_barang.php') ?>" class="px-3 py-2 rounded-lg text-white bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">Master Barang</a>
                    <a href="<?= base_url('public/admin/restock.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Restock</a>
                    <a href="<?= base_url('public/admin/kelola_akun.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Kelola Akun</a>
                    <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Semua Riwayat</a>
                    <a href="<?= base_url('public/katalog.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Katalog</a>
                    <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="px-3 py-2 rounded-lg text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 hover:bg-emerald-500/20 transition">Form Lapor</a>
                </nav>

                <!-- User Dropdown / Logout -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-semibold text-white"><?= e($user['nama']) ?></div>
                        <div class="text-[10px] text-indigo-400 font-medium">Administrator (CO)</div>
                    </div>
                    <a href="<?= base_url('public/auth/logout.php') ?>" class="p-2 rounded-xl bg-slate-800 hover:bg-rose-500/20 hover:text-rose-400 text-slate-400 transition" title="Keluar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Menu Bar -->
        <div class="md:hidden border-t border-slate-800 px-4 py-2 flex items-center justify-around text-xs font-medium">
            <a href="<?= base_url('public/admin/dashboard.php') ?>" class="text-slate-400 py-1">Dashboard</a>
            <a href="<?= base_url('public/admin/master_barang.php') ?>" class="text-indigo-400 font-bold py-1">Master</a>
            <a href="<?= base_url('public/katalog.php') ?>" class="text-slate-400 py-1">Katalog</a>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="text-emerald-400 font-bold py-1">Lapor</a>
            <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="text-slate-400 py-1">Riwayat</a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">

        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="mb-6 p-4 rounded-xl text-sm font-medium border flex items-center justify-between
                <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : '' ?>
                <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' : '' ?>
            ">
                <div class="flex items-center space-x-2">
                    <span><?= e($flash['message']) ?></span>
                </div>
            </div>
        <?php endif; ?>

        <!-- Section Header & Actions -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Master Barang Inventaris</h1>
                <p class="text-xs text-slate-400 mt-1">Kelola data seluruh barang, foto master, dan stok awal bersama.</p>
            </div>
            <div class="flex items-center space-x-3">
                <button onclick="openAddModal()"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-xl shadow-lg shadow-indigo-600/30 flex items-center space-x-2 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Tambah Barang Baru</span>
                </button>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div class="bg-slate-900/60 backdrop-blur border border-slate-800/80 rounded-2xl p-4 mb-6">
            <form action="" method="GET" class="flex flex-col sm:flex-row items-center gap-3">
                <div class="relative w-full sm:flex-1">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama barang, deskripsi, atau satuan..."
                        class="w-full pl-10 pr-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-xl transition">
                    Cari Data
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= base_url('public/admin/master_barang.php') ?>" class="w-full sm:w-auto px-4 py-2 bg-slate-800/50 hover:bg-slate-800 text-slate-400 hover:text-white text-sm rounded-xl text-center transition">
                        Reset Filter
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Data List Container -->
        <?php if (empty($barangList)): ?>
            <div class="bg-slate-900/50 border border-slate-800 rounded-2xl p-12 text-center">
                <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-4 text-slate-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white">Tidak ada data barang</h3>
                <p class="text-xs text-slate-400 mt-1">Belum ada data barang master yang sesuai dengan pencarian Anda.</p>
            </div>
        <?php else: ?>

            <!-- Desktop View: Table -->
            <div class="hidden lg:block bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
                <table class="w-full text-left text-sm text-slate-300">
                    <thead class="bg-slate-950/80 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 text-center">Foto</th>
                            <th class="py-3.5 px-4">Nama Barang</th>
                            <th class="py-3.5 px-4">Deskripsi</th>
                            <th class="py-3.5 px-4 text-center">Stok Awal</th>
                            <th class="py-3.5 px-4 text-center">Sisa Stok</th>
                            <th class="py-3.5 px-4 text-center">Status</th>
                            <th class="py-3.5 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($barangList as $b): ?>
                            <tr class="hover:bg-slate-800/30 transition">
                                <!-- Foto Thumbnail -->
                                <td class="py-3 px-4 text-center">
                                    <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden mx-auto relative group">
                                        <?php if ($b['foto_utama']): ?>
                                            <img src="<?= base_url('public/' . $b['foto_utama']) ?>" alt="<?= e($b['nama_barang']) ?>" class="w-full h-full object-cover">
                                            <?php if ($b['total_foto'] > 1): ?>
                                                <span class="absolute bottom-0 right-0 bg-indigo-600 text-white text-[9px] font-bold px-1 rounded-tl-md">+<?= $b['total_foto'] - 1 ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-slate-600">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                </svg>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <!-- Nama & Satuan -->
                                <td class="py-3 px-4">
                                    <div class="font-bold text-white text-base"><?= e($b['nama_barang']) ?></div>
                                    <div class="text-xs text-slate-400 mt-0.5">Satuan: <span class="text-slate-300 font-semibold"><?= e($b['satuan']) ?></span></div>
                                </td>
                                <!-- Deskripsi -->
                                <td class="py-3 px-4 max-w-xs truncate text-xs text-slate-400">
                                    <?= e($b['deskripsi'] ?: '-') ?>
                                </td>
                                <!-- Stok Awal -->
                                <td class="py-3 px-4 text-center font-mono text-sm text-slate-400">
                                    <?= number_format($b['stok_awal']) ?> <?= e($b['satuan']) ?>
                                </td>
                                <!-- Sisa Stok -->
                                <td class="py-3 px-4 text-center font-mono font-bold text-base <?= $b['stok_saat_ini'] <= 0 ? 'text-rose-400' : 'text-emerald-400' ?>">
                                    <?= number_format($b['stok_saat_ini']) ?> <?= e($b['satuan']) ?>
                                </td>
                                <!-- Status Badge -->
                                <td class="py-3 px-4 text-center">
                                    <?= get_stok_badge($b['stok_saat_ini']) ?>
                                </td>
                                <!-- Aksi -->
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <button onclick='openEditModal(<?= json_encode($b) ?>, <?= json_encode($allPhotos[$b['id']] ?? []) ?>)'
                                            class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400 hover:bg-indigo-500/20 border border-indigo-500/30 transition" title="Edit Barang & Foto">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </button>
                                        <button onclick='openDeleteModal(<?= json_encode($b) ?>)'
                                            class="p-2 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 border border-rose-500/30 transition" title="Hapus Barang">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:hidden gap-4">
                <?php foreach ($barangList as $b): ?>
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 flex flex-col justify-between space-y-4 shadow-lg">
                        <div class="flex items-start space-x-4">
                            <div class="w-16 h-16 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden shrink-0 relative">
                                <?php if ($b['foto_utama']): ?>
                                    <img src="<?= base_url('public/' . $b['foto_utama']) ?>" alt="<?= e($b['nama_barang']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-slate-600">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-bold text-white text-base"><?= e($b['nama_barang']) ?></h3>
                                <p class="text-xs text-slate-400 mt-1 line-clamp-2"><?= e($b['deskripsi'] ?: 'Tidak ada deskripsi.') ?></p>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-xs text-slate-400">Satuan: <strong class="text-slate-200"><?= e($b['satuan']) ?></strong></span>
                                    <?= get_stok_badge($b['stok_saat_ini']) ?>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-800/80 pt-3 flex items-center justify-between text-xs">
                            <div>
                                <span class="text-slate-500">Stok Awal: <?= number_format($b['stok_awal']) ?></span>
                                <span class="mx-1 text-slate-700">•</span>
                                <span class="text-emerald-400 font-bold">Sisa: <?= number_format($b['stok_saat_ini']) ?></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick='openEditModal(<?= json_encode($b) ?>, <?= json_encode($allPhotos[$b['id']] ?? []) ?>)'
                                    class="px-3 py-1.5 bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 rounded-lg font-medium">Edit</button>
                                <button onclick='openDeleteModal(<?= json_encode($b) ?>)'
                                    class="px-3 py-1.5 bg-rose-600/20 text-rose-400 border border-rose-500/30 rounded-lg font-medium">Hapus</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- MODAL TAMBAH BARANG -->
    <div id="addModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-5">
                <h3 class="text-lg font-bold text-white">Tambah Barang Master Baru</h3>
                <button onclick="closeAddModal()" class="text-slate-400 hover:text-white p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tambah">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Nama Barang *</label>
                    <input type="text" name="nama_barang" required placeholder="Contoh: Kursi Lipat Futura Red"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Satuan Barang *</label>
                        <input type="text" name="satuan" required value="pcs" placeholder="pcs, unit, set, dus"
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Stok Awal *</label>
                        <input type="number" name="stok_awal" required min="0" value="0"
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Deskripsi Barang</label>
                    <textarea name="deskripsi" rows="3" placeholder="Spesifikasi atau catatan barang..."
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Foto Master Barang (Multi-Upload)</label>
                    <input type="file" name="foto_barang[]" multiple accept="image/*,.heic,.heif"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-400 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
                    <p class="text-[11px] text-slate-500 mt-1">Dapat memilih lebih dari 1 foto (PNG, JPG, WEBP, HEIC).</p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 text-slate-400 hover:text-white text-sm">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-indigo-600/30">Simpan Barang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL EDIT BARANG -->
    <div id="editModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative overflow-y-auto max-h-[90vh]">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-5">
                <h3 class="text-lg font-bold text-white">Edit Master Barang</h3>
                <button onclick="closeEditModal()" class="text-slate-400 hover:text-white p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_barang_id" name="barang_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Nama Barang *</label>
                    <input type="text" id="edit_nama_barang" name="nama_barang" required
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Satuan Barang *</label>
                    <input type="text" id="edit_satuan" name="satuan" required
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Deskripsi Barang</label>
                    <textarea id="edit_deskripsi" name="deskripsi" rows="3"
                        class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500"></textarea>
                </div>

                <!-- Existing Photos Management -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Foto Master Saat Ini</label>
                    <div id="edit_photo_list" class="grid grid-cols-4 gap-2 mb-3"></div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1">Tambah Foto Baru (Opsional)</label>
                    <input type="file" name="foto_barang_baru[]" multiple accept="image/*,.heic,.heif"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-400 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white">
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-800">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-slate-400 hover:text-white text-sm">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-indigo-600/30">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL HAPUS BARANG CONFIRMATION -->
    <div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl text-center">
            <div class="w-14 h-14 rounded-full bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <h3 class="text-base font-bold text-white mb-1">Hapus Barang Master?</h3>
            <p id="delete_item_name" class="text-xs text-slate-400 mb-6"></p>

            <form action="" method="POST" class="flex items-center justify-center space-x-3">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" id="delete_barang_id" name="barang_id">
                <button type="button" onclick="closeDeleteModal()" class="w-full py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium rounded-xl">Batal</button>
                <button type="submit" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold rounded-xl shadow-lg shadow-rose-600/30">Ya, Hapus</button>
            </form>
        </div>
    </div>

    <!-- Hidden Form for Deleting Specific Photo -->
    <form id="deletePhotoForm" action="" method="POST" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="hapus_foto">
        <input type="hidden" id="delete_foto_id" name="foto_id">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.remove('hidden');
        }
        function closeAddModal() {
            document.getElementById('addModal').classList.add('hidden');
        }

        function openEditModal(barang, photos) {
            document.getElementById('edit_barang_id').value = barang.id;
            document.getElementById('edit_nama_barang').value = barang.nama_barang;
            document.getElementById('edit_satuan').value = barang.satuan;
            document.getElementById('edit_deskripsi').value = barang.deskripsi || '';

            const photoContainer = document.getElementById('edit_photo_list');
            photoContainer.innerHTML = '';

            if (photos && photos.length > 0) {
                photos.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'relative group w-full h-20 bg-slate-950 border border-slate-800 rounded-lg overflow-hidden';
                    div.innerHTML = `
                        <img src="${'<?= base_url("public/") ?>' + p.file_path}" class="w-full h-full object-cover">
                        <button type="button" onclick="deleteSpecificPhoto(${p.id})"
                            class="absolute top-1 right-1 p-1 bg-rose-600 text-white rounded-md text-xs opacity-80 hover:opacity-100" title="Hapus foto ini">
                            &times;
                        </button>
                    `;
                    photoContainer.appendChild(div);
                });
            } else {
                photoContainer.innerHTML = '<span class="text-xs text-slate-500 col-span-4">Belum ada foto master.</span>';
            }

            document.getElementById('editModal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function openDeleteModal(barang) {
            document.getElementById('delete_barang_id').value = barang.id;
            document.getElementById('delete_item_name').innerText = `Anda yakin ingin menghapus "${barang.nama_barang}"?`;
            document.getElementById('deleteModal').classList.remove('hidden');
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
        }

        function deleteSpecificPhoto(photoId) {
            if (confirm('Yakin ingin menghapus foto master ini?')) {
                document.getElementById('delete_foto_id').value = photoId;
                document.getElementById('deletePhotoForm').submit();
            }
        }
    </script>
</body>
</html>
