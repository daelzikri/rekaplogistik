<?php
/**
 * Riwayat Transaksi Seluruh Anggota & Filter (Admin Only)
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = require_auth();
$db = get_db_connection();

// Delete Transaction Handler (Reverts Stock Automatically)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_transaksi') {
    require_csrf_token();
    $transaksiId = (int)($_POST['transaksi_id'] ?? 0);

    if ($transaksiId <= 0) {
        set_flash_message('error', 'ID transaksi tidak valid.');
    } else {
        try {
            $db->beginTransaction();

            // Fetch transaction
            $tStmt = $db->prepare("
                SELECT t.*, b.nama_barang, b.satuan
                FROM transaksi t
                JOIN barang b ON t.barang_id = b.id
                WHERE t.id = :id FOR UPDATE
            ");
            $tStmt->execute(['id' => $transaksiId]);
            $tx = $tStmt->fetch();

            if (!$tx) {
                throw new Exception('Data transaksi tidak ditemukan.');
            }

            // Lock & fetch current barang stock
            $bLock = $db->prepare("SELECT id, stok_saat_ini FROM barang WHERE id = :id FOR UPDATE");
            $bLock->execute(['id' => $tx['barang_id']]);
            $barang = $bLock->fetch();

            $stokLama = 0;
            $stokBaru = 0;
            if ($barang) {
                $stokLama = (int)$barang['stok_saat_ini'];
                $jumlah   = (int)$tx['jumlah'];

                // Revert stock:
                // If original was serah_terima (took stock out), deletion restores (+jumlah) stock back
                // If original was pengembalian (returned stock in), deletion reverts (-jumlah) stock
                if ($tx['tipe_transaksi'] === 'pengembalian') {
                    $stokBaru = max(0, $stokLama - $jumlah);
                } else {
                    $stokBaru = $stokLama + $jumlah;
                }

                $upB = $db->prepare("UPDATE barang SET stok_saat_ini = :stok WHERE id = :id");
                $upB->execute(['stok' => $stokBaru, 'id' => $tx['barang_id']]);
            }

            // Delete physical files
            $fStmt = $db->prepare("SELECT file_path FROM foto_transaksi WHERE transaksi_id = :id");
            $fStmt->execute(['id' => $transaksiId]);
            $photos = $fStmt->fetchAll();

            foreach ($photos as $p) {
                $filePath = __DIR__ . '/../' . $p['file_path'];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }

            // Delete foto_transaksi and transaksi records
            $delF = $db->prepare("DELETE FROM foto_transaksi WHERE transaksi_id = :id");
            $delF->execute(['id' => $transaksiId]);

            $delT = $db->prepare("DELETE FROM transaksi WHERE id = :id");
            $delT->execute(['id' => $transaksiId]);

            // Audit log
            write_audit_log(
                $db,
                $user['id'],
                'DELETE_TRANSAKSI',
                "Menghapus transaksi #{$transaksiId} ('{$tx['nama_barang']}', {$tx['jumlah']} {$tx['satuan']}). Stok otomatis dikembalikan dari {$stokLama} ke {$stokBaru}."
            );

            $db->commit();
            set_flash_message('success', "Transaksi #{$transaksiId} ('{$tx['nama_barang']}') berhasil dihapus. Sisa stok otomatis dikembalikan dari {$stokLama} menjadi {$stokBaru} {$tx['satuan']}.");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            set_flash_message('error', 'Gagal menghapus transaksi: ' . $e->getMessage());
        }
    }
    header('Location: ' . base_url('public/admin/riwayat_transaksi.php'));
    exit;
}

// Filters
$tglMulai      = trim($_GET['tgl_mulai'] ?? '');
$tglSelesai    = trim($_GET['tgl_selesai'] ?? '');
$barangId      = (int)($_GET['barang_id'] ?? 0);
$penyerahId    = (int)($_GET['penyerah_id'] ?? 0);
$tipeTransaksi = trim($_GET['tipe'] ?? '');
$search        = trim($_GET['q'] ?? '');

$where = ["1=1"];
$params = [];

if ($tglMulai !== '') {
    $where[] = "DATE(t.waktu_transaksi) >= :tgl_mulai";
    $params['tgl_mulai'] = $tglMulai;
}
if ($tglSelesai !== '') {
    $where[] = "DATE(t.waktu_transaksi) <= :tgl_selesai";
    $params['tgl_selesai'] = $tglSelesai;
}
if ($barangId > 0) {
    $where[] = "t.barang_id = :barang_id";
    $params['barang_id'] = $barangId;
}
if ($penyerahId > 0) {
    $where[] = "t.penyerah_id = :penyerah_id";
    $params['penyerah_id'] = $penyerahId;
}
if (in_array($tipeTransaksi, ['serah_terima', 'pengembalian'], true)) {
    $where[] = "t.tipe_transaksi = :tipe";
    $params['tipe'] = $tipeTransaksi;
}
if ($search !== '') {
    $where[] = "(t.nama_penerima LIKE :q1 OR t.catatan LIKE :q2 OR b.nama_barang LIKE :q3)";
    $params['q1'] = "%{$search}%";
    $params['q2'] = "%{$search}%";
    $params['q3'] = "%{$search}%";
}

$whereSql = implode(" AND ", $where);

$querySql = "
    SELECT t.*, b.nama_barang, b.satuan, u.nama AS penyerah_nama, u.role AS penyerah_role,
           (SELECT COUNT(*) FROM foto_transaksi WHERE transaksi_id = t.id) AS total_bukti,
           (SELECT file_path FROM foto_transaksi WHERE transaksi_id = t.id ORDER BY id ASC LIMIT 1) AS foto_utama
    FROM transaksi t
    JOIN barang b ON t.barang_id = b.id
    JOIN users u ON t.penyerah_id = u.id
    WHERE {$whereSql}
    ORDER BY t.id DESC
";

$stmt = $db->prepare($querySql);
$stmt->execute($params);
$transaksiList = $stmt->fetchAll();

// Fetch all proof photos for modals
$allPhotosStmt = $db->query("SELECT id, transaksi_id, file_path, format_asli FROM foto_transaksi ORDER BY id ASC");
$allPhotos = [];
foreach ($allPhotosStmt->fetchAll() as $ph) {
    $allPhotos[$ph['transaksi_id']][] = $ph;
}

// Fetch Options for Filter Dropdowns
$barangOptions = $db->query("SELECT id, nama_barang FROM barang ORDER BY nama_barang ASC")->fetchAll();
$penyerahOptions = $db->query("SELECT id, nama, role FROM users ORDER BY nama ASC")->fetchAll();

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Transaksi Seluruh Anggota - Admin Logistik</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

    <?php render_navbar('riwayat_transaksi', $user); ?>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">

        <!-- Title & Header -->
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Semua Riwayat Transaksi</h1>
            <p class="text-xs text-slate-400 mt-1">Audit log seluruh transaksi serah terima barang keluar & pengembalian barang masuk dari Admin.</p>
        </div>

        <!-- Filter Card -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-5 shadow-xl">
            <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tanggal Mulai</label>
                    <input type="date" name="tgl_mulai" value="<?= e($tglMulai) ?>"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tanggal Selesai</label>
                    <input type="date" name="tgl_selesai" value="<?= e($tglSelesai) ?>"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Tipe Transaksi</label>
                    <select name="tipe"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Semua Tipe</option>
                        <option value="serah_terima" <?= $tipeTransaksi === 'serah_terima' ? 'selected' : '' ?>>Serah Terima (Keluar)</option>
                        <option value="pengembalian" <?= $tipeTransaksi === 'pengembalian' ? 'selected' : '' ?>>Pengembalian (Masuk)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Filter Barang</label>
                    <select name="barang_id"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Semua Barang</option>
                        <?php foreach ($barangOptions as $bo): ?>
                            <option value="<?= $bo['id'] ?>" <?= $barangId == $bo['id'] ? 'selected' : '' ?>><?= e($bo['nama_barang']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Filter Admin Pelapor</label>
                    <select name="penyerah_id"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Semua Admin</option>
                        <?php foreach ($penyerahOptions as $po): ?>
                            <option value="<?= $po['id'] ?>" <?= $penyerahId == $po['id'] ? 'selected' : '' ?>><?= e($po['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <div class="flex items-center space-x-2">
                    <button type="submit" class="flex-1 py-2 px-4 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition">
                        Filter Data
                    </button>
                    <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="py-2 px-3 bg-slate-800 hover:bg-slate-700 text-slate-400 text-xs rounded-xl transition">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Quick Filter Chips -->
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider mr-1">Quick Filter:</span>

            <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition flex items-center space-x-1.5 <?= (empty($penyerahId) && empty($tipeTransaksi)) ? 'bg-indigo-600/30 text-indigo-300 border-indigo-500/50 shadow-md font-bold' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white' ?>">
                <span>Semua Transaksi</span>
            </a>

            <a href="<?= base_url('public/admin/riwayat_transaksi.php?penyerah_id=' . $user['id']) ?>"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition flex items-center space-x-1.5 <?= ($penyerahId === (int)$user['id']) ? 'bg-indigo-600/30 text-indigo-300 border-indigo-500/50 shadow-md font-bold' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white' ?>">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Transaksi Saya</span>
            </a>

            <a href="<?= base_url('public/admin/riwayat_transaksi.php?tipe=serah_terima') ?>"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition flex items-center space-x-1.5 <?= ($tipeTransaksi === 'serah_terima') ? 'bg-rose-500/20 text-rose-300 border-rose-500/40 shadow-md font-bold' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white' ?>">
                <svg class="w-3.5 h-3.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Barang Keluar (Serah Terima)</span>
            </a>

            <a href="<?= base_url('public/admin/riwayat_transaksi.php?tipe=pengembalian') ?>"
                class="px-3 py-1.5 rounded-xl text-xs font-semibold border transition flex items-center space-x-1.5 <?= ($tipeTransaksi === 'pengembalian') ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 shadow-md font-bold' : 'bg-slate-900 text-slate-400 border-slate-800 hover:text-white' ?>">
                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Barang Masuk (Pengembalian)</span>
            </a>
        </div>

        <!-- Table Container -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <?php if (empty($transaksiList)): ?>
                <div class="p-12 text-center text-slate-500 text-xs">
                    Tidak ditemukan data transaksi yang sesuai filter.
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
            <div class="hidden lg:block overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 text-center">Bukti Foto</th>
                            <th class="py-3.5 px-4">Tipe Transaksi</th>
                            <th class="py-3.5 px-4">Waktu</th>
                            <th class="py-3.5 px-4">Barang</th>
                            <th class="py-3.5 px-4 text-center">Jumlah</th>
                            <th class="py-3.5 px-4 text-center">Audit Stok</th>
                            <th class="py-3.5 px-4">Admin Pelapor</th>
                            <th class="py-3.5 px-4">Pihak Penerima/Pengembali</th>
                            <th class="py-3.5 px-4">Catatan</th>
                            <th class="py-3.5 px-4 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($transaksiList as $t):
                            $isReturn = (isset($t['tipe_transaksi']) && $t['tipe_transaksi'] === 'pengembalian');
                        ?>
                            <tr class="hover:bg-slate-800/30 transition">
                                <!-- Bukti Foto -->
                                <td class="py-3 px-4 text-center">
                                    <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden mx-auto relative group">
                                        <?php if ($t['foto_utama']): ?>
                                            <img src="<?= base_url('public/' . $t['foto_utama']) ?>" alt="Bukti Foto"
                                                class="w-full h-full object-cover cursor-pointer"
                                                onclick='openProofGallery(<?= e(json_encode($allPhotos[$t['id']] ?? [])) ?>, <?= e(json_encode("Bukti Transaksi #" . $t['id'])) ?>)'>
                                            <?php if ($t['total_bukti'] > 1): ?>
                                                <span class="absolute bottom-0 right-0 bg-indigo-600 text-white text-[9px] font-bold px-1 rounded-tl-md">+<?= $t['total_bukti'] - 1 ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-slate-600">-</div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <!-- Tipe Transaksi -->
                                <td class="py-3 px-4 whitespace-nowrap">
                                    <?php if ($isReturn): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            Pengembalian
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            Serah Terima
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <!-- Waktu -->
                                <td class="py-3 px-4 whitespace-nowrap text-slate-400">
                                    <?= format_tanggal_indonesia($t['waktu_transaksi']) ?>
                                </td>
                                <!-- Barang -->
                                <td class="py-3 px-4 font-bold text-white text-sm">
                                    <?= e($t['nama_barang']) ?>
                                </td>
                                <!-- Jumlah -->
                                <td class="py-3 px-4 text-center font-mono font-extrabold text-sm <?= $isReturn ? 'text-emerald-400' : 'text-rose-400' ?>">
                                    <?= $isReturn ? '+' : '-' ?><?= number_format($t['jumlah']) ?> <?= e($t['satuan']) ?>
                                </td>
                                <!-- Audit Stok -->
                                <td class="py-3 px-4 text-center font-mono text-xs text-slate-400">
                                    <?= number_format($t['stok_sebelum']) ?> &rarr; <strong class="text-white"><?= number_format($t['stok_sesudah']) ?></strong>
                                </td>
                                <!-- Pelapor -->
                                <td class="py-3 px-4">
                                    <div class="font-semibold text-slate-200"><?= e($t['penyerah_nama']) ?></div>
                                    <div class="text-[10px] text-indigo-400 capitalize">Admin</div>
                                </td>
                                <!-- Penerima/Pengembali -->
                                <td class="py-3 px-4 font-bold text-indigo-300">
                                    <?= e($t['nama_penerima']) ?>
                                </td>
                                <!-- Catatan -->
                                <td class="py-3 px-4 max-w-xs truncate text-slate-400">
                                    <?= e($t['catatan'] ?: '-') ?>
                                </td>
                                <!-- Aksi Hapus -->
                                <td class="py-3 px-4 text-center">
                                    <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Sisa stok barang akan dikembalikan/disesuaikan secara otomatis!');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_transaksi">
                                        <input type="hidden" name="transaksi_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[11px] font-semibold transition" title="Hapus Transaksi & Kembalikan Stok">
                                            Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards View -->
            <div class="lg:hidden divide-y divide-slate-800/80">
                <?php foreach ($transaksiList as $t):
                    $isReturn = (isset($t['tipe_transaksi']) && $t['tipe_transaksi'] === 'pengembalian');
                ?>
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden shrink-0 relative">
                                    <?php if ($t['foto_utama']): ?>
                                        <img src="<?= base_url('public/' . $t['foto_utama']) ?>" alt="Bukti Foto"
                                            class="w-full h-full object-cover cursor-pointer"
                                            onclick='openProofGallery(<?= e(json_encode($allPhotos[$t['id']] ?? [])) ?>, <?= e(json_encode("Bukti Transaksi #" . $t['id'])) ?>)'>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-600 text-xs">-</div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-white text-sm"><?= e($t['nama_barang']) ?></h4>
                                    <div class="text-[11px] text-slate-400 mt-0.5"><?= format_tanggal_indonesia($t['waktu_transaksi']) ?></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-mono font-extrabold text-sm <?= $isReturn ? 'text-emerald-400' : 'text-rose-400' ?>">
                                    <?= $isReturn ? '+' : '-' ?><?= number_format($t['jumlah']) ?> <?= e($t['satuan']) ?>
                                </div>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[9px] font-bold <?= $isReturn ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20' ?>">
                                    <?= $isReturn ? 'Pengembalian' : 'Serah Terima' ?>
                                </span>
                            </div>
                        </div>

                        <div class="bg-slate-950/60 p-2.5 rounded-xl border border-slate-800/80 text-xs space-y-1">
                            <div class="flex items-center justify-between text-slate-400">
                                <span>Pelapor: <strong class="text-slate-200"><?= e($t['penyerah_nama']) ?></strong></span>
                                <span>Penerima: <strong class="text-indigo-300"><?= e($t['nama_penerima']) ?></strong></span>
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-slate-500 border-t border-slate-800/50 pt-2 mt-1">
                                <span>Stok: <?= number_format($t['stok_sebelum']) ?> &rarr; <strong class="text-slate-200"><?= number_format($t['stok_sesudah']) ?></strong></span>
                                <form action="" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus transaksi ini? Sisa stok barang akan dikembalikan/disesuaikan secara otomatis!');" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_transaksi">
                                    <input type="hidden" name="transaksi_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="px-2 py-0.5 rounded-md bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-[10px] font-semibold transition">
                                        Hapus Transaksi
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal Proof Photo Gallery -->
    <div id="proofModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-md hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <h3 id="proofTitle" class="text-base font-bold text-white">Bukti Foto Penyerahan</h3>
                <button onclick="closeProofModal()" class="text-slate-400 hover:text-white p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="proofImages" class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto p-1"></div>
        </div>
    </div>

    <script>
        function openProofGallery(photos, title) {
            document.getElementById('proofTitle').innerText = title;
            const container = document.getElementById('proofImages');
            container.innerHTML = '';
            if (photos && photos.length > 0) {
                photos.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'h-40 bg-slate-950 border border-slate-800 rounded-xl overflow-hidden';
                    div.innerHTML = `<img src="${'<?= base_url("public/") ?>' + p.file_path}" class="w-full h-full object-cover">`;
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<span class="text-xs text-slate-500 col-span-3">Tidak ada foto bukti.</span>';
            }
            document.getElementById('proofModal').classList.remove('hidden');
        }
        function closeProofModal() {
            document.getElementById('proofModal').classList.add('hidden');
        }
    </script>
</body>
</html>
