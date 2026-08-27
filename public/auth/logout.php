<?php
/**
 * Logout Endpoint
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
if ($userId) {
    $db = get_db_connection();
    $stmt = $db->prepare("UPDATE users SET session_token = NULL WHERE id = :id");
    $stmt->execute(['id' => $userId]);

    write_audit_log($db, $userId, 'LOGOUT', 'User berhasil keluar dari sistem.');
}

// Clear Session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}
session_destroy();

session_start();
set_flash_message('success', 'Anda telah berhasil keluar dari sistem.');
header('Location: ' . base_url('public/auth/login.php'));
exit;
