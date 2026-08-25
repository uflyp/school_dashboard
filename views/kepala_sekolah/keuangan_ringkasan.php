<?php
// views/kepala_sekolah/keuangan_ringkasan.php
check_role(['kepala_sekolah', 'admin']);

$statSppLunas = $pdo->query("SELECT SUM(nominal) FROM spp_transaksi WHERE status = 'Lunas'")->fetchColumn() ?: 0;
$statSppBelum = $pdo->query("SELECT SUM(nominal) FROM spp_transaksi WHERE status = 'Belum Lunas'")->fetchColumn() ?: 0;
$sppTrans = $pdo->query("SELECT * FROM spp_transaksi ORDER BY id DESC LIMIT 10")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-vault text-amber-400"></i> Ringkasan Kas & Penerimaan SPP
            </h1>
            <p class="text-xs text-slate-400 mt-1">Laporan pengawasan keuangan kas iuran sekolah dan perbandingan tunggakan</p>
        </div>
        <button type="button" onclick="window.print()" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-print"></i> Cetak Ringkasan Kas
        </button>
    </div>

    <!-- Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400 font-semibold mb-1">TOTAL TERIMA (LUNAS)</div>
            <div class="text-3xl font-extrabold text-emerald-400">Rp <?= number_format($statSppLunas, 0, ',', '.'); ?></div>
            <p class="text-[10px] text-slate-500 mt-1">Total iuran SPP siswa terverifikasi lunas</p>
        </div>
        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="text-xs text-slate-400 font-semibold mb-1">PIUTANG (BELUM LUNAS)</div>
            <div class="text-3xl font-extrabold text-amber-400">Rp <?= number_format($statSppBelum, 0, ',', '.'); ?></div>
            <p class="text-[10px] text-slate-500 mt-1">Total iuran belum dibayarkan</p>
        </div>
    </div>

    <!-- Transaksi Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <h3 class="text-base font-bold text-white mb-4">Transaksi SPP Terbaru</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">ID</th>
                        <th class="p-3.5">NIS & Nama Siswa</th>
                        <th class="p-3.5">Periode</th>
                        <th class="p-3.5">Nominal</th>
                        <th class="p-3.5 rounded-r-xl">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($sppTrans as $sp): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-mono text-slate-500">#<?= $sp['id']; ?></td>
                            <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($sp['nama_siswa']); ?> (<?= htmlspecialchars($sp['nis']); ?>)</td>
                            <td class="p-3.5 text-slate-300"><?= htmlspecialchars($sp['bulan']); ?> <?= htmlspecialchars($sp['tahun']); ?></td>
                            <td class="p-3.5 font-bold text-white">Rp <?= number_format($sp['nominal'], 0, ',', '.'); ?></td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold <?= $sp['status'] === 'Lunas' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' ?>">
                                    <?= htmlspecialchars($sp['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
