<?php
// views/alumni/overview.php - Beranda Dashboard Khusus Alumni
check_role(['alumni', 'admin']);

$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 0);
$userName = $user['name'] ?? ($_SESSION['name'] ?? 'Alumni');
$userEmail = $user['email'] ?? ($_SESSION['email'] ?? '');

// Ambil data alumni dari tabel alumni / tracer_study
$stmtAlumni = $pdo->prepare("SELECT * FROM alumni WHERE nama = ? OR nis = ? LIMIT 1");
$stmtAlumni->execute([$userName, $userName]);
$alumniData = $stmtAlumni->fetch(PDO::FETCH_ASSOC);

// Cek status tracer study
$stmtTracer = $pdo->prepare("SELECT * FROM tracer_study WHERE user_id = ? OR nama = ? ORDER BY id DESC LIMIT 1");
$stmtTracer->execute([$userId, $userName]);
$tracerData = $stmtTracer->fetch(PDO::FETCH_ASSOC);

$sudahTracer = !empty($tracerData);
$tahunLulus = $tracerData['tahun_lulus'] ?? ($alumniData['tahun_lulus'] ?? '2024');
$statusAktivitas = $tracerData['status_aktivitas'] ?? ($alumniData['kuliah_kerja'] ?? 'Belum Diisi');
$instansi = $tracerData['nama_perusahaan'] ?? ($tracerData['pendidikan_lanjutan'] ?? ($alumniData['kuliah_kerja'] ?? '-'));

