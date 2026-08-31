<?php
/**
 * Kelola Akun Pekerja & Reset Sesi (Admin Only)
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../../config/csrf.php';
require_once __DIR__ . '/../includes/navbar.php';

$user = require_auth();
$db = get_db_connection();

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama     = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $role     = 'admin';

        // Check user count limit (Max 2 accounts)
        $countUsers = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($countUsers >= 2) {
            set_flash_message('error', 'Sistem dibatasi hanya untuk maksimal 2 akun Admin. Tidak dapat menambah akun lebih dari 2.');
        } elseif (empty($nama) || empty($username) || empty($password)) {
            set_flash_message('error', 'Semua kolom pendaftaran akun wajib diisi.');
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

                write_audit_log($db, $user['id'], 'BUAT_AKUN_USER', "Membuat akun Admin baru: '{$username}' ({$nama}).");
                set_flash_message('success', "Akun Admin '{$nama}' ({$username}) berhasil dibuat.");
                header('Location: ' . base_url('public/admin/kelola_akun.php'));
                exit;
            }
        }
    } elseif ($action === 'edit') {
        $targetId = (int)($_POST['target_id'] ?? 0);
        $nama     = trim($_POST['nama'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $role     = 'admin';
        $password = $_POST['password'] ?? '';

        if ($targetId <= 0 || empty($nama) || empty($username)) {
            set_flash_message('error', 'Nama lengkap dan username tidak boleh kosong.');
        } else {
            // Check username uniqueness
            $uStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u AND id != :id");
            $uStmt->execute(['u' => $username, 'id' => $targetId]);
            if ((int)$uStmt->fetchColumn() > 0) {
                set_flash_message('error', "Username '{$username}' sudah digunakan oleh akun lain.");
            } else {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $upStmt = $db->prepare("UPDATE users SET nama = :nama, username = :u, role = :role, password_hash = :hash, failed_login_count = 0, locked_until = NULL WHERE id = :id");
                    $upStmt->execute([
                        'nama' => $nama,
                        'u'    => $username,
                        'role' => $role,
                        'hash' => $hash,
                        'id'   => $targetId
                    ]);
                    $msgDetail = "nama, username, dan password";
                } else {
                    $upStmt = $db->prepare("UPDATE users SET nama = :nama, username = :u, role = :role WHERE id = :id");
                    $upStmt->execute([
                        'nama' => $nama,
                        'u'    => $username,
                        'role' => $role,
                        'id'   => $targetId
                    ]);
                    $msgDetail = "nama dan username";
                }

                write_audit_log($db, $user['id'], 'EDIT_AKUN_USER', "Mengubah data akun ID {$targetId}: {$msgDetail}.");
                set_flash_message('success', "Data akun Admin '{$nama}' ({$username}) berhasil diperbarui.");
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

    <?php render_navbar('kelola_akun', $user); ?>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full space-y-8">

        <!-- Title -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Kelola Akun Admin Logistik</h1>
                <p class="text-xs text-slate-400 mt-1">Sistem ini dikhususkan untuk 2 akun Admin. Anda dapat mengubah nama, username, password, serta reset sesi.</p>
            </div>
            <div>
                <?php if (count($userList) < 2): ?>
                    <button onclick="openAddUserModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center space-x-2 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                        <span>Tambah Akun Admin (<?= count($userList) ?>/2)</span>
                    </button>
                <?php else: ?>
                    <span class="px-3.5 py-2 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-bold text-xs rounded-xl flex items-center space-x-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>2 / 2 Akun Admin Terdaftar</span>
                    </span>
                <?php endif; ?>
            </div>
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
                                <span class="px-2 py-0.5 rounded-md font-semibold text-[10px] uppercase border bg-indigo-500/10 text-indigo-400 border-indigo-500/20">
                                    Admin
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

                                    <button onclick='openEditUserModal(<?= e(json_encode($u)) ?>)'
                                        class="px-2 py-1 bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md font-semibold text-[10px] hover:bg-indigo-500/20 flex items-center space-x-1" title="Edit Nama, Username & Password">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Edit Akun</span>
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
                <h3 class="text-base font-bold text-white">Buat Akun Admin Baru</h3>
                <button onclick="closeAddUserModal()" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="" method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="tambah">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Nama Lengkap *</label>
                    <input type="text" name="nama" required placeholder="Contoh: Admin Dua"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Username *</label>
                    <input type="text" name="username" required placeholder="admin2"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Password *</label>
                    <input type="password" name="password" required placeholder="••••••••"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-800">
                    <button type="button" onclick="closeAddUserModal()" class="px-3 py-2 text-slate-400 text-xs">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl">Simpan Akun Admin</button>
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

    <!-- Modal Edit Akun & Password -->
    <div id="editUserModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm hidden">
        <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3 mb-4">
                <h3 class="text-base font-bold text-white">Edit Data Akun & Password</h3>
                <button onclick="closeEditUserModal()" class="text-slate-400 hover:text-white">&times;</button>
            </div>
            <form action="" method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit_target_id" name="target_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Nama Lengkap *</label>
                    <input type="text" id="edit_nama" name="nama" required placeholder="Nama Lengkap"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Username *</label>
                    <input type="text" id="edit_username" name="username" required placeholder="username"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase mb-1">Password Baru (Opsional)</label>
                    <input type="password" id="edit_password" name="password" placeholder="Kosongkan jika tidak diubah"
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                    <p class="text-[11px] text-slate-500 mt-1">Kosongkan jika hanya ingin mengubah nama/username saja.</p>
                </div>

                <div class="flex justify-end space-x-2 pt-3 border-t border-slate-800">
                    <button type="button" onclick="closeEditUserModal()" class="px-3 py-2 text-slate-400 text-xs">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-xl">Simpan Perubahan</button>
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

        function openEditUserModal(u) {
            document.getElementById('edit_target_id').value = u.id;
            document.getElementById('edit_nama').value = u.nama;
            document.getElementById('edit_username').value = u.username;
            document.getElementById('edit_password').value = '';
            document.getElementById('editUserModal').classList.remove('hidden');
        }
        function closeEditUserModal() {
            document.getElementById('editUserModal').classList.add('hidden');
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
