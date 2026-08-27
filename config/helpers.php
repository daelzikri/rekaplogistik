<?php
/**
 * Global Helpers & Utilities
 * Sistem Stok & Serah Terima Barang Logistik
 */

if (session_status() === PHP_SESSION_NONE) {
    // Session Cookie Parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * XSS Escaping Helper
 * @param string|null $val
 * @return string
 */
function e(?string $val): string {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Mendapatkan IP Address Client
 * @return string
 */
function get_client_ip(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Menulis Catatan ke Audit Log
 * @param PDO $db
 * @param int|null $userId
 * @param string $aksi
 * @param string $detail
 * @return bool
 */
function write_audit_log(PDO $db, ?int $userId, string $aksi, string $detail): bool {
    try {
        $stmt = $db->prepare("INSERT INTO audit_log (user_id, aksi, detail, ip_address) VALUES (:user_id, :aksi, :detail, :ip_address)");
        return $stmt->execute([
            'user_id'    => $userId,
            'aksi'       => $aksi,
            'detail'     => $detail,
            'ip_address' => get_client_ip()
        ]);
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Format Tanggal Indonesia
 * @param string|null $datetime
 * @return string
 */
function format_tanggal_indonesia(?string $datetime): string {
    if (!$datetime) return '-';
    $time = strtotime($datetime);
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', $time);
    $bulan = $bulanIndo[(int)date('m', $time)];
    $tahun = date('Y', $time);
    $jam = date('H:i', $time);
    return "$tgl $bulan $tahun, $jam WIB";
}

/**
 * Generasi Badge Status Stok untuk UI
 * @param int $stok
 * @return string HTML Badge
 */
function get_stok_badge(int $stok): string {
    if ($stok <= 0) {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5 animate-pulse"></span>Stok Habis
                </span>';
    } elseif ($stok <= 5) {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>Stok Menipis (' . $stok . ')
                </span>';
    } else {
        return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>Stok Tersedia (' . $stok . ')
                </span>';
    }
}

/**
 * Set Flash Message ke Session
 * @param string $type ('success' | 'error' | 'warning' | 'info')
 * @param string $message
 */
function set_flash_message(string $type, string $message): void {
    $_SESSION['flash_message'] = [
        'type'    => $type,
        'message' => $message
    ];
}

/**
 * Get dan Clear Flash Message dari Session
 * @return array|null
 */
function get_flash_message(): ?array {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

/**
 * Utility Response JSON
 * @param string $status
 * @param string $message
 * @param array $data
 * @param int $httpCode
 */
function json_response(string $status, string $message, array $data = [], int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'  => $status,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Base URL application helper
 * @param string $path
 * @return string
 */
function base_url(string $path = ''): string {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dirName = rtrim(dirname($scriptName), '/');

    // Remove /public or subfolders of /public to find root base directory
    $base = '';
    if (($pos = strpos($scriptName, '/public/')) !== false) {
        $base = substr($scriptName, 0, $pos);
    } elseif ($dirName === '/public' || substr($dirName, -7) === '/public') {
        $base = substr($dirName, 0, -7);
    } elseif ($dirName !== '' && $dirName !== '/' && $dirName !== '\\') {
        $base = $dirName;
    }

    $cleanPath = ltrim($path, '/');
    return ($base === '' ? '' : $base) . '/' . $cleanPath;
}