// Pengumuman sekolah / alumni
$pengumuman = $pdo->query("SELECT * FROM pengumuman ORDER BY id DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="space-y-6">

    <!-- Welcome Hero Banner -->
    <div class="p-6 sm:p-8 rounded-3xl bg-gradient-to-r from-teal-950 via-slate-900 to-indigo-950 border border-teal-500/20 shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="space-y-2 max-w-xl z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-extrabold bg-teal-500/10 text-teal-400 border border-teal-500/20">
                <i class="fa-solid fa-graduation-cap"></i> Portal Resmi Ikatan Alumni (IKA)
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                Selamat Datang, <?= htmlspecialchars($userName); ?>!
            </h1>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                Tetap terhubung dengan almamater tercinta. Bantu kami meningkatkan mutu kurikulum dan jejaring karir lulusan melalui pengisian data Tracer Study.
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto z-10">
            <a href="dashboard.php?page=tracer" class="w-full sm:w-auto px-5 py-3 rounded-2xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-extrabold flex items-center justify-center gap-2 shadow-lg shadow-teal-600/30 transition-all">
                <i class="fa-solid fa-clipboard-list"></i> <?= $sudahTracer ? 'Perbarui Tracer Study' : 'Isi Tracer Study Sekarang'; ?>
            </a>
            <a href="dashboard.php?page=profil" class="w-full sm:w-auto px-4 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold border border-slate-700 flex items-center justify-center gap-2 transition-all">
                <i class="fa-solid fa-user-pen"></i> Profil Saya
            </a>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <!-- Status Tracer Card -->
        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl <?= $sudahTracer ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'; ?> border flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid <?= $sudahTracer ? 'fa-circle-check' : 'fa-clock-rotate-left'; ?>"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Status Tracer Study</span>
                <span class="text-sm font-black <?= $sudahTracer ? 'text-emerald-400' : 'text-amber-400'; ?> block mt-0.5">
                    <?= $sudahTracer ? 'SUDAH MENGISI' : 'BELUM MENGISI'; ?>
                </span>
                <span class="text-[10px] text-slate-500 block">
                    <?= $sudahTracer ? ('Update: ' . date('d M Y', strtotime($tracerData['updated_at'] ?? $tracerData['created_at']))) : 'Mohon lengkapi kuesioner'; ?>
                </span>
            </div>
        </div>

        <!-- Tahun Lulus Card -->
        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-teal-500/10 border border-teal-500/20 text-teal-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Tahun Kelulusan</span>
                <span class="text-lg font-black text-white font-mono block mt-0.5">Angkatan <?= htmlspecialchars($tahunLulus); ?></span>
                <span class="text-[10px] text-slate-500 block">Alumni Terdata</span>
            </div>
        </div>

        <!-- Aktivitas Saat Ini Card -->
        <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-briefcase"></i>
            </div>
            <div class="truncate">
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Aktivitas / Karir</span>
                <span class="text-sm font-black text-indigo-300 truncate block mt-0.5"><?= htmlspecialchars($statusAktivitas); ?></span>
                <span class="text-[10px] text-slate-500 truncate block"><?= htmlspecialchars($instansi); ?></span>
            </div>
        </div>
    </div>

    <!-- 2-Columns: Tracer Summary & Alumni Announcements -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Summary Info -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-address-card text-teal-400"></i> Rangkuman Data Tracer Anda
                </h3>
                <a href="dashboard.php?page=tracer" class="text-xs text-teal-400 hover:text-teal-300 font-semibold flex items-center gap-1">
                    Edit Data <i class="fa-solid fa-angle-right text-[10px]"></i>
                </a>
            </div>

            <?php if ($sudahTracer): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-1">
                        <span class="text-slate-500 block text-[10px] font-bold uppercase">Status Saat Ini</span>
                        <strong class="text-white text-sm"><?= htmlspecialchars($tracerData['status_aktivitas'] ?? '-'); ?></strong>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-1">
                        <span class="text-slate-500 block text-[10px] font-bold uppercase">Instansi / Kampus / Kantor</span>
                        <strong class="text-teal-300 text-sm"><?= htmlspecialchars($tracerData['nama_perusahaan'] ?: ($tracerData['pendidikan_lanjutan'] ?: '-')); ?></strong>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-1">
                        <span class="text-slate-500 block text-[10px] font-bold uppercase">Posisi / Program Studi</span>
                        <strong class="text-white"><?= htmlspecialchars($tracerData['posisi_jabatan'] ?: ($tracerData['bidang_pekerjaan'] ?: '-')); ?></strong>
                    </div>
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-1">
                        <span class="text-slate-500 block text-[10px] font-bold uppercase">Kesesuaian Kompetensi</span>
                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                            <?= htmlspecialchars($tracerData['kesesuaian_pekerjaan'] ?: 'Sesuai'); ?>
                        </span>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-8 rounded-2xl bg-slate-950 border border-dashed border-slate-800 text-center space-y-3">
                    <i class="fa-solid fa-clipboard-question text-3xl text-teal-400/60 block"></i>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto">
                        Anda belum mengisi kuesioner Tracer Study. Data ini sangat penting untuk pengembangan mutu lulusan sekolah.
                    </p>
                    <a href="dashboard.php?page=tracer" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold shadow-md shadow-teal-600/30">
                        Isi Tracer Study
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Pengumuman & Jejaring Alumni -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                <i class="fa-solid fa-bullhorn text-indigo-400"></i> Info &amp; Pengumuman
            </h3>

            <div class="space-y-3">
                <?php if (empty($pengumuman)): ?>
                    <p class="text-xs text-slate-500 text-center py-4">Belum ada pengumuman baru.</p>
                <?php else: ?>
                    <?php foreach ($pengumuman as $p): ?>
                        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-1 hover:border-teal-500/30 transition-all">
                            <h4 class="text-xs font-bold text-white line-clamp-1"><?= htmlspecialchars($p['judul'] ?? 'Pengumuman'); ?></h4>
                            <p class="text-[11px] text-slate-400 line-clamp-2"><?= htmlspecialchars($p['konten'] ?? ''); ?></p>
                            <span class="text-[9px] text-slate-500 font-mono block pt-1"><?= date('d M Y', strtotime($p['tanggal'] ?? date('Y-m-d'))); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>
