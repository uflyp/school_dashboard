<?php
// views/admin/kalender_akademik.php - Kalender Akademik Modern Full-Width (Google Calendar Style)
check_role(['admin', 'orangtua', 'guru', 'siswa', 'kepala_sekolah']);
$currentRole = $_SESSION['role'] ?? ($role ?? 'orangtua');
$isAdmin = ($currentRole === 'admin');

$msg = '';
$msgType = 'success';

// Handle POST CRUD untuk Admin (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$isAdmin) {
        $_SESSION['flash_error'] = "Akses ditolak: Hanya Administrator yang berwenang mengelola Kalender Akademik!";
        if (!headers_sent()) {
            header("Location: dashboard.php?page=kalender_akademik");
            exit();
        }
        return;
    }

    verify_csrf_token();
    $action = $_POST['action'];

    if ($action === 'add_event') {
        $title = trim($_POST['title'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (!empty($title) && !empty($event_date)) {
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$title, $description, $event_date]);
            $msg = "Agenda akademik baru berhasil ditambahkan ke kalender!";
            log_activity("Admin added academic event: $title ($event_date)");
        } else {
            $msg = "Judul agenda dan tanggal wajib diisi!";
            $msgType = 'error';
        }
    } elseif ($action === 'edit_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $event_date = trim($_POST['event_date'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($event_id > 0 && !empty($title) && !empty($event_date)) {
            $stmt = $pdo->prepare("UPDATE events SET title = ?, description = ?, event_date = ? WHERE id = ?");
            $stmt->execute([$title, $description, $event_date, $event_id]);
            $msg = "Agenda akademik berhasil diperbarui!";
            log_activity("Admin updated academic event ID: $event_id");
        } else {
            $msg = "Data agenda tidak valid!";
            $msgType = 'error';
        }
    } elseif ($action === 'delete_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $stmt->execute([$event_id]);
            $msg = "Agenda akademik berhasil dihapus dari kalender!";
            log_activity("Admin deleted academic event ID: $event_id");
        }
    }
}

// 1. Data Agenda Bawaan Master
$defaultEvents = [
    "2026-07-13" => ["id" => "def-1", "title" => "Hari Pertama Masuk Sekolah T.A. 2026/2027", "type" => "sekolah", "description" => "Awal tahun ajaran baru dan masa orientasi peserta didik baru (MPLS)."],
    "2026-08-17" => ["id" => "def-2", "title" => "Libur Nasional Proklamasi Kemerdekaan RI", "type" => "libur", "description" => "Upacara bendera peringatan HUT Kemerdekaan Republik Indonesia ke-81."],
    "2026-09-14" => ["id" => "def-3", "title" => "Penilaian Tengah Semester (PTS) Ganjil", "type" => "ujian", "description" => "Pelaksanaan asesmen tengah semester ganjil untuk seluruh jenjang kelas."],
    "2026-10-28" => ["id" => "def-4", "title" => "Peringatan Hari Sumpah Pemuda", "type" => "event", "description" => "Kegiatan pentas seni dan upacara peringatan Hari Sumpah Pemuda."],
    "2026-11-25" => ["id" => "def-5", "title" => "Peringatan Hari Guru Nasional", "type" => "rapat", "description" => "Apresiasi tenaga pendidik dan upacara Hari Guru Nasional."],
    "2026-12-07" => ["id" => "def-6", "title" => "Penilaian Akhir Semester (PAS) Ganjil", "type" => "ujian", "description" => "Asesmen akhir semester ganjil tahun ajaran 2026/2027."],
    "2026-12-18" => ["id" => "def-7", "title" => "Pembagian Rapor Semester Ganjil", "type" => "sekolah", "description" => "Penyerahan laporan hasil belajar siswa semester ganjil kepada orang tua."],
    "2026-12-21" => ["id" => "def-8", "title" => "Libur Semester Ganjil", "type" => "libur", "description" => "Libur pembelajaran akhir semester ganjil bagi seluruh siswa."],
    "2026-12-25" => ["id" => "def-9", "title" => "Libur Hari Raya Natal", "type" => "libur", "description" => "Hari libur nasional perayaan Natal."]
];

