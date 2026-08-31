<?php
/**
 * Form & Processor Pengembalian Barang (Stok Masuk Realtime)
 * Sistem Rekap Logistik Barang
 */

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/upload_helper.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = require_auth();
$db = get_db_connection();

$error = null;
$selectedBarangId = (int)($_GET['barang_id'] ?? 0);

// Fetch list of available items for dropdown
$barangOptionsStmt = $db->query("SELECT id, nama_barang, satuan, stok_saat_ini FROM barang ORDER BY nama_barang ASC");
$barangOptions = $barangOptionsStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $barangId       = (int)($_POST['barang_id'] ?? 0);
    $jumlah         = (int)($_POST['jumlah'] ?? 0);
    $namaPengembali = trim($_POST['nama_penerima'] ?? ''); // Saved into nama_penerima field for consistency
    $catatan        = trim($_POST['catatan'] ?? '');

    // Validation
    if ($barangId <= 0) {
        $error = 'Silakan pilih barang yang dikembalikan.';
    } elseif ($jumlah <= 0) {
        $error = 'Jumlah barang yang dikembalikan harus lebih dari 0.';
    } elseif (mb_strlen($namaPengembali) < 2) {
        $error = 'Nama pengembali barang wajib diisi (minimal 2 karakter).';
    } elseif (empty($_FILES['bukti_foto']['name'][0])) {
        $error = 'Bukti foto pengembalian wajib diunggah.';
    } else {
        $uploadedPhotos = [];
        try {
            // 1. Upload & Process Proof Photos First
            $uploadedPhotos = process_multiple_image_uploads($_FILES['bukti_foto'], 'transaksi');

            // 2. Begin Database Transaction
            $db->beginTransaction();

            // 3. Row Lock Item to prevent race conditions
            $lockStmt = $db->prepare("SELECT id, nama_barang, satuan, stok_saat_ini FROM barang WHERE id = :id FOR UPDATE");
            $lockStmt->execute(['id' => $barangId]);
            $barang = $lockStmt->fetch();

            if (!$barang) {
                throw new Exception('Barang yang dipilih tidak ditemukan dalam database.');
            }

            $stokSebelum = (int)$barang['stok_saat_ini'];
            $stokSesudah = $stokSebelum + $jumlah;

            // 4. Update Stock in Barang Table (Increase stock)
            $upStmt = $db->prepare("UPDATE barang SET stok_saat_ini = :total WHERE id = :id");
            $upStmt->execute([
                'total' => $stokSesudah,
                'id'    => $barangId
            ]);

            // 5. Insert Log Transaksi with tipe_transaksi = 'pengembalian'
            $tStmt = $db->prepare("
                INSERT INTO transaksi (barang_id, tipe_transaksi, penyerah_id, nama_penerima, jumlah, stok_sebelum, stok_sesudah, catatan, waktu_transaksi)
                VALUES (:b_id, 'pengembalian', :p_id, :pengembali, :jumlah, :stok_seb, :stok_ses, :catatan, NOW())
            ");
            $tStmt->execute([
                'b_id'       => $barangId,
                'p_id'       => $user['id'],
                'pengembali' => $namaPengembali,
                'jumlah'     => $jumlah,
                'stok_seb'   => $stokSebelum,
                'stok_ses'   => $stokSesudah,
                'catatan'    => $catatan
            ]);
            $transaksiId = $db->lastInsertId();

            // 6. Insert Foto Transaksi Records
            $ftStmt = $db->prepare("INSERT INTO foto_transaksi (transaksi_id, file_path, format_asli, nama_file_server) VALUES (:t_id, :path, :fmt, :name)");
            foreach ($uploadedPhotos as $f) {
                $ftStmt->execute([
                    't_id' => $transaksiId,
                    'path' => $f['file_path'],
                    'fmt'  => $f['format_asli'],
                    'name' => $f['nama_file_server']
                ]);
            }

            // 7. Write Audit Log
            write_audit_log(
                $db,
                $user['id'],
                'PENGEMBALIAN_BARANG',
                "Menerima pengembalian +{$jumlah} {$barang['satuan']} '{$barang['nama_barang']}' dari '{$namaPengembali}'. Stok terkini: {$stokSesudah} {$barang['satuan']}."
            );

            // 8. Commit Transaction
            $db->commit();

            set_flash_message('success', "Laporan pengembalian berhasil disimpan! +{$jumlah} {$barang['satuan']} '{$barang['nama_barang']}' dari '{$namaPengembali}' dikembalikan ke stok. Total stok terkini: {$stokSesudah} {$barang['satuan']}.");
            header('Location: ' . base_url('public/serah_terima/riwayat_saya.php'));
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Cleanup uploaded physical image files on error
            foreach ($uploadedPhotos as $f) {
                $fullPath = __DIR__ . '/../' . $f['file_path'];
                if (file_exists($fullPath)) @unlink($fullPath);
            }
            $error = $e->getMessage();
        }
    }
}

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form Lapor Pengembalian Barang - Sistem Logistik</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Client-side HEIC Converter -->
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

    <?php render_navbar('pengembalian', $user); ?>

    <!-- Main Content Form -->
    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">

        <!-- Form Navigation Switch Tabs -->
        <div class="flex items-center space-x-2 mb-6 bg-slate-900/80 p-1.5 rounded-2xl border border-slate-800">
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>"
                class="flex-1 py-2.5 px-4 text-center rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition flex items-center justify-center space-x-2">
                <svg class="w-4 h-4 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                <span>Serah Terima (Barang Keluar)</span>
            </a>
            <a href="<?= base_url('public/serah_terima/pengembalian.php') ?>"
                class="flex-1 py-2.5 px-4 text-center rounded-xl text-xs font-bold bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center space-x-2 shadow-lg shadow-emerald-950/40">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Pengembalian Barang (Stok Masuk)</span>
            </a>
        </div>

        <!-- Form Card Container -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/90 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-emerald-950/20">

            <div class="flex items-center space-x-4 mb-6 pb-6 border-b border-slate-800">
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-extrabold text-white">Lapor Pengembalian Barang</h1>
                    <p class="text-xs text-slate-400 mt-0.5">Catat barang dikembalikan & upload bukti foto. Stok barang akan otomatis bertambah kembali secara realtime.</p>
                </div>
            </div>

            <!-- Flash Alert -->
            <?php if ($flash): ?>
                <div class="mb-6 p-4 rounded-xl text-sm font-medium border flex items-center justify-between
                    <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : '' ?>
                    <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' : '' ?>
                ">
                    <span><?= e($flash['message']) ?></span>
                </div>
            <?php endif; ?>

            <!-- Error Alert -->
            <?php if ($error): ?>
                <div class="mb-6 p-4 rounded-xl text-sm font-medium bg-rose-500/10 text-rose-300 border border-rose-500/20 flex items-start space-x-3">
                    <svg class="w-5 h-5 text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span><?= e($error) ?></span>
                </div>
            <?php endif; ?>

            <form id="pengembalianForm" action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                <?= csrf_field() ?>

                <!-- Penerima Laporan (Readonly Info) -->
                <div class="p-4 bg-slate-950/60 border border-slate-800 rounded-2xl flex items-center justify-between text-xs">
                    <span class="text-slate-400 font-medium">Penerima Laporan (Admin Logistik):</span>
                    <div class="flex items-center space-x-2">
                        <span class="font-bold text-white"><?= e($user['nama']) ?></span>
                        <span class="px-2 py-0.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-md font-semibold">Admin</span>
                    </div>
                </div>

                <!-- Choose Barang -->
                <div>
                    <label for="barang_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Pilih Barang Yang Dikembalikan *
                    </label>
                    <select id="barang_id" name="barang_id" required onchange="updateStockPreview()"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-500 transition">
                        <option value="">-- Pilih Barang Master --</option>
                        <?php foreach ($barangOptions as $bo): ?>
                            <option value="<?= $bo['id'] ?>"
                                    data-stok="<?= $bo['stok_saat_ini'] ?>"
                                    data-satuan="<?= e($bo['satuan']) ?>"
                                    <?= ($selectedBarangId == $bo['id'] || ($_POST['barang_id'] ?? 0) == $bo['id']) ? 'selected' : '' ?>>
                                <?= e($bo['nama_barang']) ?> — Sisa Stok Saat Ini: <?= number_format($bo['stok_saat_ini']) ?> <?= e($bo['satuan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Dynamic Stock Preview Card -->
                    <div id="stockPreviewCard" class="mt-3 p-3 bg-slate-950 border border-slate-800/80 rounded-xl hidden flex items-center justify-between text-xs">
                        <span class="text-slate-400">Sisa stok sebelum pengembalian:</span>
                        <span id="stockPreviewBadge" class="font-bold text-emerald-400 font-mono text-sm"></span>
                    </div>
                </div>

                <!-- Input Jumlah & Nama Pengembali -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="jumlah" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Jumlah Barang Dikembalikan *
                        </label>
                        <input type="number" id="jumlah" name="jumlah" min="1" required value="<?= e($_POST['jumlah'] ?? '1') ?>"
                            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono text-sm focus:outline-none focus:border-emerald-500 transition"
                            placeholder="Contoh: 5">
                    </div>

                    <div>
                        <label for="nama_penerima" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Nama Pengembali Barang *
                        </label>
                        <input type="text" id="nama_penerima" name="nama_penerima" required value="<?= e($_POST['nama_penerima'] ?? '') ?>"
                            class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-500 transition"
                            placeholder="Tulis nama pengembali (Contoh: Pak Anton, Panitia Event)">
                        <p class="text-[11px] text-slate-500 mt-1">Nama orang/pihak yang mengembalikan barang ke divisi.</p>
                    </div>
                </div>

                <!-- Bukti Foto Upload (HEIC + Multi Upload) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Upload Bukti Foto Pengembalian (Wajib) *
                    </label>
                    <div class="border-2 border-dashed border-slate-800 hover:border-emerald-500/50 rounded-2xl p-4 bg-slate-950/60 text-center transition">
                        <input type="file" id="bukti_foto" name="bukti_foto[]" multiple accept="image/*,.heic,.heif" required onchange="handleFilePreview(event)"
                            class="hidden">
                        <label for="bukti_foto" class="cursor-pointer flex flex-col items-center justify-center space-y-2 py-3">
                            <div class="w-12 h-12 rounded-full bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                            </div>
                            <span class="text-xs font-semibold text-emerald-400">Klik untuk memilih bukti foto pengembalian</span>
                            <span class="text-[11px] text-slate-500">Mendukung format JPG, PNG, WEBP, dan HEIC (iPhone). Maksimal 10MB per file.</span>
                        </label>
                    </div>

                    <!-- Image Preview List -->
                    <div id="previewContainer" class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4 hidden"></div>
                    <div id="heicProcessingMsg" class="hidden mt-2 text-xs text-amber-400 font-medium animate-pulse flex items-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Mengonversi foto HEIC di sisi browser... Mohon tunggu.</span>
                    </div>
                </div>

                <!-- Catatan Opsional -->
                <div>
                    <label for="catatan" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Catatan Opsional (Kondisi Barang Kembalian)
                    </label>
                    <textarea id="catatan" name="catatan" rows="3"
                        class="w-full px-4 py-3 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-500 transition"
                        placeholder="Contoh: Barang dikembalikan dalam kondisi lengkap & bersih..."><?= e($_POST['catatan'] ?? '') ?></textarea>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-slate-800">
                    <button type="submit" id="submitBtn"
                        class="w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm rounded-xl shadow-lg shadow-emerald-600/30 hover:shadow-emerald-500/50 flex items-center justify-center space-x-2 transition duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Simpan Pengembalian & Tambah Stok</span>
                    </button>
                </div>
            </form>
        </div>

    </main>

    <script>
        function updateStockPreview() {
            const select = document.getElementById('barang_id');
            const selectedOption = select.options[select.selectedIndex];
            const card = document.getElementById('stockPreviewCard');
            const badge = document.getElementById('stockPreviewBadge');

            if (selectedOption && selectedOption.value) {
                const stok = selectedOption.getAttribute('data-stok');
                const satuan = selectedOption.getAttribute('data-satuan');
                badge.innerText = `${stok} ${satuan}`;
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        }

        async function handleFilePreview(event) {
            const files = event.target.files;
            const container = document.getElementById('previewContainer');
            const heicMsg = document.getElementById('heicProcessingMsg');

            container.innerHTML = '';
            if (files.length > 0) {
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
                return;
            }

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const ext = file.name.split('.').pop().toLowerCase();

                const div = document.createElement('div');
                div.className = 'h-24 bg-slate-950 border border-slate-800 rounded-xl overflow-hidden relative group';

                if (ext === 'heic' || ext === 'heif') {
                    heicMsg.classList.remove('hidden');
                    try {
                        const convertedBlob = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.8 });
                        const url = URL.createObjectURL(convertedBlob);
                        div.innerHTML = `<img src="${url}" class="w-full h-full object-cover">`;
                    } catch (e) {
                        div.innerHTML = `<div class="w-full h-full flex items-center justify-center text-[10px] text-amber-400 p-1 text-center font-bold">HEIC File</div>`;
                    } finally {
                        heicMsg.classList.add('hidden');
                    }
                } else {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
                    }
                    reader.readAsDataURL(file);
                }

                container.appendChild(div);
            }
        }

        document.addEventListener('DOMContentLoaded', updateStockPreview);
    </script>
</body>
</html>
