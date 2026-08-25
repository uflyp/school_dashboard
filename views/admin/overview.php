<?php
// views/admin/overview.php
check_role(['admin']);

$statSiswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$statGuru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
$statPeng = $pdo->query("SELECT COUNT(*) FROM pengumuman")->fetchColumn();
$statSppLunas = $pdo->query("SELECT SUM(nominal) FROM spp_transaksi WHERE status = 'Lunas'")->fetchColumn() ?: 0;

$recentSiswa = $pdo->query("SELECT * FROM siswa ORDER BY id DESC LIMIT 5")->fetchAll();
$recentPengumuman = $pdo->query("SELECT * FROM pengumuman ORDER BY id DESC LIMIT 3")->fetchAll();
$allUsersList = $pdo->query("SELECT u.*, r.display_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.last_activity DESC, u.last_login DESC")->fetchAll();
$onlineCount = 0;
foreach ($allUsersList as $uCheck) {
    $tCheck = !empty($uCheck['last_activity']) ? strtotime($uCheck['last_activity']) : (!empty($uCheck['last_login']) ? strtotime($uCheck['last_login']) : 0);
    if ($tCheck > 0 && (time() - $tCheck <= 300)) {
        $onlineCount++;
    }
}
?>
<div class="space-y-8">

    <!-- Welcome Banner Card & Notifications & Quick Action -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-950 via-indigo-900 to-slate-900 p-6 sm:p-8 border border-indigo-700/50 shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-indigo-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
            <!-- Left Text Content -->
            <div class="space-y-3 flex-1 min-w-0">
                <span class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-extrabold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                    <i class="fa-solid fa-user-gear text-indigo-400"></i> Dashboard Administrator
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
                    Selamat Datang, <?= htmlspecialchars($user['name']); ?> 👋
                </h1>
                <p class="text-xs sm:text-sm text-indigo-200 leading-relaxed max-w-xl">
                    Kelola data akademik sekolah, siswa, pengajar, serta modul PPDB 2026 dalam satu kontrol panel terpadu.
                </p>
            </div>
            
            <!-- Right Action Buttons (Fixed Width Column Layout) -->
            <div class="flex flex-col sm:flex-row lg:flex-col gap-2.5 shrink-0 w-full sm:w-auto lg:w-48">
                <a href="dashboard.php?page=siswa" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center justify-center lg:justify-start gap-2 shadow-lg shadow-indigo-600/30 transition-all">
                    <i class="fa-solid fa-user-plus text-xs w-4 text-center"></i>
                    <span>+ Siswa Baru</span>
                </a>
                <a href="dashboard.php?page=ppdb" class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold flex items-center justify-center lg:justify-start gap-2 shadow-lg shadow-amber-600/30 transition-all">
                    <i class="fa-solid fa-id-card text-xs w-4 text-center"></i>
                    <span>Kelola PPDB</span>
                </a>
                <a href="dashboard.php?page=cms" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center justify-center lg:justify-start gap-2 shadow-lg shadow-emerald-600/30 transition-all">
                    <i class="fa-solid fa-sliders text-xs w-4 text-center"></i>
                    <span>CMS Web</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-white"><?= number_format($statSiswa); ?></div>
                <div class="text-xs text-slate-400 font-medium">Total Siswa Aktif</div>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-chalkboard-user"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-white"><?= number_format($statGuru); ?></div>
                <div class="text-xs text-slate-400 font-medium">Guru & Pengajar</div>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-bullhorn"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-white"><?= number_format($statPeng); ?></div>
                <div class="text-xs text-slate-400 font-medium">Pengumuman Terbit</div>
            </div>
        </div>

        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <div class="text-xl font-extrabold text-emerald-400">Rp <?= number_format($statSppLunas, 0, ',', '.'); ?></div>
                <div class="text-xs text-slate-400 font-medium">Total SPP Terkumpul</div>
            </div>
        </div>
    </div>

    <!-- Chart.js Graph & User Online Section -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Interactive Chart.js Graph -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-white">📊 Statistik Pendaftaran & SPP</h3>
                    <p class="text-xs text-slate-400">Arus statistik SPP dan siswa 6 bulan terakhir</p>
                </div>
            </div>
            <div class="h-64">
                <canvas id="adminStatChart"></canvas>
            </div>
        </div>

        <!-- 👥 User Online/Offline Status List -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> Status Aktivitas User
                </h3>
                <span class="text-xs font-mono text-emerald-400 font-bold"><?= $onlineCount; ?> Online</span>
            </div>

            <div class="space-y-3 max-h-[320px] overflow-y-auto pr-1">
                <?php foreach ($allUsersList as $au): ?>
                    <?php
                    $lTime = !empty($au['last_activity']) ? strtotime($au['last_activity']) : (!empty($au['last_login']) ? strtotime($au['last_login']) : 0);
                    $isUserOnline = ($lTime > 0) && ((time() - $lTime) <= 300);
                    
                    if ($lTime === 0) {
                        $lastActText = 'Belum aktif';
                    } elseif ($isUserOnline) {
                        $diffSec = time() - $lTime;
                        $lastActText = ($diffSec < 60) ? 'Aktif baru saja' : floor($diffSec / 60) . 'm lalu';
                    } else {
                        $lastActText = date('d M H:i', $lTime);
                    }
                    ?>
                    <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <img src="<?= htmlspecialchars($au['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150'); ?>" class="w-8 h-8 rounded-xl object-cover border border-slate-700 shrink-0">
                            <div class="overflow-hidden">
                                <h4 class="text-xs font-bold text-white truncate"><?= htmlspecialchars($au['name']); ?></h4>
                                <div class="flex items-center gap-2 text-[9px] text-slate-400">
                                    <span><?= htmlspecialchars($au['display_name']); ?></span>
                                    <span>•</span>
                                    <span><?= $lastActText; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php if ($isUserOnline): ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shrink-0 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online
                            </span>
                        <?php else: ?>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-800/80 text-slate-400 border border-slate-700/60 shrink-0 flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Data Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Table Data Siswa Terbaru -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-3xl p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-white">Siswa Terbaru</h3>
                    <p class="text-xs text-slate-400">Daftar 5 siswa terkini yang terdaftar di database</p>
                </div>
                <a href="dashboard.php?page=siswa" class="text-xs text-indigo-400 hover:underline font-semibold">Lihat Semua →</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                        <tr>
                            <th class="p-3 rounded-l-xl">NIS</th>
                            <th class="p-3">Nama Siswa</th>
                            <th class="p-3">Kelas</th>
                            <th class="p-3 rounded-r-xl">Wali</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($recentSiswa as $s): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-3 font-mono text-indigo-400 font-bold"><?= htmlspecialchars($s['nis']); ?></td>
                                <td class="p-3 font-semibold text-white"><?= htmlspecialchars($s['nama']); ?></td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded-md bg-slate-800 text-slate-300 font-mono"><?= htmlspecialchars($s['kelas']); ?></span></td>
                                <td class="p-3 text-slate-400"><?= htmlspecialchars($s['nama_ortu']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pengumuman Terkini -->
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-3xl p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-bold text-white">Pengumuman Sekolah</h3>
                    <p class="text-xs text-slate-400">Informasi dan berita penting sekolah</p>
                </div>
                <a href="dashboard.php?page=pengumuman" class="text-xs text-indigo-400 hover:underline font-semibold">Kelola →</a>
            </div>

            <div class="space-y-4">
                <?php foreach ($recentPengumuman as $p): ?>
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-400">
                                <?= htmlspecialchars($p['kategori']); ?>
                            </span>
                            <span class="text-[10px] text-slate-500"><?= date('d M Y', strtotime($p['tanggal'])); ?></span>
                        </div>
                        <h4 class="text-sm font-bold text-white mb-1"><?= htmlspecialchars($p['judul']); ?></h4>
                        <p class="text-xs text-slate-400 line-clamp-2"><?= htmlspecialchars($p['isi']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</div>

<!-- Chart Script -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctxAdmin = document.getElementById('adminStatChart').getContext('2d');
        new Chart(ctxAdmin, {
            type: 'bar',
            data: {
                labels: ['Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Penerimaan SPP (Juta Rp)',
                    data: [12, 14, 15, 13.5, 18, 16.5],
                    backgroundColor: '#6366f1',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8' } },
                    y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } }
                }
            }
        });
    });
</script>