// 2. Query Data dari Database (Tabel events)
$dbEvents = $pdo->query("SELECT * FROM events WHERE is_active = 1 OR is_active IS NULL ORDER BY event_date ASC")->fetchAll();
$allEvents = [];

// Muat default events
foreach ($defaultEvents as $dt => $ev) {
    $allEvents[$dt][] = $ev;
}

// Gabungkan dengan events DB
foreach ($dbEvents as $ev) {
    $d = $ev['event_date'];
    $tLower = strtolower($ev['title']);
    $evType = 'event';
    if (str_contains($tLower, 'libur') || str_contains($tLower, 'cuti')) $evType = 'libur';
    elseif (str_contains($tLower, 'ujian') || str_contains($tLower, 'pts') || str_contains($tLower, 'pas') || str_contains($tLower, 'asesmen')) $evType = 'ujian';
    elseif (str_contains($tLower, 'sekolah') || str_contains($tLower, 'masuk') || str_contains($tLower, 'rapor') || str_contains($tLower, 'kbm')) $evType = 'sekolah';
    elseif (str_contains($tLower, 'rapat') || str_contains($tLower, 'guru') || str_contains($tLower, 'dinas')) $evType = 'rapat';

    $allEvents[$d][] = [
        "id" => $ev['id'],
        "title" => $ev['title'],
        "type" => $evType,
        "description" => $ev['description'] ?? '',
        "is_db" => true,
        "event_date" => $d
    ];
}

// 3. Logika Navigasi Kalender (Bulan, Tahun, Mode Tampilan)
$today = date('Y-m-d');
$currentMonthNow = (int)date('n');
$currentYearNow = (int)date('Y');

$month = isset($_GET['m']) ? (int)$_GET['m'] : $currentMonthNow;
$year  = isset($_GET['y']) ? (int)$_GET['y'] : $currentYearNow;
$viewMode = $_GET['view'] ?? 'month'; // 'month' atau 'agenda'

if ($month < 1) { $month = 12; $year--; }
elseif ($month > 12) { $month = 1; $year++; }

$firstDayOfMonthTs = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = (int)date('t', $firstDayOfMonthTs);

// 1 = Senin, 7 = Minggu (Standar ISO)
$firstDayWeekday = (int)date('N', $firstDayOfMonthTs); 

$monthNamesId = [
    1 => "Januari", 2 => "Februari", 3 => "Maret", 4 => "April", 5 => "Mei", 6 => "Juni", 
    7 => "Juli", 8 => "Agustus", 9 => "September", 10 => "Oktober", 11 => "November", 12 => "Desember"
];

$prevM = $month - 1; $prevY = $year;
if ($prevM < 1) { $prevM = 12; $prevY--; }

