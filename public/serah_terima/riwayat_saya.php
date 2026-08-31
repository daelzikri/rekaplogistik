<?php
/**
 * Redirect Riwayat Saya ke Riwayat Transaksi Terpusat
 */

require_once __DIR__ . '/../middleware/auth.php';
$user = require_auth();

$queryParams = $_GET;
$queryParams['penyerah_id'] = $user['id'];
$queryString = http_build_query($queryParams);

header('Location: ' . base_url('public/admin/riwayat_transaksi.php?' . $queryString));
exit;
