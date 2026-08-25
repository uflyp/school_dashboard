<?php
// views/siswa/jadwal.php - Dynamic Schedule & Subject Viewer for Students
check_role(['siswa', 'admin']);

// Fetch Siswa profile details for logged-in user or default student
$siswaUser = current_user();
$username = $_SESSION['username'] ?? '';
$userName = $siswaUser['name'] ?? '';

$siswaStmt = $pdo->prepare("SELECT * FROM siswa WHERE nis = ? OR nama = ? LIMIT 1");
$siswaStmt->execute([$username, $userName]);
$siswaInfo = $siswaStmt->fetch();

if (!$siswaInfo) {
    $siswaInfo = $pdo->query("SELECT * FROM siswa ORDER BY id ASC LIMIT 1")->fetch() ?: ['kelas' => 'XII IPA 1', 'nis' => '2026001'];
}

$kelas_siswa = $siswaInfo['kelas'];
$current_nis = $siswaInfo['nis'];

// Query dynamic schedule from database source of truth
$stmtJadwal = $pdo->prepare("
    SELECT * FROM jadwal_pelajaran 
    WHERE kelas_nama = ? OR kelas_nama = 'Semua Kelas' 
    ORDER BY CASE hari 
        WHEN 'Senin' THEN 1 
        WHEN 'Selasa' THEN 2 
        WHEN 'Rabu' THEN 3 
        WHEN 'Kamis' THEN 4 
        WHEN 'Jumat' THEN 5 
        WHEN 'Sabtu' THEN 6 
        ELSE 7 
    END, jam_mulai ASC
");
$stmtJadwal->execute([$kelas_siswa]);
$rawJadwal = $stmtJadwal->fetchAll();

// If no class-specific schedule exists, fallback to all active schedules in system
if (empty($rawJadwal)) {
    $rawJadwal = $pdo->query("
        SELECT * FROM jadwal_pelajaran 
        ORDER BY CASE hari 
            WHEN 'Senin' THEN 1 
            WHEN 'Selasa' THEN 2 
            WHEN 'Rabu' THEN 3 
            WHEN 'Kamis' THEN 4 
            WHEN 'Jumat' THEN 5 
            WHEN 'Sabtu' THEN 6 
            ELSE 7 
        END, jam_mulai ASC
    ")->fetchAll();
}

$jadwal = [];
foreach ($rawJadwal as $row) {
    $hari = $row['hari'];
    if (!isset($jadwal[$hari])) {
        $jadwal[$hari] = [];
    }
    $jadwal[$hari][] = [
        'jam' => $row['jam_mulai'] . ' - ' . $row['jam_selesai'],
        'mapel' => $row['mapel_nama'],
        'guru' => $row['guru_nama'],
        'ruang' => $row['ruang'] ?? 'R. Kelas',
        'kelas' => $row['kelas_nama']
    ];
}

$absensiStmt = $pdo->prepare("SELECT * FROM absensi WHERE nis = ? ORDER BY tanggal DESC LIMIT 5");
$absensiStmt->execute([$siswaInfo['nis']]);
$absensi = $absensiStmt->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-calendar-week text-cyan-400"></i> Jadwal Pelajaran & Presensi Kehadiran
            </h1>
            <p class="text-xs text-slate-400 mt-1">Jadwal tatap muka mingguan terupdate dari kurikulum sekolah (Kelas: <span class="text-cyan-400 font-bold"><?= htmlspecialchars($kelas_siswa); ?></span>)</p>
        </div>
        <div class="px-3.5 py-1.5 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-arrows-rotate animate-spin"></i> Live Database Sync
        </div>
    </div>

    <!-- Jadwal Pelajaran Grid -->
    <?php if (empty($jadwal)): ?>
        <div class="p-8 rounded-3xl bg-slate-900 border border-slate-800 text-center text-slate-400 text-xs">
            <i class="fa-solid fa-calendar-xmark text-2xl text-slate-600 mb-2 block"></i>
            Belum ada jadwal pelajaran yang diatur oleh Admin untuk kelas Anda.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($jadwal as $hari => $list): ?>
                <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <h3 class="text-base font-extrabold text-cyan-400 flex items-center gap-2">
                            <i class="fa-regular fa-clock text-xs"></i> <?= htmlspecialchars($hari); ?>
                        </h3>
                        <span class="text-[10px] text-slate-400 uppercase font-bold bg-slate-800 px-2 py-0.5 rounded-full border border-slate-700"><?= count($list); ?> Sesi</span>
                    </div>
                    <div class="space-y-3">
                        <?php foreach ($list as $j): ?>
                            <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800/80 hover:border-cyan-500/30 transition-all">
                                <div class="text-[11px] font-mono text-cyan-400 font-bold mb-1 flex items-center justify-between">
                                    <span><i class="fa-regular fa-hourglass-half mr-1"></i><?= htmlspecialchars($j['jam']); ?></span>
                                    <span class="text-[9px] text-slate-500 bg-slate-900 px-1.5 py-0.5 rounded font-sans"><?= htmlspecialchars($j['kelas']); ?></span>
                                </div>
                                <h4 class="text-xs font-extrabold text-white mb-1.5"><?= htmlspecialchars($j['mapel']); ?></h4>
                                <div class="flex items-center justify-between text-[11px] text-slate-400 pt-1 border-t border-slate-900">
                                    <span class="truncate pr-2"><i class="fa-solid fa-chalkboard-user text-cyan-400 mr-1"></i><?= htmlspecialchars($j['guru']); ?></span>
                                    <span class="bg-slate-800 px-2 py-0.5 rounded text-[10px] text-slate-300 font-mono shrink-0"><?= htmlspecialchars($j['ruang']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Kehadiran Presensi Terbaru -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <h3 class="text-base font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-user-check text-emerald-400"></i> Catatan Kehadiran Presensi Terbaru
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Tanggal</th>
                        <th class="p-3.5">Status Kehadiran</th>
                        <th class="p-3.5 rounded-r-xl">Keterangan Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    <?php if (empty($absensi)): ?>
                        <tr>
                            <td colspan="3" class="p-6 text-center text-slate-500">Belum ada catatan presensi.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($absensi as $ab): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-3.5 font-mono text-slate-300"><?= format_date($ab['tanggal']); ?></td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <i class="fa-solid fa-check-circle mr-1"></i><?= htmlspecialchars($ab['status']); ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-slate-400"><?= htmlspecialchars($ab['keterangan'] ?: '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
