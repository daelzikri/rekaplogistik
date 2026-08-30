<?php
/**
 * Katalog Barang & Sisa Stok Realtime
 * Dapat diakses oleh Admin dan Pekerja Logistik
 */

require_once __DIR__ . '/middleware/auth.php';
$user = require_role(['admin', 'pekerja']);
$db = get_db_connection();

$search = trim($_GET['q'] ?? '');
$viewMode = $_GET['view'] ?? 'list'; // Default view mode is 'list' for compact display

$querySql = "
    SELECT b.*,
           (SELECT COUNT(*) FROM foto_barang WHERE barang_id = b.id) AS total_foto,
           (SELECT file_path FROM foto_barang WHERE barang_id = b.id ORDER BY id ASC LIMIT 1) AS foto_utama
    FROM barang b
";
$params = [];

if ($search !== '') {
    $querySql .= " WHERE (b.nama_barang LIKE :q1 OR b.deskripsi LIKE :q2 OR b.satuan LIKE :q3)";
    $params['q1'] = "%{$search}%";
    $params['q2'] = "%{$search}%";
    $params['q3'] = "%{$search}%";
}

$querySql .= " ORDER BY b.stok_saat_ini DESC, b.nama_barang ASC";

$stmt = $db->prepare($querySql);
$stmt->execute($params);
$barangList = $stmt->fetchAll();

