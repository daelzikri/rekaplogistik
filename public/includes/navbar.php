<?php
/**
 * Navigation Bar Component dengan Clean Text Tabs & Mobile Optimization
 * @param string $activeKey Identifier halaman aktif
 * @param array $user Data user authenticated saat ini
 */
function render_navbar(string $activeKey, array $user): void {
    // Alias active keys for merged/sub features
    if ($activeKey === 'pengembalian') {
        $activeKey = 'lapor';
    } elseif ($activeKey === 'riwayat_saya') {
        $activeKey = 'riwayat_transaksi';
    } elseif ($activeKey === 'katalog') {
        $activeKey = 'master_barang';
    }

    $navItems = [
        [
            'key'        => 'dashboard',
            'label'      => 'Dashboard',
            'shortLabel' => 'Dashboard',
            'url'        => base_url('public/admin/dashboard.php'),
        ],
        [
            'key'        => 'master_barang',
            'label'      => 'Master Barang',
            'shortLabel' => 'Master',
            'url'        => base_url('public/admin/master_barang.php'),
        ],
        [
            'key'        => 'lapor',
            'label'      => 'Input Transaksi',
            'shortLabel' => 'Input',
            'url'        => base_url('public/serah_terima/lapor.php'),
        ],
        [
            'key'        => 'riwayat_transaksi',
            'label'      => 'Riwayat Transaksi',
            'shortLabel' => 'Riwayat',
            'url'        => base_url('public/admin/riwayat_transaksi.php'),
        ],
        [
            'key'        => 'kelola_akun',
            'label'      => 'Kelola Akun',
            'shortLabel' => 'Akun',
            'url'        => base_url('public/admin/kelola_akun.php'),
        ]
    ];
    ?>
    <!-- Top Header Navigation Bar -->
    <header class="bg-slate-900/95 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">

                <!-- Brand Logo & Badge -->
                <a href="<?= base_url('public/admin/dashboard.php') ?>" class="flex items-center space-x-2.5 group shrink-0">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white font-bold shadow-md shadow-indigo-500/25 group-hover:scale-105 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div>
                        <span class="text-sm sm:text-base font-extrabold text-white tracking-tight group-hover:text-indigo-300 transition">Rekap Logistik</span>
                        <span class="hidden sm:inline-block ml-1.5 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 rounded-md">Admin</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links (Clean Text Tabs, No Cluttered Icons) -->
                <nav class="hidden lg:flex items-center space-x-1.5 text-xs font-medium">
                    <?php foreach ($navItems as $item):
                        $isActive = ($activeKey === $item['key']);
                        $class = $isActive
                            ? 'px-3.5 py-2 rounded-xl text-white bg-indigo-600/30 text-indigo-300 border border-indigo-500/50 shadow-sm font-bold transition'
                            : 'px-3.5 py-2 rounded-xl text-slate-300 hover:text-white hover:bg-slate-800/80 transition duration-150';
                    ?>
                        <a href="<?= $item['url'] ?>" class="<?= $class ?>">
                            <span><?= e($item['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <!-- User Profile & Logout -->
                <div class="flex items-center space-x-3 shrink-0">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-white"><?= e($user['nama']) ?></div>
                        <div class="text-[10px] text-indigo-400 font-medium">Admin Logistik</div>
                    </div>
                    <a href="<?= base_url('public/auth/logout.php') ?>" class="p-2 rounded-xl bg-slate-800/80 hover:bg-rose-500/20 hover:text-rose-400 text-slate-400 border border-slate-700/60 transition" title="Keluar dari sistem">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- Mobile Clean Compact Navigation Bar (Fits Mobile Screen Smoothly) -->
        <div class="lg:hidden border-t border-slate-800/80 bg-slate-950/95 px-2 py-2 flex items-center justify-between text-xs gap-1">
            <?php foreach ($navItems as $item):
                $isActive = ($activeKey === $item['key']);
                $mClass = $isActive
                    ? 'flex-1 text-center py-2 px-1 rounded-xl bg-indigo-600/30 text-indigo-300 font-bold border border-indigo-500/50 shadow-sm transition'
                    : 'flex-1 text-center py-2 px-1 rounded-xl bg-slate-900/80 text-slate-400 border border-slate-800/80 hover:text-white transition';
            ?>
                <a href="<?= $item['url'] ?>" class="<?= $mClass ?>">
                    <span class="block sm:hidden text-[11px]"><?= e($item['shortLabel']) ?></span>
                    <span class="hidden sm:block text-[11px]"><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </header>
    <?php
}
