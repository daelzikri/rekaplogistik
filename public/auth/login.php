<?php
/**
 * Login Page & Authentication Handler
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/csrf.php';

// Redirect if already logged in
if (!empty($_SESSION['user_id']) && !empty($_SESSION['session_token'])) {
    $db = get_db_connection();
    $stmt = $db->prepare("SELECT role, session_token FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $u = $stmt->fetch();
    if ($u && $u['session_token'] === $_SESSION['session_token']) {
        header('Location: ' . ($u['role'] === 'admin' ? base_url('public/admin/dashboard.php') : base_url('public/katalog.php')));
        exit;
    }
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT id, nama, username, password_hash, role, failed_login_count, locked_until FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            $error = 'Username atau password salah.';
            write_audit_log($db, null, 'LOGIN_FAILED', "Percobaan login gagal untuk username tidak terdaftar: {$username}");
        } else {
            // Check Account Lockout
            if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                $sisaMenit = ceil((strtotime($user['locked_until']) - time()) / 60);
                $error = "Akun terkunci karena 5x gagal login. Silakan tunggu {$sisaMenit} menit lagi (hingga " . date('H:i', strtotime($user['locked_until'])) . " WIB) atau hubungi Admin.";
                write_audit_log($db, $user['id'], 'LOGIN_BLOCKED_LOCKED', "Mencoba login saat akun terkunci.");
            } else {
                if (password_verify($password, $user['password_hash'])) {
                    // Reset lockout counters & generate new session token
                    $sessionToken = bin2hex(random_bytes(32));
                    $upStmt = $db->prepare("UPDATE users SET failed_login_count = 0, locked_until = NULL, session_token = :token, last_activity_at = NOW() WHERE id = :id");
                    $upStmt->execute([
                        'token' => $sessionToken,
                        'id'    => $user['id']
                    ]);

                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['nama']          = $user['nama'];
                    $_SESSION['role']          = $user['role'];
                    $_SESSION['session_token'] = $sessionToken;

                    write_audit_log($db, $user['id'], 'LOGIN_SUCCESS', "User {$user['nama']} ({$user['role']}) berhasil login.");

                    set_flash_message('success', "Selamat datang kembali, {$user['nama']}!");
                    header('Location: ' . ($user['role'] === 'admin' ? base_url('public/admin/dashboard.php') : base_url('public/katalog.php')));
                    exit;
                } else {
                    $newFailedCount = $user['failed_login_count'] + 1;
                    $lockUntil = null;

                    if ($newFailedCount >= 5) {
                        $lockUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                        $upStmt = $db->prepare("UPDATE users SET failed_login_count = :count, locked_until = :lock WHERE id = :id");
                        $upStmt->execute(['count' => $newFailedCount, 'lock' => $lockUntil, 'id' => $user['id']]);
                        $error = "Password salah. Akun Anda telah terkunci selama 15 menit karena 5x gagal login.";
                        write_audit_log($db, $user['id'], 'ACCOUNT_LOCKED', "Akun terkunci otomatis setelah 5 kali salah password.");
                    } else {
                        $upStmt = $db->prepare("UPDATE users SET failed_login_count = :count WHERE id = :id");
                        $upStmt->execute(['count' => $newFailedCount, 'id' => $user['id']]);
                        $sisaCoba = 5 - $newFailedCount;
                        $error = "Username atau password salah. (Sisa percobaan: {$sisaCoba}x sebelum akun terkunci)";
                        write_audit_log($db, $user['id'], 'LOGIN_FAILED', "Salah password. Percobaan ke-{$newFailedCount}.");
                    }
                }
            }
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
    <title>Login - Sistem Stok Logistik</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full flex items-center justify-center p-4 bg-slate-950 text-slate-100 relative overflow-hidden">
    <!-- Ambient Background Glow -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-emerald-600/20 rounded-full blur-3xl pointer-events-none"></div>

    <div class="w-full max-w-md relative z-10">
        <!-- Card Container -->
        <div class="bg-slate-900/80 backdrop-blur-xl border border-slate-800/80 rounded-2xl p-8 shadow-2xl shadow-indigo-950/40">
            <!-- Header Logo/Icon -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-400 text-white mb-4 shadow-lg shadow-indigo-500/30">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-white">Sistem Logistik</h1>
                <p class="text-xs text-slate-400 mt-1">Stok & Serah Terima Barang Divisi Logistik</p>
            </div>

            <!-- Flash Alert -->
            <?php if ($flash): ?>
                <div class="mb-6 p-4 rounded-xl text-sm font-medium border flex items-start space-x-3
                    <?= $flash['type'] === 'success' ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20' : '' ?>
                    <?= $flash['type'] === 'error' ? 'bg-rose-500/10 text-rose-300 border-rose-500/20' : '' ?>
                    <?= $flash['type'] === 'warning' ? 'bg-amber-500/10 text-amber-300 border-amber-500/20' : '' ?>
                    <?= $flash['type'] === 'info' ? 'bg-sky-500/10 text-sky-300 border-sky-500/20' : '' ?>
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

            <!-- Login Form -->
            <form action="" method="POST" class="space-y-5">
                <?= csrf_field() ?>
                <div>
                    <label for="username" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Username</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </span>
                        <input type="text" id="username" name="username" required autocomplete="username" value="<?= e($_POST['username'] ?? '') ?>"
                            class="w-full pl-11 pr-4 py-3 bg-slate-950/70 border border-slate-800 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition duration-200"
                            placeholder="Masukkan username Anda">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </span>
                        <input type="password" id="password" name="password" required autocomplete="current-password"
                            class="w-full pl-11 pr-4 py-3 bg-slate-950/70 border border-slate-800 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition duration-200"
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit"
                    class="w-full py-3.5 px-4 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm rounded-xl shadow-lg shadow-indigo-600/30 hover:shadow-indigo-500/50 transition duration-200 flex items-center justify-center space-x-2">
                    <span>Masuk ke Sistem</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-slate-800/60 text-center text-xs text-slate-500">
                &copy; <?= date('Y') ?> Divisi Logistik. Sistem Keamanan Terpadu.
            </div>
        </div>
    </div>
</body>
</html>
