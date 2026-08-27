<?php
/**
 * Restock Barang Masuk (Admin Only)
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/csrf.php';

$user = require_role(['admin']);
$db = get_db_connection();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $barangId       = (int)($_POST['barang_id'] ?? 0);
    $jumlahTambahan = (int)($_POST['jumlah_tambahan'] ?? 0);
    $catatan        = trim($_POST['catatan'] ?? '');

    if ($barangId <= 0) {
        $error = 'Silakan pilih barang yang akan di-restock.';
    } elseif ($jumlahTambahan <= 0) {
        $error = 'Jumlah stok tambahan harus lebih dari 0.';
    } else {
        try {
            $db->beginTransaction();

            $lockStmt = $db->prepare("SELECT id, nama_barang, satuan, stok_saat_ini FROM barang WHERE id = :id FOR UPDATE");
            $lockStmt->execute(['id' => $barangId]);
            $barang = $lockStmt->fetch();

            if (!$barang) {
                throw new Exception("Barang tidak ditemukan.");
            }

            $stokSebelum = (int)$barang['stok_saat_ini'];
            $stokSesudah = $stokSebelum + $jumlahTambahan;

            // Update Stock
            $upStmt = $db->prepare("UPDATE barang SET stok_saat_ini = :sisa WHERE id = :id");
            $upStmt->execute(['sisa' => $stokSesudah, 'id' => $barangId]);

            // Insert into Restock Log
            $rStmt = $db->prepare("
                INSERT INTO restock_log (barang_id, jumlah_tambahan, stok_sebelum, stok_sesudah, dicatat_oleh, catatan)
                VALUES (:b_id, :jumlah, :stok_seb, :stok_ses, :user_id, :catatan)
            ");
            $rStmt->execute([
                'b_id'     => $barangId,
                'jumlah'   => $jumlahTambahan,
                'stok_seb' => $stokSebelum,
                'stok_ses' => $stokSesudah,
                'user_id'  => $user['id'],
                'catatan'  => $catatan
            ]);

            write_audit_log(
                $db,
                $user['id'],
                'RESTOCK_BARANG',
                "Menambahkan stok masuk +{$jumlahTambahan} {$barang['satuan']} untuk '{$barang['nama_barang']}'. Stok baru: {$stokSesudah} {$barang['satuan']}."
            );

            $db->commit();

            set_flash_message('success', "Berhasil menambahkan +{$jumlahTambahan} {$barang['satuan']} stok '{$barang['nama_barang']}'. Stok terkini: {$stokSesudah} {$barang['satuan']}.");
            header('Location: ' . base_url('public/admin/restock.php'));
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Fetch barang list for dropdown
$barangListStmt = $db->query("SELECT id, nama_barang, satuan, stok_saat_ini FROM barang ORDER BY nama_barang ASC");
$barangList = $barangListStmt->fetchAll();

// Fetch Recent Restock Log
$logsStmt = $db->query("
    SELECT r.*, b.nama_barang, b.satuan, u.nama AS dicatat_oleh_nama
    FROM restock_log r
    JOIN barang b ON r.barang_id = b.id
    JOIN users u ON r.dicatat_oleh = u.id
    ORDER BY r.id DESC LIMIT 50
");
$restockLogs = $logsStmt->fetchAll();

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restock Barang Masuk - Admin Logistik</title>
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
                    <a href="<?= base_url('public/admin/master_barang.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Master Barang</a>
                    <a href="<?= base_url('public/admin/restock.php') ?>" class="px-3 py-2 rounded-lg text-white bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">Restock</a>
                    <a href="<?= base_url('public/admin/kelola_akun.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Kelola Akun</a>
                    <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Semua Riwayat</a>
                    <a href="<?= base_url('public/katalog.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Katalog</a>
                    <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="px-3 py-2 rounded-lg text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 hover:bg-emerald-500/20 transition">Form Lapor</a>
                </nav>

                <!-- User Info & Logout -->
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

        <!-- Mobile Navigation Menu -->
        <div class="md:hidden border-t border-slate-800 px-4 py-2 flex items-center justify-around text-xs font-medium">
            <a href="<?= base_url('public/admin/dashboard.php') ?>" class="text-slate-400 py-1">Dashboard</a>
            <a href="<?= base_url('public/admin/restock.php') ?>" class="text-indigo-400 font-bold py-1">Restock</a>
            <a href="<?= base_url('public/katalog.php') ?>" class="text-slate-400 py-1">Katalog</a>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="text-emerald-400 font-bold py-1">Lapor</a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">

        <!-- Header Title -->
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Restock & Stok Masuk</h1>
            <p class="text-xs text-slate-400 mt-1">Tambah jumlah stok untuk barang yang sudah ada di inventaris.</p>
        </div>

        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="p-4 rounded-xl text-sm font-medium border flex items-center justify-between
                <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : '' ?>
                <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' : '' ?>
            ">
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Error Alert -->
        <?php if ($error): ?>
            <div class="p-4 rounded-xl text-sm font-medium bg-rose-500/10 text-rose-300 border border-rose-500/20 flex items-start space-x-3">
                <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Form Restock Card -->
            <div class="lg:col-span-1 bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-6 shadow-xl h-fit">
                <h3 class="text-base font-bold text-white mb-4 pb-3 border-b border-slate-800 flex items-center space-x-2">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Form Tambah Stok Masuk</span>
                </h3>

                <form action="" method="POST" class="space-y-4">
                    <?= csrf_field() ?>

                    <div>
                        <label for="barang_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Pilih Barang *</label>
                        <select id="barang_id" name="barang_id" required
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-indigo-500">
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach ($barangList as $b): ?>
                                <option value="<?= $b['id'] ?>">
                                    <?= e($b['nama_barang']) ?> (Saat ini: <?= number_format($b['stok_saat_ini']) ?> <?= e($b['satuan']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="jumlah_tambahan" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Jumlah Stok Tambahan *</label>
                        <input type="number" id="jumlah_tambahan" name="jumlah_tambahan" min="1" required placeholder="Contoh: 20"
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono text-sm focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="catatan" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Catatan / Sumber Pembelian</label>
                        <textarea id="catatan" name="catatan" rows="3" placeholder="Contoh: Pembelian baru dari supplier X..."
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <button type="submit"
                        class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/30 transition">
                        Tambah Stok Sekarang
                    </button>
                </form>
            </div>

            <!-- Log History Table -->
            <div class="lg:col-span-2 bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h3 class="text-base font-bold text-white mb-4 pb-3 border-b border-slate-800 flex items-center justify-between">
                    <span>Riwayat Stok Masuk (Restock Log)</span>
                    <span class="text-xs text-slate-400 font-normal">50 Transaksi Terakhir</span>
                </h3>

                <?php if (empty($restockLogs)): ?>
                    <div class="p-8 text-center text-slate-500 text-xs">Belum ada riwayat restock barang.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm text-slate-300">
                            <thead class="bg-slate-950 text-xs font-semibold text-slate-400 uppercase tracking-wider border-b border-slate-800">
                                <tr>
                                    <th class="py-3 px-3">Waktu</th>
                                    <th class="py-3 px-3">Nama Barang</th>
                                    <th class="py-3 px-3 text-center">Tambahan</th>
                                    <th class="py-3 px-3 text-center">Stok (Sebelum &rarr; Sesudah)</th>
                                    <th class="py-3 px-3">Dicatat Oleh</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/60 text-xs">
                                <?php foreach ($restockLogs as $rl): ?>
                                    <tr class="hover:bg-slate-800/30 transition">
                                        <td class="py-3 px-3 whitespace-nowrap text-slate-400">
                                            <?= format_tanggal_indonesia($rl['created_at']) ?>
                                        </td>
                                        <td class="py-3 px-3 font-semibold text-white">
                                            <?= e($rl['nama_barang']) ?>
                                        </td>
                                        <td class="py-3 px-3 text-center font-mono font-bold text-emerald-400">
                                            +<?= number_format($rl['jumlah_tambahan']) ?> <?= e($rl['satuan']) ?>
                                        </td>
                                        <td class="py-3 px-3 text-center font-mono text-slate-400">
                                            <?= number_format($rl['stok_sebelum']) ?> &rarr; <strong class="text-white"><?= number_format($rl['stok_sesudah']) ?></strong>
                                        </td>
                                        <td class="py-3 px-3 text-slate-300">
                                            <?= e($rl['dicatat_oleh_nama']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </main>
</body>
</html>
