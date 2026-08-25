<?php
if (!ob_get_level()) {
    ob_start();
}
require_once 'config.php';
check_login();

$user = current_user();
$role = $user['role'];
$page = $_GET['page'] ?? 'overview';

// Early CSV Export Handler for PPDB (Before HTML output starts)
if ($page === 'ppdb' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    if ($role === 'admin') {
        if (ob_get_length()) {
            ob_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=PPDB_2026_Export_' . date('Ymd_His') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['No Registrasi', 'Nama Lengkap', 'NISN', 'NIK', 'Jenis Kelamin', 'Sekolah Asal', 'Provinsi', 'Kabupaten/Kota', 'Kecamatan', 'No HP Ortu', 'Email', 'Status', 'Tanggal Daftar']);

        $exportQuery = $pdo->query("SELECT no_pendaftaran, nama_lengkap, nisn, nik, jenis_kelamin, nama_sekolah_asal, provinsi, kabupaten, kecamatan, no_hp_ortu, email, status, created_at FROM ppdb ORDER BY id DESC");
        while ($row = $exportQuery->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
}

// Handler Upload Foto Profil (Avatar) dari Perangkat Lokal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_avatar') {
    verify_csrf_token();
    $userId = $_SESSION['user_id'] ?? 0;

    if ($userId > 0 && isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['avatar_file']['tmp_name'];
        $file_type = mime_content_type($tmp_name);
        $file_size = $_FILES['avatar_file']['size'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'image/pjpeg'];

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['flash_error'] = "Format foto profil harus JPG, PNG, atau WEBP!";
        } elseif ($file_size > 5 * 1024 * 1024) {
            $_SESSION['flash_error'] = "Ukuran file foto profil maksimal 5MB!";
        } else {
            $upload_dir = 'uploads/avatars/';
            if (!file_exists($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $ext = pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION);
            if (empty($ext)) $ext = 'jpg';
            $new_filename = 'avatar_' . $userId . '_' . time() . '.' . strtolower($ext);
            $target_file = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $target_file)) {
                $stmtUpd = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmtUpd->execute([$target_file, $userId]);

                // Sync teacher table if logged in user is a teacher
                $userEmail = $_SESSION['email'] ?? '';
                $userName = $_SESSION['name'] ?? '';
                try {
                    $pdo->prepare("UPDATE guru SET foto = ?, avatar = ? WHERE email = ? OR nama = ?")->execute([$target_file, $target_file, $userEmail, $userName]);
                } catch (Exception $e) {}

                $_SESSION['avatar'] = $target_file;
                $user['avatar'] = $target_file;
                $_SESSION['flash_success'] = "Foto profil berhasil diperbarui dari perangkat Anda!";
                log_activity("User updated profile photo: " . $_SESSION['username']);
            } else {
                $_SESSION['flash_error'] = "Gagal mengunggah foto profil ke penyimpanan server!";
            }
        }
    } else {
        $_SESSION['flash_error'] = "Harap pilih file gambar foto profil dari perangkat Anda!";
    }

    $redirectPage = $_GET['page'] ?? 'overview';
    header("Location: dashboard.php?page=" . urlencode($redirectPage));
    exit();
}

