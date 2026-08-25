<?php
// views/kepala_sekolah/kehadiran.php
check_role(['kepala_sekolah', 'admin']);

$absensiLogs = $pdo->query("SELECT a.*, s.nama, s.kelas FROM absensi a JOIN siswa s ON a.nis = s.nis ORDER BY a.id DESC LIMIT 15")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-clipboard-user text-emerald-400"></i> Laporan & Grafik Kehadiran Siswa
            </h1>
            <p class="text-xs text-slate-400 mt-1">Monitoring prosentase kehadiran dan ketidakhadiran per kelas</p>
        </div>
        <button type="button" onclick="window.print()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-print"></i> Cetak Laporan Kehadiran
        </button>
    </div>

    <!-- Chart -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <h3 class="text-base font-bold text-white mb-4">Persentase Kehadiran Bulanan (98.4% Rata-rata)</h3>
        <div class="h-64">
            <canvas id="chart-laporan-kehadiran"></canvas>
        </div>
    </div>

    <!-- Log Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <h3 class="text-base font-bold text-white mb-4">Riwayat Presensi Terbaru</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Tanggal</th>
                        <th class="p-3.5">NIS & Nama Siswa</th>
                        <th class="p-3.5">Kelas</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 rounded-r-xl">Keterangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($absensiLogs as $ab): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-mono text-slate-400"><?= date('d/m/Y', strtotime($ab['tanggal'])); ?></td>
                            <td class="p-3.5">
                                <div class="font-bold text-white"><?= htmlspecialchars($ab['nama']); ?></div>
                                <div class="text-[10px] text-indigo-400 font-mono">NIS: <?= htmlspecialchars($ab['nis']); ?></div>
                            </td>
                            <td class="p-3.5"><span class="px-2 py-0.5 rounded bg-slate-800 font-mono text-slate-300"><?= htmlspecialchars($ab['kelas']); ?></span></td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <?= htmlspecialchars($ab['status']); ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-slate-400"><?= htmlspecialchars($ab['keterangan']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctxKhd = document.getElementById('chart-laporan-kehadiran').getContext('2d');
        new Chart(ctxKhd, {
            type: 'line',
            data: {
                labels: ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4'],
                datasets: [{
                    label: 'Kehadiran (%)',
                    data: [98.2, 98.8, 97.9, 98.6],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    fill: true,
                    tension: 0.4
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
