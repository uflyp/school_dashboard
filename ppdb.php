<?php
// ppdb.php - Portal Resmi PPDB Online 2026/2027
require_once 'config.php';

$msg_success = '';
$msg_error = '';
$registration_slip = null;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_ppdb') {
    verify_csrf_token();

    // Sanitization & Input Extraction
    $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
    $nisn = trim($_POST['nisn'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $jenis_kelamin = trim($_POST['jenis_kelamin'] ?? 'Laki-laki');
    $agama = trim($_POST['agama'] ?? 'Islam');
    $anak_ke = intval($_POST['anak_ke'] ?? 1);
    $jumlah_saudara = intval($_POST['jumlah_saudara'] ?? 0);

    $alamat_lengkap = trim($_POST['alamat_lengkap'] ?? '');
    $provinsi = trim($_POST['provinsi'] ?? '');
    $kabupaten = trim($_POST['kabupaten'] ?? '');
    $kecamatan = trim($_POST['kecamatan'] ?? '');
    $kelurahan = trim($_POST['kelurahan'] ?? '');
    $kode_pos = trim($_POST['kode_pos'] ?? '');

    $nama_sekolah_asal = trim($_POST['nama_sekolah_asal'] ?? '');
    $npsn = trim($_POST['npsn'] ?? '');
    $alamat_sekolah_asal = trim($_POST['alamat_sekolah_asal'] ?? '');
    $tahun_lulus = trim($_POST['tahun_lulus'] ?? date('Y'));

    $nama_ayah = trim($_POST['nama_ayah'] ?? '');
    $nik_ayah = trim($_POST['nik_ayah'] ?? '');
    $pendidikan_ayah = trim($_POST['pendidikan_ayah'] ?? '');
    $pekerjaan_ayah = trim($_POST['pekerjaan_ayah'] ?? '');
    $penghasilan_ayah = trim($_POST['penghasilan_ayah'] ?? '');

    $nama_ibu = trim($_POST['nama_ibu'] ?? '');
    $nik_ibu = trim($_POST['nik_ibu'] ?? '');
    $pendidikan_ibu = trim($_POST['pendidikan_ibu'] ?? '');
    $pekerjaan_ibu = trim($_POST['pekerjaan_ibu'] ?? '');
    $penghasilan_ibu = trim($_POST['penghasilan_ibu'] ?? '');

    $nama_wali = trim($_POST['nama_wali'] ?? '');
    $nik_wali = trim($_POST['nik_wali'] ?? '');
    $pekerjaan_wali = trim($_POST['pekerjaan_wali'] ?? '');
    $no_hp_wali = trim($_POST['no_hp_wali'] ?? '');

    $no_hp_siswa = trim($_POST['no_hp_siswa'] ?? '');
    $no_hp_ortu = trim($_POST['no_hp_ortu'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Server-Side PPDB Period Validation
    $ppdbPeriodCheck = get_ppdb_status();
    if (!$ppdbPeriodCheck['is_open']) {
        $msg_error = "Pendaftaran Ditolak: " . $ppdbPeriodCheck['message'] . ". Silakan hubungi panitia PPDB.";
    } elseif (empty($nama_lengkap) || empty($nisn) || empty($nik) || empty($tanggal_lahir) || empty($email) || empty($no_hp_ortu)) {
        $msg_error = "Harap lengkapi semua kolom wajib (Nama Lengkap, NISN, NIK, Tanggal Lahir, Email, dan No HP Ortu)!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg_error = "Format alamat email tidak valid!";
    } elseif (strlen($nik) < 16) {
        $msg_error = "Nomor NIK Calon Siswa harus 16 digit!";
    } elseif (strlen($nisn) < 10) {
        $msg_error = "Nomor NISN harus 10 digit!";
    } else {
        // File Upload Processing
        $upload_dir = 'uploads/ppdb/';
        if (!file_exists($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }

        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $uploaded_files = [
            'file_pas_foto' => 'sample_foto.jpg',
            'file_kk' => 'sample_kk.pdf',
            'file_akta' => 'sample_akta.pdf',
            'file_rapor' => 'sample_rapor.pdf',
            'file_ijazah' => 'sample_ijazah.pdf',
            'file_kip' => ''
        ];

        foreach ($uploaded_files as $file_key => $default_val) {
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES[$file_key]['tmp_name'];
                $file_type = mime_content_type($tmp_name);
                $file_size = $_FILES[$file_key]['size'];

                if (!in_array($file_type, $allowed_types)) {
                    $msg_error = "Format berkas $file_key harus JPG, PNG, atau PDF!";
                    break;
                }
                if ($file_size > 5 * 1024 * 1024) { // Max 5MB
                    $msg_error = "Ukuran berkas $file_key maksimal 5MB!";
                    break;
                }

                $ext = pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION);
                $new_filename = $file_key . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                    $uploaded_files[$file_key] = $upload_dir . $new_filename;
                }
            }
        }

        if (empty($msg_error)) {
            // Generate Auto Registration Code
            $countTotal = $pdo->query("SELECT COUNT(*) FROM ppdb")->fetchColumn();
            $no_pendaftaran = 'PPDB2026' . str_pad($countTotal + 1, 4, '0', STR_PAD_LEFT);
            $created_at = date('Y-m-d H:i:s');

            try {
                $stmt = $pdo->prepare("INSERT INTO ppdb (
                    no_pendaftaran, nisn, nik, nama_lengkap, tempat_lahir, tanggal_lahir, jenis_kelamin, agama, anak_ke, jumlah_saudara,
                    alamat_lengkap, provinsi, kabupaten, kecamatan, kelurahan, kode_pos, nama_sekolah_asal, npsn, alamat_sekolah_asal, tahun_lulus,
                    nama_ayah, nik_ayah, pendidikan_ayah, pekerjaan_ayah, penghasilan_ayah, nama_ibu, nik_ibu, pendidikan_ibu, pekerjaan_ibu, penghasilan_ibu,
                    nama_wali, nik_wali, pekerjaan_wali, no_hp_wali, no_hp_siswa, no_hp_ortu, email,
                    file_pas_foto, file_kk, file_akta, file_rapor, file_ijazah, file_kip, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Menunggu Verifikasi', ?)");

                $stmt->execute([
                    $no_pendaftaran, $nisn, $nik, $nama_lengkap, $tempat_lahir, $tanggal_lahir, $jenis_kelamin, $agama, $anak_ke, $jumlah_saudara,
                    $alamat_lengkap, $provinsi, $kabupaten, $kecamatan, $kelurahan, $kode_pos, $nama_sekolah_asal, $npsn, $alamat_sekolah_asal, $tahun_lulus,
                    $nama_ayah, $nik_ayah, $pendidikan_ayah, $pekerjaan_ayah, $penghasilan_ayah, $nama_ibu, $nik_ibu, $pendidikan_ibu, $pekerjaan_ibu, $penghasilan_ibu,
                    $nama_wali, $nik_wali, $pekerjaan_wali, $no_hp_wali, $no_hp_siswa, $no_hp_ortu, $email,
                    $uploaded_files['file_pas_foto'], $uploaded_files['file_kk'], $uploaded_files['file_akta'], $uploaded_files['file_rapor'], $uploaded_files['file_ijazah'], $uploaded_files['file_kip'],
                    $created_at
                ]);

                $msg_success = "Selamat! Pendaftaran PPDB Online Anda berhasil terkirim dengan Nomor Registrasi: $no_pendaftaran";
                $registration_slip = [
                    'no_pendaftaran' => $no_pendaftaran,
                    'nama' => $nama_lengkap,
                    'nisn' => $nisn,
                    'sekolah' => $nama_sekolah_asal,
                    'tanggal' => $created_at
                ];
                log_activity("New PPDB application registered: $no_pendaftaran ($nama_lengkap)");
            } catch (Exception $e) {
                $msg_error = "Gagal menyimpan pendaftaran: NISN atau NIK sudah pernah terdaftar!";
            }
        }
    }
}

// Handle Check Registration Status
$search_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_status') {
    verify_csrf_token();
    $search_noreg = trim($_POST['no_pendaftaran'] ?? '');

    $stmtC = $pdo->prepare("SELECT * FROM ppdb WHERE (no_pendaftaran = ? OR nisn = ?) LIMIT 1");
    $stmtC->execute([$search_noreg, $search_noreg]);
    $found = $stmtC->fetch();

    if ($found) {
        $search_result = $found;
    } else {
        $msg_error = "Data pendaftaran dengan Nomor Registrasi / NISN '$search_noreg' tidak ditemukan!";
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_berkas_ppdb') {
    verify_csrf_token();
    $search_noreg = trim($_POST['no_pendaftaran'] ?? '');

    $stmtP = $pdo->prepare("SELECT * FROM ppdb WHERE (no_pendaftaran = ? OR nisn = ?) LIMIT 1");
    $stmtP->execute([$search_noreg, $search_noreg]);
    $record = $stmtP->fetch();

    if ($record) {
        $upload_dir = 'uploads/ppdb/';
        if (!file_exists($upload_dir)) {
            @mkdir($upload_dir, 0777, true);
        }
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        $updated_files = [];

        $file_fields = ['file_pas_foto', 'file_kk', 'file_akta', 'file_rapor', 'file_ijazah', 'file_kip'];
        foreach ($file_fields as $field) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $tmp_name = $_FILES[$field]['tmp_name'];
                $file_type = mime_content_type($tmp_name);
                $file_size = $_FILES[$field]['size'];

                if (in_array($file_type, $allowed_types) && $file_size <= 5 * 1024 * 1024) {
                    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
                    $new_filename = $field . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
                    if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                        $updated_files[$field] = $upload_dir . $new_filename;
                    }
                }
            }
        }

        if (!empty($updated_files)) {
            $sqlParts = [];
            $params = [];
            foreach ($updated_files as $col => $val) {
                $sqlParts[] = "$col = ?";
                $params[] = $val;
            }
            $sqlParts[] = "status = ?";
            $params[] = "Menunggu Verifikasi";
            $sqlParts[] = "catatan_admin = ?";
            $params[] = "Berkas dokumen telah diperbarui oleh calon siswa. Menunggu verifikasi ulang panitia.";

            $params[] = $record['id'];
            $updateSql = "UPDATE ppdb SET " . implode(", ", $sqlParts) . " WHERE id = ?";
            $pdo->prepare($updateSql)->execute($params);

            $msg_success = "Berkas pendaftaran ($search_noreg) berhasil diperbarui! Status telah dikembalikan ke 'Menunggu Verifikasi'.";
            log_activity("Applicant updated PPDB files: $search_noreg");

            $stmtRefresh = $pdo->prepare("SELECT * FROM ppdb WHERE id = ?");
            $stmtRefresh->execute([$record['id']]);
            $search_result = $stmtRefresh->fetch();
        } else {
            $msg_error = "Harap pilih minimal 1 file dokumen baru yang ingin di-update!";
            $search_result = $record;
        }
    } else {
        $msg_error = "Nomor Registrasi / NISN '$search_noreg' tidak ditemukan!";
    }
}