// Menus for 6 Roles
$menus = [
    'admin' => [
        ['key' => 'overview', 'label' => 'Dashboard System', 'icon' => 'fa-gauge-high'],
        ['key' => 'cms', 'label' => 'CMS Website', 'icon' => 'fa-laptop-code'],
        ['key' => 'siswa', 'label' => 'Data Siswa', 'icon' => 'fa-user-graduate'],
        ['key' => 'ppdb', 'label' => 'PPDB Online 2026', 'icon' => 'fa-id-card'],
        ['key' => 'guru', 'label' => 'Data Guru', 'icon' => 'fa-chalkboard-user'],
        ['key' => 'orangtua', 'label' => 'Data Orang Tua', 'icon' => 'fa-people-roof'],
        ['key' => 'kelas', 'label' => 'Data Kelas', 'icon' => 'fa-school'],
        ['key' => 'mapel', 'label' => 'Mata Pelajaran', 'icon' => 'fa-book-bookmark'],
        ['key' => 'jadwal', 'label' => 'Jadwal Pelajaran', 'icon' => 'fa-calendar-days'],
        ['key' => 'kalender_akademik', 'label' => 'Kalender Akademik', 'icon' => 'fa-calendar-week'],
        ['key' => 'pengumuman', 'label' => 'Kelola Pengumuman', 'icon' => 'fa-bullhorn'],
        ['key' => 'galeri', 'label' => 'Galeri Foto', 'icon' => 'fa-images'],
        ['key' => 'prestasi', 'label' => 'Prestasi Sekolah', 'icon' => 'fa-trophy'],
        ['key' => 'alumni', 'label' => 'Tracer Alumni', 'icon' => 'fa-graduation-cap'],
        ['key' => 'inventaris', 'label' => 'Inventaris Aset', 'icon' => 'fa-boxes-stacked'],
        ['key' => 'surat', 'label' => 'Persuratan E-Surat', 'icon' => 'fa-file-signature'],
        ['key' => 'kartu_pelajar', 'label' => 'Kartu Pelajar Digital', 'icon' => 'fa-id-card-clip'],
        ['key' => 'settings', 'label' => 'Pengaturan Web', 'icon' => 'fa-sliders'],
        ['key' => 'users', 'label' => 'User & Role System', 'icon' => 'fa-users-gear'],
        ['key' => 'backup', 'label' => 'Backup & Audit Logs', 'icon' => 'fa-shield-halved']
    ],
    'kepala_sekolah' => [
        ['key' => 'overview', 'label' => 'Executive Overview', 'icon' => 'fa-chart-pie'],
        ['key' => 'statistik', 'label' => 'Statistik Siswa', 'icon' => 'fa-chart-column'],
        ['key' => 'kehadiran', 'label' => 'Grafik Kehadiran', 'icon' => 'fa-clipboard-user'],
        ['key' => 'keuangan_ringkasan', 'label' => 'Ringkasan Kas', 'icon' => 'fa-vault'],
        ['key' => 'agenda', 'label' => 'Agenda Sekolah', 'icon' => 'fa-calendar-day'],
        ['key' => 'laporan_eksekutif', 'label' => 'Laporan Siap Cetak', 'icon' => 'fa-file-pdf']
    ],
    'guru' => [
        ['key' => 'overview', 'label' => 'Dashboard Guru', 'icon' => 'fa-chalkboard'],
        ['key' => 'jadwal_mengajar', 'label' => 'Jadwal Mengajar', 'icon' => 'fa-calendar-days'],
        ['key' => 'absensi', 'label' => 'Input Absensi Siswa', 'icon' => 'fa-user-check'],
        ['key' => 'nilai', 'label' => 'Input Nilai Siswa', 'icon' => 'fa-pen-to-square'],
        ['key' => 'materi', 'label' => 'Materi Pelajaran', 'icon' => 'fa-book-bookmark'],
        ['key' => 'tugas', 'label' => 'Tugas Pelajaran', 'icon' => 'fa-list-check'],
        ['key' => 'pengumuman', 'label' => 'Pengumuman Guru', 'icon' => 'fa-bullhorn']
    ],
    'keuangan' => [
        ['key' => 'overview', 'label' => 'Ringkasan Keuangan', 'icon' => 'fa-chart-line'],
        ['key' => 'spp', 'label' => 'Pembayaran SPP', 'icon' => 'fa-receipt'],
        ['key' => 'kwitansi', 'label' => 'Cetak Kwitansi', 'icon' => 'fa-print'],
        ['key' => 'laporan', 'label' => 'Laporan Arus Kas', 'icon' => 'fa-file-invoice-dollar']
    ],
    'siswa' => [
        ['key' => 'overview', 'label' => 'Dashboard Overview', 'icon' => 'fa-house'],
        ['key' => 'kartu_pelajar', 'label' => 'Kartu Pelajar Digital', 'icon' => 'fa-id-card-clip'],
        ['key' => 'jadwal', 'label' => 'Jadwal & Absensi', 'icon' => 'fa-calendar-week'],
        ['key' => 'nilai', 'label' => 'Rapor & Transkrip', 'icon' => 'fa-graduation-cap'],
        ['key' => 'materi', 'label' => 'Materi Pelajaran', 'icon' => 'fa-book'],
        ['key' => 'tugas', 'label' => 'Tugas & Assignment', 'icon' => 'fa-tasks'],
        ['key' => 'spp_siswa', 'label' => 'Status SPP', 'icon' => 'fa-wallet']
    ],
    'orangtua' => [
        ['key' => 'overview', 'label' => 'Monitoring Anak', 'icon' => 'fa-child-reaching'],
        ['key' => 'kalender_akademik', 'label' => 'Kalender Akademik', 'icon' => 'fa-calendar-days'],
        ['key' => 'absensi_anak', 'label' => 'Absensi Kehadiran', 'icon' => 'fa-user-check'],
        ['key' => 'nilai_anak', 'label' => 'Rapor & Wali Kelas', 'icon' => 'fa-book-open-reader'],
        ['key' => 'tagihan_anak', 'label' => 'Tagihan SPP Sekolah', 'icon' => 'fa-credit-card']
    ],
    'alumni' => [
        ['key' => 'overview', 'label' => 'Dashboard Alumni', 'icon' => 'fa-graduation-cap'],
        ['key' => 'tracer', 'label' => 'Tracer Study', 'icon' => 'fa-list-check'],
        ['key' => 'profil', 'label' => 'Profil & Karir', 'icon' => 'fa-id-card']
    ]
];

