<?php
// views/kepala_sekolah/statistik.php
check_role(['kepala_sekolah', 'admin']);

$statSiswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$statLaki = $pdo->query("SELECT COUNT(*) FROM siswa WHERE jenis_kelamin = 'Laki-laki'")->fetchColumn();
$statPerempuan = $pdo->query("SELECT COUNT(*) FROM siswa WHERE jenis_kelamin = 'Perempuan'")->fetchColumn();
$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY nama_kelas ASC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-purple-400"></i> Demografi & Statistik Siswa
            </h1>
            <p class="text-xs text-slate-400 mt-1">Laporan distribusi siswa berdasarkan kelas, gender, dan status pertumbuhan</p>
        </div>
        <button type="button" onclick="window.print()" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-print"></i> Cetak Laporan Demografi
        </button>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-users"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-white"><?= number_format($statSiswa); ?> Siswa</div>
                <div class="text-xs text-slate-400">Total Terdaftar</div>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-mars"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-cyan-400"><?= number_format($statLaki); ?> Siswa</div>
                <div class="text-xs text-slate-400">Laki-laki</div>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-venus"></i>
            </div>
            <div>
                <div class="text-2xl font-extrabold text-rose-400"><?= number_format($statPerempuan); ?> Siswa</div>
                <div class="text-xs text-slate-400">Perempuan</div>
            </div>
        </div>
    </div>

    <!-- Chart & Table -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <h3 class="text-base font-bold text-white mb-4">Grafik Distribusi Per Kelas</h3>
            <div class="h-64">
                <canvas id="chart-kelas-dist"></canvas>
            </div>
        </div>

        <div class="lg:col-span-5 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <h3 class="text-base font-bold text-white mb-4">Rincian Rombel & Wali Kelas</h3>
            <div class="space-y-3">
                <?php foreach ($kelasList as $k): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                        <div>
                            <h4 class="text-xs font-bold text-white"><?= htmlspecialchars($k['nama_kelas']); ?></h4>
                            <p class="text-[10px] text-slate-400">Wali: <?= htmlspecialchars($k['wali_kelas']); ?></p>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <?= $k['jumlah_siswa']; ?> Siswa
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctxClass = document.getElementById('chart-kelas-dist').getContext('2d');
        new Chart(ctxClass, {
            type: 'bar',
            data: {
                labels: [<?= implode(',', array_map(fn($k) => "'" . $k['nama_kelas'] . "'", $kelasList)); ?>],
                datasets: [{
                    label: 'Jumlah Siswa',
                    data: [<?= implode(',', array_column($kelasList, 'jumlah_siswa')); ?>],
                    backgroundColor: '#818cf8',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: '#94a3b8' }, grid: { display: false } },
                    y: { ticks: { color: '#94a3b8' }, grid: { color: 'rgba(255,255,255,0.05)' } }
                }
            }
        });
    });
</script>
