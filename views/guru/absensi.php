<?php
// views/guru/absensi.php
check_role(['guru', 'admin']);
$role = $role ?? ($_SESSION['role'] ?? 'guru');

$user = $user ?? current_user();
$user_name = $user['name'] ?? ($_SESSION['name'] ?? '');
$user_email = $user['email'] ?? ($_SESSION['email'] ?? '');

// Identifikasi profil data guru
$stmtG = $pdo->prepare("SELECT * FROM guru WHERE email = ? OR nama = ? LIMIT 1");
$stmtG->execute([$user_email, $user_name]);
$guruData = $stmtG->fetch();

$guru_nama = $guruData['nama'] ?? $user_name;
$guru_id = $guruData['id'] ?? 0;

// Ambil Periode Akademik Aktif
$tahunAjaran = get_setting('academic_year', '2026/2027');
$semesterAktif = get_setting('active_semester', 'Ganjil');

// Ambil Daftar Jadwal Mengajar Guru
if ($role === 'guru') {
    $stmtJadwal = $pdo->prepare("
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
    $stmtJadwal->execute([$guru_nama, $guru_id]);
    $jadwalList = $stmtJadwal->fetchAll();
} else {
    // Admin dapat melihat dan memilih semua jadwal
    $jadwalList = $pdo->query("SELECT * FROM jadwal_pelajaran ORDER BY kelas_nama ASC, hari ASC, jam_mulai ASC")->fetchAll();
}

// Tentukan Jadwal / Sesi yang Sedang Dibuka
$selected_jadwal_id = (int)($_GET['jadwal_id'] ?? $_POST['jadwal_id'] ?? 0);
if ($selected_jadwal_id === 0 && !empty($jadwalList)) {
    // Default ke jadwal pertama yang tersedia
    $selected_jadwal_id = (int)$jadwalList[0]['id'];
}

$activeJadwal = null;
foreach ($jadwalList as $j) {
    if ((int)$j['id'] === $selected_jadwal_id) {
        $activeJadwal = $j;
        break;
    }
}
if (!$activeJadwal && !empty($jadwalList)) {
    $activeJadwal = $jadwalList[0];
    $selected_jadwal_id = (int)$activeJadwal['id'];
}

$msg = '';
$msgType = 'success';

// Handle POST Presensi (Scan QR / Manual Input)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_absensi') {
    verify_csrf_token();
    $nis = trim($_POST['nis'] ?? '');
    $tgl = date('Y-m-d');
    $status = trim($_POST['status'] ?? 'Hadir');
    $ket = trim($_POST['keterangan'] ?? 'Presensi Terverifikasi');
    $post_jadwal_id = (int)($_POST['jadwal_id'] ?? $selected_jadwal_id);

    // 1. Validasi Sesi Jadwal
    $stmtValJadwal = $pdo->prepare("SELECT * FROM jadwal_pelajaran WHERE id = ?");
    $stmtValJadwal->execute([$post_jadwal_id]);
    $targetJadwal = $stmtValJadwal->fetch();

    if (!$targetJadwal) {
        $msg = "Sesi jadwal pelajaran tidak valid atau tidak ditemukan!";
        $msgType = 'error';
    } elseif ($role === 'guru' && strcasecmp($targetJadwal['guru_nama'], $guru_nama) !== 0 && (int)$targetJadwal['guru_id'] !== $guru_id) {
        // Keamanan: Guru tidak boleh absen di jadwal yang bukan tanggung jawabnya
        $msg = "Akses ditolak: Jadwal ini diampu oleh {$targetJadwal['guru_nama']}, bukan tanggung jawab Anda!";
        $msgType = 'error';
    } elseif (empty($nis)) {
        $msg = "NIS siswa tidak boleh kosong!";
        $msgType = 'error';
    } else {
        // 2. Validasi Keberadaan Siswa di Database
        $stmtCheckSiswa = $pdo->prepare("SELECT * FROM siswa WHERE nis = ? OR nama = ? LIMIT 1");
        $stmtCheckSiswa->execute([$nis, $nis]);
        $siswaData = $stmtCheckSiswa->fetch();

        if (!$siswaData) {
            $msg = "NIS / Data Siswa '$nis' dari QR Code tidak terdaftar dalam database sekolah!";
            $msgType = 'error';
        } else {
            $targetKelas = trim($targetJadwal['kelas_nama']);
            $siswaKelas = trim($siswaData['kelas']);

            // 3. SERVER-SIDE VALIDATION: Siswa harus terdaftar di kelas sesi tersebut!
            if (strcasecmp($siswaKelas, $targetKelas) !== 0) {
                $msg = "DITOLAK: Siswa {$siswaData['nama']} (NIS: {$siswaData['nis']}) terdaftar di kelas {$siswaKelas}, BUKAN kelas {$targetKelas}!";
                $msgType = 'error';
                log_activity("Unauthorized attendance attempt: {$siswaData['nama']} ({$siswaKelas}) scanned on class {$targetKelas}");
            } else {
                // 4. Cek apakah sudah ada presensi pada tanggal dan sesi jadwal ini
                $realNis = $siswaData['nis'];
                $namaSiswa = $siswaData['nama'];
                $mapelNama = $targetJadwal['mapel_nama'];
                $gId = $targetJadwal['guru_id'] ?: $guru_id;

                $stmtCheckToday = $pdo->prepare("
                    SELECT id FROM absensi 
                    WHERE nis = ? AND tanggal = ? AND (jadwal_id = ? OR (kelas = ? AND mapel = ?))
                ");
                $stmtCheckToday->execute([$realNis, $tgl, $post_jadwal_id, $targetKelas, $mapelNama]);
                $existingAbsen = $stmtCheckToday->fetch();

                if ($existingAbsen) {
                    // Update record
                    $stmtUpd = $pdo->prepare("
                        UPDATE absensi 
                        SET status = ?, keterangan = ?, kelas = ?, mapel = ?, guru_id = ?, jadwal_id = ?, semester = ?, tahun_ajaran = ? 
                        WHERE id = ?
                    ");
                    $stmtUpd->execute([$status, $ket, $targetKelas, $mapelNama, $gId, $post_jadwal_id, $semesterAktif, $tahunAjaran, $existingAbsen['id']]);
                    $msg = "Presensi diperbarui: $namaSiswa ($targetKelas) — Status: " . strtoupper($status);
                    log_activity("Attendance updated: $namaSiswa ($realNis) on $targetKelas $mapelNama - $status");
                } else {
                    // Insert new record
                    $stmtIns = $pdo->prepare("
                        INSERT INTO absensi (nis, tanggal, status, keterangan, kelas, mapel, guru_id, jadwal_id, semester, tahun_ajaran) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmtIns->execute([$realNis, $tgl, $status, $ket, $targetKelas, $mapelNama, $gId, $post_jadwal_id, $semesterAktif, $tahunAjaran]);
                    $msg = "Presensi berhasil dicatat: $namaSiswa ($targetKelas) — Status: " . strtoupper($status);
                    log_activity("Attendance recorded: $namaSiswa ($realNis) on $targetKelas $mapelNama - $status");
                }
            }
        }
    }
}

// Ambil Daftar Siswa HANYA dari Kelas Jadwal Terpilih
$siswaKelasList = [];
$absensiSesiList = [];
$todayStr = date('Y-m-d');

if ($activeJadwal) {
    $activeKelas = $activeJadwal['kelas_nama'];
    $activeMapel = $activeJadwal['mapel_nama'];

    // 1. Siswa terdaftar di kelas aktif
    $stmtSiswaKls = $pdo->prepare("SELECT * FROM siswa WHERE kelas = ? AND (status_siswa = 'Aktif' OR status_siswa IS NULL) ORDER BY nama ASC");
    $stmtSiswaKls->execute([$activeKelas]);
    $siswaKelasList = $stmtSiswaKls->fetchAll();

    // 2. Presensi yang sudah tercatat pada sesi ini hari ini
    $stmtAbsSesi = $pdo->prepare("
        SELECT a.*, s.nama, s.kelas as kelas_siswa 
        FROM absensi a 
        JOIN siswa s ON a.nis = s.nis 
        WHERE a.tanggal = ? AND (a.jadwal_id = ? OR (a.kelas = ? AND a.mapel = ?))
        ORDER BY a.id DESC
    ");
    $stmtAbsSesi->execute([$todayStr, $selected_jadwal_id, $activeKelas, $activeMapel]);
    $absensiSesiList = $stmtAbsSesi->fetchAll();
}

// Peta status absensi siswa per NIS pada sesi ini
$absenMap = [];
foreach ($absensiSesiList as $ab) {
    $absenMap[$ab['nis']] = $ab;
}
?>
<div class="space-y-6">

    <!-- Header Portal Presensi -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <i class="fa-solid fa-graduation-cap mr-1"></i> T.A. <?= htmlspecialchars($tahunAjaran); ?> • Semester <?= htmlspecialchars($semesterAktif); ?>
                </span>
                <span class="text-xs text-slate-400">• <?= date('l, d F Y'); ?></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-clipboard-user text-emerald-400"></i> Presensi Kehadiran Siswa
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">Pilih jadwal mengajar aktif untuk memulai presensi QR Code dan validasi siswa.</p>
        </div>

        <?php if ($activeJadwal): ?>
            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" onclick="startQrScanner()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/30 flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-qrcode text-sm"></i> Scan QR Siswa
                </button>
                <button type="button" onclick="openModal('modal-absensi')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/30 flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-user-plus text-sm"></i> Input Manual
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Alert Notifikasi Flash -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border text-xs font-semibold flex items-center gap-2.5 shadow-lg <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> text-base shrink-0"></i>
            <span><?= htmlspecialchars($msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- Selector Sesi Jadwal Mengajar Guru -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-calendar-check text-indigo-400"></i> Pilih Jadwal / Sesi Kelas
            </h3>
            <span class="text-xs text-slate-400">Guru Pengampu: <strong class="text-white"><?= htmlspecialchars($guru_nama); ?></strong></span>
        </div>

        <?php if (empty($jadwalList)): ?>
            <div class="p-6 rounded-2xl bg-slate-950 border border-slate-800 text-center text-slate-400 text-xs">
                <i class="fa-solid fa-calendar-xmark text-2xl text-slate-600 mb-2 block"></i>
                Belum ada jadwal mengajar yang ditugaskan kepada Anda. Hubungi Administrator Kurikulum.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <?php foreach ($jadwalList as $j): ?>
                    <?php $isActive = ((int)$j['id'] === $selected_jadwal_id); ?>
                    <a href="dashboard.php?page=absensi&jadwal_id=<?= $j['id']; ?>" class="p-4 rounded-2xl border transition-all text-left block <?= $isActive ? 'bg-indigo-600/15 border-indigo-500 text-white shadow-lg shadow-indigo-500/10' : 'bg-slate-950/60 border-slate-800 text-slate-300 hover:border-slate-700 hover:bg-slate-950' ?>">
                        <div class="flex items-center justify-between mb-2">
                            <span class="px-2.5 py-0.5 rounded-lg text-[10px] font-extrabold <?= $isActive ? 'bg-indigo-500 text-white' : 'bg-slate-800 text-indigo-400 border border-slate-700' ?>">
                                <?= htmlspecialchars($j['kelas_nama']); ?>
                            </span>
                            <span class="text-[11px] font-bold font-mono <?= $isActive ? 'text-indigo-300' : 'text-slate-400' ?>">
                                <?= htmlspecialchars($j['hari']); ?>, <?= htmlspecialchars($j['jam_mulai']); ?> - <?= htmlspecialchars($j['jam_selesai']); ?>
                            </span>
                        </div>
                        <div class="font-extrabold text-xs text-white truncate"><?= htmlspecialchars($j['mapel_nama']); ?></div>
                        <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-[10px] text-rose-400"></i> <?= htmlspecialchars($j['ruang']); ?>
                            <span>•</span>
                            <span class="text-emerald-400"><?= $isActive ? '● Sesi Aktif' : 'Buka Sesi' ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($activeJadwal): ?>
        <!-- Active Session Info Card -->
        <div class="p-5 rounded-3xl bg-gradient-to-r from-indigo-950 via-slate-900 to-slate-900 border border-indigo-700/40 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                        SESI AKTIF
                    </span>
                    <span class="text-xs font-bold text-white"><?= htmlspecialchars($activeJadwal['hari']); ?>, <?= htmlspecialchars($activeJadwal['jam_mulai']); ?> - <?= htmlspecialchars($activeJadwal['jam_selesai']); ?> (<?= htmlspecialchars($activeJadwal['ruang']); ?>)</span>
                </div>
                <h2 class="text-lg font-extrabold text-white">
                    Kelas <?= htmlspecialchars($activeJadwal['kelas_nama']); ?> — <?= htmlspecialchars($activeJadwal['mapel_nama']); ?>
                </h2>
                <p class="text-xs text-indigo-200">
                    Siswa terdaftar: <strong><?= count($siswaKelasList); ?></strong> siswa • Sudah presensi: <strong class="text-emerald-400"><?= count($absensiSesiList); ?></strong> siswa
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="startQrScanner()" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/30 flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-qrcode text-base"></i> Scan Kartu Pelajar
                </button>
            </div>
        </div>

        <!-- Tabel Daftar Siswa Kelas Terpilih & Status Presensi -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-users-viewfinder text-indigo-400"></i> Presensi Siswa Kelas <?= htmlspecialchars($activeJadwal['kelas_nama']); ?> Hari Ini
                </h3>
                <span class="text-xs text-slate-400">Tanggal: <strong class="text-white"><?= date('d/m/Y'); ?></strong></span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                        <tr>
                            <th class="p-3.5 rounded-l-xl">No</th>
                            <th class="p-3.5">NIS</th>
                            <th class="p-3.5">Nama Lengkap Siswa</th>
                            <th class="p-3.5">Kelas</th>
                            <th class="p-3.5">Status Presensi</th>
                            <th class="p-3.5 rounded-r-xl">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php if (empty($siswaKelasList)): ?>
                            <tr>
                                <td colspan="6" class="p-8 text-center text-slate-500">
                                    Belum ada data siswa aktif yang terdaftar di kelas <strong><?= htmlspecialchars($activeJadwal['kelas_nama']); ?></strong>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $no = 1; foreach ($siswaKelasList as $sw): ?>
                                <?php 
                                $isRecorded = isset($absenMap[$sw['nis']]); 
                                $absenData = $isRecorded ? $absenMap[$sw['nis']] : null;
                                ?>
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="p-3.5 text-slate-500 font-mono"><?= $no++; ?></td>
                                    <td class="p-3.5 font-mono text-emerald-400 font-bold"><?= htmlspecialchars($sw['nis']); ?></td>
                                    <td class="p-3.5 font-extrabold text-white"><?= htmlspecialchars($sw['nama']); ?></td>
                                    <td class="p-3.5 font-semibold text-slate-300"><?= htmlspecialchars($sw['kelas']); ?></td>
                                    <td class="p-3.5">
                                        <?php if ($isRecorded): ?>
                                            <?php
                                            $st = strtolower($absenData['status']);
                                            if (str_contains($st, 'hadir') || $st === 'h') {
                                                echo '<span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">HADIR</span>';
                                            } elseif (str_contains($st, 'izin') || $st === 'i') {
                                                echo '<span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20">IZIN</span>';
                                            } elseif (str_contains($st, 'sakit') || $st === 's') {
                                                echo '<span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">SAKIT</span>';
                                            } else {
                                                echo '<span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">ALPHA</span>';
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-800 text-slate-400 border border-slate-700">Belum Presensi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3.5 text-slate-400">
                                        <?= $isRecorded ? htmlspecialchars($absenData['keterangan']) : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Form Absensi Manual Terikat Sesi -->
        <div id="modal-absensi" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-blue-400"></i> Presensi Manual Kelas <?= htmlspecialchars($activeJadwal['kelas_nama']); ?>
                    </h3>
                    <button type="button" onclick="closeModal('modal-absensi')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <form action="dashboard.php?page=absensi&jadwal_id=<?= $selected_jadwal_id; ?>" method="POST" class="space-y-4">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="save_absensi">
                    <input type="hidden" name="jadwal_id" value="<?= $selected_jadwal_id; ?>">

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Pilih Siswa Kelas <?= htmlspecialchars($activeJadwal['kelas_nama']); ?></label>
                        <select name="nis" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-blue-500">
                            <?php if (empty($siswaKelasList)): ?>
                                <option value="">Tidak ada siswa di kelas ini</option>
                            <?php else: ?>
                                <?php foreach ($siswaKelasList as $sw): ?>
                                    <option value="<?= $sw['nis']; ?>"><?= htmlspecialchars($sw['nama']); ?> (<?= htmlspecialchars($sw['nis']); ?>)</option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Status Kehadiran</label>
                        <select name="status" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-blue-500">
                            <option value="Hadir">Hadir (Tepat Waktu)</option>
                            <option value="Izin">Izin</option>
                            <option value="Sakit">Sakit</option>
                            <option value="Alpha">Alpha / Tanpa Keterangan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Catatan / Keterangan</label>
                        <input type="text" name="keterangan" value="Presensi Manual Guru" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-blue-500">
                    </div>

                    <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                        <button type="button" onclick="closeModal('modal-absensi')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                        <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition-all">Simpan Presensi</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal QR Code Scanner Interaktif -->
        <div id="modal-qr-scanner" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center text-base">
                            <i class="fa-solid fa-qrcode"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-white">Scanner QR Kelas <?= htmlspecialchars($activeJadwal['kelas_nama']); ?></h3>
                            <p class="text-[11px] text-slate-400">Mapel: <?= htmlspecialchars($activeJadwal['mapel_nama']); ?> (Hanya Siswa <?= htmlspecialchars($activeJadwal['kelas_nama']); ?>)</p>
                        </div>
                    </div>
                    <button type="button" onclick="stopQrScanner()" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <!-- Kamera Box Scanner -->
                <div class="relative bg-slate-950 rounded-2xl overflow-hidden border border-slate-800 min-h-[260px] flex items-center justify-center">
                    <div id="qr-reader" class="w-full"></div>
                    <div id="qr-loading" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-slate-950/90 text-slate-400">
                        <i class="fa-solid fa-camera fa-fade text-3xl text-emerald-400"></i>
                        <span class="text-xs font-semibold">Mengaktifkan kamera pemindai...</span>
                    </div>
                </div>

                <!-- Status Terbaca Realtime -->
                <div id="scan-result-card" class="hidden mt-3 p-3 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-bold flex items-center gap-2 animate-pulse">
                    <i class="fa-solid fa-circle-check text-base text-emerald-400"></i>
                    <span id="scan-result-text">QR Berhasil Terbaca! Memverifikasi data siswa...</span>
                </div>

                <!-- Opsi Unggah File Foto Kartu Pelajar / Barcode Gun -->
                <div class="mt-4 pt-3 border-t border-slate-800 space-y-3">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-slate-300">Pindai dari Gambar / Barcode:</span>
                        <label class="cursor-pointer px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-indigo-300 border border-slate-700 text-[11px] font-bold flex items-center gap-1.5 transition-all">
                            <i class="fa-solid fa-image"></i> Unggah Gambar Kartu
                            <input type="file" id="qr-file-input" accept="image/*" class="hidden" onchange="scanQrFromImage(this)">
                        </label>
                    </div>

                    <!-- Input Fast Scan Barcode Gun -->
                    <form id="form-fast-scan" action="dashboard.php?page=absensi&jadwal_id=<?= $selected_jadwal_id; ?>" method="POST" class="flex gap-2">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="save_absensi">
                        <input type="hidden" name="jadwal_id" value="<?= $selected_jadwal_id; ?>">
                        <input type="hidden" name="status" value="Hadir">
                        <input type="hidden" name="keterangan" value="Presensi via QR Code Kartu Pelajar">
                        <input type="text" id="fast-scan-nis" name="nis" placeholder="NIS otomatis terisi dari scan..." class="flex-1 px-3.5 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-emerald-500 font-mono">
                        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all">
                            Hadir
                        </button>
                    </form>
                </div>

                <div class="mt-4 flex justify-end">
                    <button type="button" onclick="stopQrScanner()" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Tutup Scanner</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Library html5-qrcode untuk camera & file scanning -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrcodeScanner = null;

function playBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioCtx.createOscillator();
        const gainNode = audioCtx.createGain();
        oscillator.type = 'sine';
        oscillator.frequency.value = 880;
        gainNode.gain.setValueAtTime(0.2, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.25);
        oscillator.connect(gainNode);
        gainNode.connect(audioCtx.destination);
        oscillator.start();
        oscillator.stop(audioCtx.currentTime + 0.25);
    } catch(e) {}
}

function startQrScanner() {
    openModal('modal-qr-scanner');
    const loadingEl = document.getElementById('qr-loading');
    const resultCard = document.getElementById('scan-result-card');
    if (loadingEl) loadingEl.style.display = 'flex';
    if (resultCard) resultCard.classList.add('hidden');

    setTimeout(() => {
        if (typeof Html5Qrcode !== 'undefined') {
            if (!html5QrcodeScanner) {
                html5QrcodeScanner = new Html5Qrcode("qr-reader");
            }
            const config = { fps: 15, qrbox: { width: 240, height: 240 } };

            html5QrcodeScanner.start({ facingMode: "environment" }, config, onScanSuccess)
                .then(() => {
                    if (loadingEl) loadingEl.style.display = 'none';
                })
                .catch(err => {
                    if (loadingEl) {
                        loadingEl.innerHTML = `
                            <div class="text-center p-4 space-y-2">
                                <i class="fa-solid fa-camera-slash text-amber-400 text-2xl"></i>
                                <p class="text-xs text-slate-300 font-semibold">Kamera tidak aktif atau izin diblokir browser.</p>
                                <p class="text-[11px] text-slate-500">Gunakan tombol <strong>Unggah Gambar Kartu</strong> atau ketik NIS di bawah.</p>
                            </div>
                        `;
                    }
                    const fastInput = document.getElementById('fast-scan-nis');
                    if (fastInput) fastInput.focus();
                });
        } else {
            if (loadingEl) {
                loadingEl.innerHTML = '<span class="text-xs text-slate-400">Scanner siap. Gunakan input barcode / gambar di bawah.</span>';
            }
            const fastInput = document.getElementById('fast-scan-nis');
            if (fastInput) fastInput.focus();
        }
    }, 300);
}

function extractNisFromQr(decodedText) {
    if (!decodedText) return '';
    let text = String(decodedText).trim();

    // 1. URL parameter format (verifikasi_kartu.php?nis=xxx)
    const urlMatch = text.match(/[?&]nis=([^&#\s]+)/i);
    if (urlMatch) {
        return decodeURIComponent(urlMatch[1]).trim();
    }

    // 2. JSON format
    try {
        const json = JSON.parse(text);
        if (json.nis) return String(json.nis).trim();
    } catch(e) {}

    // 3. Prefix format (NIS: xxx)
    const prefixMatch = text.match(/NIS\s*[:=]\s*([a-zA-Z0-9_-]+)/i);
    if (prefixMatch) {
        return prefixMatch[1].trim();
    }

    return text.replace(/[^a-zA-Z0-9_-]/g, '').trim();
}

function onScanSuccess(decodedText) {
    const nis = extractNisFromQr(decodedText);
    if (!nis) return;

    playBeep();

    const resultCard = document.getElementById('scan-result-card');
    const resultText = document.getElementById('scan-result-text');
    if (resultCard && resultText) {
        resultCard.classList.remove('hidden');
        resultText.innerText = `QR Terdeteksi: NIS ${nis}. Menyimpan presensi...`;
    }

    const fastInput = document.getElementById('fast-scan-nis');
    if (fastInput) {
        fastInput.value = nis;
        setTimeout(() => {
            document.getElementById('form-fast-scan').submit();
        }, 300);
    }
}

function scanQrFromImage(input) {
    if (input.files && input.files[0]) {
        const imageFile = input.files[0];
        if (!html5QrcodeScanner) {
            html5QrcodeScanner = new Html5Qrcode("qr-reader");
        }
        html5QrcodeScanner.scanFile(imageFile, true)
            .then(decodedText => {
                onScanSuccess(decodedText);
            })
            .catch(err => {
                alert('Gagal mendeteksi QR Code dari gambar yang diunggah. Pastikan gambar memuat QR Code yang jelas.');
            });
    }
}

function stopQrScanner() {
    if (html5QrcodeScanner) {
        try {
            html5QrcodeScanner.stop().catch(() => {});
        } catch (e) {}
    }
    closeModal('modal-qr-scanner');
}
</script>
