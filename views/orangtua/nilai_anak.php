<?php
// views/orangtua/nilai_anak.php
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

$stmtNilai = $pdo->prepare("SELECT * FROM nilai WHERE nis = ? ORDER BY id ASC");
$stmtNilai->execute([$nisAnak]);
$nilaiAnak = $stmtNilai->fetchAll();

if (empty($nilaiAnak)) {
    $nilaiAnak = $pdo->query("SELECT * FROM nilai ORDER BY id ASC LIMIT 6")->fetchAll();
}
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Rapor Perkembangan Akademik Anak</h1>
            <p class="text-xs text-slate-400">Transkrip pencapaian nilai mata pelajaran dan evaluasi wali kelas</p>
        </div>
        <button type="button" onclick="window.print()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold rounded-xl border border-slate-700 flex items-center gap-1.5">
            <i class="fa-solid fa-print"></i> Cetak Rapor Anak
        </button>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Mata Pelajaran</th>
                        <th class="p-3.5 text-center">Tugas</th>
                        <th class="p-3.5 text-center">UTS</th>
                        <th class="p-3.5 text-center">UAS</th>
                        <th class="p-3.5 text-center">Predikat</th>
                        <th class="p-3.5 rounded-r-xl">Catatan Perkembangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($nilaiAnak as $n): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($n['mata_pelajaran']); ?></td>
                            <td class="p-3.5 text-center font-mono"><?= $n['nilai_tugas']; ?></td>
                            <td class="p-3.5 text-center font-mono"><?= $n['nilai_uts']; ?></td>
                            <td class="p-3.5 text-center font-mono"><?= $n['nilai_uas']; ?></td>
                            <td class="p-3.5 text-center">
                                <span class="px-2 py-0.5 rounded text-xs font-extrabold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                    <?= htmlspecialchars($n['predikat']); ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-slate-400 italic"><?= htmlspecialchars($n['catatan']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Homeroom teacher summary box -->
        <div class="p-5 rounded-2xl bg-slate-950 border border-slate-800/80">
            <h4 class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-2">
                <i class="fa-solid fa-comment-dots mr-1"></i> Catatan Wali Kelas (XII IPA 1):
            </h4>
            <p class="text-xs text-slate-300 leading-relaxed italic">
                "Ananda Bintang Pratama menunjukkan dedikasi belajar yang sangat konsisten, aktif dalam diskusi kelas, dan memiliki sikap empati serta kepemimpinan yang terpuji. Pertahankan prestasi!"
            </p>
        </div>
    </div>
</div>
