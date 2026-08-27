<?php
/**
 * Kelola Akun Pekerja & Reset Sesi (Admin Only)
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/csrf.php';

$user = require_role(['admin']);
$db = get_db_connection();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama     = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? 'pekerja';

        if (empty($nama) || empty($username) || empty($password)) {
            set_flash_message('error', 'Semua kolom pendaftaran akun wajib diisi.');
        } elseif (!in_array($role, ['admin', 'pekerja'], true)) {
            set_flash_message('error', 'Role user tidak valid.');
        } else {
            // Check username uniqueness
            $uStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
            $uStmt->execute(['u' => $username]);
            if ((int)$uStmt->fetchColumn() > 0) {
                set_flash_message('error', "Username '{$username}' sudah digunakan oleh akun lain.");
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $iStmt = $db->prepare("INSERT INTO users (nama, username, password_hash, role) VALUES (:nama, :u, :hash, :role)");
                $iStmt->execute([
                    'nama' => $nama,
                    'u'    => $username,
                    'hash' => $hash,
                    'role' => $role
                ]);

                write_audit_log($db, $user['id'], 'BUAT_AKUN_USER', "Membuat akun baru: '{$username}' ({$nama}) sebagai role {$role}.");
                set_flash_message('success', "Akun '{$nama}' ({$username}) berhasil dibuat.");
                header('Location: ' . base_url('public/admin/kelola_akun.php'));
                exit;
            }
        }
    } elseif ($action === 'reset_password') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $newPass  = $_POST['new_password'] ?? '';

        if ($targetId <= 0 || empty($newPass)) {
            set_flash_message('error', 'Password baru tidak boleh kosong.');
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $rStmt = $db->prepare("UPDATE users SET password_hash = :hash, failed_login_count = 0, locked_until = NULL WHERE id = :id");
            $rStmt->execute(['hash' => $hash, 'id' => $targetId]);

            write_audit_log($db, $user['id'], 'RESET_PASSWORD_USER', "Mereset password user ID: {$targetId}.");
            set_flash_message('success', "Password user berhasil di-reset.");
            header('Location: ' . base_url('public/admin/kelola_akun.php'));
            exit;
        }
    } elseif ($action === 'unlock') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId > 0) {
            $uStmt = $db->prepare("UPDATE users SET failed_login_count = 0, locked_until = NULL WHERE id = :id");
            $uStmt->execute(['id' => $targetId]);

            write_audit_log($db, $user['id'], 'UNLOCK_AKUN_USER', "Membuka kuncian akun user ID: {$targetId}.");
            set_flash_message('success', "Kuncian akun berhasil dibuka.");
            header('Location: ' . base_url('public/admin/kelola_akun.php'));
            exit;
        }
    } elseif ($action === 'reset_sesi') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        if ($targetId > 0) {
            $sStmt = $db->prepare("UPDATE users SET session_token = NULL WHERE id = :id");
            $sStmt->execute(['id' => $targetId]);

            write_audit_log($db, $user['id'], 'FORCE_RESET_SESSION', "Mereset sesi paksa user ID: {$targetId}.");
            set_flash_message('success', "Sesi user berhasil di-reset paksa.");
            header('Location: ' . base_url('public/admin/kelola_akun.php'));
            exit;
        }
    }
}

// Fetch all users list
$usersStmt = $db->query("SELECT id, nama, username, role, session_token, last_activity_at, failed_login_count, locked_until, created_at FROM users ORDER BY role ASC, nama ASC");
$userList = $usersStmt->fetchAll();

$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun Pekerja - Admin Logistik</title>
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
                    <a href="<?= base_url('public/admin/kelola_akun.php') ?>" class="px-3 py-2 rounded-lg text-white bg-indigo-600/20 text-indigo-400 border border-indigo-500/30">Kelola Akun</a>
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

        <!-- Mobile Menu -->
        <div class="md:hidden border-t border-slate-800 px-4 py-2 flex items-center justify-around text-xs font-medium">
            <a href="<?= base_url('public/admin/dashboard.php') ?>" class="text-slate-400 py-1">Dashboard</a>
            <a href="<?= base_url('public/admin/kelola_akun.php') ?>" class="text-indigo-400 font-bold py-1">Akun</a>
            <a href="<?= base_url('public/katalog.php') ?>" class="text-slate-400 py-1">Katalog</a>
            <a href="<?= base_url('public/serah_terima/lapor.php') ?>" class="text-emerald-400 font-bold py-1">Lapor</a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">

        <!-- Title -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Kelola Akun Anggota Logistik</h1>
                <p class="text-xs text-slate-400 mt-1">Buat akun pekerja baru, reset password, buka akun terkunci, dan reset sesi paksa.</p>
            </div>
            <button onclick="openAddUserModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center space-x-2 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                <span>Buat Akun Pekerja Baru</span>
            </button>
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

        <!-- Table User Container -->
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                    <tr>
                        <th class="py-3.5 px-4">Nama Lengkap</th>
                        <th class="py-3.5 px-4">Username</th>
                        <th class="py-3.5 px-4 text-center">Role</th>
                        <th class="py-3.5 px-4 text-center">Status Sesi</th>
                        <th class="py-3.5 px-4 text-center">Status Keamanan</th>
                        <th class="py-3.5 px-4">Aktivitas Terakhir</th>
                        <th class="py-3.5 px-4 text-center">Aksi Admin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($userList as $u):
                        $isLocked = ($u['locked_until'] && strtotime($u['locked_until']) > time());
                        $isActiveSession = !empty($u['session_token']);
                    ?>
                        <tr class="hover:bg-slate-800/30 transition">
                            <td class="py-3 px-4 font-bold text-white text-sm">
                                <?= e($u['nama']) ?>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-300">
                                <?= e($u['username']) ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded-md font-semibold text-[10px] uppercase border
                                    <?= $u['role'] === 'admin' ? 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20' : 'bg-slate-800 text-slate-300 border-slate-700' ?>">
                                    <?= e($u['role']) ?>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <?php if ($isActiveSession): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1 animate-pulse"></span>Sesi Aktif
                                    </span>
                                <?php else: ?>
                                    <span class="text-slate-500 text-[10px]">Offline</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <?php if ($isLocked): ?>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                        Terkunci (<?= date('H:i', strtotime($u['locked_until'])) ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="text-emerald-400 text-[10px]">Normal (<?= $u['failed_login_count'] ?>x gagal)</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4 text-slate-400 whitespace-nowrap">
                                <?= format_tanggal_indonesia($u['last_activity_at']) ?>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="flex items-center justify-center space-x-1.5">
                                    <?php if ($isLocked): ?>
                                        <form action="" method="POST" inline class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="unlock">
                                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="px-2 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-md font-semibold text-[10px] hover:bg-amber-500/20">
                                                Unlock
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($isActiveSession): ?>
                                        <form action="" method="POST" inline class="inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reset_sesi">
                                            <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="px-2 py-1 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-md font-semibold text-[10px] hover:bg-rose-500/20" title="Keluarkan dari device">
                                                Reset Sesi
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <button onclick='openResetPassModal(<?= json_encode($u) ?>)'
                                        class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md font-semibold text-[10px] hover:bg-indigo-500/20">
                                        Reset Pass
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </main>

    <!-- Modal Buat Akun Baru -->
    <div id="addUserModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <h3 class="text-base font-bold text-white">Buat Akun Anggota Logistik</h3>
                <button onclick="closeAddUserModal()" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="" method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tambah">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Nama Lengkap *</label>
                    <input type="text" name="nama" required placeholder="Contoh: Budi Santoso"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Username *</label>
                    <input type="text" name="username" required placeholder="pekerja_budi"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Password *</label>
                    <input type="password" name="password" required placeholder="••••••••"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Role Akun *</label>
                    <select name="role" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                        <option value="pekerja">Pekerja Logistik (Semua Akun Setara)</option>
                        <option value="admin">Admin (CO Lapangan)</option>
                    </select>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-800">
                    <button type="button" onclick="closeAddUserModal()" class="px-3 py-2 text-slate-400 text-xs">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl">Simpan Akun</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Reset Password -->
    <div id="resetPassModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-sm w-full p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <h3 class="text-base font-bold text-white">Reset Password User</h3>
                <button onclick="closeResetPassModal()" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="" method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" id="reset_target_id" name="target_id">

                <p id="reset_user_info" class="text-xs text-slate-400"></p>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Password Baru *</label>
                    <input type="password" name="new_password" required placeholder="Masukkan password baru"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-800">
                    <button type="button" onclick="closeResetPassModal()" class="px-3 py-2 text-slate-400 text-xs">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddUserModal() {
            document.getElementById('addUserModal').classList.remove('hidden');
        }
        function closeAddUserModal() {
            document.getElementById('addUserModal').classList.add('hidden');
        }

        function openResetPassModal(u) {
            document.getElementById('reset_target_id').value = u.id;
            document.getElementById('reset_user_info').innerText = `Reset password untuk ${u.nama} (${u.username}).`;
            document.getElementById('resetPassModal').classList.remove('hidden');
        }
        function closeResetPassModal() {
            document.getElementById('resetPassModal').classList.add('hidden');
        }
    </script>
</body>
</html>