// Fetch all photos for lightbox/preview modal
$allPhotosStmt = $db->query("SELECT id, barang_id, file_path FROM foto_barang ORDER BY id ASC");
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
    <title>Katalog Barang Logistik</title>
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

    <!-- Navbar Navigation -->
    <header class="bg-slate-900/90 backdrop-blur-md border-b border-slate-800 sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Brand -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-lg shadow-indigo-500/20">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-base font-bold text-white tracking-wide">Logistik System</span>
                        <span class="hidden sm:inline-block ml-2 px-2 py-0.5 text-[10px] font-semibold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-md">Katalog Barang</span>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="<?= base_url('public/admin/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Dashboard</a>
                        <a href="<?= base_url('public/admin/master_barang.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Master Barang</a>
                        <a href="<?= base_url('public/admin/restock.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Restock</a>
                    <?php endif; ?>

                    <a href="<?= base_url('public/katalog.php') ?>" class="px-3 py-2 rounded-lg text-white bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">Katalog Barang</a>
                    <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="px-3 py-2 rounded-lg text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 hover:bg-emerald-500/20 transition">Lapor Serah Terima</a>
                    <a href="<?= base_url('public/serah_terima/riwayat_saya.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Riwayat Saya</a>

                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Semua Riwayat</a>
                    <?php endif; ?>
                </nav>

                <!-- User Profile & Logout -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-semibold text-white"><?= e($user['nama']) ?></div>
                        <div class="text-[10px] text-slate-400 font-medium capitalize"><?= e($user['role']) ?> Logistik</div>
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
            <a href="<?= base_url('public/katalog.php') ?>" class="text-indigo-400 font-bold py-1">Katalog</a>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="text-emerald-400 font-bold py-1">Lapor</a>
            <a href="<?= base_url('public/serah_terima/riwayat_saya.php') ?>" class="text-slate-400 py-1">Riwayat Saya</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= base_url('public/admin/dashboard.php') ?>" class="text-slate-400 py-1">Dashboard</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-6">

        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="p-4 rounded-xl text-sm font-medium border flex items-center justify-between
                <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : '' ?>
                <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' : '' ?>
            ">
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Hero Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Katalog Stok Barang</h1>
                <p class="text-xs text-slate-400 mt-1">Daftar sisa stok barang ringkas untuk efisiensi tempat & kemudahan pemantauan.</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="<?= base_url('public/serah_terima/lapor.php') ?>"
                    class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-emerald-600/30 flex items-center space-x-2 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                    <span>Lapor Serah Terima Barang</span>
                </a>
            </div>
        </div>

        <!-- Search Bar & View Switcher Toggle -->
        <div class="bg-slate-900/60 backdrop-blur border border-slate-800/80 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4">
            <form action="" method="GET" class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto flex-1">
                <input type="hidden" name="view" value="<?= e($viewMode) ?>">
                <div class="relative w-full sm:w-96">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </span>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Cari nama barang atau deskripsi..."
                        class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                </div>
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-sm font-medium rounded-xl transition">
                    Cari Barang
                </button>
                <?php if ($search !== ''): ?>
                    <a href="<?= base_url('public/katalog.php?view=' . $viewMode) ?>" class="w-full sm:w-auto px-4 py-2.5 bg-slate-800/50 hover:bg-slate-800 text-slate-400 hover:text-white text-sm rounded-xl text-center transition">
                        Reset Filter
                    </a>
                <?php endif; ?>
            </form>

            <!-- View Switcher Toggle Buttons -->
            <div class="flex items-center space-x-1 bg-slate-950 border border-slate-800 p-1 rounded-xl shrink-0 self-end md:self-auto">
                <a href="<?= base_url('public/katalog.php?view=list' . ($search ? '&q=' . urlencode($search) : '')) ?>"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition <?= $viewMode === 'list' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' ?>"
                    title="Tampilan Ringkas List">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <span>Mode List (Ringkas)</span>
                </a>
                <a href="<?= base_url('public/katalog.php?view=grid' . ($search ? '&q=' . urlencode($search) : '')) ?>"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center space-x-1.5 transition <?= $viewMode === 'grid' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' ?>"
                    title="Tampilan Grid Kartu">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    <span>Mode Grid</span>
                </a>
            </div>
        </div>

        <!-- Barang List Container -->
        <?php if (empty($barangList)): ?>
            <div class="bg-slate-900/50 border border-slate-800 rounded-2xl p-12 text-center">
                <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-4 text-slate-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-white">Tidak ada barang ditemukan</h3>
                <p class="text-xs text-slate-400 mt-1">Coba kata kunci lain atau hubungi Admin untuk menambah barang.</p>
            </div>
        <?php else: ?>

            <?php if ($viewMode === 'list'): ?>
                <!-- COMPACT LIST VIEW (Save Space & Compact Layout) -->
                <div class="bg-slate-900/80 border border-slate-800/90 rounded-2xl overflow-hidden shadow-xl divide-y divide-slate-800/70">
                    <?php foreach ($barangList as $b): ?>
                        <div class="p-4 hover:bg-slate-800/40 transition duration-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">

                            <!-- Left: Thumbnail + Item Details -->
                            <div class="flex items-center space-x-4 min-w-0 flex-1">
                                <!-- Photo Thumbnail -->
                                <div class="w-14 h-14 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden shrink-0 relative cursor-pointer group"
                                     onclick='openPhotoGallery(<?= e(json_encode($allPhotos[$b['id']] ?? [])) ?>, <?= e(json_encode($b['nama_barang'])) ?>)'>
                                    <?php if ($b['foto_utama']): ?>
                                        <img src="<?= base_url('public/' . $b['foto_utama']) ?>" alt="<?= e($b['nama_barang']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition">
                                        <?php if ($b['total_foto'] > 1): ?>
                                            <span class="absolute bottom-0 right-0 bg-indigo-600 text-white text-[9px] font-bold px-1 rounded-tl-md">+<?= $b['total_foto'] - 1 ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-600">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Info -->
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                                        <h3 class="font-bold text-white text-base truncate"><?= e($b['nama_barang']) ?></h3>
                                        <span class="text-[11px] font-medium text-slate-400 bg-slate-950 px-2 py-0.5 rounded-md border border-slate-800">
                                            Satuan: <?= e($b['satuan']) ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 truncate mt-1">
                                        <?= e($b['deskripsi'] ?: 'Tidak ada deskripsi rinci.') ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Right: Stock Status & Action -->
                            <div class="flex items-center justify-between sm:justify-end space-x-4 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-800/60">
                                <!-- Stock Numbers & Badge -->
                                <div class="text-left sm:text-right">
                                    <div class="flex items-center sm:justify-end space-x-2">
                                        <?= get_stok_badge($b['stok_saat_ini']) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-1 font-mono">
                                        Sisa: <strong class="text-base <?= $b['stok_saat_ini'] <= 0 ? 'text-rose-400' : 'text-emerald-400' ?>"><?= number_format($b['stok_saat_ini']) ?></strong> <?= e($b['satuan']) ?>
                                        <span class="text-slate-600 mx-1">|</span>
                                        <span class="text-slate-500">Stok Awal: <?= number_format($b['stok_awal']) ?></span>
                                    </div>
                                </div>

                                <!-- Serahkan Button -->
                                <div>
                                    <?php if ($b['stok_saat_ini'] > 0): ?>
                                        <a href="<?= base_url('public/serah_terima/lapor.php?barang_id=' . $b['id']) ?>"
                                            class="px-4 py-2 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/30 hover:border-indigo-500 text-xs font-semibold rounded-xl flex items-center space-x-1.5 transition shadow-md whitespace-nowrap">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                            <span>Serahkan</span>
                                        </a>
                                    <?php else: ?>
                                        <button disabled class="px-4 py-2 bg-slate-800/50 text-slate-600 border border-slate-800 text-xs font-semibold rounded-xl cursor-not-allowed whitespace-nowrap">
                                            Stok Habis
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
                <!-- GRID CARD VIEW MODE -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($barangList as $b): ?>
                        <div class="bg-slate-900/80 border border-slate-800/90 rounded-2xl overflow-hidden hover:border-slate-700/80 transition duration-300 flex flex-col justify-between group shadow-xl">

                            <!-- Item Image Cover / Preview -->
                            <div class="h-48 bg-slate-950 relative overflow-hidden">
                                <?php if ($b['foto_utama']): ?>
                                    <img src="<?= base_url('public/' . $b['foto_utama']) ?>" alt="<?= e($b['nama_barang']) ?>"
                                        class="w-full h-full object-cover group-hover:scale-105 transition duration-500 cursor-pointer"
                                        onclick='openPhotoGallery(<?= e(json_encode($allPhotos[$b['id']] ?? [])) ?>, <?= e(json_encode($b['nama_barang'])) ?>)'>
                                    <?php if ($b['total_foto'] > 1): ?>
                                        <button onclick='openPhotoGallery(<?= e(json_encode($allPhotos[$b['id']] ?? [])) ?>, <?= e(json_encode($b['nama_barang'])) ?>)'
                                            class="absolute bottom-2 right-2 px-2 py-1 bg-slate-950/80 backdrop-blur text-xs font-semibold text-slate-300 rounded-lg border border-slate-700 flex items-center space-x-1 hover:text-white">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                            <span>+<?= $b['total_foto'] - 1 ?> Foto</span>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-700">
                                        <svg class="w-10 h-10 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="text-xs">Tanpa Foto</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Badge Top Left -->
                                <div class="absolute top-3 left-3">
                                    <?= get_stok_badge($b['stok_saat_ini']) ?>
                                </div>
                            </div>

                            <!-- Card Body -->
                            <div class="p-5 flex-1 flex flex-col justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-white tracking-tight group-hover:text-indigo-400 transition mb-1">
                                        <?= e($b['nama_barang']) ?>
                                    </h3>
                                    <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                                        <?= e($b['deskripsi'] ?: 'Tidak ada deskripsi rinci.') ?>
                                    </p>
                                </div>

                                <div class="mt-4 pt-4 border-t border-slate-800/80">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <div class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Sisa Stok Saat Ini</div>
                                            <div class="text-xl font-extrabold font-mono <?= $b['stok_saat_ini'] <= 0 ? 'text-rose-400' : 'text-emerald-400' ?>">
                                                <?= number_format($b['stok_saat_ini']) ?>
                                                <span class="text-xs font-normal text-slate-400 ml-0.5"><?= e($b['satuan']) ?></span>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] text-slate-500 font-medium uppercase tracking-wider">Stok Awal</div>
                                            <div class="text-xs font-mono text-slate-400">
                                                <?= number_format($b['stok_awal']) ?> <?= e($b['satuan']) ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Action Button -->
                                    <?php if ($b['stok_saat_ini'] > 0): ?>
                                        <a href="<?= base_url('public/serah_terima/lapor.php?barang_id=' . $b['id']) ?>"
                                            class="w-full py-2.5 px-3 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/30 hover:border-indigo-500 text-xs font-semibold rounded-xl flex items-center justify-center space-x-2 transition duration-200 shadow-md">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                            <span>Serahkan Barang Ini</span>
                                        </a>
                                    <?php else: ?>
                                        <button disabled
                                            class="w-full py-2.5 px-3 bg-slate-800/50 text-slate-600 border border-slate-800 text-xs font-semibold rounded-xl cursor-not-allowed text-center">
                                            Stok Habis
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>

    </main>

    <!-- Modal Gallery Preview -->
    <div id="galleryModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-md hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl relative">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <h3 id="galleryTitle" class="text-base font-bold text-white">Galeri Foto Barang</h3>
                <button onclick="closeGalleryModal()" class="text-slate-400 hover:text-white p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div id="galleryImages" class="grid grid-cols-2 sm:grid-cols-3 gap-3 max-h-[60vh] overflow-y-auto p-1"></div>
        </div>
    </div>

    <script>
        function openPhotoGallery(photos, title) {
            document.getElementById('galleryTitle').innerText = title;
            const container = document.getElementById('galleryImages');
            container.innerHTML = '';
            if (photos && photos.length > 0) {
                photos.forEach(p => {
                    const div = document.createElement('div');
                    div.className = 'h-40 bg-slate-950 border border-slate-800 rounded-xl overflow-hidden';
                    div.innerHTML = `<img src="${'<?= base_url("public/") ?>' + p.file_path}" class="w-full h-full object-cover">`;
                    container.appendChild(div);
                });
            } else {
                container.innerHTML = '<span class="text-xs text-slate-500 col-span-3">Tidak ada foto lain.</span>';
            }
            document.getElementById('galleryModal').classList.remove('hidden');
        }
        function closeGalleryModal() {
            document.getElementById('galleryModal').classList.add('hidden');
        }
    </script>
</body>
</html>
