<?php
// views/keuangan/laporan.php
check_role(['keuangan', 'admin']);

$rekapBulan = $pdo->query("SELECT bulan, tahun, SUM(nominal) as total, COUNT(*) as transaksi FROM spp_transaksi WHERE status = 'Lunas' GROUP BY bulan, tahun")->fetchAll();
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Laporan Rekapitulasi Keuangan</h1>
        <p class="text-xs text-slate-400">Ringkasan statistik penerimaan iuran per periode bulan</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <h3 class="text-base font-bold text-white mb-4">Rekap Penerimaan Per Bulan</h3>
            <div class="space-y-3">
                <?php foreach ($rekapBulan as $r): ?>
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-white block"><?= htmlspecialchars($r['bulan']); ?> <?= htmlspecialchars($r['tahun']); ?></span>
                            <span class="text-[10px] text-slate-400"><?= $r['transaksi']; ?> Siswa Lunas</span>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-extrabold text-emerald-400 font-mono">Rp <?= number_format($r['total'], 0, ',', '.'); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
            <div>
                <h3 class="text-base font-bold text-white mb-2">Cetak Laporan Keuangan</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-4">Gunakan tombol di bawah ini untuk mencetak rekapitulasi laporan kas atau mengunduh rangkuman keuangan dalam format cetak resmi sekolah.</p>
            </div>
            <button type="button" onclick="window.print()" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/30 flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-pdf"></i> Cetak / Export Laporan Keuangan
            </button>
        </div>
    </div>
</div>
