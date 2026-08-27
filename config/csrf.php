<?php
/**
 * CSRF Protection Utilities
 * Sistem Stok & Serah Terima Barang Logistik
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generasi Token CSRF untuk Sesi
 * @return string
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output Input HTML Hidden Field CSRF Token
 * @return string
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Validasi CSRF Token dari Request
 * @param string|null $postedToken
 * @return bool
 */
function verify_csrf_token(?string $postedToken): bool {
    if (empty($_SESSION['csrf_token']) || empty($postedToken)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $postedToken);
}

/**
 * Enforce CSRF Token Verification, Abort if Invalid
 * @param string|null $postedToken
 */
function require_csrf_token(?string $postedToken = null): void {
    if ($postedToken === null) {
        $postedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }
    if (!verify_csrf_token($postedToken)) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            json_response('error', 'Sesi Anda telah kedaluwarsa atau token CSRF tidak valid. Silakan muat ulang halaman.', [], 403);
        }
        set_flash_message('error', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
        exit;
    }
}
