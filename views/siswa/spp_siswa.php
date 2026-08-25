<?php
// views/siswa/spp_siswa.php
check_role(['siswa', 'admin']);

// Fetch logged-in student info
$username = $_SESSION['username'] ?? '';
$userName = $user['name'] ?? '';

$stmtS = $pdo->prepare("SELECT * FROM siswa WHERE nis = ? OR nama = ? LIMIT 1");
$stmtS->execute([$username, $userName]);
$siswaDetail = $stmtS->fetch();

if (!$siswaDetail) {
    $siswaDetail = $pdo->query("SELECT * FROM siswa ORDER BY id ASC LIMIT 1")->fetch();
}

$nis = $siswaDetail['nis'] ?? '2026001';

$stmtSpp = $pdo->prepare("SELECT * FROM spp_transaksi WHERE nis = ? ORDER BY id DESC");
$stmtSpp->execute([$nis]);
$sppHistory = $stmtSpp->fetchAll();

// If empty for this specific NIS, fetch recent SPP transactions as preview
if (empty($sppHistory)) {
    $sppHistory = $pdo->query("SELECT * FROM spp_transaksi ORDER BY id DESC LIMIT 5")->fetchAll();
}
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Status & Riwayat SPP Siswa</h1>
        <p class="text-xs text-slate-400">Informasi kewajiban iuran bulanan dan riwayat verifikasi kasir</p>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Bulan & Tahun</th>
                        <th class="p-3.5">Nominal Tagihan</th>
                        <th class="p-3.5">Tanggal Bayar</th>
                        <th class="p-3.5">Metode Bayar</th>
                        <th class="p-3.5 rounded-r-xl">Status Verifikasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($sppHistory as $sp): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($sp['bulan']); ?> <?= htmlspecialchars($sp['tahun']); ?></td>
                            <td class="p-3.5 font-bold text-slate-200">Rp <?= number_format($sp['nominal'], 0, ',', '.'); ?></td>
                            <td class="p-3.5 text-slate-400"><?= $sp['tanggal_bayar'] ? date('d F Y', strtotime($sp['tanggal_bayar'])) : '-'; ?></td>
                            <td class="p-3.5 text-slate-400"><?= htmlspecialchars($sp['metode_pembayaran'] ?: '-'); ?></td>
                            <td class="p-3.5">
                                <?php if ($sp['status'] === 'Lunas'): ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">LUNAS</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">BELUM DIBAYAR</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