$nextM = $month + 1; $nextY = $year;
if ($nextM > 12) { $nextM = 1; $nextY++; }
?>
<div class="w-full space-y-6">

    <!-- Top Banner Info & Context -->
    <div class="w-full flex flex-col md:flex-row items-start md:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-5 sm:p-6 rounded-3xl shadow-xl">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                    <i class="fa-solid fa-graduation-cap mr-1"></i> T.A. <?= htmlspecialchars(get_setting('academic_year', '2026/2027')); ?> • Semester <?= htmlspecialchars(get_setting('active_semester', 'Ganjil')); ?>
                </span>
                <span class="text-xs text-slate-400">• <?= date('l, d F Y'); ?></span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-calendar-days text-indigo-400"></i> Kalender Akademik Sekolah
            </h1>
            <p class="text-xs text-slate-400">
                <?= $isAdmin ? 'Kelola agenda kegiatan sekolah, ujian semester, dan jadwal pembelajaran terpadu.' : 'Informasi resmi jadwal pembelajaran, evaluasi semester, dan hari libur sekolah.' ?>
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <?php if ($isAdmin): ?>
                <button type="button" onclick="openAddEventModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-plus text-sm"></i> Tambah Agenda
                </button>
            <?php endif; ?>
            <button onclick="window.print()" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-print"></i> Cetak
            </button>
        </div>
    </div>

    <!-- Alert Notifikasi Flash -->
    <?php if ($msg): ?>
        <div class="w-full p-4 rounded-2xl border text-xs font-semibold flex items-center gap-2.5 shadow-lg <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> text-base shrink-0"></i>
            <span><?= htmlspecialchars($msg); ?></span>
        </div>
    <?php endif; ?>

    <!-- Full-Width Calendar Wrapper (Google Calendar Style) -->
    <div class="w-full bg-slate-900 border border-slate-800 rounded-3xl p-4 sm:p-6 shadow-xl space-y-6">

        <!-- Unified Horizontal Calendar Toolbar -->
        <div class="w-full flex flex-col md:flex-row items-center justify-between gap-4 pb-4 border-b border-slate-800">
            
            <!-- Left: Navigation Arrows + Today Button + Month/Year Title -->
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto justify-between md:justify-start">
                <div class="flex items-center gap-1.5">
                    <a href="dashboard.php?page=kalender_akademik&m=<?= $prevM; ?>&y=<?= $prevY; ?>&view=<?= $viewMode; ?>" title="Bulan Sebelumnya" class="w-9 h-9 rounded-xl bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-300 hover:text-white flex items-center justify-center text-xs transition-colors">
                        <i class="fa-solid fa-chevron-left"></i>
                    </a>
                    <a href="dashboard.php?page=kalender_akademik&m=<?= $nextM; ?>&y=<?= $nextY; ?>&view=<?= $viewMode; ?>" title="Bulan Berikutnya" class="w-9 h-9 rounded-xl bg-slate-950 hover:bg-slate-800 border border-slate-800 text-slate-300 hover:text-white flex items-center justify-center text-xs transition-colors">
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <a href="dashboard.php?page=kalender_akademik&m=<?= $currentMonthNow; ?>&y=<?= $currentYearNow; ?>&view=<?= $viewMode; ?>" class="px-4 py-2 rounded-xl bg-slate-950 hover:bg-slate-800 border border-slate-800 text-xs font-bold text-slate-300 hover:text-white transition-colors flex items-center gap-1.5">
                    <i class="fa-regular fa-calendar-check text-indigo-400"></i> Hari Ini
                </a>

                <div class="flex items-center gap-2 pl-1 sm:pl-2">
                    <h2 class="text-lg sm:text-xl font-extrabold text-white tracking-tight font-sans">
                        <?= $monthNamesId[$month]; ?> <?= $year; ?>
                    </h2>
                </div>
            </div>

            <!-- Right: View Switcher (Bulan / Agenda) & Category Filters -->
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto justify-between md:justify-end">
                
                <!-- Quick Category Legend Badges -->
                <div class="hidden xl:flex items-center gap-2 text-[11px] font-medium text-slate-400 mr-2">
                    <span class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-950 border border-slate-800"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> KBM</span>
                    <span class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-950 border border-slate-800"><span class="w-2 h-2 rounded-full bg-amber-400"></span> Ujian</span>
                    <span class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-950 border border-slate-800"><span class="w-2 h-2 rounded-full bg-rose-400"></span> Libur</span>
                    <span class="flex items-center gap-1.5 px-2 py-1 rounded-lg bg-slate-950 border border-slate-800"><span class="w-2 h-2 rounded-full bg-indigo-400"></span> Event</span>
                </div>

                <!-- View Toggle Buttons -->
                <div class="flex items-center p-1 rounded-2xl bg-slate-950 border border-slate-800 shrink-0">
                    <a href="dashboard.php?page=kalender_akademik&m=<?= $month; ?>&y=<?= $year; ?>&view=month" class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $viewMode === 'month' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-200' ?>">
                        <i class="fa-solid fa-table-cells"></i> Bulan
                    </a>
                    <a href="dashboard.php?page=kalender_akademik&m=<?= $month; ?>&y=<?= $year; ?>&view=agenda" class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 <?= $viewMode === 'agenda' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' : 'text-slate-400 hover:text-slate-200' ?>">
                        <i class="fa-solid fa-list-ul"></i> Agenda
                    </a>
                </div>
            </div>

        </div>

        <!-- Mode 1: Month Full-Width 7-Column Grid -->
        <?php if ($viewMode === 'month'): ?>
            <div class="w-full overflow-hidden space-y-2">
                
                <!-- 7 Weekday Headers (Senin s/d Minggu) -->
                <div class="w-full text-center text-xs font-extrabold text-slate-400 pb-2 border-b border-slate-800/80" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px;">
                    <div class="py-1">Senin</div>
                    <div class="py-1">Selasa</div>
                    <div class="py-1">Rabu</div>
                    <div class="py-1">Kamis</div>
                    <div class="py-1">Jumat</div>
                    <div class="py-1">Sabtu</div>
                    <div class="py-1 text-rose-400">Minggu</div>
                </div>

                <!-- 7-Column Month Grid Cells (100% Width Fit & Larger Comfortable Box Size) -->
                <div class="w-full pt-1.5" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px;">
                    <?php
                    // 1. Tanggal Faded Bulan Sebelumnya
                    $prevMonthDays = (int)date('t', mktime(0, 0, 0, $prevM, 1, $prevY));
                    $startDayPrev = $prevMonthDays - ($firstDayWeekday - 2);
                    for ($k = 1; $k < $firstDayWeekday; $k++) {
                        $fDayNum = $startDayPrev + ($k - 1);
                        echo '<div class="min-h-[130px] sm:min-h-[155px] p-3 rounded-2xl bg-slate-950/20 border border-slate-800/20 opacity-30 select-none flex flex-col justify-between">';
                        echo '<span class="text-xs font-mono text-slate-600 font-bold">' . $fDayNum . '</span>';
                        echo '<div></div>';
                        echo '</div>';
                    }

                    // 2. Tanggal Bulan Aktif
                    for ($day = 1; $day <= $daysInMonth; $day++) {
                        $dateKey = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $isToday = ($dateKey === $today);
                        $dayOfWeekIso = (int)date('N', strtotime($dateKey));
                        $isSunday = ($dayOfWeekIso === 7);

                        $dayEvents = $allEvents[$dateKey] ?? [];
                        $eventCount = count($dayEvents);

                        $cellCls = 'bg-slate-950 border-slate-800/80 hover:border-slate-700';
                        if ($isToday) {
                            $cellCls = 'bg-indigo-950/30 border-indigo-500/60 shadow-lg shadow-indigo-500/10 ring-1 ring-indigo-500/30';
                        }

                        echo '<div class="min-h-[130px] sm:min-h-[155px] p-2.5 sm:p-3 rounded-2xl border transition-all flex flex-col justify-between ' . $cellCls . '">';
                        
                        // Header Tanggal di pojok
                        echo '<div class="flex items-center justify-between mb-1.5">';
                        echo '<span class="text-xs sm:text-sm font-mono font-bold ' . ($isToday ? 'w-7 h-7 rounded-full bg-indigo-600 text-white flex items-center justify-center font-extrabold shadow-md shadow-indigo-600/40' : ($isSunday ? 'text-rose-400 font-extrabold' : 'text-slate-200')) . '">' . $day . '</span>';
                        if ($isToday) {
                            echo '<span class="text-[9px] font-extrabold text-indigo-400 uppercase tracking-widest hidden md:inline">Hari Ini</span>';
                        }
                        echo '</div>';

                        // Daftar Chip Event
                        echo '<div class="space-y-1.5 overflow-hidden flex-1 flex flex-col justify-start">';
                        if (!empty($dayEvents)) {
                            $displayLimit = 3;
                            $shown = 0;
                            foreach ($dayEvents as $ev) {
                                if ($shown < $displayLimit) {
                                    $evType = $ev['type'] ?? 'event';
                                    $chipCls = 'bg-indigo-500/15 text-indigo-300 border-indigo-500/30 hover:bg-indigo-500/25';
                                    if ($evType === 'libur') $chipCls = 'bg-rose-500/15 text-rose-300 border-rose-500/30 hover:bg-rose-500/25';
                                    elseif ($evType === 'ujian') $chipCls = 'bg-amber-500/15 text-amber-300 border-amber-500/30 hover:bg-amber-500/25';
                                    elseif ($evType === 'sekolah') $chipCls = 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30 hover:bg-emerald-500/25';
                                    elseif ($evType === 'rapat') $chipCls = 'bg-orange-500/15 text-orange-300 border-orange-500/30 hover:bg-orange-500/25';

                                    $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES);
                                    $safeDesc = htmlspecialchars($ev['description'] ?? '', ENT_QUOTES);
                                    $safeId = htmlspecialchars($ev['id'] ?? '0', ENT_QUOTES);
                                    $isDb = !empty($ev['is_db']) ? '1' : '0';

                                    echo '<button type="button" onclick="showEventDetail(\'' . $safeId . '\', \'' . $safeTitle . '\', \'' . $dateKey . '\', \'' . $evType . '\', \'' . $safeDesc . '\', ' . $isDb . ')" class="w-full text-left p-1.5 sm:p-2 rounded-xl border text-[10px] sm:text-[11px] font-bold ' . $chipCls . ' truncate block transition-all shadow-sm" title="' . $safeTitle . '">';
                                    echo '<span class="truncate block leading-tight">' . $safeTitle . '</span>';
                                    echo '</button>';
                                    $shown++;
                                }
                            }

                            if ($eventCount > $displayLimit) {
                                $extra = $eventCount - $displayLimit;
                                echo '<a href="dashboard.php?page=kalender_akademik&m=' . $month . '&y=' . $year . '&view=agenda#' . $dateKey . '" class="text-[10px] font-extrabold text-indigo-400 hover:text-indigo-300 block text-center mt-0.5">+ ' . $extra . ' agenda lainnya</a>';
                            }
                        }
                        echo '</div>';

                        echo '</div>';
                    }

                    // 3. Tanggal Faded Bulan Berikutnya
                    $totalCellsRendered = ($firstDayWeekday - 1) + $daysInMonth;
                    $remainingCells = (ceil($totalCellsRendered / 7) * 7) - $totalCellsRendered;
                    for ($j = 1; $j <= $remainingCells; $j++) {
                        echo '<div class="min-h-[130px] sm:min-h-[155px] p-3 rounded-2xl bg-slate-950/20 border border-slate-800/20 opacity-30 select-none flex flex-col justify-between">';
                        echo '<span class="text-xs font-mono text-slate-600 font-bold">' . $j . '</span>';
                        echo '<div></div>';
                        echo '</div>';
                    }
                    ?>
                </div>

                <!-- Footer Legend Bar -->
                <div class="w-full pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-4 text-xs font-semibold text-slate-400">
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="text-slate-300 font-bold">Kategori:</span>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-400"></span> Pembelajaran / KBM</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-amber-400"></span> Ujian / Evaluasi (PTS/PAS)</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-rose-400"></span> Hari Libur Resmi</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-indigo-400"></span> Event & Peringatan</div>
                        <div class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-orange-400"></span> Rapat Guru / Dinas</div>
                    </div>
                    <span class="text-[11px] text-slate-500 font-mono">Klik agenda untuk melihat detail & deskripsi</span>
                </div>

            </div>
        <?php else: ?>
            <!-- Mode 2: Agenda Chronological List View (Full-Width, Clear Spacing, No Overlap) -->
            <div class="w-full space-y-4">
                <div class="w-full flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 border-b border-slate-800 pb-3">
                    <div>
                        <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-indigo-400"></i> Rincian Agenda Kegiatan
                        </h3>
                        <p class="text-xs text-slate-400">Periode: <strong><?= $monthNamesId[$month]; ?> <?= $year; ?></strong></p>
                    </div>
                    <span class="px-3 py-1 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-300 font-medium">
                        Total: <strong class="text-indigo-400 font-mono"><?= count(array_filter($allEvents, fn($k) => str_starts_with($k, sprintf('%04d-%02d', $year, $month)), ARRAY_FILTER_USE_KEY)); ?></strong> Kegiatan
                    </span>
                </div>

                <?php
                $monthEvents = [];
                $prefix = sprintf('%04d-%02d', $year, $month);
                foreach ($allEvents as $dt => $evList) {
                    if (str_starts_with($dt, $prefix)) {
                        $monthEvents[$dt] = $evList;
                    }
                }
                ksort($monthEvents);
                ?>

                <?php if (empty($monthEvents)): ?>
                    <div class="w-full p-12 rounded-3xl bg-slate-950 border border-slate-800 text-center text-slate-500 text-xs space-y-2">
                        <i class="fa-solid fa-calendar-xmark text-4xl text-slate-600 block"></i>
                        <span class="text-sm font-semibold text-slate-400 block">Tidak ada agenda kegiatan pada bulan <?= $monthNamesId[$month]; ?> <?= $year; ?>.</span>
                        <p class="text-slate-500">Gunakan navigasi bulan di atas untuk melihat agenda periode lain.</p>
                    </div>
                <?php else: ?>
                    <div class="w-full space-y-3.5">
                        <?php foreach ($monthEvents as $dt => $evList): ?>
                            <div id="<?= $dt; ?>" class="w-full p-4 sm:p-5 rounded-2xl bg-slate-950 border border-slate-800 space-y-3 shadow-md">
                                
                                <!-- Header Tanggal Hari -->
                                <div class="w-full flex items-center justify-between border-b border-slate-800/80 pb-2.5">
                                    <div class="flex items-center gap-2.5 font-mono text-xs sm:text-sm font-extrabold text-indigo-400">
                                        <i class="fa-regular fa-calendar text-slate-400"></i>
                                        <span><?= date('l, d F Y', strtotime($dt)); ?></span>
                                        <?php if ($dt === $today): ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold bg-indigo-600 text-white uppercase tracking-wider">Hari Ini</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="text-xs text-slate-400 font-mono"><?= count($evList); ?> Agenda</span>
                                </div>

                                <!-- Item Agenda List -->
                                <div class="w-full divide-y divide-slate-800/60">
                                    <?php foreach ($evList as $ev): ?>
                                        <?php
                                        $evType = $ev['type'] ?? 'event';
                                        $pillCls = 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20';
                                        $typeLbl = 'Event Sekolah';
                                        if ($evType === 'libur') { $pillCls = 'bg-rose-500/10 text-rose-400 border-rose-500/20'; $typeLbl = 'Hari Libur'; }
                                        elseif ($evType === 'ujian') { $pillCls = 'bg-amber-500/10 text-amber-400 border-amber-500/20'; $typeLbl = 'Ujian Semester'; }
                                        elseif ($evType === 'sekolah') { $pillCls = 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'; $typeLbl = 'KBM / Pembelajaran'; }
                                        elseif ($evType === 'rapat') { $pillCls = 'bg-orange-500/10 text-orange-400 border-orange-500/20'; $typeLbl = 'Rapat Guru / Dinas'; }

                                        $safeTitle = htmlspecialchars($ev['title'], ENT_QUOTES);
                                        $safeDesc = htmlspecialchars($ev['description'] ?? '', ENT_QUOTES);
                                        $safeId = htmlspecialchars($ev['id'] ?? '0', ENT_QUOTES);
                                        $isDb = !empty($ev['is_db']) ? '1' : '0';
                                        ?>
                                        <div class="py-3 flex flex-col md:flex-row md:items-center justify-between gap-3">
                                            <div class="space-y-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <span class="px-2.5 py-0.5 rounded text-[10px] font-bold border <?= $pillCls; ?>"><?= $typeLbl; ?></span>
                                                    <h4 class="text-xs sm:text-sm font-extrabold text-white"><?= htmlspecialchars($ev['title']); ?></h4>
                                                </div>
                                                <?php if (!empty($ev['description'])): ?>
                                                    <p class="text-xs text-slate-400 pl-1 leading-relaxed"><?= htmlspecialchars($ev['description']); ?></p>
                                                <?php endif; ?>
                                            </div>

                                            <div class="flex items-center gap-2 shrink-0">
                                                <button type="button" onclick="showEventDetail('<?= $safeId; ?>', '<?= $safeTitle; ?>', '<?= $dt; ?>', '<?= $evType; ?>', '<?= $safeDesc; ?>', <?= $isDb; ?>)" class="px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-slate-300 text-xs font-bold rounded-xl border border-slate-700 transition-colors">
                                                    Lihat Detail
                                                </button>
                                                <?php if ($isAdmin && !empty($ev['is_db']) && !empty($ev['id'])): ?>
                                                    <form action="dashboard.php?page=kalender_akademik&m=<?= $month; ?>&y=<?= $year; ?>&view=agenda" method="POST" onsubmit="return confirm('Hapus agenda ini?');" class="inline">
                                                        <?= csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete_event">
                                                        <input type="hidden" name="event_id" value="<?= $ev['id']; ?>">
                                                        <button type="submit" class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-600 hover:text-white border border-rose-500/20 flex items-center justify-center text-xs transition-all" title="Hapus Agenda">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Modal 1: Detail Agenda Interaktif (Google Calendar Popover Style) -->
    <div id="modal-event-detail" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 w-full max-w-md shadow-2xl relative space-y-5">
            <div class="flex items-start justify-between gap-3 border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div id="detail-badge-icon" class="w-9 h-9 rounded-xl flex items-center justify-center text-base font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div>
                        <span id="detail-type-badge" class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase tracking-wider bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                            Agenda Sekolah
                        </span>
                        <h3 id="detail-title" class="text-sm font-extrabold text-white mt-1 leading-snug">Nama Agenda</h3>
                    </div>
                </div>
                <button type="button" onclick="closeModal('modal-event-detail')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="space-y-3 text-xs text-slate-300">
                <div class="flex items-center gap-2.5 text-slate-400">
                    <i class="fa-regular fa-calendar text-indigo-400 w-4 text-center"></i>
                    <span id="detail-date" class="text-white font-mono font-bold">17 Agustus 2026</span>
                </div>
                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                    <span class="text-[10px] text-slate-500 uppercase tracking-wider font-extrabold block">Deskripsi Kegiatan:</span>
                    <p id="detail-desc" class="text-xs text-slate-300 leading-relaxed">Tidak ada catatan deskripsi.</p>
                </div>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-slate-800">
                <button type="button" onclick="closeModal('modal-event-detail')" class="px-4 py-2 bg-slate-800 text-slate-300 hover:text-white rounded-xl text-xs font-bold">Tutup</button>
                
                <?php if ($isAdmin): ?>
                    <div id="detail-admin-actions" class="flex items-center gap-2">
                        <button type="button" id="btn-edit-detail" onclick="openEditFromDetail()" class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-md shadow-indigo-600/30">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                        <form id="form-delete-detail" action="dashboard.php?page=kalender_akademik&m=<?= $month; ?>&y=<?= $year; ?>&view=<?= $viewMode; ?>" method="POST" onsubmit="return confirm('Hapus agenda ini secara permanen?');" class="inline">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_event">
                            <input type="hidden" id="delete-event-id" name="event_id" value="0">
                            <button type="submit" class="px-3.5 py-2 bg-rose-600/20 hover:bg-rose-600 text-rose-400 hover:text-white border border-rose-500/30 rounded-xl text-xs font-bold flex items-center gap-1.5 transition-colors">
                                <i class="fa-solid fa-trash"></i> Hapus
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal 2: Form Tambah / Edit Agenda Khusus Admin -->
    <?php if ($isAdmin): ?>
        <div id="modal-manage-event" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h3 id="manage-modal-title" class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-calendar-plus text-indigo-400"></i> Tambah Agenda Akademik
                    </h3>
                    <button type="button" onclick="closeModal('modal-manage-event')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                </div>

                <form id="form-manage-event" action="dashboard.php?page=kalender_akademik&m=<?= $month; ?>&y=<?= $year; ?>&view=<?= $viewMode; ?>" method="POST" class="space-y-4">
                    <?= csrf_field(); ?>
                    <input type="hidden" id="manage-action" name="action" value="add_event">
                    <input type="hidden" id="manage-event-id" name="event_id" value="0">

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nama / Judul Kegiatan</label>
                        <input type="text" id="manage-title" name="title" required placeholder="Contoh: Penilaian Akhir Semester Ganjil" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Tanggal Pelaksanaan</label>
                        <input type="date" id="manage-date" name="event_date" required value="<?= sprintf('%04d-%02d-%02d', $year, $month, date('d')); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500 font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Deskripsi / Catatan Tambahan</label>
                        <textarea id="manage-desc" name="description" rows="3" placeholder="Informasi detail mengenai agenda kegiatan..." class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                        <button type="button" onclick="closeModal('modal-manage-event')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                        <button type="submit" id="btn-save-manage" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/30">
                            Simpan Agenda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Interactive JavaScript for Event Popover & Management -->
<script>
let currentEventData = null;

function showEventDetail(id, title, dateStr, type, desc, isDb) {
    currentEventData = { id, title, dateStr, type, desc, isDb };
    
    document.getElementById('detail-title').innerText = title;
    document.getElementById('detail-date').innerText = dateStr;
    document.getElementById('detail-desc').innerText = desc || 'Tidak ada catatan deskripsi khusus.';
    
    const typeBadge = document.getElementById('detail-type-badge');
    const badgeIcon = document.getElementById('detail-badge-icon');
    
    let typeText = 'Event Sekolah';
    let iconCls = 'fa-calendar-day';
    let themeColor = 'indigo';

    if (type === 'libur') {
        typeText = 'Hari Libur Resmi';
        iconCls = 'fa-umbrella-beach';
        themeColor = 'rose';
    } else if (type === 'ujian') {
        typeText = 'Ujian Semester (PTS/PAS)';
        iconCls = 'fa-graduation-cap';
        themeColor = 'amber';
    } else if (type === 'sekolah') {
        typeText = 'KBM / Pembelajaran';
        iconCls = 'fa-school';
        themeColor = 'emerald';
    } else if (type === 'rapat') {
        typeText = 'Rapat Guru / Dinas';
        iconCls = 'fa-users-gear';
        themeColor = 'orange';
    }

    typeBadge.innerText = typeText;
    typeBadge.className = `px-2 py-0.5 rounded text-[9px] font-extrabold uppercase tracking-wider bg-${themeColor}-500/10 text-${themeColor}-300 border border-${themeColor}-500/20`;
    badgeIcon.className = `w-9 h-9 rounded-xl flex items-center justify-center text-base font-bold bg-${themeColor}-500/10 text-${themeColor}-400 border border-${themeColor}-500/20`;
    badgeIcon.innerHTML = `<i class="fa-solid ${iconCls}"></i>`;

    const adminActions = document.getElementById('detail-admin-actions');
    if (adminActions) {
        if (isDb && id !== '0' && !id.startsWith('def-')) {
            adminActions.style.display = 'flex';
            document.getElementById('delete-event-id').value = id;
        } else {
            adminActions.style.display = 'none';
        }
    }

    openModal('modal-event-detail');
}

<?php if ($isAdmin): ?>
function openAddEventModal() {
    const titleEl = document.getElementById('manage-modal-title');
    const form = document.getElementById('form-manage-event');
    if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-calendar-plus text-indigo-400"></i> Tambah Agenda Akademik';
    if (form) {
        document.getElementById('manage-action').value = 'add_event';
        document.getElementById('manage-event-id').value = '0';
        document.getElementById('manage-title').value = '';
        document.getElementById('manage-desc').value = '';
        document.getElementById('btn-save-manage').innerText = 'Simpan Agenda';
    }
    openModal('modal-manage-event');
}

function openEditFromDetail() {
    if (!currentEventData) return;
    closeModal('modal-event-detail');

    const titleEl = document.getElementById('manage-modal-title');
    const form = document.getElementById('form-manage-event');
    if (titleEl) titleEl.innerHTML = '<i class="fa-solid fa-pen-to-square text-indigo-400"></i> Edit Agenda Akademik';
    if (form) {
        document.getElementById('manage-action').value = 'edit_event';
        document.getElementById('manage-event-id').value = currentEventData.id;
        document.getElementById('manage-title').value = currentEventData.title;
        document.getElementById('manage-date').value = currentEventData.dateStr;
        document.getElementById('manage-desc').value = currentEventData.desc;
        document.getElementById('btn-save-manage').innerText = 'Perbarui Agenda';
    }
    openModal('modal-manage-event');
}
<?php endif; ?>
</script>