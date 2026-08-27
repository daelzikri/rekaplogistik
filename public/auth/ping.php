<?php
/**
 * Heartbeat Ping Endpoint (AJAX)
 * Menjaga sesi tetap hidup saat pengguna membuka halaman
 */

require_once __DIR__ . '/../middleware/auth.php';

$user = require_auth();

json_response('success', 'Heartbeat OK', [
    'user_id'          => $user['id'],
    'nama'             => $user['nama'],
    'role'             => $user['role'],
    'last_activity_at' => date('Y-m-d H:i:s')
]);
