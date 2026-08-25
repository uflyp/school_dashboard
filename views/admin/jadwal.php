<?php
// views/admin/jadwal.php - Manajemen & Validasi Jadwal Pelajaran Admin
check_role(['admin']);

$msg_success = '';
$msg_error = '';

// Helper function to convert various time formats to minutes for accurate overlap check
// Accepts: HH:MM (13:00), HH.MM (13.00), HHMM (1300), H:MM (8:00), H.MM (8.00)
function time_to_minutes($time_str) {
    $time_str = trim($time_str);

    // Format HH:MM or H:MM  → separator ":"
    if (preg_match('/^(\d{1,2}):(\d{2})$/', $time_str, $m)) {
        return (int)$m[1] * 60 + (int)$m[2];
    }

    // Format HH.MM or H.MM  → separator "."
    if (preg_match('/^(\d{1,2})\.(\d{2})$/', $time_str, $m)) {
        return (int)$m[1] * 60 + (int)$m[2];
    }

    // Format HHMM (4 digits, no separator) → e.g. 1300, 0730
    if (preg_match('/^(\d{2})(\d{2})$/', $time_str, $m)) {
        $h = (int)$m[1];
        $i = (int)$m[2];
        if ($h <= 23 && $i <= 59) {
            return $h * 60 + $i;
        }
    }

    return -1; // Unrecognised format — treated as invalid by caller
}

