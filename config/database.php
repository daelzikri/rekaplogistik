<?php
/**
 * Database Connection Configuration (PDO Prepared Statements)
 * Sistem Stok & Serah Terima Barang Logistik
 */

// Load server-specific local configuration if present (Ignored by Git)
if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
}

// Fallback configuration if constants are not defined in database.local.php
if (!defined('DB_HOST'))    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
if (!defined('DB_PORT'))    define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME'))    define('DB_NAME', getenv('DB_NAME') ?: 'logistik_barang');
if (!defined('DB_USER'))    define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS'))    define('DB_PASS', getenv('DB_PASS') ?: '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Mendapatkan koneksi PDO ke database
 * @return PDO
 */
function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Koneksi ke database gagal. Silakan periksa kredensial database di config/database.local.php.");
        }
    }
    return $pdo;
}
