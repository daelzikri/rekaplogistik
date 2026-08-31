<?php
/**
 * Restock Barang Redirect
 * Fitur Restock disatukan ke bagian Edit di Master Barang
 */

require_once __DIR__ . '/../middleware/auth.php';
require_auth();

set_flash_message('info', 'Fitur Restock dan Penyesuaian Stok telah disatukan ke halaman Master Barang. Silakan klik tombol "Edit / Restock" pada barang yang bersangkutan.');
header('Location: ' . base_url('public/admin/master_barang.php'));
exit;