$role_folder = $role;
$view_file = __DIR__ . "/views/{$role_folder}/{$page}.php";

if (!file_exists($view_file)) {
    if ($page === 'profil' && file_exists(__DIR__ . "/views/guru/profil.php") && $role === 'guru') {
        $view_file = __DIR__ . "/views/guru/profil.php";
    } elseif ($page === 'profil' && file_exists(__DIR__ . "/views/alumni/profil.php") && $role === 'alumni') {
        $view_file = __DIR__ . "/views/alumni/profil.php";
    } elseif ($page === 'kartu_pelajar' && file_exists(__DIR__ . "/views/siswa/kartu_pelajar.php")) {
        $view_file = __DIR__ . "/views/siswa/kartu_pelajar.php";
    } elseif ($page === 'kalender_akademik' && file_exists(__DIR__ . "/views/admin/kalender_akademik.php")) {
        $view_file = __DIR__ . "/views/admin/kalender_akademik.php";
    } elseif ($page === 'prestasi' && file_exists(__DIR__ . "/views/admin/prestasi.php")) {
        $view_file = __DIR__ . "/views/admin/prestasi.php";
    } elseif ($page === 'surat' && file_exists(__DIR__ . "/views/admin/surat.php")) {
        $view_file = __DIR__ . "/views/admin/surat.php";
    } else {
        $page = 'overview';
        $view_file = __DIR__ . "/views/{$role_folder}/overview.php";
    }
}

