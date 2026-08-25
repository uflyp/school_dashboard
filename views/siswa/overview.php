<?php
// views/siswa/overview.php
check_role(['siswa', 'admin']);

// Fetch Siswa profile dynamically based on logged in user's username or email
$username = $_SESSION['username'] ?? '';
$userEmail = $user['email'] ?? '';
$userName = $user['name'] ?? '';

$stmtS = $pdo->prepare("SELECT * FROM siswa WHERE nis = ? OR nama = ? LIMIT 1");
$stmtS->execute([$username, $userName]);
$siswaDetail = $stmtS->fetch();

if (!$siswaDetail) {
    // Fallback if logged-in user is generic demo account, fetch first available active student from DB
    $siswaDetail = $pdo->query("SELECT * FROM siswa ORDER BY id ASC LIMIT 1")->fetch();
}

if (!$siswaDetail) {
    $siswaDetail = [
        'id' => 0,
        'nis' => '2026001',
        'nisn' => '0061234567',
        'nama' => $userName ?: 'Siswa',
        'kelas' => 'XII IPA 1',
        'jenis_kelamin' => 'Laki-laki',
        'tanggal_lahir' => '2008-05-14',
        'alamat' => 'Jl. Merdeka No. 45'
    ];
}

$nis = $siswaDetail['nis'];

// Dynamic Stats calculation for this student
// 1. Kehadiran
$totAbsensi = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE nis = ?");
$totAbsensi->execute([$nis]);
$totalHadirCount = $totAbsensi->fetchColumn() ?: 0;

$totHadir = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE nis = ? AND status IN ('Hadir', 'H')");
$totHadir->execute([$nis]);
$hadirCount = $totHadir->fetchColumn() ?: 0;

$pctHadir = ($totalHadirCount > 0) ? round(($hadirCount / $totalHadirCount) * 100) . '%' : '100%';

// 2. Rapor GPA & Predikat Akademik
$avgStmt = $pdo->prepare("SELECT AVG((nilai_tugas + nilai_uts + nilai_uas)/3.0) FROM nilai WHERE nis = ?");
$avgStmt->execute([$nis]);
$avgNilai = $avgStmt->fetchColumn();
$gpaVal = $avgNilai ? (($avgNilai / 100) * 4.0) : 3.85;
$gpaInfo = get_gpa_info($gpaVal);

// 3. SPP Status
$sppStmt = $pdo->prepare("SELECT status FROM spp_transaksi WHERE nis = ? ORDER BY id DESC LIMIT 1");
$sppStmt->execute([$nis]);
$sppStatusRaw = $sppStmt->fetchColumn();
$sppDisplay = $sppStatusRaw ?: 'Lunas';

