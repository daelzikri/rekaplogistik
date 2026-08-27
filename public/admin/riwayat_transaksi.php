<?php
/**
 * Riwayat Transaksi Seluruh Anggota & Filter (Admin Only)
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['admin']);
$db = get_db_connection();

// Filters
$tglMulai   = trim($_GET['tgl_mulai'] ?? '');
$tglSelesai = trim($_GET['tgl_selesai'] ?? '');
$barangId   = (int)($_GET['barang_id'] ?? 0);
$penyerahId = (int)($_GET['penyerah_id'] ?? 0);
$search     = trim($_GET['q'] ?? '');

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
if ($search !== '') {
    $where[] = "(t.nama_penerima LIKE :q OR t.catatan LIKE :q OR b.nama_barang LIKE :q)";
    $params['q'] = "%{$search}%";
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

    <!-- Header Navbar -->
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
                        <span class="hidden sm:inline-block ml-2 px-2 py-0.5 text-[10px] font-semibold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md">Admin Portal</span>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                    <a href="<?= base_url('public/admin/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Dashboard</a>
                    <a href="<?= base_url('public/admin/master_barang.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Master Barang</a>
                    <a href="<?= base_url('public/admin/restock.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Restock</a>
                    <a href="<?= base_url('public/admin/kelola_akun.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Kelola Akun</a>
                    <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="px-3 py-2 rounded-lg text-white bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">Semua Riwayat</a>
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

        <!-- Mobile Menu -->
        <div class="md:hidden border-t border-slate-800 px-4 py-2 flex items-center justify-around text-xs font-medium">
            <a href="<?= base_url('public/admin/dashboard.php') ?>" class="text-slate-400 py-1">Dashboard</a>
            <a href="<?= base_url('public/admin/riwayat_transaksi.php') ?>" class="text-indigo-400 font-bold py-1">Riwayat</a>
            <a href="<?= base_url('public/katalog.php') ?>" class="text-slate-400 py-1">Katalog</a>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="text-emerald-400 font-bold py-1">Lapor</a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">

        <!-- Title & Header -->
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Riwayat Transaksi Serah Terima (Seluruh Anggota)</h1>
            <p class="text-xs text-slate-400 mt-1">Audit log seluruh transaksi serah terima barang dari Admin maupun Pekerja Logistik.</p>
        </div>

        <!-- Filter Card -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-5 shadow-xl">
            <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
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
                    <label class="block text-xs font-semibold text-slate-400 uppercase mb-1">Filter Pelapor / Penyerah</label>
                    <select name="penyerah_id"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="">Semua Anggota</option>
                        <?php foreach ($penyerahOptions as $po): ?>
                            <option value="<?= $po['id'] ?>" <?= $penyerahId == $po['id'] ? 'selected' : '' ?>><?= e($po['nama']) ?> (<?= e($po['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

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

        <!-- Table Container -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <?php if (empty($transaksiList)): ?>
                <div class="p-12 text-center text-slate-500 text-xs">
                    Tidak ditemukan data transaksi serah terima yang sesuai filter.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                            <tr>
                                <th class="py-3.5 px-4 text-center">Bukti Foto</th>
                                <th class="py-3.5 px-4">Waktu Transaksi</th>
                                <th class="py-3.5 px-4">Barang</th>
                                <th class="py-3.5 px-4 text-center">Jumlah</th>
                                <th class="py-3.5 px-4 text-center">Audit Stok</th>
                                <th class="py-3.5 px-4">Pelapor (Penyerah)</th>
                                <th class="py-3.5 px-4">Penerima (Bebas)</th>
                                <th class="py-3.5 px-4">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <?php foreach ($transaksiList as $t): ?>
                                <tr class="hover:bg-slate-800/30 transition">
                                    <!-- Bukti Foto -->
                                    <td class="py-3 px-4 text-center">
                                        <div class="w-12 h-12 rounded-xl bg-slate-950 border border-slate-800 overflow-hidden mx-auto relative group">
                                            <?php if ($t['foto_utama']): ?>
                                                <img src="<?= base_url('public/' . $t['foto_utama']) ?>" alt="Bukti Foto"
                                                    class="w-full h-full object-cover cursor-pointer"
                                                    onclick='openProofGallery(<?= json_encode($allPhotos[$t['id']] ?? []) ?>, "Bukti Serah Terima #<?= $t['id'] ?>")'>
                                                <?php if ($t['total_bukti'] > 1): ?>
                                                    <span class="absolute bottom-0 right-0 bg-indigo-600 text-white text-[9px] font-bold px-1 rounded-tl-md">+<?= $t['total_bukti'] - 1 ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-slate-600">-</div>
                                            <?php endif; ?>
                                        </div>
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
                                    <td class="py-3 px-4 text-center font-mono font-extrabold text-sm text-emerald-400">
                                        <?= number_format($t['jumlah']) ?> <?= e($t['satuan']) ?>
                                    </td>
                                    <!-- Audit Stok -->
                                    <td class="py-3 px-4 text-center font-mono text-xs text-slate-400">
                                        <?= number_format($t['stok_sebelum']) ?> &rarr; <strong class="text-white"><?= number_format($t['stok_sesudah']) ?></strong>
                                    </td>
                                    <!-- Pelapor -->
                                    <td class="py-3 px-4">
                                        <div class="font-semibold text-slate-200"><?= e($t['penyerah_nama']) ?></div>
                                        <div class="text-[10px] text-indigo-400 capitalize"><?= e($t['penyerah_role']) ?></div>
                                    </td>
                                    <!-- Penerima -->
                                    <td class="py-3 px-4 font-bold text-indigo-300">
                                        <?= e($t['nama_penerima']) ?>
                                    </td>
                                    <!-- Catatan -->
                                    <td class="py-3 px-4 max-w-xs truncate text-slate-400">
                                        <?= e($t['catatan'] ?: '-') ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