// Fetch Active Countdown Event for PPDB
$stmtCd = $pdo->query("SELECT * FROM events WHERE is_countdown = 1 AND is_active = 1 ORDER BY id DESC LIMIT 1");
$countdownEvent = $stmtCd->fetch();
$countdownTarget = $countdownEvent['event_date'] ?? '2026-08-25 23:59:00';
?>
<!DOCTYPE html>
<html lang="id" class="dark scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PPDB Online 2026/2027 - <?= htmlspecialchars(get_setting('school_name')); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Output -->
    <link rel="stylesheet" href="assets/css/output.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased selection:bg-indigo-500 selection:text-white min-h-screen flex flex-col">

    <!-- Header Navbar -->
    <header class="sticky top-0 z-50 bg-slate-900/90 backdrop-blur-md border-b border-slate-800 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16 sm:h-20">
                <!-- Brand / Logo -->
                <a href="index.php" class="flex items-center gap-3 group shrink-0">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                        <i class="fa-solid fa-graduation-cap text-lg"></i>
                    </div>
                    <div>
                        <h1 class="text-base sm:text-lg font-bold text-white leading-tight group-hover:text-indigo-400 transition-colors">
                            <?= htmlspecialchars(get_setting('school_name')); ?>
                        </h1>
                        <span class="text-[10px] text-slate-400 font-medium uppercase tracking-widest block">Portal PPDB Online 2026</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links (Beranda Utama removed per requirements) -->
                <nav class="hidden md:flex items-center gap-6 lg:gap-8 text-xs font-semibold">
                    <a href="#hero" class="text-slate-300 hover:text-indigo-400 transition-colors">PPDB Home</a>
                    <a href="#alur" class="text-slate-300 hover:text-indigo-400 transition-colors">Alur Pendaftaran</a>
                    <a href="#form-daftar" class="text-slate-300 hover:text-indigo-400 transition-colors">Formulir</a>
                    <a href="#cek-status" class="text-slate-300 hover:text-indigo-400 transition-colors">Cek Status</a>
                    <a href="#faq" class="text-slate-300 hover:text-indigo-400 transition-colors">FAQ</a>
                </nav>

                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <a href="#form-daftar" class="px-4 py-2 sm:px-5 sm:py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-lg shadow-indigo-600/20 flex items-center gap-2 transition-all">
                        <i class="fa-solid fa-pen-to-square"></i>
                        <span>Daftar Sekarang</span>
                    </a>
                    <a href="index.php" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold border border-slate-700 hidden sm:flex items-center gap-1.5 transition-all">
                        <i class="fa-solid fa-arrow-left text-slate-400"></i> Ke Website
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- 1. HERO SECTION -->
    <section id="hero" class="relative py-16 sm:py-24 px-4 sm:px-6 lg:px-8 overflow-hidden bg-gradient-to-b from-slate-950 via-indigo-950/30 to-slate-950 border-b border-slate-800/80">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-xs font-extrabold tracking-wide uppercase">
                    <i class="fa-solid fa-id-card"></i> Pendaftaran Tahun Ajaran 2026/2027
                </div>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight leading-tight">
                    Penerimaan Peserta Didik Baru (PPDB) 2026/2027
                </h1>

                <p class="text-sm sm:text-base text-slate-400 leading-relaxed max-w-2xl">
                    Selamat datang di sistem PPDB Online <?= htmlspecialchars(get_setting('school_name')); ?>. Silakan melakukan pendaftaran secara online dengan mudah, cepat, dan aman.
                </p>

                <!-- Accurate Realtime Countdown Timer -->
                <div class="p-6 rounded-3xl bg-slate-900/90 border border-indigo-500/30 shadow-2xl space-y-3 max-w-xl mx-auto lg:mx-0">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-400 border-b border-slate-800 pb-2">
                        <span><i class="fa-regular fa-clock text-amber-400 mr-1.5"></i> BATAS AKHIR PENDAFTARAN GELOMBANG 1</span>
                        <span class="text-amber-400 font-mono">AKURAT TIMESTAMP</span>
                    </div>

                    <div id="ppdb-countdown-timer" class="grid grid-cols-4 gap-3 text-center">
                        <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20">
                            <span id="cd-days" class="block text-2xl font-black text-indigo-400 font-mono">00</span>
                            <span class="text-[10px] text-slate-400 uppercase font-bold">Hari</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20">
                            <span id="cd-hours" class="block text-2xl font-black text-indigo-400 font-mono">00</span>
                            <span class="text-[10px] text-slate-400 uppercase font-bold">Jam</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20">
                            <span id="cd-minutes" class="block text-2xl font-black text-indigo-400 font-mono">00</span>
                            <span class="text-[10px] text-slate-400 uppercase font-bold">Menit</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-indigo-500/10 border border-indigo-500/20">
                            <span id="cd-seconds" class="block text-2xl font-black text-indigo-400 font-mono">00</span>
                            <span class="text-[10px] text-slate-400 uppercase font-bold">Detik</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                    <a href="#cek-status" class="px-6 py-4 rounded-2xl bg-amber-600 hover:bg-amber-500 text-white font-extrabold text-xs shadow-xl shadow-amber-600/30 hover:scale-105 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass"></i> Cek Status Pendaftaran
                    </a>
                    <a href="#form-daftar" class="px-7 py-4 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold text-xs shadow-xl shadow-indigo-600/30 hover:scale-105 transition-all flex items-center gap-2">
                        <i class="fa-solid fa-paper-plane"></i> Isi Form Pendaftaran
                    </a>
                    <a href="#alur" class="px-6 py-4 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs border border-slate-700 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-indigo-400"></i> Alur & Persyaratan
                    </a>
                </div>
            </div>

            <div class="lg:col-span-5">
                <div class="relative rounded-3xl bg-slate-900 p-3 border border-indigo-500/30 shadow-2xl overflow-hidden group">
                    <img src="https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=800&q=80" alt="PPDB Sekolah" class="w-full h-80 sm:h-96 object-cover rounded-2xl group-hover:scale-105 transition-transform duration-700">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-transparent to-transparent rounded-2xl"></div>
                    <div class="absolute bottom-6 left-6 right-6 p-4 rounded-2xl bg-slate-900/90 backdrop-blur-xl border border-indigo-500/30 text-xs">
                        <div class="flex items-center justify-between font-bold text-white mb-1">
                            <span><i class="fa-solid fa-check-circle text-emerald-400 mr-1"></i> Kuota Tersedia</span>
                            <span class="text-emerald-400 font-mono">120 Siswa Baru</span>
                        </div>
                        <p class="text-[11px] text-slate-400">Jalur Prestasi, Zonasi & Afirmasi T.A. 2026/2027</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- 2. CEK STATUS PENDAFTARAN (UTAMA & UTAMA DI ATAS) -->
    <section id="cek-status" class="py-12 px-4 sm:px-6 lg:px-8 border-b border-indigo-500/30 bg-slate-900/40">
        <div class="max-w-3xl mx-auto space-y-6">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-amber-400 uppercase tracking-widest px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/20">Portal Cek Hasil Verifikasi</span>
                <h2 class="text-2xl sm:text-3xl font-black text-white">Cek Status Pendaftaran PPDB</h2>
                <p class="text-xs sm:text-sm text-slate-400">Masukkan Nomor Registrasi (contoh: PPDB20260001) atau NISN untuk melihat hasil seleksi panitia</p>
            </div>

            <form action="ppdb.php#cek-status" method="POST" class="p-6 rounded-3xl bg-slate-900 border border-indigo-500/30 shadow-2xl flex flex-col sm:flex-row gap-3">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="check_status">
                <input type="text" name="no_pendaftaran" required placeholder="Masukkan Nomor Registrasi / NISN..." class="flex-1 px-4 py-3 bg-slate-950 border border-slate-800 rounded-2xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                <button type="submit" class="px-6 py-3 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-2xl shadow-lg shadow-amber-600/30 flex items-center justify-center gap-2 transition-all">
                    <i class="fa-solid fa-magnifying-glass"></i> Cek Status Sekarang
                </button>
            </form>

            <?php if ($search_result): ?>
                <?php
                // Check required data fields completeness
                $isComplete = !empty($search_result['nama_lengkap']) &&
                              !empty($search_result['nisn']) &&
                              !empty($search_result['nik']) &&
                              !empty($search_result['nama_sekolah_asal']) &&
                              !empty($search_result['provinsi']) &&
                              !empty($search_result['kabupaten']) &&
                              !empty($search_result['kecamatan']) &&
                              !empty($search_result['alamat_lengkap']) &&
                              !empty($search_result['nama_ayah']) &&
                              !empty($search_result['nama_ibu']) &&
                              !empty($search_result['no_hp_ortu']);

                $statusVal = $search_result['status'] ?? 'Menunggu Verifikasi';
                $isVerified = ($statusVal === 'Lulus Administrasi' || $statusVal === 'Diterima');
                ?>

                <div class="p-6 rounded-3xl bg-slate-900 border border-indigo-500/30 shadow-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase font-bold block">No Registrasi:</span>
                            <span class="font-mono text-amber-400 font-extrabold text-sm"><?= htmlspecialchars($search_result['no_pendaftaran']); ?></span>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-extrabold border bg-indigo-500/10 text-indigo-400 border-indigo-500/30">
                            STATUS: <?= htmlspecialchars($statusVal); ?>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs text-slate-300">
                        <div><span class="text-slate-500 block">Nama Siswa:</span><strong><?= htmlspecialchars($search_result['nama_lengkap']); ?></strong></div>
                        <div><span class="text-slate-500 block">NISN:</span><span class="font-mono"><?= htmlspecialchars($search_result['nisn']); ?></span></div>
                        <div><span class="text-slate-500 block">Sekolah Asal:</span><span><?= htmlspecialchars($search_result['nama_sekolah_asal']); ?></span></div>
                        <div><span class="text-slate-500 block">Tanggal Daftar:</span><span><?= format_datetime($search_result['created_at']); ?></span></div>
                    </div>

                    <?php if ($search_result['catatan_admin']): ?>
                        <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs">
                            <strong>Catatan Panitia:</strong> <?= htmlspecialchars($search_result['catatan_admin']); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Button based on Data Completeness & Verification Status -->
                    <div class="pt-3 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <?php if (!$isComplete): ?>
                                <!-- If data incomplete: show "Lengkapi Data" -->
                                <a href="#form-daftar" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white font-extrabold text-xs rounded-xl flex items-center gap-2 shadow-lg shadow-amber-600/30 transition-all">
                                    <i class="fa-solid fa-pen-to-square"></i> Lengkapi Data Pendaftaran
                                </a>
                            <?php elseif ($isVerified): ?>
                                <!-- If complete and verified: show "Data Terverifikasi" (NO "Lengkapi Data") -->
                                <span class="px-4 py-2 bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 font-extrabold text-xs rounded-xl inline-flex items-center gap-2">
                                    <i class="fa-solid fa-shield-check text-emerald-400"></i> Data Terverifikasi
                                </span>
                            <?php else: ?>
                                <!-- If complete but not verified: show "Menunggu Verifikasi" -->
                                <span class="px-4 py-2 bg-blue-500/10 text-blue-400 border border-blue-500/30 font-extrabold text-xs rounded-xl inline-flex items-center gap-2">
                                    <i class="fa-solid fa-clock text-blue-400"></i> Menunggu Verifikasi Panitia
                                </span>
                            <?php endif; ?>

                            <?php if ($statusVal === 'Berkas Kurang' || !$isVerified): ?>
                                <!-- Button Update Berkas for Berkas Kurang or Re-upload -->
                                <button type="button" onclick="openModal('modal-update-berkas')" class="px-4 py-2 bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-500 hover:to-orange-500 text-white font-extrabold text-xs rounded-xl flex items-center gap-2 shadow-lg shadow-amber-600/30 transition-all">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Update Berkas
                                </button>
                            <?php endif; ?>
                        </div>

                        <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl flex items-center gap-2 transition-all">
                            <i class="fa-solid fa-print"></i> Cetak Bukti Status
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($search_result && ($statusVal === 'Berkas Kurang' || !$isVerified)): ?>
                <!-- Modal Update Berkas Dokumen Calon Siswa -->
                <div id="modal-update-berkas" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
                    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-xl shadow-2xl relative max-h-[90vh] overflow-y-auto">
                        <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                            <div>
                                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                    <i class="fa-solid fa-cloud-arrow-up text-amber-400"></i> Update / Unggah Berkas PPDB
                                </h3>
                                <p class="text-xs text-slate-400">Nomor Registrasi: <span class="font-mono text-amber-400 font-bold"><?= htmlspecialchars($search_result['no_pendaftaran']); ?></span></p>
                            </div>
                            <button type="button" onclick="closeModal('modal-update-berkas')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
                        </div>

                        <form action="ppdb.php#cek-status" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs text-slate-300">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="update_berkas_ppdb">
                            <input type="hidden" name="no_pendaftaran" value="<?= htmlspecialchars($search_result['no_pendaftaran']); ?>">

                            <?php if ($search_result['catatan_admin']): ?>
                                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs">
                                    <strong>Catatan Panitia:</strong> <?= htmlspecialchars($search_result['catatan_admin']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                                    <label class="block font-bold text-white mb-1">Pas Foto Terbaru (JPG/PNG)</label>
                                    <input type="file" name="file_pas_foto" accept="image/*" class="w-full text-[11px] text-slate-400">
                                </div>
                                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                                    <label class="block font-bold text-white mb-1">Kartu Keluarga (PDF/JPG)</label>
                                    <input type="file" name="file_kk" accept="image/*,.pdf" class="w-full text-[11px] text-slate-400">
                                </div>
                                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                                    <label class="block font-bold text-white mb-1">Akta Kelahiran (PDF/JPG)</label>
                                    <input type="file" name="file_akta" accept="image/*,.pdf" class="w-full text-[11px] text-slate-400">
                                </div>
                                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                                    <label class="block font-bold text-white mb-1">Rapor SMP (PDF/JPG)</label>
                                    <input type="file" name="file_rapor" accept="image/*,.pdf" class="w-full text-[11px] text-slate-400">
                                </div>
                                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                                    <label class="block font-bold text-white mb-1">Ijazah / SKL (PDF/JPG)</label>
                                    <input type="file" name="file_ijazah" accept="image/*,.pdf" class="w-full text-[11px] text-slate-400">
                                </div>
                                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                                    <label class="block font-bold text-white mb-1">Kartu KIP/KKS (Opsional)</label>
                                    <input type="file" name="file_kip" accept="image/*,.pdf" class="w-full text-[11px] text-slate-400">
                                </div>
                            </div>

                            <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                                <button type="button" onclick="closeModal('modal-update-berkas')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl font-bold">Batal</button>
                                <button type="submit" class="px-5 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl font-bold flex items-center gap-1.5 shadow-lg shadow-amber-600/30">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Simpan & Update Berkas
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- 3. ALUR PPDB SECTION -->
    <section id="alur" class="py-16 px-4 sm:px-6 lg:px-8 border-b border-slate-800/80">
        <div class="max-w-6xl mx-auto space-y-12">
            <div class="text-center max-w-2xl mx-auto space-y-2">
                <span class="text-xs font-extrabold text-indigo-400 uppercase tracking-widest">Langkah Pendaftaran</span>
                <h2 class="text-2xl sm:text-3xl font-black text-white">4 Alur Pendaftaran PPDB Online</h2>
                <p class="text-xs sm:text-sm text-slate-400">Proses pendaftaran yang praktis, transparan, dan terukur secara sistemik</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 hover:border-indigo-500/40 transition-all space-y-3 relative">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-xl">
                        <i class="fa-solid fa-file-pen"></i>
                    </div>
                    <span class="text-[10px] font-bold text-indigo-400 tracking-wider uppercase block">Langkah 1</span>
                    <h3 class="text-base font-extrabold text-white">1. Isi Formulir</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">Isi data calon siswa, data sekolah asal, data orang tua, dan kontak secara lengkap pada form online.</p>
                </div>

                <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 hover:border-indigo-500/40 transition-all space-y-3 relative">
                    <div class="w-12 h-12 rounded-2xl bg-purple-500/10 text-purple-400 flex items-center justify-center font-bold text-xl">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </div>
                    <span class="text-[10px] font-bold text-purple-400 tracking-wider uppercase block">Langkah 2</span>
                    <h3 class="text-base font-extrabold text-white">2. Upload Berkas</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">Unggah berkas persyaratan digital seperti Pas Foto, KK, Akta Kelahiran, Rapor, dan Ijazah/SKL.</p>
                </div>

                <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 hover:border-indigo-500/40 transition-all space-y-3 relative">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold text-xl">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <span class="text-[10px] font-bold text-amber-400 tracking-wider uppercase block">Langkah 3</span>
                    <h3 class="text-base font-extrabold text-white">3. Verifikasi Admin</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">Panitia PPDB sekolah akan memeriksa kelengkapan dan verifikasi validitas dokumen yang terunggah.</p>
                </div>

                <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 hover:border-indigo-500/40 transition-all space-y-3 relative">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-xl">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <span class="text-[10px] font-bold text-emerald-400 tracking-wider uppercase block">Langkah 4</span>
                    <h3 class="text-base font-extrabold text-white">4. Pengumuman</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">Cek pengumuman status pendaftaran melalui portal ini dengan Nomor Registrasi dan cetak bukti kelulusan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. FORM PENDAFTARAN ONLINE TERSTRUKTUR (6 SECTION) -->
    <section id="form-daftar" class="py-16 px-4 sm:px-6 lg:px-8 bg-slate-950">
        <div class="max-w-5xl mx-auto space-y-8">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-indigo-400 uppercase tracking-widest">Formulir Pendaftaran</span>
                <h2 class="text-2xl sm:text-3xl font-black text-white">Formulir Pendaftaran Siswa Baru 2026/2027</h2>
                <p class="text-xs sm:text-sm text-slate-400">Pastikan seluruh data yang dimasukkan benar dan sesuai dengan dokumen resmi (KK / Akta / Ijazah)</p>
            </div>

            <?php if ($msg_success): ?>
                <div class="p-6 rounded-3xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-semibold space-y-3">
                    <div class="flex items-center gap-2 text-sm font-extrabold">
                        <i class="fa-solid fa-circle-check text-emerald-400 text-lg"></i>
                        <span><?= htmlspecialchars($msg_success); ?></span>
                    </div>
                    <?php if ($registration_slip): ?>
                        <div class="p-4 rounded-2xl bg-slate-950 border border-emerald-500/20 space-y-2 font-mono">
                            <div>No Registrasi: <strong class="text-amber-400"><?= $registration_slip['no_pendaftaran']; ?></strong></div>
                            <div>Nama: <strong><?= htmlspecialchars($registration_slip['nama']); ?></strong></div>
                            <div>NISN: <strong><?= htmlspecialchars($registration_slip['nisn']); ?></strong></div>
                            <button onclick="window.print()" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl text-xs flex items-center gap-2 mt-2">
                                <i class="fa-solid fa-print"></i> Cetak Bukti Pendaftaran PDF
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($msg_error): ?>
                <div class="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-rose-400 text-base"></i>
                    <span><?= htmlspecialchars($msg_error); ?></span>
                </div>
            <?php endif; ?>

            <form action="ppdb.php#form-daftar" method="POST" enctype="multipart/form-data" class="space-y-8">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="register_ppdb">

                <!-- SECTION 1: DATA CALON SISWA -->
                <div class="bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xl space-y-5">
                    <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-sm">1</div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">1. DATA CALON SISWA</h3>
                            <p class="text-[11px] text-slate-400">Identitas pribadi calon peserta didik baru</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">NISN (10 Digit) <span class="text-rose-400">*</span></label>
                            <input type="text" name="nisn" required maxlength="10" placeholder="0061234567" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">NIK KTP/KK (16 Digit) <span class="text-rose-400">*</span></label>
                            <input type="text" name="nik" required maxlength="16" placeholder="3171012345670001" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Nama Lengkap Siswa <span class="text-rose-400">*</span></label>
                        <input type="text" name="nama_lengkap" required placeholder="Bintang Pratama" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Tempat Lahir</label>
                            <input type="text" name="tempat_lahir" placeholder="Jakarta" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Tanggal Lahir <span class="text-rose-400">*</span></label>
                            <input type="date" name="tanggal_lahir" required value="2008-05-14" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Jenis Kelamin</label>
                            <select name="jenis_kelamin" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Agama</label>
                            <select name="agama" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                                <option value="Islam">Islam</option>
                                <option value="Kristen">Kristen Protestan</option>
                                <option value="Katolik">Katolik</option>
                                <option value="Hindu">Hindu</option>
                                <option value="Buddha">Buddha</option>
                                <option value="Konghucu">Konghucu</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Anak Ke-</label>
                            <input type="number" name="anak_ke" value="1" min="1" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Jumlah Saudara</label>
                            <input type="number" name="jumlah_saudara" value="2" min="0" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: DATA ORANG TUA / WALI -->
                <div class="bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xl space-y-5">
                    <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold text-sm">2</div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">2. DATA ORANG TUA / WALI</h3>
                            <p class="text-[11px] text-slate-400">Data Ayah, Ibu Kandung, atau Wali Siswa</p>
                        </div>
                    </div>

                    <!-- Ayah -->
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-3">
                        <h4 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider"><i class="fa-solid fa-user-tie mr-1"></i> Data Ayah Kandung</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">Nama Ayah <span class="text-rose-400">*</span></label>
                                <input type="text" name="nama_ayah" required placeholder="Ir. Budi Santoso" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">NIK Ayah</label>
                                <input type="text" name="nik_ayah" placeholder="3171012345670002" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">Pekerjaan Ayah</label>
                                <input type="text" name="pekerjaan_ayah" placeholder="Wiraswasta / PNS" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Ibu -->
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-3">
                        <h4 class="text-xs font-extrabold text-amber-400 uppercase tracking-wider"><i class="fa-solid fa-person-dress mr-1"></i> Data Ibu Kandung</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">Nama Ibu <span class="text-rose-400">*</span></label>
                                <input type="text" name="nama_ibu" required placeholder="Siti Rahmawati, S.E." class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">NIK Ibu</label>
                                <input type="text" name="nik_ibu" placeholder="3171012345670003" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">Pekerjaan Ibu</label>
                                <input type="text" name="pekerjaan_ibu" placeholder="Ibu Rumah Tangga / Karyawan" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Wali / Kontak Utama -->
                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-3">
                        <h4 class="text-xs font-extrabold text-slate-300 uppercase tracking-wider"><i class="fa-solid fa-phone mr-1"></i> Kontak Utama & Data Wali (Opsional)</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">No. HP / WA Ortu <span class="text-rose-400">*</span></label>
                                <input type="text" name="no_hp_ortu" required placeholder="081234567890" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">Email Aktif <span class="text-rose-400">*</span></label>
                                <input type="email" name="email" required placeholder="bintang@gmail.com" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-300 mb-1">Nama Wali (Jika ada)</label>
                                <input type="text" name="nama_wali" placeholder="Ahmad Wali" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: ALAMAT LENGKAP -->
                <div class="bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xl space-y-5">
                    <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-400 flex items-center justify-center font-bold text-sm">3</div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">3. ALAMAT LENGKAP</h3>
                            <p class="text-[11px] text-slate-400">Domisili tempat tinggal calon peserta didik</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">Alamat Jalan / RT / RW</label>
                        <textarea name="alamat_lengkap" rows="2" placeholder="Jl. Merdeka Raya No. 45, RT 02 / RW 05" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none"></textarea>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Provinsi <span class="text-rose-400">*</span></label>
                            <select name="provinsi" id="provinsi" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                                <option value="">-- Pilih Provinsi --</option>
                                <option value="DKI Jakarta" selected>DKI Jakarta</option>
                                <option value="Jawa Barat">Jawa Barat</option>
                                <option value="Jawa Tengah">Jawa Tengah</option>
                                <option value="Jawa Timur">Jawa Timur</option>
                                <option value="Banten">Banten</option>
                                <option value="D.I. Yogyakarta">D.I. Yogyakarta</option>
                                <option value="Sumatera Utara">Sumatera Utara</option>
                                <option value="Sumatera Barat">Sumatera Barat</option>
                                <option value="Riau">Riau</option>
                                <option value="Kepulauan Riau">Kepulauan Riau</option>
                                <option value="Lampung">Lampung</option>
                                <option value="Sumatera Selatan">Sumatera Selatan</option>
                                <option value="Bali">Bali</option>
                                <option value="Nusa Tenggara Barat">Nusa Tenggara Barat</option>
                                <option value="Kalimantan Timur">Kalimantan Timur</option>
                                <option value="Sulawesi Selatan">Sulawesi Selatan</option>
                                <option value="Provinsi Lainnya">Provinsi Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Kabupaten/Kota <span class="text-rose-400">*</span></label>
                            <select name="kabupaten" id="kabupaten" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                                <option value="">-- Pilih Kabupaten/Kota --</option>
                                <option value="Kota Jakarta Selatan" selected>Kota Jakarta Selatan</option>
                                <option value="Kota Jakarta Timur">Kota Jakarta Timur</option>
                                <option value="Kota Jakarta Pusat">Kota Jakarta Pusat</option>
                                <option value="Kota Jakarta Barat">Kota Jakarta Barat</option>
                                <option value="Kota Jakarta Utara">Kota Jakarta Utara</option>
                                <option value="Kota Bandung">Kota Bandung</option>
                                <option value="Kota Bekasi">Kota Bekasi</option>
                                <option value="Kota Depok">Kota Depok</option>
                                <option value="Kota Tangerang">Kota Tangerang</option>
                                <option value="Kota Tangerang Selatan">Kota Tangerang Selatan</option>
                                <option value="Kota Bogor">Kota Bogor</option>
                                <option value="Kota Semarang">Kota Semarang</option>
                                <option value="Kota Surabaya">Kota Surabaya</option>
                                <option value="Kota Yogyakarta">Kota Yogyakarta</option>
                                <option value="Kota Malang">Kota Malang</option>
                                <option value="Kota Medan">Kota Medan</option>
                                <option value="Kabupaten/Kota Lainnya">Kabupaten/Kota Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Kecamatan <span class="text-rose-400">*</span></label>
                            <select name="kecamatan" id="kecamatan" required class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                                <option value="">-- Pilih Kecamatan --</option>
                                <option value="Kebayoran Baru" selected>Kebayoran Baru</option>
                                <option value="Cilandak">Cilandak</option>
                                <option value="Jagakarsa">Jagakarsa</option>
                                <option value="Kebayoran Lama">Kebayoran Lama</option>
                                <option value="Mampang Prapatan">Mampang Prapatan</option>
                                <option value="Tebet">Tebet</option>
                                <option value="Pasar Minggu">Pasar Minggu</option>
                                <option value="Pancoran">Pancoran</option>
                                <option value="Setiabudi">Setiabudi</option>
                                <option value="Pesanggrahan">Pesanggrahan</option>
                                <option value="Matraman">Matraman</option>
                                <option value="Jatinegara">Jatinegara</option>
                                <option value="Duren Sawit">Duren Sawit</option>
                                <option value="Bandung Wetan">Bandung Wetan</option>
                                <option value="Coblong">Coblong</option>
                                <option value="Sumur Bandung">Sumur Bandung</option>
                                <option value="Wonokromo">Wonokromo</option>
                                <option value="Gubeng">Gubeng</option>
                                <option value="Kecamatan Lainnya">Kecamatan Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Kode Pos</label>
                            <input type="text" name="kode_pos" placeholder="12110" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: DATA AKADEMIK (SEKOLAH ASAL) -->
                <div class="bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xl space-y-5">
                    <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center font-bold text-sm">4</div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">4. DATA AKADEMIK (SEKOLAH ASAL)</h3>
                            <p class="text-[11px] text-slate-400">Informasi pendidikan sekolah jenjang sebelumnya</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-300 mb-1">Nama Sekolah Asal (SMP/MTs) <span class="text-rose-400">*</span></label>
                            <select name="nama_sekolah_asal" id="nama_sekolah_asal" required onchange="toggleCustomSchool(this.value, 'custom_school_box')" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none cursor-pointer">
                                <option value="">-- Pilih Sekolah Asal --</option>
                                <option value="SMP Negeri 1 Jakarta" selected>SMP Negeri 1 Jakarta</option>
                                <option value="SMP Negeri 2 Jakarta">SMP Negeri 2 Jakarta</option>
                                <option value="SMP Negeri 3 Jakarta">SMP Negeri 3 Jakarta</option>
                                <option value="SMP Negeri 4 Jakarta">SMP Negeri 4 Jakarta</option>
                                <option value="SMP Negeri 19 Jakarta">SMP Negeri 19 Jakarta</option>
                                <option value="SMP Negeri 115 Jakarta">SMP Negeri 115 Jakarta</option>
                                <option value="SMP Negeri 1 Bandung">SMP Negeri 1 Bandung</option>
                                <option value="SMP Negeri 2 Bandung">SMP Negeri 2 Bandung</option>
                                <option value="SMP Negeri 1 Surabaya">SMP Negeri 1 Surabaya</option>
                                <option value="SMP Islam Al-Azhar 1">SMP Islam Al-Azhar 1</option>
                                <option value="MTs Negeri 1 Jakarta">MTs Negeri 1 Jakarta</option>
                                <option value="MTs Negeri 3 Jakarta">MTs Negeri 3 Jakarta</option>
                                <option value="SMP Taruna Bakti">SMP Taruna Bakti</option>
                                <option value="SMP Labschool Jakarta">SMP Labschool Jakarta</option>
                                <option value="SMP Santa Ursula">SMP Santa Ursula</option>
                                <option value="Lainnya">Lainnya (Input Manual Sekolah)</option>
                            </select>
                            <input type="text" name="nama_sekolah_asal_custom" id="custom_school_box" placeholder="Ketik nama sekolah asal Anda..." class="hidden mt-2 w-full px-4 py-2.5 bg-slate-950 border border-indigo-500/50 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">NPSN Sekolah Asal</label>
                            <input type="text" name="npsn" placeholder="20101234" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Alamat Sekolah Asal</label>
                            <input type="text" name="alamat_sekolah_asal" placeholder="Jl. Terusan SMP No. 10" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-300 mb-1">Tahun Lulus</label>
                            <input type="text" name="tahun_lulus" value="2026" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: DOKUMEN / UPLOAD BERKAS -->
                <div class="bg-slate-900 border border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xl space-y-5">
                    <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-sm">5</div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">5. DOKUMEN / PERSYARATAN BERKAS DIGITAL</h3>
                            <p class="text-[11px] text-slate-400">Unggah salinan dokumen pendukung (Format: JPG, PNG, atau PDF. Max: 5MB)</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                            <label class="block text-xs font-bold text-slate-300">Pas Foto 3x4 (JPG/PNG)</label>
                            <input type="file" name="file_pas_foto" accept="image/*" class="w-full text-xs text-slate-400">
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                            <label class="block text-xs font-bold text-slate-300">Kartu Keluarga (PDF/JPG)</label>
                            <input type="file" name="file_kk" accept="image/*,.pdf" class="w-full text-xs text-slate-400">
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                            <label class="block text-xs font-bold text-slate-300">Akta Kelahiran (PDF/JPG)</label>
                            <input type="file" name="file_akta" accept="image/*,.pdf" class="w-full text-xs text-slate-400">
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                            <label class="block text-xs font-bold text-slate-300">Rapor SMP (PDF/JPG)</label>
                            <input type="file" name="file_rapor" accept="image/*,.pdf" class="w-full text-xs text-slate-400">
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                            <label class="block text-xs font-bold text-slate-300">Ijazah / SKL (PDF/JPG)</label>
                            <input type="file" name="file_ijazah" accept="image/*,.pdf" class="w-full text-xs text-slate-400">
                        </div>
                        <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                            <label class="block text-xs font-bold text-slate-300">Kartu KIP/KKS (Opsional)</label>
                            <input type="file" name="file_kip" accept="image/*,.pdf" class="w-full text-xs text-slate-400">
                        </div>
                    </div>
                </div>

                <!-- SECTION 6: KONFIRMASI DAN SUBMIT -->
                <div class="bg-slate-900 border border-indigo-500/30 p-6 sm:p-8 rounded-3xl shadow-2xl space-y-6">
                    <div class="flex items-center gap-3 border-b border-slate-800 pb-4">
                        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center font-bold text-sm">6</div>
                        <div>
                            <h3 class="text-base font-extrabold text-white">6. KONFIRMASI DAN SUBMIT</h3>
                            <p class="text-[11px] text-slate-400">Pernyataan kebenaran data & pengiriman pendaftaran</p>
                        </div>
                    </div>

                    <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 text-xs text-slate-300 space-y-2">
                        <label class="flex items-start gap-3 cursor-pointer select-none">
                            <input type="checkbox" required class="mt-0.5 w-4 h-4 rounded bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500">
                            <span class="leading-relaxed">Saya menyatakan dengan sesungguhnya bahwa seluruh data yang saya isikan pada formulir pendaftaran PPDB Online ini adalah BENAR dan sesuai dengan dokumen asli. Apabila di kemudian hari ditemukan ketidaksesuaian data, saya bersedia menerima sanksi pembatalan kelulusan.</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-4">
                        <button type="submit" class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-extrabold text-xs shadow-xl shadow-indigo-600/30 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-paper-plane text-xs"></i> Kirim Pendaftaran Sekarang
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- FAQ SECTION -->
    <section id="faq" class="py-16 px-4 sm:px-6 lg:px-8 bg-slate-950 border-t border-slate-800/80">
        <div class="max-w-4xl mx-auto space-y-8">
            <div class="text-center space-y-2">
                <span class="text-xs font-extrabold text-indigo-400 uppercase tracking-widest">Tanya Jawab</span>
                <h2 class="text-2xl sm:text-3xl font-black text-white">Pertanyaan Sering Diajukan (FAQ)</h2>
            </div>

            <div class="space-y-4">
                <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800">
                    <h4 class="text-sm font-bold text-white mb-2">Berapa biaya pendaftaran PPDB Online?</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Pendaftaran PPDB Online sepenuhnya GRATIS tanpa dipungut biaya apapun.</p>
                </div>
                <div class="p-5 rounded-2xl bg-slate-900 border border-slate-800">
                    <h4 class="text-sm font-bold text-white mb-2">Bagaimana jika salah menginput data pendaftaran?</h4>
                    <p class="text-xs text-slate-400 leading-relaxed">Anda dapat menghubungi panitia PPDB melalui nomor WhatsApp sekolah atau email resmi sekolah dengan menyertakan Nomor Registrasi pendaftaran Anda.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="mt-auto bg-slate-900 border-t border-slate-800 py-8 px-4 sm:px-8 text-xs text-slate-400">
        <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 font-bold text-white">
                <i class="fa-solid fa-graduation-cap text-indigo-500 text-base"></i>
                <span>PPDB ONLINE 2026 • <?= htmlspecialchars(get_setting('school_name')); ?></span>
            </div>
            <div><?= htmlspecialchars(get_setting('footer_text')); ?></div>
        </div>
    </footer>

    <!-- Accurate Countdown Timer Script -->
    <script>
        const targetDate = new Date("<?= $countdownTarget; ?>").getTime();

        function updatePpdbCountdown() {
            const now = Date.now();
            const diff = targetDate - now;

            if (diff <= 0) {
                document.getElementById('ppdb-countdown-timer').innerHTML = `
                    <div class="col-span-4 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/30 text-rose-400 font-extrabold text-sm">
                        🚨 PPDB Gelombang 1 Telah Resmi Ditutup
                    </div>`;
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById('cd-days').innerText = String(days).padStart(2, '0');
            document.getElementById('cd-hours').innerText = String(hours).padStart(2, '0');
            document.getElementById('cd-minutes').innerText = String(minutes).padStart(2, '0');
            document.getElementById('cd-seconds').innerText = String(seconds).padStart(2, '0');
        }

        function openModal(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
            }
        }

        function closeModal(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        }

        function toggleCustomSchool(val, targetId) {
            const el = document.getElementById(targetId);
            if (el) {
                if (val === 'Lainnya') {
                    el.classList.remove('hidden');
                    el.focus();
                } else {
                    el.classList.add('hidden');
                    el.value = '';
                }
            }
        }

        updatePpdbCountdown();
        setInterval(updatePpdbCountdown, 1000);
    </script>
</body>
</html>
