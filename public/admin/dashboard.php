<?php
/**
 * Realtime Admin Dashboard
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['admin']);
$db = get_db_connection();

// 1. Stat Cards Metrics
$totalJenisBarang = (int)$db->query("SELECT COUNT(*) FROM barang")->fetchColumn();
$totalKuantitasStok = (int)$db->query("SELECT SUM(stok_saat_ini) FROM barang")->fetchColumn();
$transaksiHariIni = (int)$db->query("SELECT COUNT(*) FROM transaksi WHERE DATE(waktu_transaksi) = CURDATE()")->fetchColumn();
$stokKritisCount = (int)$db->query("SELECT COUNT(*) FROM barang WHERE stok_saat_ini <= 5")->fetchColumn();

// 2. Fetch Low Stock Items (Stok <= 5)
$stokKritisStmt = $db->query("SELECT id, nama_barang, satuan, stok_saat_ini, stok_awal FROM barang WHERE stok_saat_ini <= 5 ORDER BY stok_saat_ini ASC LIMIT 10");
$stokKritisList = $stokKritisStmt->fetchAll();

// 3. Fetch Recent Transactions (Last 10)
$recentTxStmt = $db->query("
    SELECT t.*, b.nama_barang, b.satuan, u.nama AS penyerah_nama,
           (SELECT file_path FROM foto_transaksi WHERE transaksi_id = t.id ORDER BY id ASC LIMIT 1) AS foto_bukti
    FROM transaksi t
    JOIN barang b ON t.barang_id = b.id
    JOIN users u ON t.penyerah_id = u.id
    ORDER BY t.id DESC LIMIT 10
");
$recentTxList = $recentTxStmt->fetchAll();

// 4. Fetch Recent Audit Logs
$recentAuditStmt = $db->query("
    SELECT a.*, u.nama AS user_nama
    FROM audit_log a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.id DESC LIMIT 8
");
$recentAuditList = $recentAuditStmt->fetchAll();

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Realtime - Admin Logistik</title>
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

    <!-- Header Navbar -->
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
                    <a href="<?= base_url('public/admin/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-white bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">Dashboard</a>
                    <a href="<?= base_url('public/admin/master_barang.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Master Barang</a>
                    <a href="<?= base_url('public/admin/restock.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Restock</a>
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
            <a href="<?= base_url('public/admin/dashboard.php') ?>" class="text-indigo-400 font-bold py-1">Dashboard</a>
            <a href="<?= base_url('public/admin/master_barang.php') ?>" class="text-slate-400 py-1">Master</a>
            <a href="<?= base_url('public/katalog.php') ?>" class="text-slate-400 py-1">Katalog</a>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="text-emerald-400 font-bold py-1">Lapor</a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">

        <!-- Flash Alert -->
        <?php if ($flash): ?>
            <div class="p-4 rounded-xl text-sm font-medium border flex items-center justify-between
                <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : '' ?>
                <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' : '' ?>
            ">
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner & Quick Action -->
        <div class="bg-gradient-to-r from-indigo-900/60 via-slate-900 to-slate-900 border border-indigo-500/20 rounded-3xl p-6 sm:p-8 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 shadow-2xl">
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Dashboard Realtime Logistik</h1>
                <p class="text-xs text-slate-300 mt-1 max-w-xl">
                    Pantau sisa stok barang, transaksi serah terima realtime dari seluruh anggota pekerja, dan peringatan restock barang.
                </p>
            </div>
            <div class="flex items-center space-x-3 shrink-0">
                <a href="<?= base_url('public/serah_terima/lapor.php') ?>"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-emerald-600/30 flex items-center space-x-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <span>Lapor Serah Terima</span>
                </a>
                <a href="<?= base_url('public/admin/restock.php') ?>"
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center space-x-2 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Restock Barang</span>
                </a>
            </div>
        </div>

        <!-- 4 Metrics Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
            <!-- Metric 1: Total Jenis Barang -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </div>
                <div>
                    <div class="text-xs text-slate-400 font-medium">Jenis Barang Master</div>
                    <div class="text-2xl font-extrabold text-white font-mono mt-0.5"><?= number_format($totalJenisBarang) ?></div>
                </div>
            </div>

            <!-- Metric 2: Total Kuantitas Stok -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <div class="text-xs text-slate-400 font-medium">Total Unit Stok Tersedia</div>
                    <div class="text-2xl font-extrabold text-emerald-400 font-mono mt-0.5"><?= number_format($totalKuantitasStok) ?></div>
                </div>
            </div>

            <!-- Metric 3: Transaksi Hari Ini -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div>
                    <div class="text-xs text-slate-400 font-medium">Serah Terima Hari Ini</div>
                    <div class="text-2xl font-extrabold text-white font-mono mt-0.5"><?= number_format($transaksiHariIni) ?></div>
                </div>
            </div>

            <!-- Metric 4: Stok Kritis / Menipis -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg flex items-center space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <div class="text-xs text-slate-400 font-medium">Stok Menipis / Kritis</div>
                    <div class="text-2xl font-extrabold text-rose-400 font-mono mt-0.5"><?= number_format($stokKritisCount) ?></div>
                </div>
            </div>
        </div>

        <!-- Section Grid: Low Stock Alert & Recent Transactions -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Low Stock Warning Column -->
            <div class="lg:col-span-1 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                        <h3 class="text-base font-bold text-white flex items-center space-x-2">
                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                            <span>Peringatan Stok Kritis</span>
                        </h3>
                        <span class="text-xs text-slate-500">Stok &le; 5</span>
                    </div>

                    <?php if (empty($stokKritisList)): ?>
                        <div class="py-8 text-center text-xs text-slate-500">
                            Semua stok barang berada dalam kondisi aman (&gt; 5).
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($stokKritisList as $sk): ?>
                                <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-xl flex items-center justify-between">
                                    <div>
                                        <div class="font-bold text-white text-xs"><?= e($sk['nama_barang']) ?></div>
                                        <div class="text-[10px] text-slate-500">Stok awal: <?= number_format($sk['stok_awal']) ?> <?= e($sk['satuan']) ?></div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-mono font-bold text-xs text-rose-400">
                                            <?= number_format($sk['stok_saat_ini']) ?> <?= e($sk['satuan']) ?>
                                        </div>
                                        <a href="<?= base_url('public/admin/restock.php') ?>" class="text-[10px] text-indigo-400 hover:underline font-semibold">
                                            + Restock
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800">
                    <a href="<?= base_url('public/admin/master_barang.php') ?>" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl block text-center transition">
                        Buka Master Barang
                    </a>
                </div>
            </div>

            <!-- Recent Transactions Table Column -->
            <div class="lg:col-span-2 bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                        <h3 class="text-base font-bold text-white">Transaksi Serah Terima Terbaru</h3>
                        <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="text-xs text-indigo-400 hover:underline font-semibold">
                            Lihat Semua &rarr;
                        </a>
                    </div>

                    <?php if (empty($recentTxList)): ?>
                        <div class="py-12 text-center text-xs text-slate-500">
                            Belum ada laporan serah terima barang.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-slate-300">
                                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider border-b border-slate-800 font-semibold">
                                    <tr>
                                        <th class="py-3 px-3 text-center">Bukti</th>
                                        <th class="py-3 px-3">Waktu</th>
                                        <th class="py-3 px-3">Barang</th>
                                        <th class="py-3 px-3 text-center">Jumlah</th>
                                        <th class="py-3 px-3">Penyerah</th>
                                        <th class="py-3 px-3">Penerima</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60">
                                    <?php foreach ($recentTxList as $tx): ?>
                                        <tr class="hover:bg-slate-800/30 transition">
                                            <td class="py-2.5 px-3 text-center">
                                                <div class="w-9 h-9 rounded-lg bg-slate-950 border border-slate-800 overflow-hidden mx-auto">
                                                    <?php if ($tx['foto_bukti']): ?>
                                                        <img src="<?= base_url('public/' . $tx['foto_bukti']) ?>" class="w-full h-full object-cover">
                                                    <?php else: ?>
                                                        <div class="w-full h-full flex items-center justify-center text-slate-600">-</div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-3 text-slate-400 whitespace-nowrap">
                                                <?= date('d M Y, H:i', strtotime($tx['waktu_transaksi'])) ?>
                                            </td>
                                            <td class="py-2.5 px-3 font-semibold text-white">
                                                <?= e($tx['nama_barang']) ?>
                                            </td>
                                            <td class="py-2.5 px-3 text-center font-mono font-bold text-emerald-400">
                                                <?= number_format($tx['jumlah']) ?> <?= e($tx['satuan']) ?>
                                            </td>
                                            <td class="py-2.5 px-3 text-slate-300">
                                                <?= e($tx['penyerah_nama']) ?>
                                            </td>
                                            <td class="py-2.5 px-3 font-semibold text-indigo-300">
                                                <?= e($tx['nama_penerima']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

        <!-- System Audit Log Trail -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h3 class="text-base font-bold text-white mb-4 pb-3 border-b border-slate-800 flex items-center justify-between">
                <span>Aktivitas Audit Log Terbaru</span>
                <span class="text-xs text-slate-500 font-normal">Jejak Keamanan Sistem</span>
            </h3>

            <div class="space-y-2">
                <?php foreach ($recentAuditList as $al): ?>
                    <div class="p-2.5 bg-slate-950/60 border border-slate-800/80 rounded-xl flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-3">
                            <span class="px-2 py-0.5 rounded font-mono font-semibold text-[10px] uppercase bg-slate-800 text-slate-300">
                                <?= e($al['aksi']) ?>
                            </span>
                            <span class="text-slate-300"><?= e($al['detail']) ?></span>
                        </div>
                        <div class="text-right shrink-0 text-slate-500 text-[11px]">
                            <span><?= e($al['user_nama'] ?: 'Guest/System') ?></span> •
                            <span><?= date('d M H:i', strtotime($al['created_at'])) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>
</body>
</html>
