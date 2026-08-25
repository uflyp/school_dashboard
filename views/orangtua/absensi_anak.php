<?php
// views/orangtua/absensi_anak.php
check_role(['orangtua', 'admin']);

// Fetch logged-in Parent data
$userName = $user['name'] ?? '';

$stmtOrtu = $pdo->prepare("SELECT * FROM orangtua WHERE nama = ? LIMIT 1");
$stmtOrtu->execute([$userName]);
$ortuData = $stmtOrtu->fetch();

if (!$ortuData) {
    $ortuData = $pdo->query("SELECT * FROM orangtua ORDER BY id ASC LIMIT 1")->fetch();
}

$nisAnak = $ortuData['nis_anak'] ?? '';
$namaAnak = $ortuData['nama_anak'] ?? 'Anak Siswa';

$stmtAbs = $pdo->prepare("SELECT * FROM absensi WHERE nis = ? ORDER BY tanggal DESC");
$stmtAbs->execute([$nisAnak]);
$absensi = $stmtAbs->fetchAll();

if (empty($absensi)) {
    $absensi = $pdo->query("SELECT * FROM absensi ORDER BY tanggal DESC LIMIT 5")->fetchAll();
}
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Monitoring Absensi Kehadiran Anak</h1>
        <p class="text-xs text-slate-400">Rekapitulasi persentase presensi dan ketepatan waktu masuk sekolah</p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Tanggal</th>
                        <th class="p-3.5">Status Kehadiran</th>
                        <th class="p-3.5 rounded-r-xl">Catatan Petugas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($absensi as $ab): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-bold text-white font-mono"><?= date('d F Y', strtotime($ab['tanggal'])); ?></td>
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
