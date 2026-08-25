<?php
// verifikasi_kartu.php - Halaman Publik Verifikasi Kartu Pelajar Digital
require_once 'config.php';

$nis = trim($_GET['nis'] ?? '');
$siswa = null;

if (!empty($nis)) {
    $stmt = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
    $stmt->execute([$nis]);
    $siswa = $stmt->fetch();
}

// Fetch avatar from users table if not set in siswa table
$fotoSiswa = '';
if ($siswa) {
    if (!empty($siswa['foto'])) {
        $fotoSiswa = $siswa['foto'];
    } else {
        $stmtUser = $pdo->prepare("SELECT avatar FROM users WHERE name = ? OR username = ? LIMIT 1");
        $stmtUser->execute([$siswa['nama'], $siswa['nis']]);
        $uFetch = $stmtUser->fetch();
        $fotoSiswa = $uFetch['avatar'] ?? 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150';
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Kartu Pelajar Digital - <?= htmlspecialchars(get_setting('school_name')); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Output -->
    <link rel="stylesheet" href="assets/css/output.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased min-h-screen flex items-center justify-center p-4 selection:bg-indigo-500 selection:text-white">

    <div class="w-full max-w-lg space-y-6">
        
        <!-- School Header Brand -->
        <div class="text-center space-y-2">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-indigo-600 via-purple-500 to-emerald-400 p-0.5 shadow-xl shadow-indigo-500/20 mx-auto">
                <div class="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                    <i class="fa-solid fa-graduation-cap text-indigo-400 text-2xl"></i>
                </div>
            </div>
            <h1 class="text-xl font-extrabold text-white tracking-tight"><?= htmlspecialchars(get_setting('school_name')); ?></h1>
            <p class="text-xs text-indigo-400 font-semibold uppercase tracking-widest"><?= htmlspecialchars(get_setting('school_tagline')); ?></p>
        </div>

        <?php if ($siswa): ?>
            <!-- Card Valid Verification -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-emerald-500 via-teal-400 to-indigo-500"></div>

                <!-- Verification Status Banner -->
                <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-center space-y-1">
                    <div class="inline-flex items-center gap-2 text-sm font-extrabold">
                        <i class="fa-solid fa-circle-check text-lg"></i>
                        <span>KARTU PELAJAR VALID & TERVERIFIKASI</span>
                    </div>
                    <p class="text-[11px] text-emerald-300/80 font-medium">Sistem Informasi Akademik (SIAKAD) Portal Sekolah</p>
                </div>

                <!-- Student Main Profile Box -->
                <div class="flex flex-col sm:flex-row items-center gap-5 text-center sm:text-left">
                    <img src="<?= htmlspecialchars($fotoSiswa); ?>" style="width: 96px; height: 96px; object-fit: cover;" class="w-24 h-24 rounded-2xl object-cover border-2 border-emerald-500 shadow-xl shrink-0">
                    <div class="space-y-1 min-w-0 flex-1">
                        <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 uppercase">
                            Status: <?= htmlspecialchars($siswa['status_siswa'] ?? 'Aktif'); ?>
                        </span>
                        <h2 class="text-lg font-extrabold text-white break-words"><?= htmlspecialchars($siswa['nama']); ?></h2>
                        <p class="text-xs text-indigo-400 font-mono font-bold">NIS: <?= htmlspecialchars($siswa['nis']); ?> <?= !empty($siswa['nisn']) ? ' | NISN: ' . htmlspecialchars($siswa['nisn']) : ''; ?></p>
                        <p class="text-xs text-slate-400">Kelas: <strong class="text-slate-200"><?= htmlspecialchars($siswa['kelas']); ?></strong></p>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="grid grid-cols-2 gap-3 text-xs pt-4 border-t border-slate-800">
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] text-slate-500 font-bold block uppercase">Tempat, Tgl Lahir</span>
                        <span class="font-bold text-slate-200"><?= htmlspecialchars($siswa['tempat_lahir'] ?? 'Jakarta'); ?>, <?= !empty($siswa['tanggal_lahir']) ? date('d M Y', strtotime($siswa['tanggal_lahir'])) : '-'; ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] text-slate-500 font-bold block uppercase">Tahun Ajaran</span>
                        <span class="font-bold text-slate-200"><?= htmlspecialchars($siswa['tahun_ajaran'] ?? '2025/2026'); ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] text-slate-500 font-bold block uppercase">Jenis Kelamin</span>
                        <span class="font-bold text-slate-200"><?= htmlspecialchars($siswa['jenis_kelamin']); ?></span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                        <span class="text-[10px] text-slate-500 font-bold block uppercase">Alamat Siswa</span>
                        <span class="font-bold text-slate-200 truncate block"><?= htmlspecialchars($siswa['alamat'] ?? 'Jakarta'); ?></span>
                    </div>
                </div>

                <!-- Footer Note -->
                <div class="text-center pt-2 text-[10px] text-slate-500 space-y-1">
                    <p><i class="fa-solid fa-shield-halved text-indigo-400 mr-1"></i> Terverifikasi secara elektronik pada <?= date('d F Y H:i'); ?> WIB</p>
                    <p class="text-slate-600">Dokumen ini merupakan bagian dari Kartu Pelajar Digital Resmi <?= htmlspecialchars(get_setting('school_name')); ?>.</p>
                </div>
            </div>

        <?php else: ?>
            <!-- Card Invalid / Not Found -->
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center space-y-4">
                <div class="w-16 h-16 rounded-2xl bg-rose-500/10 text-rose-400 flex items-center justify-center text-3xl mx-auto border border-rose-500/20">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <h3 class="text-lg font-extrabold text-white">Kartu Tidak Ditemukan / Tidak Valid</h3>
                    <p class="text-xs text-slate-400 mt-1">Data Kartu Pelajar Digital dengan NIS "<?= htmlspecialchars($nis); ?>" tidak terdaftar pada database sistem sekolah.</p>
                </div>
                <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all">
                    <i class="fa-solid fa-house"></i> Kembali ke Beranda
                </a>
            </div>
        <?php endif; ?>

    </div>

</body>
</html>
