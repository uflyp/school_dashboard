<?php
// views/guru/jadwal_mengajar.php
check_role(['guru', 'admin']);
$role = $role ?? ($_SESSION['role'] ?? 'guru');

$user_name = $user['name'] ?? '';
$user_email = $user['email'] ?? '';

// Fetch associated teacher record from database to match assignment name or ID
$stmtG = $pdo->prepare("SELECT * FROM guru WHERE email = ? OR nama = ? LIMIT 1");
$stmtG->execute([$user_email, $user_name]);
$guruData = $stmtG->fetch();

$guru_nama = $guruData['nama'] ?? $user_name;
$guru_id = $guruData['id'] ?? 0;

// Dynamic database query: Guru ONLY views schedule assigned to them by Admin
if ($role === 'guru') {
    $stmt = $pdo->prepare("
        SELECT * FROM jadwal_pelajaran 
        WHERE guru_nama = ? OR (guru_id = ? AND guru_id > 0)
        ORDER BY 
            CASE hari 
                WHEN 'Senin' THEN 1 
                WHEN 'Selasa' THEN 2 
                WHEN 'Rabu' THEN 3 
                WHEN 'Kamis' THEN 4 
                WHEN 'Jumat' THEN 5 
                WHEN 'Sabtu' THEN 6 
                ELSE 7 
            END, jam_mulai ASC
    ");
    $stmt->execute([$guru_nama, $guru_id]);
    $jadwalList = $stmt->fetchAll();
} else {
    // Admin override: view all active schedules
    $jadwalList = $pdo->query("
        SELECT * FROM jadwal_pelajaran 
        ORDER BY 
            CASE hari 
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
?>
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-indigo-400"></i> Jadwal Mengajar Saya
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                Jadwal tatap muka semester ganjil T.A. 2026/2027 terdaftar di Database Sekolah
            </p>
        </div>
        <button onclick="window.print()" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition-all">
            <i class="fa-solid fa-print"></i> Cetak Jadwal
        </button>
    </div>

    <!-- Schedule List Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Hari</th>
                        <th class="p-3.5">Waktu / Jam</th>
                        <th class="p-3.5">Kelas</th>
                        <th class="p-3.5">Mata Pelajaran</th>
                        <th class="p-3.5 rounded-r-xl">Ruangan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    <?php if (empty($jadwalList)): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-500">
                                <i class="fa-solid fa-calendar-xmark text-2xl mb-2 block text-slate-600"></i>
                                Belum ada penugasan jadwal mengajar yang ditugaskan kepada Anda oleh Admin.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($jadwalList as $j): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($j['hari']); ?></td>
                                <td class="p-3.5 font-mono text-indigo-400 font-bold"><?= htmlspecialchars($j['jam_mulai']); ?> - <?= htmlspecialchars($j['jam_selesai']); ?></td>
                                <td class="p-3.5"><span class="px-2.5 py-1 rounded-md bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 font-bold"><?= htmlspecialchars($j['kelas_nama']); ?></span></td>
                                <td class="p-3.5 text-white font-semibold"><?= htmlspecialchars($j['mapel_nama']); ?></td>
                                <td class="p-3.5 text-slate-400"><i class="fa-solid fa-location-dot text-rose-400 mr-1"></i><?= htmlspecialchars($j['ruang']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
