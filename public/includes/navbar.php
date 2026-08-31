<?php
/**
 * Navigation Bar Component dengan Dynamic Active State & Mobile Optimization
 * @param string $activeKey Identifier halaman aktif (katalog|lapor|pengembalian|riwayat_saya|dashboard|master_barang|riwayat_transaksi|kelola_akun)
 * @param array $user Data user authenticated saat ini
 */
function render_navbar(string $activeKey, array $user): void {
    $navItems = [
        [
            'key'   => 'katalog',
            'label' => 'Katalog Barang',
            'url'   => base_url('public/katalog.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>'
        ],
        [
            'key'   => 'lapor',
            'label' => 'Serah Terima',
            'url'   => base_url('public/serah_terima/lapor.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>'
        ],
        [
            'key'   => 'pengembalian',
            'label' => 'Pengembalian',
            'url'   => base_url('public/serah_terima/pengembalian.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>'
        ],
        [
            'key'   => 'riwayat_saya',
            'label' => 'Riwayat Saya',
            'url'   => base_url('public/serah_terima/riwayat_saya.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>'
        ],
        [
            'key'   => 'dashboard',
            'label' => 'Dashboard',
            'url'   => base_url('public/admin/dashboard.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'
        ],
        [
            'key'   => 'master_barang',
            'label' => 'Master Barang',
            'url'   => base_url('public/admin/master_barang.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>'
        ],
        [
            'key'   => 'riwayat_transaksi',
            'label' => 'Semua Riwayat',
            'url'   => base_url('public/admin/riwayat_transaksi.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>'
        ],
        [
            'key'   => 'kelola_akun',
            'label' => 'Kelola Akun',
            'url'   => base_url('public/admin/kelola_akun.php'),
            'icon'  => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'
        ]
    ];
    ?>
    <!-- Top Header Navigation Bar -->
    <header class="bg-slate-900/90 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Brand Logo & Badge -->
                <a href="<?= base_url('public/katalog.php') ?>" class="flex items-center space-x-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white font-bold shadow-lg shadow-indigo-500/25 group-hover:scale-105 transition">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-base font-extrabold text-white tracking-tight group-hover:text-indigo-300 transition">Rekap Logistik</span>
                        <span class="hidden sm:inline-block ml-2 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md">Admin Portal</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center space-x-1 text-xs font-medium">
                    <?php foreach ($navItems as $item):
                        $isActive = ($activeKey === $item['key']);
                        $class = $isActive
                            ? 'px-3 py-2 rounded-xl text-white bg-indigo-600/30 text-indigo-300 border border-indigo-500/50 shadow-md font-bold flex items-center space-x-1.5'
                            : 'px-3 py-2 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800/80 transition duration-150 flex items-center space-x-1.5';
                    ?>
                        <a href="<?= $item['url'] ?>" class="<?= $class ?>">
                            <?= $item['icon'] ?>
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- User Profile & Logout -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-white"><?= e($user['nama']) ?></div>
                        <div class="text-[10px] text-indigo-400 font-medium">Admin Logistik</div>
                    </div>
                    <a href="<?= base_url('public/auth/logout.php') ?>" class="p-2.5 rounded-xl bg-slate-800/80 hover:bg-rose-500/20 hover:text-rose-400 text-slate-400 border border-slate-700/60 transition" title="Keluar dari sistem">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Mobile Scrollable Active Navigation Bar -->
        <div class="lg:hidden border-t border-slate-800/80 bg-slate-950/90 px-3 py-2 overflow-x-auto no-scrollbar flex items-center space-x-2 text-xs">
            <?php foreach ($navItems as $item):
                $isActive = ($activeKey === $item['key']);
                $mClass = $isActive
                    ? 'px-3 py-1.5 rounded-xl bg-indigo-600 text-white font-bold border border-indigo-400 shrink-0 flex items-center space-x-1 shadow-lg shadow-indigo-600/30'
                    : 'px-3 py-1.5 rounded-xl bg-slate-900 text-slate-300 border border-slate-800 shrink-0 flex items-center space-x-1 hover:text-white';
            ?>
                <a href="<?= $item['url'] ?>" class="<?= $mClass ?>">
                    <?= $item['icon'] ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </header>
    <?php
}