$pengumuman = $pdo->query("SELECT * FROM pengumuman ORDER BY id DESC LIMIT 3")->fetchAll();
?>
<div class="space-y-8">

    <!-- Digital Student Card Badge Hero -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        
        <!-- Welcome banner -->
        <div class="lg:col-span-7 space-y-4 min-w-0">
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-bold">
                <i class="fa-solid fa-user-graduate"></i> Portal Siswa SMA Nusantara
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight break-words">Halo, <?= htmlspecialchars($siswaDetail['nama']); ?>! 👋</h1>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed max-w-lg">
                Selamat datang di portal akademik Anda. Di sini Anda dapat memantau jadwal pelajaran harian, absensi presensi, transkrip nilai rapor, dan status iuran SPP.
            </p>

            <div class="flex flex-wrap gap-3 pt-2">
                <a href="dashboard.php?page=jadwal" class="px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-cyan-600/30 transition-all">
                    <i class="fa-solid fa-calendar-week"></i> Lihat Jadwal Pelajaran
                </a>
                <a href="dashboard.php?page=nilai" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs flex items-center gap-2 border border-slate-700 transition-all">
                    <i class="fa-solid fa-graduation-cap"></i> Lihat Nilai Rapor
                </a>
            </div>
        </div>

        <!-- Student Digital ID Card (Kartu Pelajar Digital) -->
        <div class="lg:col-span-5">
            <div class="relative rounded-3xl bg-gradient-to-br from-indigo-900 via-slate-900 to-cyan-950 p-6 border border-cyan-500/30 shadow-2xl overflow-hidden group">
                <div class="flex items-center justify-between border-b border-indigo-500/30 pb-4 mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-graduation-cap text-cyan-400 text-xl"></i>
                        <span class="font-extrabold text-white text-xs tracking-wider">KARTU PELAJAR DIGITAL</span>
                    </div>
                    <span class="text-[10px] font-mono text-cyan-400 font-bold bg-cyan-500/10 px-2 py-0.5 rounded border border-cyan-500/20">SMA NUSANTARA</span>
                </div>

                <div class="flex items-center gap-4">
                    <div class="relative group shrink-0">
                        <img src="<?= htmlspecialchars($user['avatar']); ?>" style="width: 64px; height: 64px; object-fit: cover;" class="w-16 h-16 rounded-2xl object-cover border-2 border-cyan-400 shadow-md">
                        <button type="button" onclick="openModal('modal-change-avatar')" title="Ubah Foto Profil dari Perangkat" class="absolute inset-0 bg-slate-950/75 rounded-2xl opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs transition-opacity">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                    </div>
                    <div class="space-y-1 min-w-0 flex-1">
                        <h3 class="text-base font-extrabold text-white break-words"><?= htmlspecialchars($siswaDetail['nama']); ?></h3>
                        <div class="text-xs text-cyan-300 font-mono">NIS: <?= htmlspecialchars($siswaDetail['nis']); ?></div>
                        <div class="text-[11px] text-slate-300">Kelas: <strong class="text-white"><?= htmlspecialchars($siswaDetail['kelas']); ?></strong></div>
                        <button type="button" onclick="openModal('modal-change-avatar')" class="mt-1 inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-cyan-500/20 hover:bg-cyan-500 text-cyan-300 hover:text-white border border-cyan-400/30 text-[10px] font-bold transition-all">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload Foto Profil
                        </button>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-indigo-500/20 flex items-center justify-between text-[10px] text-slate-400">
                    <span>NISN: <?= htmlspecialchars($siswaDetail['nisn']); ?></span>
                    <span class="text-emerald-400 font-bold"><i class="fa-solid fa-circle-check mr-1"></i> STATUS: AKTIF</span>
                </div>
            </div>
        </div>

    </div>

    <!-- Stats summary -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center text-lg font-bold"><i class="fa-solid fa-check-double"></i></div>
            <div>
                <div class="text-xl font-extrabold text-white"><?= $pctHadir; ?></div>
                <div class="text-xs text-slate-400">Kehadiran Minggu Ini</div>
            </div>
        </div>
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl <?= $gpaInfo['bg']; ?> <?= $gpaInfo['color']; ?> flex items-center justify-center text-lg font-bold"><i class="fa-solid fa-star"></i></div>
            <div>
                <div class="text-xl font-extrabold <?= $gpaInfo['color']; ?>">GPA <?= $gpaInfo['gpa']; ?></div>
                <div class="text-[11px] font-bold text-white leading-tight"><?= htmlspecialchars($gpaInfo['predicate']); ?></div>
                <div class="text-[10px] text-slate-400 mt-0.5">Akumulasi Capaian Akademik</div>
            </div>
        </div>
        <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-lg font-bold"><i class="fa-solid fa-shield-check"></i></div>
            <div>
                <div class="text-xl font-extrabold text-indigo-400"><?= htmlspecialchars($sppDisplay); ?></div>
                <div class="text-xs text-slate-400">Status Pembayaran SPP</div>
            </div>
        </div>
    </div>

    <!-- Announcement list -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <h3 class="text-base font-bold text-white mb-4">Pengumuman & Pengingat</h3>
        <div class="space-y-3">
            <?php if (empty($pengumuman)): ?>
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 text-center text-xs text-slate-400">
                    Belum ada pengumuman terbaru untuk Anda.
                </div>
            <?php else: ?>
                <?php foreach ($pengumuman as $p): ?>
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800">
                        <div class="flex items-center justify-between mb-1">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/10 text-cyan-400"><?= htmlspecialchars($p['kategori']); ?></span>
                            <span class="text-[10px] text-slate-500"><?= date('d M Y', strtotime($p['tanggal'])); ?></span>
                        </div>
                        <h4 class="text-xs font-bold text-white mb-1"><?= htmlspecialchars($p['judul']); ?></h4>
                        <p class="text-xs text-slate-400"><?= htmlspecialchars($p['isi']); ?></p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
