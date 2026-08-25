<?php
// views/alumni/tracer.php - Modul Pengisian & Update Kuesioner Tracer Study Alumni
check_role(['alumni', 'admin']);

$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 0);
$userName = $user['name'] ?? ($_SESSION['name'] ?? 'Alumni');

// Ambil data alumni dasar
$stmtAl = $pdo->prepare("SELECT * FROM alumni WHERE nama = ? OR nis = ? LIMIT 1");
$stmtAl->execute([$userName, $userName]);
$alumniData = $stmtAl->fetch(PDO::FETCH_ASSOC);

$defaultNis = $alumniData['nis'] ?? '202401001';
$defaultTahun = $alumniData['tahun_lulus'] ?? date('Y');

$msg = '';
$msgType = 'success';

// Handle Submit / Update Tracer Study
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();

    if ($_POST['action'] === 'simpan_tracer') {
        $nis = trim($_POST['nis'] ?? $defaultNis);
        $nama = trim($_POST['nama'] ?? $userName);
        $tahun_lulus = trim($_POST['tahun_lulus'] ?? '');
        $status_aktivitas = trim($_POST['status_aktivitas'] ?? '');
        $pendidikan_lanjutan = trim($_POST['pendidikan_lanjutan'] ?? '');
        $nama_perusahaan = trim($_POST['nama_perusahaan'] ?? '');
        $bidang_pekerjaan = trim($_POST['bidang_pekerjaan'] ?? '');
        $posisi_jabatan = trim($_POST['posisi_jabatan'] ?? '');
        $kesesuaian_pekerjaan = trim($_POST['kesesuaian_pekerjaan'] ?? '');
        $waktu_memperoleh_kerja = trim($_POST['waktu_memperoleh_kerja'] ?? '');
        $feedback_sekolah = trim($_POST['feedback_sekolah'] ?? '');
        $saran_sekolah = trim($_POST['saran_sekolah'] ?? '');
        $now = date('Y-m-d H:i:s');

        if (empty($tahun_lulus) || empty($status_aktivitas)) {
            $msg = "Tahun Lulus dan Status Aktivitas saat ini wajib diisi!";
            $msgType = 'error';
        } else {
            // Cek apakah user sudah pernah submit tracer
            $stmtCheck = $pdo->prepare("SELECT id FROM tracer_study WHERE user_id = ? OR (nis = ? AND nis != '') LIMIT 1");
            $stmtCheck->execute([$userId, $nis]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $stmtUpdate = $pdo->prepare("
                    UPDATE tracer_study 
                    SET nis = ?, nama = ?, tahun_lulus = ?, status_aktivitas = ?, 
                        pendidikan_lanjutan = ?, nama_perusahaan = ?, bidang_pekerjaan = ?, 
                        posisi_jabatan = ?, kesesuaian_pekerjaan = ?, waktu_memperoleh_kerja = ?, 
                        feedback_sekolah = ?, saran_sekolah = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmtUpdate->execute([
                    $nis, $nama, $tahun_lulus, $status_aktivitas,
                    $pendidikan_lanjutan, $nama_perusahaan, $bidang_pekerjaan,
                    $posisi_jabatan, $kesesuaian_pekerjaan, $waktu_memperoleh_kerja,
                    $feedback_sekolah, $saran_sekolah, $now, $existing['id']
                ]);
                $msg = "Data Tracer Study berhasil diperbarui pada " . date('d M Y H:i') . "!";
            } else {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO tracer_study (
                        user_id, nis, nama, tahun_lulus, status_aktivitas,
                        pendidikan_lanjutan, nama_perusahaan, bidang_pekerjaan,
                        posisi_jabatan, kesesuaian_pekerjaan, waktu_memperoleh_kerja,
                        feedback_sekolah, saran_sekolah, created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([
                    $userId, $nis, $nama, $tahun_lulus, $status_aktivitas,
                    $pendidikan_lanjutan, $nama_perusahaan, $bidang_pekerjaan,
                    $posisi_jabatan, $kesesuaian_pekerjaan, $waktu_memperoleh_kerja,
                    $feedback_sekolah, $saran_sekolah, $now, $now
                ]);
                $msg = "Terima kasih! Kuesioner Tracer Study Anda berhasil tersimpan di sistem.";
            }

            // Sync ringkasan ke tabel alumni
            $instansiClean = !empty($nama_perusahaan) ? $nama_perusahaan : (!empty($pendidikan_lanjutan) ? $pendidikan_lanjutan : $status_aktivitas);
            $stmtSync = $pdo->prepare("UPDATE alumni SET tahun_lulus = ?, kuliah_kerja = ? WHERE nama = ? OR nis = ?");
            $stmtSync->execute([$tahun_lulus, $instansiClean, $nama, $nis]);

            log_activity("Alumni submitted/updated tracer study: $nama");
        }
    }
}

// Ambil jawaban tracer terkini
$stmtTracer = $pdo->prepare("SELECT * FROM tracer_study WHERE user_id = ? OR (nis = ? AND nis != '') ORDER BY id DESC LIMIT 1");
$stmtTracer->execute([$userId, $defaultNis]);
$tracer = $stmtTracer->fetch(PDO::FETCH_ASSOC) ?: [];

$isCompleted = !empty($tracer);
?>
<div class="space-y-6 max-w-4xl">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold <?= $isCompleted ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20'; ?>">
                    <i class="fa-solid <?= $isCompleted ? 'fa-circle-check' : 'fa-clock'; ?> mr-1"></i> Status: <?= $isCompleted ? 'Sudah Mengisi' : 'Belum Mengisi'; ?>
                </span>
                <?php if ($isCompleted): ?>
                    <span class="text-[10px] text-slate-400 font-mono">Terakhir diupdate: <?= date('d/m/Y H:i', strtotime($tracer['updated_at'] ?? $tracer['created_at'])); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-list-check text-teal-400"></i> Kuesioner Tracer Study Alumni
            </h1>
            <p class="text-xs text-slate-400 mt-1">Pendataan rekam jejak karir, studi lanjut, dan evaluasi mutu lulusan sekolah</p>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border text-xs font-semibold flex items-center justify-between shadow-lg <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Form Tracer Study -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <form action="dashboard.php?page=tracer" method="POST" class="space-y-6">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="simpan_tracer">

            <!-- Bagian 1: Identitas Kelulusan -->
            <div class="space-y-4">
                <h3 class="text-sm font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2 border-b border-slate-800 pb-2">
                    <span class="w-6 h-6 rounded-lg bg-teal-500/10 text-teal-400 flex items-center justify-center text-xs">1</span> Identitas Kelulusan
                </h3>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nama Lengkap</label>
                        <input type="text" name="nama" value="<?= htmlspecialchars($tracer['nama'] ?? $userName); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">NIS Saat Sekolah</label>
                        <input type="text" name="nis" value="<?= htmlspecialchars($tracer['nis'] ?? $defaultNis); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Tahun Kelulusan (Angkatan)</label>
                        <input type="text" name="tahun_lulus" value="<?= htmlspecialchars($tracer['tahun_lulus'] ?? $defaultTahun); ?>" required placeholder="Contoh: 2024" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                    </div>
                </div>
            </div>

            <!-- Bagian 2: Status Aktivitas & Pekerjaan -->
            <div class="space-y-4 pt-2">
                <h3 class="text-sm font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2 border-b border-slate-800 pb-2">
                    <span class="w-6 h-6 rounded-lg bg-teal-500/10 text-teal-400 flex items-center justify-center text-xs">2</span> Status Aktivitas &amp; Karir Saat Ini
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Status Utama Saat Ini</label>
                        <?php $currStatus = $tracer['status_aktivitas'] ?? 'Bekerja Full-time'; ?>
                        <select name="status_aktivitas" id="select-status" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-teal-500">
                            <option value="Bekerja Full-time" <?= $currStatus === 'Bekerja Full-time' ? 'selected' : ''; ?>>Bekerja (Penuh Waktu / Full-time)</option>
                            <option value="Bekerja Part-time" <?= $currStatus === 'Bekerja Part-time' ? 'selected' : ''; ?>>Bekerja (Paruh Waktu / Freelance)</option>
                            <option value="Kuliah / Studi Lanjut" <?= $currStatus === 'Kuliah / Studi Lanjut' ? 'selected' : ''; ?>>Kuliah / Studi Lanjut di Perguruan Tinggi</option>
                            <option value="Bekerja & Kuliah" <?= $currStatus === 'Bekerja & Kuliah' ? 'selected' : ''; ?>>Bekerja Sambil Kuliah</option>
                            <option value="Wirausaha / Bisnis Mandiri" <?= $currStatus === 'Wirausaha / Bisnis Mandiri' ? 'selected' : ''; ?>>Wirausaha / Membuka Usaha Mandiri</option>
                            <option value="Mencari Kerja" <?= $currStatus === 'Mencari Kerja' ? 'selected' : ''; ?>>Sedang Mencari Pekerjaan</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Pendidikan Lanjutan (Kampus &amp; Jurusan jika kuliah)</label>
                        <input type="text" name="pendidikan_lanjutan" value="<?= htmlspecialchars($tracer['pendidikan_lanjutan'] ?? ''); ?>" placeholder="Contoh: Institut Teknologi Bandung - Teknik Informatika" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nama Perusahaan / Institusi / Usaha (Jika bekerja)</label>
                        <input type="text" name="nama_perusahaan" value="<?= htmlspecialchars($tracer['nama_perusahaan'] ?? ''); ?>" placeholder="Contoh: PT Telkom Indonesia / Startup Digital" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Bidang Sektor Pekerjaan</label>
                        <?php $currBidang = $tracer['bidang_pekerjaan'] ?? 'Teknologi Informasi'; ?>
                        <select name="bidang_pekerjaan" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-teal-500">
                            <option value="Teknologi Informasi" <?= $currBidang === 'Teknologi Informasi' ? 'selected' : ''; ?>>Teknologi Informasi & Software</option>
                            <option value="Pendidikan & Riset" <?= $currBidang === 'Pendidikan & Riset' ? 'selected' : ''; ?>>Pendidikan, Sekolah & Riset</option>
                            <option value="Bisnis, Keuangan & Perbankan" <?= $currBidang === 'Bisnis, Keuangan & Perbankan' ? 'selected' : ''; ?>>Bisnis, Keuangan & Perbankan</option>
                            <option value="Kesehatan & Farmasi" <?= $currBidang === 'Kesehatan & Farmasi' ? 'selected' : ''; ?>>Kesehatan & Farmasi</option>
                            <option value="Industri & Manufaktur" <?= $currBidang === 'Industri & Manufaktur' ? 'selected' : ''; ?>>Industri, Manufaktur & Logistik</option>
                            <option value="Kreatif & Media" <?= $currBidang === 'Kreatif & Media' ? 'selected' : ''; ?>>Industri Kreatif, Media & Seni</option>
                            <option value="Pemerintahan & BUMN" <?= $currBidang === 'Pemerintahan & BUMN' ? 'selected' : ''; ?>>Pemerintahan / ASN / BUMN</option>
                            <option value="Lainnya" <?= $currBidang === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Posisi / Jabatan Pekerjaan</label>
                        <input type="text" name="posisi_jabatan" value="<?= htmlspecialchars($tracer['posisi_jabatan'] ?? ''); ?>" placeholder="Contoh: Frontend Developer / Staff Akuntansi" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Kesesuaian dengan Pendidikan</label>
                        <?php $currKesesuaian = $tracer['kesesuaian_pekerjaan'] ?? 'Sangat Sesuai'; ?>
                        <select name="kesesuaian_pekerjaan" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-teal-500">
                            <option value="Sangat Sesuai" <?= $currKesesuaian === 'Sangat Sesuai' ? 'selected' : ''; ?>>Sangat Sesuai</option>
                            <option value="Sesuai" <?= $currKesesuaian === 'Sesuai' ? 'selected' : ''; ?>>Sesuai</option>
                            <option value="Kurang Sesuai" <?= $currKesesuaian === 'Kurang Sesuai' ? 'selected' : ''; ?>>Kurang Sesuai</option>
                            <option value="Tidak Sesuai" <?= $currKesesuaian === 'Tidak Sesuai' ? 'selected' : ''; ?>>Tidak Sesuai</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Waktu Tunggu Kerja Pertama</label>
                        <?php $currWaktu = $tracer['waktu_memperoleh_kerja'] ?? '< 3 Bulan'; ?>
                        <select name="waktu_memperoleh_kerja" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-teal-500">
                            <option value="Langsung Bekerja" <?= $currWaktu === 'Langsung Bekerja' ? 'selected' : ''; ?>>Langsung Bekerja Saat/Setelah Lulus</option>
                            <option value="< 3 Bulan" <?= $currWaktu === '< 3 Bulan' ? 'selected' : ''; ?>>Kurang dari 3 Bulan</option>
                            <option value="3 - 6 Bulan" <?= $currWaktu === '3 - 6 Bulan' ? 'selected' : ''; ?>>3 sampai 6 Bulan</option>
                            <option value="6 - 12 Bulan" <?= $currWaktu === '6 - 12 Bulan' ? 'selected' : ''; ?>>6 sampai 12 Bulan</option>
                            <option value="> 1 Tahun" <?= $currWaktu === '> 1 Tahun' ? 'selected' : ''; ?>>Lebih dari 1 Tahun</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bagian 3: Evaluasi & Saran untuk Sekolah -->
            <div class="space-y-4 pt-2">
                <h3 class="text-sm font-bold text-teal-400 uppercase tracking-wider flex items-center gap-2 border-b border-slate-800 pb-2">
                    <span class="w-6 h-6 rounded-lg bg-teal-500/10 text-teal-400 flex items-center justify-center text-xs">3</span> Feedback &amp; Evaluasi untuk Sekolah
                </h3>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Feedback / Penilaian terhadap Kualitas Pengajaran &amp; Fasilitas Sekolah</label>
                    <textarea name="feedback_sekolah" rows="3" placeholder="Bagikan pengalaman Anda mengenai bekal ilmu, bimbingan guru, dan fasilitas sekolah..." class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500"><?= htmlspecialchars($tracer['feedback_sekolah'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Saran &amp; Masukan untuk Peningkatan Kurikulum / Soft Skills Siswa</label>
                    <textarea name="saran_sekolah" rows="3" placeholder="Saran Anda untuk kurikulum, pelatihan bahasa asing, teknologi, atau bimbingan karir bagi adik-adik kelas..." class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500"><?= htmlspecialchars($tracer['saran_sekolah'] ?? ''); ?></textarea>
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                <a href="dashboard.php?page=overview" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold hover:bg-slate-700 transition-colors">Kembali ke Dashboard</a>
                <button type="submit" class="px-6 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-teal-600/30 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> <?= $isCompleted ? 'Perbarui Data Tracer' : 'Kirim Jawaban Tracer Study'; ?>
                </button>
            </div>
        </form>
    </div>

</div>
