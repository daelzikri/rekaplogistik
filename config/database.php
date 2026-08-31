<?php
/**
 * Database Connection Configuration (PDO Prepared Statements)
 * Sistem Stok & Serah Terima Barang Logistik
 */

$localConfigLoaded = false;

// Load server-specific local configuration if present (Ignored by Git)
if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
    $localConfigLoaded = true;
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
    global $localConfigLoaded;

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

            // Auto Migration: ensure tipe_transaksi column exists in transaksi table
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM `transaksi` LIKE 'tipe_transaksi'")->fetch();
                if (!$colCheck) {
                    $pdo->exec("ALTER TABLE `transaksi` ADD COLUMN `tipe_transaksi` ENUM('serah_terima','pengembalian') NOT NULL DEFAULT 'serah_terima' AFTER `barang_id`");
                }
            } catch (Exception $ex) {
                // Ignore if table doesn't exist yet
            }

            // Auto Migration: ensure role column allows admin and updates any pekerja to admin
            try {
                $pdo->exec("UPDATE `users` SET `role` = 'admin' WHERE `role` != 'admin'");
            } catch (Exception $ex) {
                // Ignore
            }

        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());

            // Diagnostic Output for Database Connection Errors
            http_response_code(500);
            header('Content-Type: text/html; charset=utf-8');
            ?>
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Koneksi Database Gagal</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans">
                <div class="max-w-lg w-full bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                    <div class="flex items-center space-x-3 text-rose-400">
                        <svg class="w-7 h-7 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <h2 class="text-lg font-bold text-white">Koneksi Database Gagal</h2>
                    </div>

                    <div class="p-3.5 bg-slate-950 border border-slate-800 rounded-xl space-y-2 text-xs font-mono">
                        <div><span class="text-slate-500">File Config Terbaca:</span> <strong class="<?= $localConfigLoaded ? 'text-emerald-400' : 'text-amber-400' ?>"><?= $localConfigLoaded ? 'config/database.local.php (Aktif)' : 'config/database.php (Default Fallback)' ?></strong></div>
                        <div><span class="text-slate-500">DB Host:</span> <span class="text-slate-200"><?= htmlspecialchars(DB_HOST) ?></span></div>
                        <div><span class="text-slate-500">DB Name:</span> <span class="text-slate-200"><?= htmlspecialchars(DB_NAME) ?></span></div>
                        <div><span class="text-slate-500">DB User:</span> <span class="text-slate-200"><?= htmlspecialchars(DB_USER) ?></span></div>
                    </div>

                    <div class="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-xl text-xs text-rose-300 space-y-1">
                        <div class="font-bold uppercase tracking-wider text-[10px] text-rose-400">Pesan Error MySQL:</div>
                        <div class="font-mono break-all"><?= htmlspecialchars($e->getMessage()) ?></div>
                    </div>

                    <div class="text-xs text-slate-400 leading-relaxed pt-2 border-t border-slate-800">
                        <strong>Solusi Error 1045 (Access Denied):</strong>
                        <ol class="list-decimal list-inside space-y-1 mt-1 text-slate-300">
                            <li>Buka <strong>Hostinger File Manager</strong> &rarr; folder <code>config/database.local.php</code>.</li>
                            <li>Pastikan <code>DB_USER</code> dan <code>DB_PASS</code> cocok persis dengan yang Anda buat di hPanel Hostinger.</li>
                            <li>Jika lupa password DB Hostinger, reset password user database di menu <strong>Databases &rarr; Management</strong> hPanel Hostinger.</li>
                        </ol>
                    </div>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
    return $pdo;
}
