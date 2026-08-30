<?php
/**
 * Riwayat Transaksi Pribadi (Milik User yang Sedang Login)
 * Dapat diakses oleh Admin dan Pekerja Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
$user = require_role(['admin', 'pekerja']);
$db = get_db_connection();

$search     = trim($_GET['q'] ?? '');
$tglMulai   = trim($_GET['tgl_mulai'] ?? '');
$tglSelesai = trim($_GET['tgl_selesai'] ?? '');

$where = ["t.penyerah_id = :my_id"];
$params = ['my_id' => $user['id']];

if ($tglMulai !== '') {
    $where[] = "DATE(t.waktu_transaksi) >= :tgl_mulai";
    $params['tgl_mulai'] = $tglMulai;
}
if ($tglSelesai !== '') {
    $where[] = "DATE(t.waktu_transaksi) <= :tgl_selesai";
    $params['tgl_selesai'] = $tglSelesai;
}
if ($search !== '') {
    $where[] = "(t.nama_penerima LIKE :q1 OR b.nama_barang LIKE :q2 OR t.catatan LIKE :q3)";
    $params['q1'] = "%{$search}%";
    $params['q2'] = "%{$search}%";
    $params['q3'] = "%{$search}%";
}

$whereSql = implode(" AND ", $where);

$querySql = "
    SELECT t.*, b.nama_barang, b.satuan,
           (SELECT COUNT(*) FROM foto_transaksi WHERE transaksi_id = t.id) AS total_bukti,
           (SELECT file_path FROM foto_transaksi WHERE transaksi_id = t.id ORDER BY id ASC LIMIT 1) AS foto_utama
    FROM transaksi t
    JOIN barang b ON t.barang_id = b.id
    WHERE {$whereSql}
    ORDER BY t.id DESC
";

$stmt = $db->prepare($querySql);
$stmt->execute($params);
$myTransaksi = $stmt->fetchAll();

// Fetch all proof photos for modals
$allPhotosStmt = $db->query("SELECT id, transaksi_id, file_path FROM foto_transaksi ORDER BY id ASC");
$allPhotos = [];
foreach ($allPhotosStmt->fetchAll() as $ph) {
    $allPhotos[$ph['transaksi_id']][] = $ph;
}

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Saya - Sistem Logistik</title>
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

    <!-- Navbar Header -->
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
                        <span class="hidden sm:inline-block ml-2 px-2 py-0.5 text-[10px] font-semibold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md">Riwayat Saya</span>
                    </div>
                </div>

                <!-- Navigation Links -->
                <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="<?= base_url('public/admin/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Dashboard</a>
                        <a href="<?= base_url('public/admin/master_barang.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Master Barang</a>
                        <a href="<?= base_url('public/admin/restock.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Restock</a>
                    <?php endif; ?>

                    <a href="<?= base_url('public/katalog.php') ?>" class="px-3 py-2 rounded-lg text-slate-300 hover:text-white hover:bg-slate-800 transition">Katalog Barang</a>
                    <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="px-3 py-2 rounded-lg text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 hover:bg-emerald-500/20 transition">Lapor Serah Terima</a>
                    <a href="<?= base_url('public/serah_terima/riwayat_saya.php') ?>" class="px-3 py-2 rounded-lg text-white bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">Riwayat Saya</a>

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

        <!-- Mobile Nav Menu -->
        <div class="md:hidden border-t border-slate-800 px-4 py-2 flex items-center justify-around text-xs font-medium">
            <a href="<?= base_url('public/katalog.php') ?>" class="text-slate-400 py-1">Katalog</a>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="text-emerald-400 font-bold py-1">Lapor</a>
            <a href="<?= base_url('public/serah_terima/riwayat_saya.php') ?>" class="text-indigo-400 font-bold py-1">Riwayat Saya</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= base_url('public/admin/dashboard.php') ?>" class="text-slate-400 py-1">Dashboard</a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">

        <!-- Title -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Riwayat Serah Terima Saya</h1>
                <p class="text-xs text-slate-400 mt-1">Daftar seluruh serah terima barang yang pernah Anda laporkan sendiri.</p>
            </div>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>"
                class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-emerald-600/30 flex items-center space-x-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                <span>Buat Laporan Baru</span>
            </a>
        </div>

        <!-- Flash Alert -->
        <?php if ($flash): ?>
            <div class="p-4 rounded-xl text-sm font-medium border flex items-center justify-between
                <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : '' ?>
                <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' : '' ?>
            ">
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Search & Filter Card -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800 rounded-2xl p-4">
            <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Cari Kata Kunci</label>
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Nama barang, penerima..."
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Tanggal Mulai</label>
                    <input type="date" name="tgl_mulai" value="<?= e($tglMulai) ?>"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase mb-1">Tanggal Selesai</label>
                    <input type="date" name="tgl_selesai" value="<?= e($tglSelesai) ?>"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>
                <div class="flex items-center space-x-2">
                    <button type="submit" class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl transition">
                        Filter
                    </button>
                    <?php if ($search !== '' || $tglMulai !== '' || $tglSelesai !== ''): ?>
                        <a href="<?= base_url('public/serah_terima/riwayat_saya.php') ?>" class="py-2 px-3 bg-slate-800 text-slate-400 text-xs rounded-xl">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Content Area -->
        <?php if (empty($myTransaksi)): ?>
            <div class="bg-slate-900/50 border border-slate-800 rounded-2xl p-12 text-center">
                <div class="w-16 h-16 rounded-full bg-slate-800/80 flex items-center justify-center mx-auto mb-4 text-slate-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <h3 class="text-base font-bold text-white">Belum Ada Riwayat Laporan</h3>
                <p class="text-xs text-slate-400 mt-1">Anda belum pernah menyerahkan barang atau tidak ada transaksi yang cocok dengan filter.</p>
            </div>
        <?php else: ?>

            <!-- Desktop View: Table -->
            <div class="hidden lg:block bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4 text-center">Bukti Foto</th>
                            <th class="py-3.5 px-4">Waktu Transaksi</th>
                            <th class="py-3.5 px-4">Nama Barang</th>
                            <th class="py-3.5 px-4 text-center">Jumlah Diserahkan</th>
                            <th class="py-3.5 px-4 text-center">Sisa Stok Setelahnya</th>
                            <th class="py-3.5 px-4">Penerima Barang</th>
                            <th class="py-3.5 px-4">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($myTransaksi as $t): ?>
                            <tr class="hover:bg-slate-800/30 transition">
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
                                <td class="py-3 px-4 text-slate-400 whitespace-nowrap">
                                    <?= format_tanggal_indonesia($t['waktu_transaksi']) ?>
                                </td>
                                <td class="py-3 px-4 font-bold text-white text-sm">
                                    <?= e($t['nama_barang']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-extrabold text-sm text-emerald-400">
                                    <?= number_format($t['jumlah']) ?> <?= e($t['satuan']) ?>
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-slate-400">
                                    <?= number_format($t['stok_sesudah']) ?> <?= e($t['satuan']) ?>
                                </td>
                                <td class="py-3 px-4 font-bold text-indigo-300">
                                    <?= e($t['nama_penerima']) ?>
                                </td>
                                <td class="py-3 px-4 max-w-xs truncate text-slate-400">
                                    <?= e($t['catatan'] ?: '-') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile View: Responsive Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:hidden gap-4">
                <?php foreach ($myTransaksi as $t): ?>
                    <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-5 shadow-lg space-y-3">
                        <div class="flex items-start justify-between">
                            <div>
                                <span class="text-[10px] text-slate-500 font-mono"><?= format_tanggal_indonesia($t['waktu_transaksi']) ?></span>
                                <h3 class="font-bold text-white text-base mt-0.5"><?= e($t['nama_barang']) ?></h3>
                            </div>
                            <span class="font-mono font-extrabold text-emerald-400 text-sm bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1 rounded-lg">
                                <?= number_format($t['jumlah']) ?> <?= e($t['satuan']) ?>
                            </span>
                        </div>

                        <div class="p-3 bg-slate-950/70 border border-slate-800 rounded-xl space-y-1 text-xs">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Diserahkan Kepada:</span>
                                <span class="font-bold text-indigo-300"><?= e($t['nama_penerima']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Sisa Stok Setelah Transaksi:</span>
                                <span class="font-mono text-slate-200"><?= number_format($t['stok_sesudah']) ?> <?= e($t['satuan']) ?></span>
                            </div>
                            <?php if ($t['catatan']): ?>
                                <div class="pt-1 text-slate-400 italic">"<?= e($t['catatan']) ?>"</div>
                            <?php endif; ?>
                        </div>

                        <?php if ($t['foto_utama']): ?>
                            <button onclick='openProofGallery(<?= e(json_encode($allPhotos[$t['id']] ?? [])) ?>, <?= e(json_encode("Bukti Transaksi #" . $t['id'])) ?>)'
                                class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Lihat <?= $t['total_bukti'] ?> Bukti Foto</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </main>

    <!-- Modal Gallery Proof -->
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