// Function to check schedule collisions
function check_schedule_collision($pdo, $hari, $jam_mulai, $jam_selesai, $kelas_nama, $guru_nama, $ruang, $exclude_id = 0) {
    $start_new = time_to_minutes($jam_mulai);
    $end_new = time_to_minutes($jam_selesai);

    if ($start_new === -1 || $end_new === -1) {
        return "Format jam tidak valid! Gunakan format HH:MM (contoh: 13:00 atau 07:30).";
    }

    if ($start_new >= $end_new) {
        return "Jam mulai ($jam_mulai) harus lebih awal dari jam selesai ($jam_selesai)!";
    }

    // Fetch all existing schedules for the given day
    if ($exclude_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM jadwal_pelajaran WHERE hari = ? AND id != ?");
        $stmt->execute([$hari, $exclude_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM jadwal_pelajaran WHERE hari = ?");
        $stmt->execute([$hari]);
    }
    $existing = $stmt->fetchAll();

    foreach ($existing as $item) {
        $start_ex = time_to_minutes($item['jam_mulai']);
        $end_ex = time_to_minutes($item['jam_selesai']);

        // Check if time intervals overlap: max(start1, start2) < min(end1, end2)
        if (max($start_new, $start_ex) < min($end_new, $end_ex)) {
            // A. Check Teacher Conflict
            if (!empty($guru_nama) && strcasecmp($item['guru_nama'], $guru_nama) === 0) {
                return "Jadwal bentrok: Guru {$item['guru_nama']} sudah memiliki jadwal pada {$hari} pukul {$item['jam_mulai']} - {$item['jam_selesai']} (Kelas {$item['kelas_nama']}).";
            }
            // B. Check Class Conflict
            if (!empty($kelas_nama) && strcasecmp($item['kelas_nama'], $kelas_nama) === 0) {
                return "Jadwal bentrok: Kelas {$item['kelas_nama']} sudah memiliki jadwal mata pelajaran ({$item['mapel_nama']}) pada {$hari} pukul {$item['jam_mulai']} - {$item['jam_selesai']}.";
            }
            // C. Check Room Conflict
            if (!empty($ruang) && strcasecmp($item['ruang'], $ruang) === 0) {
                return "Jadwal bentrok: Ruangan {$item['ruang']} sudah digunakan pada {$hari} pukul {$item['jam_mulai']} - {$item['jam_selesai']} (Kelas {$item['kelas_nama']}).";
            }
        }
    }

    return null; // No collision
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];

    if ($action === 'add_jadwal') {
        $kelas_nama = trim($_POST['kelas_nama'] ?? '');
        $mapel_nama = trim($_POST['mapel_nama'] ?? '');
        $guru_nama = trim($_POST['guru_nama'] ?? '');
        $hari = trim($_POST['hari'] ?? '');
        $jam_mulai = trim($_POST['jam_mulai'] ?? '');
        $jam_selesai = trim($_POST['jam_selesai'] ?? '');
        $ruang = trim($_POST['ruang'] ?? 'R. Kelas');

        // Validation
        if (empty($kelas_nama) || empty($mapel_nama) || empty($guru_nama) || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
            $msg_error = "Harap isi semua kolom wajib pada form jadwal!";
        } else {
            // Collision Check
            $collision_error = check_schedule_collision($pdo, $hari, $jam_mulai, $jam_selesai, $kelas_nama, $guru_nama, $ruang);
            if ($collision_error) {
                $msg_error = $collision_error;
            } else {
                // Fetch teacher ID
                $stmtG = $pdo->prepare("SELECT id FROM guru WHERE nama = ? LIMIT 1");
                $stmtG->execute([$guru_nama]);
                $guru_id = $stmtG->fetchColumn() ?: 0;

                $stmt = $pdo->prepare("INSERT INTO jadwal_pelajaran (hari, jam_mulai, jam_selesai, kelas_nama, mapel_nama, guru_nama, guru_id, ruang) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$hari, $jam_mulai, $jam_selesai, $kelas_nama, $mapel_nama, $guru_nama, $guru_id, $ruang]);
                $msg_success = "Jadwal pelajaran baru berhasil ditambahkan tanpa bentrok!";
                log_activity("Admin added schedule: $kelas_nama - $mapel_nama ($guru_nama)");
            }
        }
    } elseif ($action === 'edit_jadwal') {
        $id = intval($_POST['id'] ?? 0);
        $kelas_nama = trim($_POST['kelas_nama'] ?? '');
        $mapel_nama = trim($_POST['mapel_nama'] ?? '');
        $guru_nama = trim($_POST['guru_nama'] ?? '');
        $hari = trim($_POST['hari'] ?? '');
        $jam_mulai = trim($_POST['jam_mulai'] ?? '');
        $jam_selesai = trim($_POST['jam_selesai'] ?? '');
        $ruang = trim($_POST['ruang'] ?? 'R. Kelas');

        if ($id <= 0 || empty($kelas_nama) || empty($mapel_nama) || empty($guru_nama) || empty($hari) || empty($jam_mulai) || empty($jam_selesai)) {
            $msg_error = "Harap lengkapi semua kolom saat memperbarui jadwal!";
        } else {
            // Collision Check excluding current record
            $collision_error = check_schedule_collision($pdo, $hari, $jam_mulai, $jam_selesai, $kelas_nama, $guru_nama, $ruang, $id);
            if ($collision_error) {
                $msg_error = $collision_error;
            } else {
                $stmtG = $pdo->prepare("SELECT id FROM guru WHERE nama = ? LIMIT 1");
                $stmtG->execute([$guru_nama]);
                $guru_id = $stmtG->fetchColumn() ?: 0;

                $stmt = $pdo->prepare("UPDATE jadwal_pelajaran SET hari = ?, jam_mulai = ?, jam_selesai = ?, kelas_nama = ?, mapel_nama = ?, guru_nama = ?, guru_id = ?, ruang = ? WHERE id = ?");
                $stmt->execute([$hari, $jam_mulai, $jam_selesai, $kelas_nama, $mapel_nama, $guru_nama, $guru_id, $ruang, $id]);
                $msg_success = "Jadwal pelajaran berhasil diperbarui!";
                log_activity("Admin updated schedule ID: $id");
            }
        }
    } elseif ($action === 'delete_jadwal') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM jadwal_pelajaran WHERE id = ?");
            $stmt->execute([$id]);
            $msg_success = "Jadwal pelajaran berhasil dihapus!";
            log_activity("Admin deleted schedule ID: $id");
        }
    }
}