$role_badge = [
    'admin' => ['label' => 'ADMINISTRATOR', 'color' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20'],
    'kepala_sekolah' => ['label' => 'KEPALA SEKOLAH', 'color' => 'bg-purple-500/10 text-purple-400 border-purple-500/20'],
    'guru' => ['label' => 'GURU / PENGAJAR', 'color' => 'bg-blue-500/10 text-blue-400 border-blue-500/20'],
    'keuangan' => ['label' => 'STAF KEUANGAN', 'color' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'],
    'siswa' => ['label' => 'SISWA', 'color' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20'],
    'orangtua' => ['label' => 'ORANG TUA / WALI', 'color' => 'bg-amber-500/10 text-amber-400 border-amber-500/20'],
    'alumni' => ['label' => 'ALUMNI / LULUSAN', 'color' => 'bg-teal-500/10 text-teal-400 border-teal-500/20']
];
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard <?= htmlspecialchars($user['role_display']); ?> - <?= htmlspecialchars(get_setting('school_name')); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Output -->
    <link rel="stylesheet" href="assets/css/output.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js CDN for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dark:bg-slate-950 bg-slate-100 dark:text-slate-100 text-slate-800 font-sans antialiased min-h-screen flex selection:bg-indigo-500 selection:text-white overflow-x-hidden transition-colors duration-300">

    <!-- Mobile Overlay -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-slate-950/80 z-40 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

    <!-- Sidebar Navigation -->
    <aside id="app-sidebar" class="fixed md:static inset-y-0 left-0 z-50 w-64 dark:bg-slate-900 bg-white border-r dark:border-slate-800 border-slate-200 flex flex-col justify-between transform -translate-x-full md:translate-x-0 transition-all duration-300 ease-in-out shrink-0 shadow-xl">
        <div>
            <!-- Sidebar Header -->
            <div class="h-20 px-6 flex items-center justify-between border-b dark:border-slate-800 border-slate-200">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-emerald-400 p-0.5 shadow-md shrink-0">
                        <div class="w-full h-full dark:bg-slate-950 bg-indigo-50 rounded-[10px] flex items-center justify-center">
                            <i class="fa-solid fa-graduation-cap text-indigo-500 text-lg"></i>
                        </div>
                    </div>
                    <div class="overflow-hidden sidebar-header-text">
                        <h1 class="text-sm font-extrabold dark:text-white text-slate-900 truncate"><?= htmlspecialchars(get_setting('school_name')); ?></h1>
                        <span class="text-[9px] text-slate-400 font-semibold tracking-wider uppercase">Portal V2.0 System</span>
                    </div>
                </div>
            </div>

            <!-- User Profile Box -->
            <div class="px-4 py-4 user-box-detail">
                <div class="p-3.5 rounded-2xl dark:bg-slate-950 bg-slate-50 border dark:border-slate-800 border-slate-200 flex items-center gap-3">
                    <div class="relative group shrink-0">
                        <img src="<?= htmlspecialchars($user['avatar']); ?>" style="width: 40px; height: 40px; object-fit: cover;" class="w-10 h-10 rounded-xl object-cover border border-slate-700 shrink-0">
                        <button type="button" onclick="openModal('modal-change-avatar')" title="Ubah Foto Profil" class="absolute inset-0 bg-slate-950/75 rounded-xl opacity-0 group-hover:opacity-100 flex items-center justify-center text-white text-xs transition-opacity">
                            <i class="fa-solid fa-camera"></i>
                        </button>
                    </div>
                    <div class="overflow-hidden min-w-0 flex-1">
                        <h4 class="text-xs font-extrabold dark:text-white text-slate-900 truncate"><?= htmlspecialchars($user['name']); ?></h4>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="inline-block px-2 py-0.5 rounded-md text-[9px] font-extrabold border <?= $role_badge[$role]['color'] ?? 'bg-slate-800 text-slate-300'; ?>">
                                <?= $role_badge[$role]['label'] ?? strtoupper($role); ?>
                            </span>
                            <button type="button" onclick="openModal('modal-change-avatar')" class="text-[10px] text-indigo-400 hover:underline font-semibold flex items-center gap-1">
                                <i class="fa-solid fa-pen-to-square text-[9px]"></i> Foto
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Navigation Links -->
            <nav class="px-3 space-y-1">
                <div class="px-3 py-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest sidebar-header-text">Menu Utama</div>
                <?php foreach ($menus[$role] as $item): ?>
                    <?php $isActive = ($page === $item['key']); ?>
                    <a href="dashboard.php?page=<?= $item['key']; ?>" class="sidebar-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all duration-200 <?= $isActive ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/30 font-bold' : 'text-slate-400 dark:hover:text-white hover:text-slate-900 dark:hover:bg-slate-800/60 hover:bg-slate-100' ?>" title="<?= $item['label']; ?>">
                        <i class="fa-solid <?= $item['icon']; ?> text-sm w-5 text-center shrink-0 <?= $isActive ? 'text-white' : 'text-slate-500' ?>"></i>
                        <span class="sidebar-text truncate"><?= $item['label']; ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t dark:border-slate-800 border-slate-200 space-y-2">
            <a href="index.php" class="sidebar-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-slate-400 dark:hover:text-white hover:text-slate-900 dark:hover:bg-slate-800 hover:bg-slate-100 transition-all" title="Website Utama">
                <i class="fa-solid fa-globe text-sm w-5 text-center text-slate-500 shrink-0"></i>
                <span class="sidebar-text truncate">Lihat Website Utama</span>
            </a>
            <a href="logout.php" class="sidebar-link flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold text-rose-400 hover:bg-rose-500/10 border border-transparent hover:border-rose-500/20 transition-all" title="Keluar">
                <i class="fa-solid fa-right-from-bracket text-sm w-5 text-center text-rose-400 shrink-0"></i>
                <span class="sidebar-text truncate">Keluar (Logout)</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 min-h-screen">

        <!-- Top Header Navbar -->
        <header class="h-20 dark:bg-slate-900/90 bg-white/90 backdrop-blur-md border-b dark:border-slate-800 border-slate-200 px-4 sm:px-8 flex items-center justify-between sticky top-0 z-30 shadow-sm">
            <div class="flex items-center gap-3 sm:gap-4">
                <!-- Mobile Drawer Toggle -->
                <button id="sidebar-toggle" class="md:hidden w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-md">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <!-- Desktop Sidebar Collapse Toggle -->
                <button id="sidebar-collapse-btn" class="hidden md:flex w-10 h-10 rounded-xl dark:bg-slate-800 bg-slate-100 dark:text-slate-300 text-slate-700 items-center justify-center hover:bg-indigo-600 hover:text-white transition-all shadow-sm" title="Expand/Collapse Sidebar">
                    <i class="fa-solid fa-bars-staggered text-sm"></i>
                </button>

                <div>
                    <h2 class="text-base sm:text-lg font-bold dark:text-white text-slate-900 capitalize">
                        <?= str_replace('_', ' ', $page); ?>
                    </h2>
                    <p class="text-xs text-slate-400 hidden sm:block">Sesi Login: <?= htmlspecialchars($user['name']); ?> (<?= htmlspecialchars($user['role_display']); ?>)</p>
                </div>
            </div>

            <div class="flex items-center gap-3 sm:gap-4">
                <!-- Live Digital Clock -->
                <div class="digital-clock-display"></div>

                <!-- Interactive Tour / Panduan Button -->
                <button type="button" onclick="startInteractiveTour()" title="Panduan Fitur Portal" class="px-2.5 py-1 rounded-xl dark:bg-slate-800 bg-slate-100 border dark:border-slate-700 border-slate-200 hover:border-indigo-500 text-xs font-bold dark:text-indigo-400 text-indigo-600 flex items-center gap-1.5 transition-all shrink-0" style="height: 36px;">
                    <i class="fa-solid fa-circle-question"></i> <span class="hidden md:inline">Panduan</span>
                </button>

                <!-- Profile Photo Upload Trigger Button -->
                <button type="button" onclick="openModal('modal-change-avatar')" title="Ubah Foto Profil Perangkat" class="flex items-center gap-2 px-2.5 py-1 rounded-xl dark:bg-slate-800 bg-slate-100 border dark:border-slate-700 border-slate-200 hover:border-indigo-500 dark:hover:bg-slate-700 hover:bg-slate-200 transition-all group shrink-0" style="height: 36px;">
                    <img src="<?= htmlspecialchars($user['avatar']); ?>" style="width: 28px; height: 28px; max-width: 28px; max-height: 28px; object-fit: cover;" class="w-7 h-7 rounded-lg object-cover border border-indigo-500 shrink-0">
                </button>

                <!-- Quick Logout -->
                <a href="logout.php" title="Keluar" class="w-9 h-9 rounded-xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center hover:bg-rose-500 hover:text-white transition-colors">
                    <i class="fa-solid fa-power-off text-xs"></i>
                </a>
            </div>
        </header>

        <!-- Dynamic Content Body -->
        <main class="flex-1 p-4 sm:p-8 overflow-y-auto">
            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-bold flex items-center justify-between shadow-lg">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-check text-base"></i>
                        <span><?= htmlspecialchars($_SESSION['flash_success']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-bold flex items-center justify-between shadow-lg">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-circle-exclamation text-base"></i>
                        <span><?= htmlspecialchars($_SESSION['flash_error']); ?></span>
                    </div>
                    <button type="button" onclick="this.parentElement.remove()" class="text-rose-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <?php
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                echo "<div class='p-6 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm'>Halaman modul belum tersedia.</div>";
            }
            ?>
        </main>
    </div>

    <!-- Modal Ubah Foto Profil (Upload Lokal) -->
    <div id="modal-change-avatar" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md max-h-[90vh] overflow-y-auto shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3 sticky top-0 bg-slate-900 z-10 pt-1">
                <div>
                    <h3 class="text-lg font-extrabold text-white flex items-center gap-2">
                        <i class="fa-solid fa-camera text-indigo-400"></i> Ubah Foto Profil
                    </h3>
                    <p class="text-xs text-slate-400">Upload foto dari penyimpanan lokal perangkat Anda</p>
                </div>
                <button type="button" onclick="closeModal('modal-change-avatar')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition-colors shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=<?= htmlspecialchars($page); ?>" method="POST" enctype="multipart/form-data" class="space-y-5">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="change_avatar">

                <div class="text-center py-2">
                    <div class="relative w-32 h-32 mx-auto shrink-0" style="width: 140px; height: 140px;">
                        <img id="avatar-preview" src="<?= htmlspecialchars($user['avatar']); ?>" style="width: 140px; height: 140px; max-width: 140px; max-height: 140px; object-fit: cover;" class="w-32 h-32 rounded-2xl object-cover border-2 border-indigo-500 shadow-xl mx-auto">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-3 font-medium">Pengguna: <strong class="text-white"><?= htmlspecialchars($user['name']); ?></strong> (<?= htmlspecialchars($user['role_display']); ?>)</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">Pilih Foto Dari Laptop / HP (JPG, PNG, WEBP)</label>
                    <input type="file" name="avatar_file" id="avatar_file_input" accept="image/jpeg,image/png,image/webp,image/jpg" required onchange="previewAvatarImage(this)" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
                    <p class="text-[10px] text-slate-500 mt-1">Maksimal ukuran file: 5MB</p>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800 sticky bottom-0 bg-slate-900 pb-1">
                    <button type="button" onclick="closeModal('modal-change-avatar')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">Batal</button>
                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload & Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="assets/js/clock.js"></script>
    <script src="assets/js/app.js"></script>
    <script src="assets/js/tour.js"></script>
    <script>
        function previewAvatarImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('avatar-preview');
                    if (preview) preview.src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Register Service Worker for PWA Offline Support
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js').then(reg => {
                    console.log('Dashboard PWA Ready:', reg.scope);
                }).catch(err => {});
            });
        }
    </script>
</body>
</html>
