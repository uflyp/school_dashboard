<?php
// views/kepala_sekolah/overview.php
check_role(['kepala_sekolah', 'admin']);

$statSiswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$statGuru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
$statSppTotal = $pdo->query("SELECT SUM(nominal) FROM spp_transaksi WHERE status = 'Lunas'")->fetchColumn() ?: 0;
$statHadir = 98.4; // Persentase rata-rata

$recentPengumuman = $pdo->query("SELECT * FROM pengumuman ORDER BY id DESC LIMIT 3")->fetchAll();
$events = $pdo->query("SELECT * FROM events ORDER BY event_date ASC LIMIT 3")->fetchAll();
?>
<div class="space-y-8">

    <!-- Executive Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-purple-950 via-indigo-950 to-slate-900 p-6 sm:p-8 border border-purple-700/50 shadow-2xl">
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="min-w-0 flex-1 space-y-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-purple-500/20 text-purple-300 border border-purple-400/30">
                    <i class="fa-solid fa-chart-pie"></i> Dashboard Eksekutif Kepala Sekolah
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight break-words">Selamat Datang, Bapak <?= htmlspecialchars($user['name']); ?></h1>
                <p class="text-xs sm:text-sm text-purple-200 leading-relaxed max-w-2xl">Ringkasan statistik perkembangan sekolah, grafik kehadiran, kinerja keuangan, dan laporan eksekutif siap cetak.</p>
            </div>
            <div class="shrink-0">
                <button type="button" onclick="window.print()" class="px-5 py-3 rounded-2xl bg-purple-500 hover:bg-purple-400 text-slate-950 font-extrabold text-xs flex items-center gap-2 shadow-lg shadow-purple-500/30 transition-all">
                    <i class="fa-solid fa-print"></i> Cetak Laporan Eksekutif
                </button>
            </div>
        </div>
    </div>

    <!-- Stat Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="flex items-center justify-between text-slate-400 text-xs font-semibold mb-2">
                <span>TOTAL SISWA AKTIF</span>
                <i class="fa-solid fa-user-graduate text-cyan-400 text-base"></i>
            </div>
            <div class="text-3xl font-extrabold text-white"><?= number_format($statSiswa); ?> Siswa</div>
            <span class="text-[10px] text-slate-500 mt-1 block">T.A 2025/2026</span>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="flex items-center justify-between text-slate-400 text-xs font-semibold mb-2">
                <span>TENAGA PENDIDIK</span>
                <i class="fa-solid fa-chalkboard-user text-indigo-400 text-base"></i>
            </div>
            <div class="text-3xl font-extrabold text-white"><?= number_format($statGuru); ?> Guru</div>
            <span class="text-[10px] text-slate-500 mt-1 block">Guru Aktif Mengajar</span>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="flex items-center justify-between text-slate-400 text-xs font-semibold mb-2">
                <span>RATA-RATA KEHADIRAN</span>
                <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
            </div>
            <div class="text-3xl font-extrabold text-emerald-400"><?= $statHadir; ?>%</div>
            <span class="text-[10px] text-slate-500 mt-1 block">Sangat Baik</span>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="flex items-center justify-between text-slate-400 text-xs font-semibold mb-2">
                <span>PENERIMAAN KAS SPP</span>
                <i class="fa-solid fa-vault text-amber-400 text-base"></i>
            </div>
            <div class="text-xl font-extrabold text-amber-400">Rp <?= number_format($statSppTotal, 0, ',', '.'); ?></div>
            <span class="text-[10px] text-slate-500 mt-1 block">Total Terverifikasi</span>
        </div>
    </div>

    <!-- Charts Section (Chart.js) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Attendance Chart -->
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <h3 class="text-base font-bold text-white mb-1">Grafik Tren Kehadiran Siswa</h3>
            <p class="text-xs text-slate-400 mb-4">Persentase kehadiran bulanan semester berjalan</p>
            <div class="h-64">
                <canvas id="chart-kehadiran"></canvas>
            </div>
        </div>

        <!-- Financial Summary Chart -->
        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <h3 class="text-base font-bold text-white mb-1">Ringkasan Arus Kas SPP</h3>
            <p class="text-xs text-slate-400 mb-4">Perbandingan status lunas vs tunggakan</p>
            <div class="h-64 flex items-center justify-center">
                <canvas id="chart-keuangan"></canvas>
            </div>
        </div>

    </div>

    <!-- Recent Announcements & Agenda Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
            <h3 class="text-base font-bold text-white mb-4">Pengumuman Terbaru</h3>
            <div class="space-y-3">
                <?php foreach ($recentPengumuman as $p): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800">
                        <div class="flex items-center justify-between mb-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/10 text-purple-400 border border-purple-500/20"><?= htmlspecialchars($p['kategori']); ?></span>
                            <span class="text-[10px] text-slate-500"><?= date('d M Y', strtotime($p['tanggal'])); ?></span>
                        </div>
                        <h4 class="text-xs font-bold text-white mb-1"><?= htmlspecialchars($p['judul']); ?></h4>
                        <p class="text-[11px] text-slate-400 line-clamp-2"><?= htmlspecialchars($p['isi']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6">
            <h3 class="text-base font-bold text-white mb-4">Agenda Kegiatan Sekolah</h3>
            <div class="space-y-3">
                <?php foreach ($events as $ev): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-600 text-white font-bold flex flex-col items-center justify-center shrink-0">
                            <span class="text-xs font-extrabold leading-none"><?= date('d', strtotime($ev['event_date'])); ?></span>
                            <span class="text-[8px] uppercase"><?= date('M', strtotime($ev['event_date'])); ?></span>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-white mb-0.5"><?= htmlspecialchars($ev['title']); ?></h4>
                            <p class="text-[10px] text-slate-400"><?= htmlspecialchars($ev['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div>

<!-- Render Charts with Chart.js -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Attendance Line Chart
        const ctxKehadiran = document.getElementById('chart-kehadiran').getContext('2d');
        new Chart(ctxKehadiran, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul'],
                datasets: [{
                    label: 'Kehadiran (%)',
                    data: [96.5, 97.2, 98.0, 97.8, 98.4, 99.1, 98.8],
                    borderColor: '#a855f7',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { min: 90, max: 100, grid: { color: '#1e293b' }, ticks: { color: '#94a3b8' } },
                    x: { grid: { color: '#1e293b' }, ticks: { color: '#94a3b8' } }
                }
            }
        });

        // Financial Doughnut Chart
        const ctxKeuangan = document.getElementById('chart-keuangan').getContext('2d');
        new Chart(ctxKeuangan, {
            type: 'doughnut',
            data: {
                labels: ['Lunas', 'Tunggakan'],
                datasets: [{
                    data: [<?= $statSppTotal; ?>, 1500000],
                    backgroundColor: ['#10b981', '#f59e0b'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { color: '#94a3b8', font: { size: 11 } } }
                }
            }
        });
    });
</script>