// Fetch Helper Dropdown Data from Database
$guruOptions = $pdo->query("SELECT * FROM guru ORDER BY nama ASC")->fetchAll();
$kelasOptions = $pdo->query("
    SELECT * FROM kelas 
    ORDER BY 
        CASE 
            WHEN nama_kelas LIKE 'X %' AND nama_kelas NOT LIKE 'XI%' AND nama_kelas NOT LIKE 'XII%' THEN 1
            WHEN nama_kelas LIKE 'XI %' AND nama_kelas NOT LIKE 'XII%' THEN 2
            WHEN nama_kelas LIKE 'XII %' THEN 3
            ELSE 4
        END,
        CASE 
            WHEN nama_kelas LIKE '% IPA%' THEN 1
            WHEN nama_kelas LIKE '% IPS%' THEN 2
            WHEN nama_kelas LIKE '% Bahasa%' THEN 3
            ELSE 4
        END,
        nama_kelas ASC
")->fetchAll();
$mapelOptions = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC")->fetchAll();

// Ruang Kelas Dropdown Options
$ruangOptions = [
    'Kelas X' => [
        'X IPA 1', 'X IPA 2', 'X IPA 3',
        'X IPS 1', 'X IPS 2', 'X IPS 3',
        'X Bahasa 1', 'X Bahasa 2',
    ],
    'Kelas XI' => [
        'XI IPA 1', 'XI IPA 2', 'XI IPA 3',
        'XI IPS 1', 'XI IPS 2', 'XI IPS 3',
        'XI Bahasa 1', 'XI Bahasa 2',
    ],
    'Kelas XII' => [
        'XII IPA 1', 'XII IPA 2', 'XII IPA 3',
        'XII IPS 1', 'XII IPS 2', 'XII IPS 3',
        'XII Bahasa 1', 'XII Bahasa 2',
    ],
    'Laboratorium' => [
        'Lab Fisika', 'Lab Kimia', 'Lab Biologi',
        'Lab Komputer', 'Lab Bahasa',
    ],
    'Ruang Khusus' => [
        'Perpustakaan', 'Aula', 'Lapangan Olahraga',
        'Studio Seni', 'Ruang Multimedia',
    ],
];

// Fetch Full Schedule List
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
?>
<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-calendar-days text-indigo-400"></i> Kelola Jadwal Pelajaran Sekolah
            </h1>
            <p class="text-xs text-slate-400 mt-1">Atur alokasi jam mengajar guru, kelas, mata pelajaran, serta verifikasi anti-bentrok ruangan & waktu</p>
        </div>
        <button type="button" onclick="openModal('modal-add-jadwal')" class="px-4.5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all shrink-0">
            <i class="fa-solid fa-plus"></i> Tambah Jadwal Baru
        </button>
    </div>

    <!-- Notification Messages -->
    <?php if ($msg_success): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-semibold flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-400 text-base shrink-0"></i>
            <span><?= htmlspecialchars($msg_success); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($msg_error): ?>
        <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-semibold flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-rose-400 text-base shrink-0"></i>
            <span><?= htmlspecialchars($msg_error); ?></span>
        </div>
    <?php endif; ?>

    <!-- Filter & Search Toolbar -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Filter Kelas -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-300">Filter Kelas</label>
                <select id="filter-kelas" onchange="applyScheduleFilters()" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                    <option value="">[ Semua Kelas ]</option>
                    <?php foreach ($kelasOptions as $k): ?>
                        <option value="<?= htmlspecialchars($k['nama_kelas']); ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Filter Guru -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-300">Filter Guru Pengajar</label>
                <select id="filter-guru" onchange="applyScheduleFilters()" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                    <option value="">[ Semua Guru ]</option>
                    <?php foreach ($guruOptions as $gr): ?>
                        <option value="<?= htmlspecialchars($gr['nama']); ?>"><?= htmlspecialchars($gr['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Filter Hari -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-300">Filter Hari</label>
                <select id="filter-hari" onchange="applyScheduleFilters()" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                    <option value="">[ Semua Hari ]</option>
                    <option value="Senin">Senin</option>
                    <option value="Selasa">Selasa</option>
                    <option value="Rabu">Rabu</option>
                    <option value="Kamis">Kamis</option>
                    <option value="Jumat">Jumat</option>
                    <option value="Sabtu">Sabtu</option>
                </select>
            </div>
            <!-- Search Input -->
            <div class="space-y-1">
                <label class="block text-xs font-bold text-slate-300">Cari Mapel / Ruangan</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass text-slate-500 text-xs pointer-events-none" style="position:absolute; left:12px; top:50%; transform:translateY(-50%);"></i>
                    <input type="text" id="search-jadwal" onkeyup="applyScheduleFilters()" placeholder="Ketik kata kunci..." class="w-full pr-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none" style="padding-left: 2.25rem;">
                </div>
            </div>
        </div>
    </div>

    <!-- Table Jadwal (Requirement 9) -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table id="table-jadwal" class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl text-center w-12">No</th>
                        <th class="p-3.5">Hari</th>
                        <th class="p-3.5">Jam / Waktu</th>
                        <th class="p-3.5">Kelas</th>
                        <th class="p-3.5">Mata Pelajaran</th>
                        <th class="p-3.5">Guru Pengajar</th>
                        <th class="p-3.5">Ruangan</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium" id="jadwal-tbody">
                    <?php if (empty($jadwalList)): ?>
                        <tr id="no-data-row">
                            <td colspan="8" class="p-8 text-center text-slate-500 font-semibold">Belum ada data jadwal pelajaran terdaftar di database.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($jadwalList as $j): ?>
                            <tr class="jadwal-row hover:bg-slate-800/40 transition-colors"
                                data-kelas="<?= htmlspecialchars($j['kelas_nama']); ?>"
                                data-guru="<?= htmlspecialchars($j['guru_nama']); ?>"
                                data-hari="<?= htmlspecialchars($j['hari']); ?>">
                                <td class="p-3.5 text-center font-mono text-slate-500"><?= $no++; ?></td>
                                <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($j['hari']); ?></td>
                                <td class="p-3.5 font-mono text-indigo-400 font-bold"><?= htmlspecialchars($j['jam_mulai']); ?> - <?= htmlspecialchars($j['jam_selesai']); ?></td>
                                <td class="p-3.5"><span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 font-extrabold text-[11px]"><?= htmlspecialchars($j['kelas_nama']); ?></span></td>
                                <td class="p-3.5 text-white font-semibold"><?= htmlspecialchars($j['mapel_nama']); ?></td>
                                <td class="p-3.5 text-emerald-400 font-bold"><?= htmlspecialchars($j['guru_nama']); ?></td>
                                <td class="p-3.5 text-slate-400"><i class="fa-solid fa-location-dot text-rose-400 mr-1.5"></i><?= htmlspecialchars($j['ruang']); ?></td>
                                <td class="p-3.5 text-center flex items-center justify-center gap-1.5">
                                    <button type="button" onclick='openEditJadwal(<?= json_encode($j, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>)' class="px-2.5 py-1.5 rounded-lg bg-indigo-600/20 hover:bg-indigo-600 text-indigo-400 hover:text-white font-bold text-[11px] border border-indigo-500/30 transition-all flex items-center gap-1" title="Edit Jadwal">
                                        <i class="fa-solid fa-pen-to-square text-xs"></i> Edit
                                    </button>
                                    <form action="dashboard.php?page=jadwal" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus jadwal <?= htmlspecialchars($j['mapel_nama'], ENT_QUOTES, 'UTF-8'); ?> di kelas <?= htmlspecialchars($j['kelas_nama'], ENT_QUOTES, 'UTF-8'); ?>?');" class="inline">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_jadwal">
                                        <input type="hidden" name="id" value="<?= $j['id']; ?>">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white font-bold text-[11px] border border-rose-500/20 transition-all flex items-center gap-1" title="Hapus Jadwal">
                                            <i class="fa-solid fa-trash text-xs"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Jadwal Baru (Relasi Dropdown & Order: Kelas -> Mapel -> Guru -> Hari -> Jam -> Ruang) -->
    <div id="modal-add-jadwal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-lg font-extrabold text-white">Tambah Jadwal Mengajar</h3>
                    <p class="text-xs text-slate-400">Sistem akan memverifikasi bentrok jam guru, kelas & ruangan</p>
                </div>
                <button type="button" onclick="closeModal('modal-add-jadwal')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=jadwal" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_jadwal">

                <!-- 1. Kelas -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">1. Pilih Kelas Target <span class="text-rose-400">*</span></label>
                    <select name="kelas_nama" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($kelasOptions as $k): ?>
                            <option value="<?= htmlspecialchars($k['nama_kelas']); ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 2. Mata Pelajaran -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">2. Pilih Mata Pelajaran <span class="text-rose-400">*</span></label>
                    <select name="mapel_nama" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">-- Pilih Mata Pelajaran --</option>
                        <?php foreach ($mapelOptions as $mp): ?>
                            <option value="<?= htmlspecialchars($mp['nama_mapel']); ?>"><?= htmlspecialchars($mp['nama_mapel']); ?> (<?= htmlspecialchars($mp['kode']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Guru Pengajar (Database Select) -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">3. Guru Pengajar <span class="text-rose-400">*</span></label>
                    <select name="guru_nama" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach ($guruOptions as $gr): ?>
                            <option value="<?= htmlspecialchars($gr['nama']); ?>"><?= htmlspecialchars($gr['nama']); ?> &mdash; <?= htmlspecialchars($gr['mata_pelajaran']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 4. Hari & Jam -->
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">4. Hari <span class="text-rose-400">*</span></label>
                        <select name="hari" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Senin">Senin</option>
                            <option value="Selasa">Selasa</option>
                            <option value="Rabu">Rabu</option>
                            <option value="Kamis">Kamis</option>
                            <option value="Jumat">Jumat</option>
                            <option value="Sabtu">Sabtu</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jam Mulai <span class="text-rose-400">*</span></label>
                        <input type="text" name="jam_mulai" required placeholder="07:30" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jam Selesai <span class="text-rose-400">*</span></label>
                        <input type="text" name="jam_selesai" required placeholder="09:00" class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <!-- 5. Ruang Kelas -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">5. Ruang Kelas <span class="text-rose-400">*</span></label>
                    <select name="ruang" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">-- Pilih Ruang --</option>
                        <?php foreach ($ruangOptions as $group => $rooms): ?>
                            <optgroup label="<?= htmlspecialchars($group); ?>">
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= htmlspecialchars($room); ?>"><?= htmlspecialchars($room); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-jadwal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-xs"></i> Simpan Jadwal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Jadwal -->
    <div id="modal-edit-jadwal" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-lg font-extrabold text-white">Edit Jadwal Mengajar</h3>
                    <p class="text-xs text-slate-400">Perbarui penugasan jam & verifikasi ulang jadwal bentrok</p>
                </div>
                <button type="button" onclick="closeModal('modal-edit-jadwal')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=jadwal" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_jadwal">
                <input type="hidden" name="id" id="edit_jadwal_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">1. Kelas Target <span class="text-rose-400">*</span></label>
                    <select name="kelas_nama" id="edit_jadwal_kelas" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($kelasOptions as $k): ?>
                            <option value="<?= htmlspecialchars($k['nama_kelas']); ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">2. Mata Pelajaran <span class="text-rose-400">*</span></label>
                    <select name="mapel_nama" id="edit_jadwal_mapel" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <?php foreach ($mapelOptions as $mp): ?>
                            <option value="<?= htmlspecialchars($mp['nama_mapel']); ?>"><?= htmlspecialchars($mp['nama_mapel']); ?> (<?= htmlspecialchars($mp['kode']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">3. Guru Pengajar <span class="text-rose-400">*</span></label>
                    <select name="guru_nama" id="edit_jadwal_guru" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach ($guruOptions as $gr): ?>
                            <option value="<?= htmlspecialchars($gr['nama']); ?>"><?= htmlspecialchars($gr['nama']); ?> &mdash; <?= htmlspecialchars($gr['mata_pelajaran']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">4. Hari <span class="text-rose-400">*</span></label>
                        <select name="hari" id="edit_jadwal_hari" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                            <option value="Senin">Senin</option>
                            <option value="Selasa">Selasa</option>
                            <option value="Rabu">Rabu</option>
                            <option value="Kamis">Kamis</option>
                            <option value="Jumat">Jumat</option>
                            <option value="Sabtu">Sabtu</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jam Mulai <span class="text-rose-400">*</span></label>
                        <input type="text" name="jam_mulai" id="edit_jadwal_jam_mulai" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jam Selesai <span class="text-rose-400">*</span></label>
                        <input type="text" name="jam_selesai" id="edit_jadwal_jam_selesai" required class="w-full px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">5. Ruang Kelas <span class="text-rose-400">*</span></label>
                    <select name="ruang" id="edit_jadwal_ruang" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">-- Pilih Ruang --</option>
                        <?php foreach ($ruangOptions as $group => $rooms): ?>
                            <optgroup label="<?= htmlspecialchars($group); ?>">
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= htmlspecialchars($room); ?>"><?= htmlspecialchars($room); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-jadwal')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-check text-xs"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JS Filtering & Modal Modal Helper Scripts -->
<script>
    function openEditJadwal(data) {
        document.getElementById('edit_jadwal_id').value = data.id || '';
        document.getElementById('edit_jadwal_kelas').value = data.kelas_nama || '';
        document.getElementById('edit_jadwal_mapel').value = data.mapel_nama || '';
        document.getElementById('edit_jadwal_guru').value = data.guru_nama || '';
        document.getElementById('edit_jadwal_hari').value = data.hari || 'Senin';
        document.getElementById('edit_jadwal_jam_mulai').value = data.jam_mulai || '';
        document.getElementById('edit_jadwal_jam_selesai').value = data.jam_selesai || '';
        document.getElementById('edit_jadwal_ruang').value = data.ruang || '';
        openModal('modal-edit-jadwal');
    }

    function applyScheduleFilters() {
        const filterKelas = document.getElementById('filter-kelas').value.toLowerCase();
        const filterGuru = document.getElementById('filter-guru').value.toLowerCase();
        const filterHari = document.getElementById('filter-hari').value.toLowerCase();
        const searchQuery = document.getElementById('search-jadwal').value.toLowerCase();

        const rows = document.querySelectorAll('.jadwal-row');
        rows.forEach(row => {
            const kelas = (row.getAttribute('data-kelas') || '').toLowerCase();
            const guru = (row.getAttribute('data-guru') || '').toLowerCase();
            const hari = (row.getAttribute('data-hari') || '').toLowerCase();
            const rowText = row.innerText.toLowerCase();

            const matchKelas = !filterKelas || kelas === filterKelas;
            const matchGuru = !filterGuru || guru === filterGuru;
            const matchHari = !filterHari || hari === filterHari;
            const matchSearch = !searchQuery || rowText.includes(searchQuery);

            if (matchKelas && matchGuru && matchHari && matchSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>
