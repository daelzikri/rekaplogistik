<?php
/**
 * Authentication Middleware & Session Security
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

function require_auth(): array {
    if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response('error', 'Silakan login terlebih dahulu.', [], 401);
        }
        header('Location: ' . base_url('public/auth/login.php'));
        exit;
    }

    $db = get_db_connection();
    $stmt = $db->prepare("SELECT id, nama, username, role, session_token, last_activity_at, locked_until FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: ' . base_url('public/auth/login.php'));
        exit;
    }

    // Check Account Lockout
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        session_destroy();
        set_flash_message('error', 'Akun Anda sedang terkunci hingga ' . date('H:i', strtotime($user['locked_until'])) . ' WIB karena percobaan login berulang.');
        header('Location: ' . base_url('public/auth/login.php'));
        exit;
    }

    // Single Active Session Verification (1 Akun = 1 Device)
    if ($user['session_token'] !== $_SESSION['session_token']) {
        session_destroy();
        set_flash_message('warning', 'Sesi Anda diakhiri karena akun ini telah login di perangkat/browser lain.');
        header('Location: ' . base_url('public/auth/login.php'));
        exit;
    }

    // Inactivity Timeout Verification (Default 30 Menit / 1800 detik)
    $timeoutSeconds = 1800;
    if ($user['last_activity_at']) {
        $inactiveTime = time() - strtotime($user['last_activity_at']);
        if ($inactiveTime > $timeoutSeconds) {
            // Update db session token to null
            $stmtNull = $db->prepare("UPDATE users SET session_token = NULL WHERE id = :id");
            $stmtNull->execute(['id' => $user['id']]);

            write_audit_log($db, $user['id'], 'AUTO_LOGOUT', 'Sesi berakhir otomatis karena tidak aktif selama > 30 menit.');
            session_destroy();
            set_flash_message('info', 'Sesi Anda telah berakhir otomatis karena tidak ada aktivitas.');
            header('Location: ' . base_url('public/auth/login.php'));
            exit;
        }
    }

    // Update Last Activity
    $upStmt = $db->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = :id");
    $upStmt->execute(['id' => $user['id']]);

    return $user;
}

/**
 * Enforce Hak Akses Berdasarkan Role
 * @param array $allowedRoles Array of roles e.g. ['admin', 'pekerja']
 * @return array Current authenticated user array
 */
function require_role(array $allowedRoles): array {
    $user = require_auth();
    if (!in_array($user['role'], $allowedRoles, true)) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response('error', 'Anda tidak memiliki hak akses untuk fitur ini.', [], 403);
        }
        set_flash_message('error', 'Akses ditolak. Anda tidak memiliki izin membuka halaman tersebut.');
        header('Location: ' . ($user['role'] === 'admin' ? base_url('public/admin/dashboard.php') : base_url('public/katalog.php')));
        exit;
    }
    return $user;
}
