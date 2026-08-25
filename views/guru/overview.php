<?php
// views/guru/overview.php
check_role(['guru', 'admin']);
$role = $role ?? ($_SESSION['role'] ?? 'guru');

// Fetch logged-in user info and gender (jenis_kelamin) dynamically
$user_id = $_SESSION['user_id'] ?? 0;
$user_email = $user['email'] ?? '';
$user_name = $user['name'] ?? '';

$jk_val = $user['jenis_kelamin'] ?? null;
if (!$jk_val && $user_id) {
    $stmtJk = $pdo->prepare("SELECT jenis_kelamin FROM users WHERE id = ?");
    $stmtJk->execute([$user_id]);
    $jk_val = $stmtJk->fetchColumn();
}
if (!$jk_val && ($user_email || $user_name)) {
    $stmtGuru = $pdo->prepare("SELECT jenis_kelamin FROM guru WHERE email = ? OR nama = ? LIMIT 1");
    $stmtGuru->execute([$user_email, $user_name]);
    $jk_val = $stmtGuru->fetchColumn();
}

$jkUpper = strtoupper(trim($jk_val ?? ''));
// Bapak = L / LAKI / LAKI-LAKI; Ibu = P / PEREMPUAN / anything else
if ($jkUpper === 'L' || $jkUpper === 'LAKI' || str_starts_with($jkUpper, 'LAKI-')) {
    $sapaan = 'Bapak';
} else {
    $sapaan = 'Ibu';
}

// Strictly scope metrics and list widgets to logged-in teacher's user_id
if ($role === 'admin') {
    $totalMateri = $pdo->query("SELECT COUNT(*) FROM materi")->fetchColumn();
    $totalTugas = $pdo->query("SELECT COUNT(*) FROM tugas")->fetchColumn();
    $materiList = $pdo->query("SELECT * FROM materi ORDER BY id DESC LIMIT 3")->fetchAll();
    $tugasList = $pdo->query("SELECT * FROM tugas ORDER BY id DESC LIMIT 3")->fetchAll();
} else {
    $stmtMatCount = $pdo->prepare("SELECT COUNT(*) FROM materi WHERE user_id = ?");
    $stmtMatCount->execute([$user_id]);
    $totalMateri = $stmtMatCount->fetchColumn();

    $stmtTugCount = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE user_id = ?");
    $stmtTugCount->execute([$user_id]);
    $totalTugas = $stmtTugCount->fetchColumn();

    $stmtMatList = $pdo->prepare("SELECT * FROM materi WHERE user_id = ? ORDER BY id DESC LIMIT 3");
    $stmtMatList->execute([$user_id]);
    $materiList = $stmtMatList->fetchAll();

    $stmtTugList = $pdo->prepare("SELECT * FROM tugas WHERE user_id = ? ORDER BY id DESC LIMIT 3");
    $stmtTugList->execute([$user_id]);
    $tugasList = $stmtTugList->fetchAll();
}

$totalSiswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
?>
<div class="space-y-8">

    <!-- Teacher Welcome Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-blue-950 via-indigo-900 to-slate-900 p-6 sm:p-8 border border-blue-700/50 shadow-2xl">
        <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            <div class="min-w-0 flex-1 space-y-2">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-blue-500/20 text-blue-300 border border-blue-400/30">
                    <i class="fa-solid fa-chalkboard-user"></i> Portal Tenaga Pendidik (Guru)
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight break-words">Selamat Datang, <?= $sapaan; ?> <?= htmlspecialchars($user['name']); ?></h1>
                <p class="text-xs sm:text-sm text-blue-200 leading-relaxed max-w-2xl">Kelola materi pelajaran pribadi, tugas aktif siswa, pengisian nilai rapor, dan presensi absensi.</p>
            </div>
            <div class="flex flex-wrap gap-3 shrink-0">
                <a href="dashboard.php?page=materi" class="px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-blue-600/30 transition-all">
                    <i class="fa-solid fa-upload"></i> Unggah Materi
                </a>
                <a href="dashboard.php?page=tugas" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition-all">
                    <i class="fa-solid fa-plus"></i> Buat Tugas
                </a>
            </div>
        </div>
    </div>

    <!-- Stat Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex items-center gap-4 shadow-xl">
            <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-book-bookmark"></i></div>
            <div>
                <div class="text-2xl font-extrabold text-white"><?= $totalMateri; ?> Modul</div>
                <div class="text-xs text-slate-400">Materi Pelajaran Saya</div>
            </div>
        </div>
        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex items-center gap-4 shadow-xl">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-list-check"></i></div>
            <div>
                <div class="text-2xl font-extrabold text-white"><?= $totalTugas; ?> Tugas</div>
                <div class="text-xs text-slate-400">Penugasan Aktif Saya</div>
            </div>
        </div>
        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex items-center gap-4 shadow-xl">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-xl shrink-0"><i class="fa-solid fa-user-graduate"></i></div>
            <div>
                <div class="text-2xl font-extrabold text-emerald-400"><?= $totalSiswa; ?> Siswa</div>
                <div class="text-xs text-slate-400">Total Siswa Terdaftar</div>
            </div>
        </div>
    </div>

    <!-- Modul & Tugas Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- Materi Pelajaran Terbaru -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-book-bookmark text-blue-400"></i> Materi Pelajaran Terbaru Saya
                </h3>
                <a href="dashboard.php?page=materi" class="text-xs text-blue-400 hover:underline font-bold">Kelola Semua →</a>
            </div>
            <div class="space-y-3">
                <?php if (empty($materiList)): ?>
                    <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800 text-center text-xs text-slate-500">
                        Belum ada materi pelajaran yang Anda buat.
                    </div>
                <?php else: ?>
                    <?php foreach ($materiList as $m): ?>
                        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800">
                            <div class="flex items-center justify-between mb-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20"><?= htmlspecialchars($m['mapel']); ?></span>
                                <span class="text-[10px] text-slate-500"><?= htmlspecialchars($m['kelas']); ?></span>
                            </div>
                            <h4 class="text-xs font-bold text-white mb-1"><?= htmlspecialchars($m['judul']); ?></h4>
                            <p class="text-[11px] text-slate-400 line-clamp-2"><?= htmlspecialchars($m['deskripsi']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Penugasan Siswa -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-indigo-400"></i> Penugasan Aktif Saya
                </h3>
                <a href="dashboard.php?page=tugas" class="text-xs text-indigo-400 hover:underline font-bold">Kelola Semua →</a>
            </div>
            <div class="space-y-3">
                <?php if (empty($tugasList)): ?>
                    <div class="p-4 rounded-2xl bg-slate-950/60 border border-slate-800 text-center text-xs text-slate-500">
                        Belum ada tugas pelajaran yang Anda terbitkan.
                    </div>
                <?php else: ?>
                    <?php foreach ($tugasList as $t): ?>
                        <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800">
                            <div class="flex items-center justify-between mb-1">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"><?= htmlspecialchars($t['mapel']); ?></span>
                                <span class="text-[10px] text-amber-400 font-mono"><i class="fa-regular fa-clock mr-1"></i><?= date('d M Y', strtotime($t['deadline'])); ?></span>
                            </div>
                            <h4 class="text-xs font-bold text-white mb-1"><?= htmlspecialchars($t['judul']); ?></h4>
                            <p class="text-[11px] text-slate-400 line-clamp-2"><?= htmlspecialchars($t['instruksi']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
