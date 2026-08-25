<?php
// views/orangtua/overview.php
check_role(['orangtua', 'admin']);

// Fetch logged-in Parent data
$userName = $user['name'] ?? '';

$stmtOrtu = $pdo->prepare("SELECT * FROM orangtua WHERE nama = ? LIMIT 1");
$stmtOrtu->execute([$userName]);
$ortuData = $stmtOrtu->fetch();

if (!$ortuData) {
    // Fallback: fetch first parent record
    $ortuData = $pdo->query("SELECT * FROM orangtua ORDER BY id ASC LIMIT 1")->fetch();
}

$nisAnak = $ortuData['nis_anak'] ?? '';
$namaAnak = $ortuData['nama_anak'] ?? '';

// Fetch child student details
$stmtAnak = $pdo->prepare("SELECT * FROM siswa WHERE nis = ? OR nama = ? LIMIT 1");
$stmtAnak->execute([$nisAnak, $namaAnak]);
$anak = $stmtAnak->fetch();

if (!$anak) {
    $anak = $pdo->query("SELECT * FROM siswa ORDER BY id ASC LIMIT 1")->fetch() ?: [
        'nama' => $namaAnak ?: 'Siswa Anak',
        'nis' => $nisAnak ?: '2026001',
        'kelas' => 'XII IPA 1'
    ];
}
$nisAnak = $anak['nis'];

// Dynamic query for child attendance & bills
$stmtAbs = $pdo->prepare("SELECT * FROM absensi WHERE nis = ? ORDER BY tanggal DESC LIMIT 3");
$stmtAbs->execute([$nisAnak]);
$absensiTerakhir = $stmtAbs->fetchAll();

$stmtSpp = $pdo->prepare("SELECT * FROM spp_transaksi WHERE nis = ? ORDER BY id DESC LIMIT 2");
$stmtSpp->execute([$nisAnak]);
$sppTerakhir = $stmtSpp->fetchAll();

