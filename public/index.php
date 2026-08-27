<?php
/**
 * Application Entry & Role Router
 * Sistem Stok & Serah Terima Barang Logistik
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session
if (!empty($_SESSION['user_id']) && !empty($_SESSION['session_token'])) {
    try {
        $db = get_db_connection();
        $stmt = $db->prepare("SELECT role, session_token FROM users WHERE id = :id");
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $u = $stmt->fetch();

        if ($u && $u['session_token'] === $_SESSION['session_token']) {
            if ($u['role'] === 'admin') {
                header('Location: ' . base_url('public/admin/dashboard.php'));
                exit;
            } else {
                header('Location: ' . base_url('public/katalog.php'));
                exit;
            }
        }
    } catch (Exception $e) {
        error_log("Router Error: " . $e->getMessage());
    }
}

// Default redirect to login
header('Location: ' . base_url('public/auth/login.php'));
exit;
