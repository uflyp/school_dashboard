<?php
// config.php - System Informasi Sekolah V2.0 Engine & Database Helper
date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session Timeout Management (30 Minutes Inactivity)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['auth_error'] = "Sesi Anda telah berakhir karena inaktivitas. Silakan login kembali.";
}
$_SESSION['last_activity'] = time();

$db_file = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Auto-migration check: verify if users table has role_id column
    $userCols = [];
    try {
        $colsStmt = $pdo->query("PRAGMA table_info(users)");
        while ($col = $colsStmt->fetch()) {
            $userCols[] = $col['name'];
        }
    } catch (Exception $e) {}

    // If old V1 schema exists (role column instead of role_id), reset tables for V2.0 clean schema
    if (!empty($userCols) && !in_array('role_id', $userCols)) {
        $pdo->exec("DROP TABLE IF EXISTS users;");
        $pdo->exec("DROP TABLE IF EXISTS roles;");
    }

    // 1. Core Tables Definition
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            display_name TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            name TEXT NOT NULL,
            role_id INTEGER NOT NULL,
            jenis_kelamin TEXT DEFAULT 'L',
            avatar TEXT,
            status TEXT DEFAULT 'active',
            last_login TEXT,
            last_activity TEXT,
            FOREIGN KEY (role_id) REFERENCES roles(id)
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        );

        CREATE TABLE IF NOT EXISTS running_text (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content TEXT NOT NULL,
            is_active INTEGER DEFAULT 1,
            start_date TEXT,
            end_date TEXT
        );

        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            event_date TEXT NOT NULL,
            is_countdown INTEGER DEFAULT 0,
            is_popup INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1
        );

        CREATE TABLE IF NOT EXISTS berita (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT UNIQUE NOT NULL,
            content TEXT NOT NULL,
            thumbnail TEXT,
            author TEXT,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS kelas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_kelas TEXT UNIQUE NOT NULL,
            wali_kelas TEXT NOT NULL,
            jumlah_siswa INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS mapel (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_mapel TEXT UNIQUE NOT NULL,
            kode TEXT UNIQUE NOT NULL,
            pengampu TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS siswa (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nis TEXT UNIQUE NOT NULL,
            nisn TEXT NOT NULL,
            nama TEXT NOT NULL,
            kelas TEXT NOT NULL,
            jenis_kelamin TEXT NOT NULL,
            tanggal_lahir TEXT,
            alamat TEXT,
            nama_ortu TEXT
        );

        CREATE TABLE IF NOT EXISTS guru (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nip TEXT UNIQUE NOT NULL,
            nama TEXT NOT NULL,
            mata_pelajaran TEXT NOT NULL,
            email TEXT,
            no_hp TEXT,
            jenis_kelamin TEXT DEFAULT 'L'
        );

        CREATE TABLE IF NOT EXISTS orangtua (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            nis_anak TEXT NOT NULL,
            nama_anak TEXT NOT NULL,
            no_hp TEXT,
            pekerjaan TEXT
        );

        CREATE TABLE IF NOT EXISTS pengumuman (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul TEXT NOT NULL,
            isi TEXT NOT NULL,
            kategori TEXT DEFAULT 'Umum',
            tanggal TEXT NOT NULL,
            created_by TEXT
        );

        CREATE TABLE IF NOT EXISTS spp_transaksi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nis TEXT NOT NULL,
            nama_siswa TEXT NOT NULL,
            bulan TEXT NOT NULL,
            tahun TEXT NOT NULL,
            nominal INTEGER NOT NULL,
            tanggal_bayar TEXT,
            status TEXT NOT NULL DEFAULT 'Belum Lunas',
            metode_pembayaran TEXT
        );

        CREATE TABLE IF NOT EXISTS nilai (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nis TEXT NOT NULL,
            mata_pelajaran TEXT NOT NULL,
            semester TEXT NOT NULL,
            nilai_tugas INTEGER,
            nilai_uts INTEGER,
            nilai_uas INTEGER,
            predikat TEXT,
            catatan TEXT
        );

        CREATE TABLE IF NOT EXISTS absensi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nis TEXT NOT NULL,
            tanggal TEXT NOT NULL,
            status TEXT NOT NULL,
            keterangan TEXT
        );

        CREATE TABLE IF NOT EXISTS materi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER DEFAULT 3,
            judul TEXT NOT NULL,
            mapel TEXT NOT NULL,
            kelas TEXT NOT NULL,
            deskripsi TEXT,
            file_path TEXT,
            created_by TEXT,
            tanggal TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS tugas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER DEFAULT 3,
            judul TEXT NOT NULL,
            mapel TEXT NOT NULL,
            kelas TEXT NOT NULL,
            deadline TEXT NOT NULL,
            instruksi TEXT,
            created_by TEXT
        );

        CREATE TABLE IF NOT EXISTS tugas_jawaban (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tugas_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            siswa_nama TEXT,
            catatan TEXT,
            file_path TEXT NOT NULL,
            file_name TEXT,
            file_type TEXT,
            file_size INTEGER,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT,
            action TEXT NOT NULL,
            ip_address TEXT,
            timestamp TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS galeri (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul TEXT NOT NULL,
            kategori TEXT NOT NULL,
            url_gambar TEXT NOT NULL,
            tanggal TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS prestasi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            judul TEXT NOT NULL,
            tingkat TEXT NOT NULL,
            peraih TEXT NOT NULL,
            tahun TEXT NOT NULL,
            kategori TEXT DEFAULT 'Siswa'
        );

        CREATE TABLE IF NOT EXISTS alumni (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nis TEXT NOT NULL,
            nama TEXT NOT NULL,
            tahun_lulus TEXT NOT NULL,
            kuliah_kerja TEXT NOT NULL,
            kontak TEXT
        );

        CREATE TABLE IF NOT EXISTS inventaris (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_barang TEXT NOT NULL,
            kode_barang TEXT UNIQUE NOT NULL,
            jumlah INTEGER NOT NULL,
            kondisi TEXT NOT NULL,
            lokasi TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS surat (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nomor_surat TEXT UNIQUE NOT NULL,
            perihal TEXT NOT NULL,
            jenis TEXT NOT NULL,
            pengirim_penerima TEXT NOT NULL,
            tanggal TEXT NOT NULL,
            status TEXT DEFAULT 'Diproses'
        );

        CREATE TABLE IF NOT EXISTS jadwal_pelajaran (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hari TEXT NOT NULL,
            jam_mulai TEXT NOT NULL,
            jam_selesai TEXT NOT NULL,
            kelas_nama TEXT NOT NULL,
            mapel_nama TEXT NOT NULL,
            guru_nama TEXT NOT NULL,
            guru_id INTEGER DEFAULT 0,
            ruang TEXT DEFAULT 'R. Kelas'
        );

        CREATE TABLE IF NOT EXISTS ppdb (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            no_pendaftaran TEXT UNIQUE NOT NULL,
            nisn TEXT,
            nik TEXT,
            nama_lengkap TEXT NOT NULL,
            tempat_lahir TEXT,
            tanggal_lahir TEXT,
            jenis_kelamin TEXT,
            agama TEXT,
            anak_ke INTEGER,
            jumlah_saudara INTEGER,
            alamat_lengkap TEXT,
            provinsi TEXT,
            kabupaten TEXT,
            kecamatan TEXT,
            kelurahan TEXT,
            kode_pos TEXT,
            nama_sekolah_asal TEXT,
            npsn TEXT,
            alamat_sekolah_asal TEXT,
            tahun_lulus TEXT,
            nama_ayah TEXT,
            nik_ayah TEXT,
            pendidikan_ayah TEXT,
            pekerjaan_ayah TEXT,
            penghasilan_ayah TEXT,
            nama_ibu TEXT,
            nik_ibu TEXT,
            pendidikan_ibu TEXT,
            pekerjaan_ibu TEXT,
            penghasilan_ibu TEXT,
            nama_wali TEXT,
            nik_wali TEXT,
            pekerjaan_wali TEXT,
            no_hp_wali TEXT,
            no_hp_siswa TEXT,
            no_hp_ortu TEXT,
            email TEXT,
            file_pas_foto TEXT,
            file_kk TEXT,
            file_akta TEXT,
            file_rapor TEXT,
            file_ijazah TEXT,
            file_kip TEXT,
            status TEXT DEFAULT 'Menunggu Verifikasi',
            catatan_admin TEXT,
            created_at TEXT NOT NULL
        );
    ");

    // Seed Roles if empty
    $roleCount = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    if ($roleCount == 0) {
        $stmtRole = $pdo->prepare("INSERT INTO roles (id, name, display_name) VALUES (?, ?, ?)");
        $rolesData = [
            [1, 'admin', 'Administrator'],
            [2, 'kepala_sekolah', 'Kepala Sekolah'],
            [3, 'guru', 'Guru / Tenaga Pendidik'],
            [4, 'keuangan', 'Staf Keuangan'],
            [5, 'siswa', 'Siswa'],
            [6, 'orangtua', 'Orang Tua / Wali']
        ];
        foreach ($rolesData as $r) {
            $stmtRole->execute($r);
        }
    }

    // Seed Initial Settings if empty
    $settingCount = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($settingCount == 0) {
        $stmtSetting = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
        $settingsData = [
            ['school_name', 'SMA NUSANTARA JAKARTA'],
            ['school_tagline', 'Excellence in Education & Character Building'],
            ['school_email', 'info@sma-nusantara.sch.id'],
            ['school_phone', '(021) 7890123 / 0812-3456-7890'],
            ['school_address', 'Jl. Merdeka Raya No. 100, Jakarta Selatan'],
            ['hero_title', 'Membentuk Generasi Cerdas, Berkarakter & Unggul'],
            ['hero_subtitle', 'Selamat datang di portal akademik terpadu SMA Nusantara. Akses terintegrasi untuk Siswa, Guru, Orang Tua, Keuangan, dan Manajemen dalam satu platform modern.'],
            ['ppdb_status', 'Buka Gelombang 1'],
            ['footer_text', 'SMA Nusantara Jakarta © 2026 • Hak Cipta Dilindungi System V2.0'],
            ['site_theme_color', 'indigo']
        ];
        foreach ($settingsData as $set) {
            $stmtSetting->execute($set);
        }
    }
    // Ensure jenis_kelamin & last_activity column exists in users & guru tables
    try { $pdo->exec("ALTER TABLE users ADD COLUMN jenis_kelamin TEXT DEFAULT 'L'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN last_activity TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE guru ADD COLUMN jenis_kelamin TEXT DEFAULT 'L'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE materi ADD COLUMN user_id INTEGER DEFAULT 3"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE tugas ADD COLUMN user_id INTEGER DEFAULT 3"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE materi SET user_id = 3 WHERE user_id IS NULL OR user_id = 0"); } catch (Exception $e) {}
    try { $pdo->exec("UPDATE tugas SET user_id = 3 WHERE user_id IS NULL OR user_id = 0"); } catch (Exception $e) {}

    // Update logged in user activity timestamp
    if (isset($_SESSION['user_id'])) {
        if (!isset($_SESSION['last_db_activity_time']) || (time() - $_SESSION['last_db_activity_time'] >= 30)) {
            try {
                $stmtAct = $pdo->prepare("UPDATE users SET last_activity = ? WHERE id = ?");
                $stmtAct->execute([date('Y-m-d H:i:s'), $_SESSION['user_id']]);
                $_SESSION['last_db_activity_time'] = time();
            } catch (Exception $e) {}
        }
    }

    // Seed Users if table is completely empty
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($userCount == 0) {
        $pdo->exec("DELETE FROM users;");
        $stmtUser = $pdo->prepare("INSERT INTO users (username, email, password, name, role_id, jenis_kelamin, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $usersSeed = [
            ['admin', 'admin@sekolah.sch.id', password_hash('admin123', PASSWORD_DEFAULT), 'Dr. H. Ahmad Sanusi, M.Pd.', 1, 'L', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150'],
            ['kepsek', 'kepsek@sekolah.sch.id', password_hash('kepsek123', PASSWORD_DEFAULT), 'Prof. Dr. Bambang Sudrajat, M.Ed.', 2, 'L', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150'],
            ['guru', 'guru@sekolah.sch.id', password_hash('guru123', PASSWORD_DEFAULT), 'Dra. Endang Lestari, M.Si.', 3, 'P', 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=150'],
            ['keuangan', 'keuangan@sekolah.sch.id', password_hash('keuangan123', PASSWORD_DEFAULT), 'Siti Rahmawati, S.E.', 4, 'P', 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=150'],
            ['siswa', 'bintang@siswa.sch.id', password_hash('siswa123', PASSWORD_DEFAULT), 'Bintang Pratama', 5, 'L', 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150'],
            ['ortu', 'budi.santoso@gmail.com', password_hash('ortu123', PASSWORD_DEFAULT), 'Ir. Budi Santoso', 6, 'L', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150']
        ];
        foreach ($usersSeed as $u) {
            $stmtUser->execute($u);
        }
    }

    // Update existing user/guru gender records
    $pdo->exec("UPDATE users SET jenis_kelamin = 'P' WHERE username IN ('guru', 'keuangan')");
    $pdo->exec("UPDATE users SET jenis_kelamin = 'L' WHERE username IN ('admin', 'kepsek', 'siswa', 'ortu')");

    // Seed Kelas if empty
    $kelasCount = $pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
    if ($kelasCount == 0) {
        $pdo->exec("INSERT INTO kelas (nama_kelas, wali_kelas, jumlah_siswa) VALUES 
            ('X IPA 1', 'Nadia Syahfitri, S.Pd', 32),
            ('XI IPA 1', 'Bambang Hermawan, M.Si', 30),
            ('XII IPA 1', 'Dra. Endang Lestari, M.Si', 35),
            ('XII IPS 1', 'Drs. H. Mulyono', 28);
        ");
    }

    // Seed Mapel if empty
    $mapelCount = $pdo->query("SELECT COUNT(*) FROM mapel")->fetchColumn();
    if ($mapelCount == 0) {
        $pdo->exec("INSERT INTO mapel (nama_mapel, kode, pengampu) VALUES 
            ('Matematika Wajib', 'MTK-101', 'Dra. Endang Lestari, M.Si'),
            ('Fisika Kuantum', 'FIS-102', 'Bambang Hermawan, M.Si'),
            ('Bahasa Inggris', 'BIG-103', 'Nadia Syahfitri, S.Pd'),
            ('Bahasa Indonesia', 'BIN-104', 'Drs. H. Mulyono');
        ");
    }

    // Seed Running Text if empty
    $rtCount = $pdo->query("SELECT COUNT(*) FROM running_text")->fetchColumn();
    if ($rtCount == 0) {
        $pdo->exec("INSERT INTO running_text (content, is_active) VALUES ('📢 Penerimaan Peserta Didik Baru (PPDB) T.A. 2026/2027 Gelombang I Resmi Dibuka! Dapatkan Beasiswa Prestasi bagi 10 Pendaftar Pertama. Hubungi Sekretariat PPDB.', 1)");
    }

    // Seed Events & Countdown if empty
    $evtCount = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    if ($evtCount == 0) {
        $stmtEvt = $pdo->prepare("INSERT INTO events (title, description, event_date, is_countdown, is_popup, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtEvt->execute(['Pendaftaran PPDB Gelombang I Ditutup', 'Batas akhir penyerahan berkas dan verifikasi data calon siswa baru.', '2026-08-25 23:59:00', 1, 1, 1]);
        $stmtEvt->execute(['Upacara Bendera HUT Kemerdekaan RI ke-81', 'Seluruh siswa dan guru wajib memakai seragam kebesaran.', '2026-08-17 07:00:00', 0, 0, 1]);
    }

    // Seed Berita if empty
    $beritaCount = $pdo->query("SELECT COUNT(*) FROM berita")->fetchColumn();
    if ($beritaCount == 0) {
        $stmtB = $pdo->prepare("INSERT INTO berita (title, slug, content, thumbnail, author, created_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtB->execute([
            'Tim Robotik SMA Nusantara Raih Juara 1 Tingkat Nasional',
            'tim-robotik-juara-1',
            'Selamat kepada Tim Robotik SMA Nusantara yang berhasil meraih Juara 1 pada Kompetisi Robotika Nasional 2026 di Yogyakarta.',
            'https://images.unsplash.com/photo-1561557944-6e7860d1a7eb?w=600&q=80',
            'Humas Sekolah',
            '2026-07-28'
        ]);
        $stmtB->execute([
            'Pelaksanaan Ujian Akhir Semester Berbasis Komputer (CBT)',
            'pelaksanaan-uas-cbt',
            'Seluruh persiapan infrastruktur server dan jaringan laboratorium komputer sekolah telah siap 100% untuk ujian CBT.',
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80',
            'Tim IT Sekolah',
            '2026-07-25'
        ]);
    }

    // Seed Siswa if empty
    $siswaCount = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
    if ($siswaCount == 0) {
        $pdo->exec("INSERT INTO siswa (nis, nisn, nama, kelas, jenis_kelamin, tanggal_lahir, alamat, nama_ortu) VALUES 
            ('2026001', '0054891201', 'Bintang Pratama', 'XII IPA 1', 'Laki-laki', '2008-04-12', 'Jl. Wijaya No. 12, Jakarta', 'Ir. Budi Santoso'),
            ('2026002', '0054891202', 'Anisa Rahmawati', 'XII IPA 1', 'Perempuan', '2008-07-22', 'Jl. Tebet Barat No. 45, Jakarta', 'H. Ahmad Subandi'),
            ('2026003', '0054891203', 'Rizky Febrian', 'XII IPS 1', 'Laki-laki', '2008-02-15', 'Jl. Kemang Selatan No. 8', 'Suryanto, S.E.');
        ");
    }

    // Seed Guru if empty
    $guruCount = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
    if ($guruCount == 0) {
        $pdo->exec("INSERT INTO guru (nip, nama, mata_pelajaran, email, no_hp) VALUES 
            ('198501152010011002', 'Dra. Endang Lestari, M.Si.', 'Matematika Wajib', 'guru@sekolah.sch.id', '081298765432'),
            ('198203202008021001', 'Bambang Hermawan, M.Si', 'Fisika Kuantum', 'bambang@sekolah.sch.id', '081311223344'),
            ('199005122015032004', 'Nadia Syahfitri, S.Pd', 'Bahasa Inggris', 'nadia@sekolah.sch.id', '081599887766');
        ");
    }

    // Seed SPP if empty
    $sppCount = $pdo->query("SELECT COUNT(*) FROM spp_transaksi")->fetchColumn();
    if ($sppCount == 0) {
        $pdo->exec("INSERT INTO spp_transaksi (nis, nama_siswa, bulan, tahun, nominal, tanggal_bayar, status, metode_pembayaran) VALUES 
            ('2026001', 'Bintang Pratama', 'Juli', '2026', 750000, '2026-07-05 10:30:00', 'Lunas', 'Transfer Transfer Bank BCA'),
            ('2026001', 'Bintang Pratama', 'Agustus', '2026', 750000, NULL, 'Belum Lunas', NULL),
            ('2026002', 'Anisa Rahmawati', 'Juli', '2026', 750000, '2026-07-08 14:15:00', 'Lunas', 'Tunai Kasir Sekolah');
        ");
    }

    // Seed Nilai if empty
    $nilaiCount = $pdo->query("SELECT COUNT(*) FROM nilai")->fetchColumn();
    if ($nilaiCount == 0) {
        $pdo->exec("INSERT INTO nilai (nis, mata_pelajaran, semester, nilai_tugas, nilai_uts, nilai_uas, predikat, catatan) VALUES 
            ('2026001', 'Matematika Wajib', 'Ganjil 2026', 90, 88, 92, 'A', 'Sangat menguasai materi kalkulus'),
            ('2026001', 'Fisika Kuantum', 'Ganjil 2026', 85, 84, 88, 'A-', 'Aktif dalam praktikum laboratorium'),
            ('2026001', 'Bahasa Inggris', 'Ganjil 2026', 95, 90, 94, 'A', 'Grammar dan speaking fluent');
        ");
    }

    // Seed Absensi if empty
    $absCount = $pdo->query("SELECT COUNT(*) FROM absensi")->fetchColumn();
    if ($absCount == 0) {
        $pdo->exec("INSERT INTO absensi (nis, tanggal, status, keterangan) VALUES 
            ('2026001', '2026-07-28', 'Hadir', 'Hadir Tepat Waktu'),
            ('2026001', '2026-07-29', 'Hadir', 'Hadir Tepat Waktu'),
            ('2026001', '2026-07-30', 'Hadir', 'Hadir Tepat Waktu'),
            ('2026001', '2026-07-31', 'Hadir', 'Hadir Tepat Waktu');
        ");
    }

    // Seed Materi & Tugas for Guru
    $materiCount = $pdo->query("SELECT COUNT(*) FROM materi")->fetchColumn();
    if ($materiCount == 0) {
        $pdo->exec("INSERT INTO materi (judul, mapel, kelas, deskripsi, created_by, tanggal) VALUES 
            ('Modul Kalkulus & Turunan Fungsi', 'Matematika Wajib', 'XII IPA 1', 'Bahan ajar materi turunan fungsi aljabar semester genap.', 'Dra. Endang Lestari, M.Si.', '2026-07-29'),
            ('Panduan Praktikum Fisika Kuantum', 'Fisika', 'XII IPA 1', 'Modul panduan langkah percobaan fotolistrik.', 'Bambang Hermawan, M.Si', '2026-07-20');
        ");
    }

    // Migrations for Siswa table (tempat_lahir, tahun_ajaran, foto, status_siswa)
    try { $pdo->exec("ALTER TABLE siswa ADD COLUMN tempat_lahir TEXT DEFAULT 'Jakarta'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE siswa ADD COLUMN tahun_ajaran TEXT DEFAULT '2025/2026'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE siswa ADD COLUMN foto TEXT"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE siswa ADD COLUMN status_siswa TEXT DEFAULT 'Aktif'"); } catch (Exception $e) {}

    // Seed Galeri if empty
    $galeriCount = $pdo->query("SELECT COUNT(*) FROM galeri")->fetchColumn();
    if ($galeriCount == 0) {
        $pdo->exec("INSERT INTO galeri (judul, kategori, url_gambar, tanggal) VALUES 
            ('Upacara Bendera HUT RI', 'Kegiatan', 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?w=600', '2026-07-17'),
            ('Praktikum Kimia Laboratorium', 'Akademik', 'https://images.unsplash.com/photo-1561557944-6e7860d1a7eb?w=600', '2026-07-22');
        ");
    }

    // Seed Prestasi if empty
    $presCount = $pdo->query("SELECT COUNT(*) FROM prestasi")->fetchColumn();
    if ($presCount == 0) {
        $pdo->exec("INSERT INTO prestasi (judul, tingkat, peraih, tahun, kategori) VALUES 
            ('Juara 1 Robotika Nasional', 'Nasional', 'Tim Robotik SMA Nusantara', '2026', 'Siswa'),
            ('Medali Emas OSN Matematika', 'Nasional', 'Bintang Pratama', '2025', 'Siswa');
        ");
    }

    // Seed Alumni if empty
    $alumniCount = $pdo->query("SELECT COUNT(*) FROM alumni")->fetchColumn();
    if ($alumniCount == 0) {
        $pdo->exec("INSERT INTO alumni (nis, nama, tahun_lulus, kuliah_kerja, kontak) VALUES 
            ('2024012', 'Muhammad Rizky, S.T.', '2024', 'Teknik Informatika ITB', '081234567890'),
            ('2024045', 'Siti Nurhaliza, S.Ked.', '2024', 'Fakultas Kedokteran UI', '081987654321');
        ");
    }

    // Seed Inventaris if empty
    $invCount = $pdo->query("SELECT COUNT(*) FROM inventaris")->fetchColumn();
    if ($invCount == 0) {
        $pdo->exec("INSERT INTO inventaris (nama_barang, kode_barang, jumlah, kondisi, lokasi) VALUES 
            ('Proyektor Epson EB-X400', 'INV-PRJ-01', 12, 'Baik', 'Lab Komputer & Kelas XII'),
            ('Laptop Asus Core i7', 'INV-LPT-02', 30, 'Baik', 'Ruang IT & Multimedia');
        ");
    }

    // Seed Surat if empty
    $suratCount = $pdo->query("SELECT COUNT(*) FROM surat")->fetchColumn();
    if ($suratCount == 0) {
        $pdo->exec("INSERT INTO surat (nomor_surat, perihal, jenis, pengirim_penerima, tanggal, status) VALUES 
            ('421.3/001/SMA-NS/2026', 'Undangan Rapat Orang Tua Siswa', 'Surat Keluar', 'Orang Tua / Wali Murid Kelas XII', '2026-07-25', 'Terkirim'),
            ('005/Dinas-Edu/VII/2026', 'Pemberitahuan Akreditasi Sekolah', 'Surat Masuk', 'Dinas Pendidikan DKI Jakarta', '2026-07-20', 'Diterima');
        ");
    }

    // Seed Jadwal Pelajaran if empty
    $jadwalCount = $pdo->query("SELECT COUNT(*) FROM jadwal_pelajaran")->fetchColumn();
    if ($jadwalCount == 0) {
        $pdo->exec("INSERT INTO jadwal_pelajaran (hari, jam_mulai, jam_selesai, kelas_nama, mapel_nama, guru_nama, guru_id, ruang) VALUES 
            ('Senin', '07:30', '09:00', 'XII IPA 1', 'Matematika Wajib', 'Dra. Endang Lestari, M.Si.', 1, 'R. 301'),
            ('Senin', '09:30', '11:00', 'XI IPA 1', 'Fisika Kuantum', 'Bambang Hermawan, M.Si', 2, 'R. 204'),
            ('Selasa', '08:00', '10:00', 'XII IPS 1', 'Matematika Wajib', 'Dra. Endang Lestari, M.Si.', 1, 'R. 305'),
            ('Rabu', '10:00', '12:00', 'X IPA 1', 'Matematika Wajib', 'Dra. Endang Lestari, M.Si.', 1, 'R. 102'),
            ('Kamis', '07:30', '09:30', 'XII IPA 1', 'Fisika Kuantum', 'Bambang Hermawan, M.Si', 2, 'R. 301'),
            ('Jumat', '08:00', '09:30', 'XI IPA 1', 'Bahasa Inggris', 'Nadia Syahfitri, S.Pd', 3, 'R. 204');
        ");
    }

} catch (PDOException $e) {
    die("Connection error: " . $e->getMessage());
}

// Security CSRF Helper Functions
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function verify_csrf_token() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            // Generate a fresh new token for safety
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // If user is submitting login form
            if (str_contains($_SERVER['PHP_SELF'] ?? '', 'login.php')) {
                $_SESSION['auth_error'] = "Sesi formulir login telah diperbarui demi keamanan. Silakan klik Masuk kembali.";
                header("Location: login.php");
                exit();
            }

            // Set flash message and redirect back gracefully
            $_SESSION['flash_error'] = "Sesi keamanan formulir telah diperbarui. Silakan ulangi penyimpanan/tindakan Anda.";
            $referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
            header("Location: " . $referer);
            exit();
        }
    }
}

// Helpers
function get_setting($key, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = ?");
    $stmt->execute([$key]);
    $res = $stmt->fetchColumn();
    return $res !== false ? $res : $default;
}

function log_activity($action) {
    global $pdo;
    $username = $_SESSION['username'] ?? 'guest';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $timestamp = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO activity_logs (username, action, ip_address, timestamp) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $action, $ip, $timestamp]);
}

function format_log_timestamp($datetimeStr) {
    if (empty($datetimeStr)) return '-';
    $time = is_numeric($datetimeStr) ? (int)$datetimeStr : strtotime($datetimeStr);
    if (!$time) return htmlspecialchars($datetimeStr);
    return date('d-m-Y H:i:s', $time) . ' WIB';
}

function check_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

function check_role($allowed_roles = []) {
    check_login();
    $currentRole = $_SESSION['role'] ?? '';
    if (!in_array($currentRole, $allowed_roles, true)) {
        header("Location: dashboard.php");
        exit();
    }
}

function current_user() {
    return [
        'id'           => $_SESSION['user_id'] ?? null,
        'username'     => $_SESSION['username'] ?? '',
        'name'         => $_SESSION['name'] ?? 'User',
        'role'         => $_SESSION['role'] ?? 'guest',
        'role_display' => $_SESSION['role_display'] ?? 'Guest',
        'email'        => $_SESSION['email'] ?? '',
        'avatar'       => $_SESSION['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150',
        'jenis_kelamin'=> $_SESSION['jenis_kelamin'] ?? 'L',
    ];
}

function export_to_csv($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

function format_datetime($datetimeStr) {
    if (empty($datetimeStr)) return '-';
    $time = is_numeric($datetimeStr) ? (int)$datetimeStr : strtotime($datetimeStr);
    if (!$time) return '-';
    
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', $time);
    $bln = $bulanIndo[(int)date('m', $time)] ?? date('F', $time);
    $thn = date('Y', $time);
    $jam = date('H:i', $time);
    
    return "{$tgl} {$bln} {$thn} {$jam} WIB";
}

function format_date($dateStr) {
    if (empty($dateStr)) return '-';
    $time = is_numeric($dateStr) ? (int)$dateStr : strtotime($dateStr);
    if (!$time) return '-';
    
    $bulanIndo = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $tgl = date('d', $time);
    $bln = $bulanIndo[(int)date('m', $time)] ?? date('F', $time);
    $thn = date('Y', $time);
    
    return "{$tgl} {$bln} {$thn}";
}

function time_ago($datetimeStr) {
    if (empty($datetimeStr)) return 'Belum pernah';
    $time = is_numeric($datetimeStr) ? (int)$datetimeStr : strtotime($datetimeStr);
    if (!$time) return 'Belum pernah';
    
    $diff = time() - $time;
    if ($diff < 60) return 'Baru saja';
    if ($diff < 3600) return floor($diff / 60) . ' menit yang lalu';
    if ($diff < 86400) return floor($diff / 3600) . ' jam yang lalu';
    if ($diff < 2592000) return floor($diff / 86400) . ' hari yang lalu';
    return format_datetime($datetimeStr);
}

function get_ppdb_status() {
    $start = get_setting('ppdb_start_date', '2026-05-01 08:00:00');
    $end = get_setting('ppdb_end_date', '2026-08-31 23:59:59');

    $now = time();
    $startTime = strtotime($start);
    $endTime = strtotime($end);

    if ($now < $startTime) {
        return [
            'code' => 'BELUM_DIMULAI',
            'label' => 'BELUM DIMULAI',
            'color' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
            'is_open' => false,
            'message' => 'Pendaftaran PPDB 2026 Belum Dimulai (Buka: ' . format_datetime($start) . ')'
        ];
    } elseif ($now >= $startTime && $now <= $endTime) {
        return [
            'code' => 'DIBUKA',
            'label' => 'DIBUKA',
            'color' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'is_open' => true,
            'message' => 'Pendaftaran PPDB 2026 Resmi DIBUKA (s.d ' . format_datetime($end) . ')'
        ];
    } else {
        return [
            'code' => 'DITUTUP',
            'label' => 'DITUTUP',
            'color' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
            'is_open' => false,
            'message' => 'Pendaftaran PPDB 2026 Telah Resmi DITUTUP'
        ];
    }
}

/**
 * Helper Kategori & Predikat Akreditasi GPA Akademik
 * GPA >= 3.51: Eligible for High Honors (Sangat Istimewa)
 * GPA 3.00 - 3.50: Eligible for Honors (Dengan Pujian)
 * GPA 2.00 - 2.99: Satisfactory (Memenuhi Syarat/Lulus)
 * GPA < 2.00: Academic Probation (Butuh Perbaikan/Percobaan Akademik)
 */
function get_gpa_info($gpaFloat) {
    $gpa = floatval($gpaFloat);
    if ($gpa >= 3.51) {
        return [
            'gpa' => number_format($gpa, 2),
            'predicate' => 'Eligible for High Honors (Sangat Istimewa)',
            'short' => 'High Honors',
            'badge' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'color' => 'text-emerald-400',
            'bg' => 'bg-emerald-500/10',
            'border' => 'border-emerald-500/30'
        ];
    } elseif ($gpa >= 3.00) {
        return [
            'gpa' => number_format($gpa, 2),
            'predicate' => 'Eligible for Honors (Dengan Pujian)',
            'short' => 'Honors',
            'badge' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
            'color' => 'text-indigo-400',
            'bg' => 'bg-indigo-500/10',
            'border' => 'border-indigo-500/30'
        ];
    } elseif ($gpa >= 2.00) {
        return [
            'gpa' => number_format($gpa, 2),
            'predicate' => 'Satisfactory (Memenuhi Syarat/Lulus)',
            'short' => 'Satisfactory',
            'badge' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
            'color' => 'text-blue-400',
            'bg' => 'bg-blue-500/10',
            'border' => 'border-blue-500/30'
        ];
    } else {
        return [
            'gpa' => number_format($gpa, 2),
            'predicate' => 'Academic Probation (Butuh Perbaikan/Percobaan Akademik)',
            'short' => 'Academic Probation',
            'badge' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
            'color' => 'text-rose-400',
            'bg' => 'bg-rose-500/10',
            'border' => 'border-rose-500/30'
        ];
    }
}

