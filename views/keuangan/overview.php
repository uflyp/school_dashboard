<?php
// views/keuangan/overview.php
check_role(['keuangan', 'admin']);

$totalMasuk = $pdo->query("SELECT SUM(nominal) FROM spp_transaksi WHERE status = 'Lunas'")->fetchColumn() ?: 0;
$totalTunggakan = $pdo->query("SELECT SUM(nominal) FROM spp_transaksi WHERE status = 'Belum Lunas'")->fetchColumn() ?: 0;
$transaksiBulan = $pdo->query("SELECT COUNT(*) FROM spp_transaksi WHERE status = 'Lunas'")->fetchColumn() ?: 0;

$recentSpp = $pdo->query("SELECT * FROM spp_transaksi ORDER BY id DESC LIMIT 5")->fetchAll();
?>
<div class="space-y-8">

    <!-- Welcome Card -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-950 via-teal-900 to-slate-900 p-6 sm:p-8 border border-emerald-700/50 shadow-2xl">
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="min-w-0 flex-1 space-y-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                    <i class="fa-solid fa-calculator"></i> Dashboard Keuangan Sekolah
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight break-words">Selamat Datang, <?= htmlspecialchars($user['name']); ?></h1>
                <p class="text-xs sm:text-sm text-emerald-200 leading-relaxed max-w-2xl">Kelola arus penerimaan SPP, verifikasi pembayaran siswa, cetak kuitansi resmi, dan pantau rekapitulasi keuangan sekolah.</p>
            </div>
            <div class="shrink-0">
                <a href="dashboard.php?page=spp" class="px-5 py-3 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs flex items-center gap-2 shadow-lg shadow-emerald-500/30 transition-all">
                    <i class="fa-solid fa-receipt text-sm"></i> Input Pembayaran SPP
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="flex items-center justify-between text-slate-400 text-xs font-semibold mb-2">
                <span>TOTAL SPP TERBAYAR (LUNAS)</span>
                <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-emerald-400">Rp <?= number_format($totalMasuk, 0, ',', '.'); ?></div>
            <span class="text-[10px] text-slate-500 mt-1 block">Terkumpul di kas sekolah</span>
        </div>

        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="flex items-center justify-between text-slate-400 text-xs font-semibold mb-2">
                <span>ESTIMASI TUNGGAKAN</span>
                <i class="fa-solid fa-clock-rotate-left text-amber-400 text-base"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-amber-400">Rp <?= number_format($totalTunggakan, 0, ',', '.'); ?></div>
            <span class="text-[10px] text-slate-500 mt-1 block">Belum dibayar oleh siswa</span>
        </div>

        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800">
            <div class="flex items-center justify-between text-slate-400 text-xs font-semibold mb-2">
                <span>TOTAL TRANSAKSI DIVERIFIKASI</span>
                <i class="fa-solid fa-file-invoice text-indigo-400 text-base"></i>
            </div>
            <div class="text-2xl sm:text-3xl font-extrabold text-white"><?= number_format($transaksiBulan); ?> Transaksi</div>
            <span class="text-[10px] text-slate-500 mt-1 block">Status Lunas</span>
        </div>
    </div>

    <!-- Table SPP Terbaru -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-white">Riwayat Transaksi Pembayaran SPP</h3>
                <p class="text-xs text-slate-400">Catatan mutasi penerimaan pembayaran terbaru</p>
            </div>
            <a href="dashboard.php?page=spp" class="text-xs text-emerald-400 hover:underline font-bold">Kelola SPP →</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">ID Tx</th>
                        <th class="p-3.5">NIS & Nama Siswa</th>
                        <th class="p-3.5">Bulan & Tahun</th>
                        <th class="p-3.5">Nominal</th>
                        <th class="p-3.5">Metode Bayar</th>
                        <th class="p-3.5 rounded-r-xl">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($recentSpp as $sp): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-mono text-slate-500">#TX-<?= $sp['id']; ?></td>
                            <td class="p-3.5">
                                <div class="font-bold text-white"><?= htmlspecialchars($sp['nama_siswa']); ?></div>
                                <div class="text-[10px] text-indigo-400 font-mono">NIS: <?= htmlspecialchars($sp['nis']); ?></div>
                            </td>
                            <td class="p-3.5 font-semibold text-slate-300"><?= htmlspecialchars($sp['bulan']); ?> <?= htmlspecialchars($sp['tahun']); ?></td>
                            <td class="p-3.5 font-bold text-white">Rp <?= number_format($sp['nominal'], 0, ',', '.'); ?></td>
                            <td class="p-3.5 text-slate-400"><?= htmlspecialchars($sp['metode_pembayaran'] ?: '-'); ?></td>
                            <td class="p-3.5">
                                <?php if ($sp['status'] === 'Lunas'): ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">LUNAS</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">BELUM LUNAS</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