// Dynamic GPA of child
$stmtGpa = $pdo->prepare("SELECT AVG((nilai_tugas + nilai_uts + nilai_uas)/3.0) FROM nilai WHERE nis = ?");
$stmtGpa->execute([$nisAnak]);
$avgNilaiAnak = $stmtGpa->fetchColumn();
$gpaValAnak = $avgNilaiAnak ? (($avgNilaiAnak / 100) * 4.0) : 3.90;
$gpaInfoAnak = get_gpa_info($gpaValAnak);
?>
<div class="space-y-8">

    <!-- Welcome Banner Parent -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-amber-950 via-stone-900 to-slate-900 p-6 sm:p-8 border border-amber-700/50 shadow-2xl">
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="min-w-0 flex-1 space-y-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-400/30">
                    <i class="fa-solid fa-users-viewfinder"></i> Dashboard Portal Orang Tua / Wali
                </span>
                <div class="flex items-center gap-3">
                    <div class="relative group shrink-0">
                        <img src="<?= htmlspecialchars($user['avatar']); ?>" style="width: 48px; height: 48px; object-fit: cover;" class="w-12 h-12 rounded-xl object-cover border border-amber-500/50 shadow-md">
                        <button type="button" onclick="openModal('modal-change-avatar')" title="Ubah Foto Profil dari Perangkat" class="absolute inset-0 bg-slate-950/75 rounded-xl opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs transition-opacity">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                    </div>
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight break-words">Selamat Datang, Bapak <?= htmlspecialchars($user['name']); ?></h1>
                        <button type="button" onclick="openModal('modal-change-avatar')" class="mt-1 inline-flex items-center gap-1.5 text-[11px] font-bold text-amber-400 hover:text-amber-300 hover:underline">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Foto Profil (Lokal)
                        </button>
                    </div>
                </div>
                <p class="text-xs sm:text-sm text-amber-200 leading-relaxed max-w-2xl">Pantau perkembangan akademik, kehadiran presensi harian, dan kewajiban administrasi sekolah anak Anda secara real-time.</p>
            </div>
            
            <!-- Child Info Badge -->
            <div class="p-4 rounded-2xl bg-slate-950/80 border border-amber-500/30 flex items-center gap-3 shrink-0">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg shrink-0">
                    <i class="fa-solid fa-child"></i>
                </div>
                <div class="min-w-0">
                    <span class="text-[10px] text-amber-300 uppercase font-bold block">Monitoring Anak:</span>
                    <h4 class="text-sm font-bold text-white break-words"><?= htmlspecialchars($anak['nama']); ?></h4>
                    <span class="text-[10px] text-slate-400 font-mono">Kelas: <?= htmlspecialchars($anak['kelas']); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-lg font-bold"><i class="fa-solid fa-user-check"></i></div>
            <div>
                <div class="text-xl font-extrabold text-emerald-400">Hadir Hari Ini</div>
                <div class="text-xs text-slate-400">Absensi Tepat Waktu</div>
            </div>
        </div>
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl <?= $gpaInfoAnak['bg']; ?> <?= $gpaInfoAnak['color']; ?> flex items-center justify-center text-lg font-bold"><i class="fa-solid fa-graduation-cap"></i></div>
            <div>
                <div class="text-xl font-extrabold <?= $gpaInfoAnak['color']; ?>">GPA <?= $gpaInfoAnak['gpa']; ?></div>
                <div class="text-[11px] font-bold text-white leading-tight"><?= htmlspecialchars($gpaInfoAnak['predicate']); ?></div>
                <div class="text-[10px] text-slate-400 mt-0.5">Capaian GPA Rapor Anak</div>
            </div>
        </div>
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-lg font-bold"><i class="fa-solid fa-receipt"></i></div>
            <div>
                <?php
                $sppLatest = $sppTerakhir[0] ?? null;
                $sppStatusText = ($sppLatest && $sppLatest['status'] === 'Lunas') ? 'Iuran SPP Lunas' : ($sppLatest ? 'Ada Tagihan ' . $sppLatest['bulan'] : 'Tidak Ada Tagihan');
                $sppSubText = $sppLatest ? ('Periode ' . $sppLatest['bulan'] . ' ' . $sppLatest['tahun']) : 'Administrasi SPP Lancar';
                ?>
                <div class="text-xl font-extrabold text-indigo-400"><?= htmlspecialchars($sppStatusText); ?></div>
                <div class="text-xs text-slate-400"><?= htmlspecialchars($sppSubText); ?></div>
            </div>
        </div>
    </div>

    <!-- Attendance & Invoices Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- Absensi Anak -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white">Riwayat Kehadiran Anak</h3>
                <a href="dashboard.php?page=absensi_anak" class="text-xs text-amber-400 hover:underline font-bold">Selengkapnya →</a>
            </div>
            <div class="space-y-3">
                <?php foreach ($absensiTerakhir as $ab): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-white block"><?= date('d F Y', strtotime($ab['tanggal'])); ?></span>
                            <span class="text-[10px] text-slate-400"><?= htmlspecialchars($ab['keterangan']); ?></span>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <?= htmlspecialchars($ab['status']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Status SPP Anak -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white">Status Tagihan SPP Sekolah</h3>
                <a href="dashboard.php?page=tagihan_anak" class="text-xs text-amber-400 hover:underline font-bold">Selengkapnya →</a>
            </div>
            <div class="space-y-3">
                <?php foreach ($sppTerakhir as $sp): ?>
                    <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-white block">SPP Periode <?= htmlspecialchars($sp['bulan']); ?> <?= htmlspecialchars($sp['tahun']); ?></span>
                            <span class="text-[10px] text-slate-400">Rp <?= number_format($sp['nominal'], 0, ',', '.'); ?></span>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold 
                            <?= $sp['status'] === 'Lunas' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' ?>">
                            <?= htmlspecialchars($sp['status']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

</div>
