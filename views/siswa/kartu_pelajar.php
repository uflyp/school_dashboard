<?php
// views/siswa/kartu_pelajar.php - Modul Kartu Pelajar Digital Siswa & Admin
check_role(['siswa', 'admin']);

$user = current_user();
$role = $user['role'];

// Server-side Authorization & NIS Resolution
$target_nis = '';

if ($role === 'admin') {
    $target_nis = trim($_GET['nis'] ?? '');
    if (empty($target_nis)) {
        $firstSiswa = $pdo->query("SELECT nis FROM siswa ORDER BY id ASC LIMIT 1")->fetch();
        $target_nis = $firstSiswa['nis'] ?? '';
    }
} else {
    // For Siswa: Strictly enforce logged-in student's NIS
    $stmtFind = $pdo->prepare("SELECT * FROM siswa WHERE nis = ? OR LOWER(nama) = LOWER(?) LIMIT 1");
    $stmtFind->execute([$user['username'], $user['name']]);
    $sData = $stmtFind->fetch();
    $target_nis = $sData['nis'] ?? '20241001';
}

// Fetch Student details
$stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
$stmtSiswa->execute([$target_nis]);
$siswa = $stmtSiswa->fetch();

if (!$siswa) {
    echo "<div class='p-6 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm'>Data siswa tidak ditemukan dalam sistem database.</div>";
    return;
}

// Resolve Student Photo
$fotoSiswa = '';
if (!empty($siswa['foto'])) {
    $fotoSiswa = $siswa['foto'];
} else {
    $stmtUser = $pdo->prepare("SELECT avatar FROM users WHERE name = ? OR username = ? LIMIT 1");
    $stmtUser->execute([$siswa['nama'], $siswa['nis']]);
    $uFetch = $stmtUser->fetch();
    $fotoSiswa = $uFetch['avatar'] ?? 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150';
}

// Generate QR Code URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = isset($_SERVER['PHP_SELF']) ? dirname($_SERVER['PHP_SELF']) : '/school_dashboard';
$baseUrl = $protocol . "://" . $host . rtrim($scriptDir, '/\\') . "/";
$verifyUrl = $baseUrl . "verifikasi_kartu.php?nis=" . urlencode($siswa['nis']);
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verifyUrl);

// List of all students for Admin Selector
$allSiswaList = [];
if ($role === 'admin') {
    $allSiswaList = $pdo->query("SELECT nis, nama, kelas FROM siswa ORDER BY nama ASC")->fetchAll();
}
?>

<div class="space-y-8 max-w-5xl mx-auto">

    <!-- Header Banner Card -->
    <div class="bg-gradient-to-r from-indigo-950 via-slate-900 to-slate-900 border border-indigo-700/40 p-6 sm:p-8 rounded-3xl shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="space-y-2">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                <i class="fa-solid fa-id-card-clip"></i> E-ID Card System SIAKAD
            </span>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Kartu Pelajar Digital</h1>
            <p class="text-xs sm:text-sm text-indigo-200 leading-relaxed max-w-xl">
                Identitas resmi siswa berbasis digital yang terverifikasi secara elektronik melalui QR Code resmi sekolah.
            </p>
        </div>

        <!-- Action Buttons Bar -->
        <div class="flex flex-wrap items-center gap-3 shrink-0">
            <a href="cetak_kartu.php?nis=<?= urlencode($siswa['nis']); ?>&download=1" target="_blank" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition-all">
                <i class="fa-solid fa-file-pdf"></i> Download PDF
            </a>
            <a href="cetak_kartu.php?nis=<?= urlencode($siswa['nis']); ?>" target="_blank" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs flex items-center gap-2 border border-slate-700 transition-all">
                <i class="fa-solid fa-print"></i> Cetak Kartu
            </a>
            <?php if ($role === 'admin'): ?>
                <a href="dashboard.php?page=siswa&edit_nis=<?= urlencode($siswa['nis']); ?>" class="px-4 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs flex items-center gap-2 shadow-lg shadow-amber-600/30 transition-all">
                    <i class="fa-solid fa-user-pen"></i> Edit Data Siswa
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($role === 'admin'): ?>
        <!-- Admin Student Selector Bar -->
        <div class="p-4 rounded-2xl bg-slate-900 border border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-xs font-bold text-slate-300 flex items-center gap-2">
                <i class="fa-solid fa-users text-indigo-400"></i> Pilih Siswa Untuk Melihat Kartu Pelajar:
            </div>
            <select onchange="window.location.href='dashboard.php?page=kartu_pelajar&nis=' + this.value" class="px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:border-indigo-500 focus:outline-none min-w-[280px]">
                <?php foreach ($allSiswaList as $sOption): ?>
                    <option value="<?= htmlspecialchars($sOption['nis']); ?>" <?= $sOption['nis'] === $siswa['nis'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($sOption['nama']); ?> (NIS: <?= htmlspecialchars($sOption['nis']); ?> - <?= htmlspecialchars($sOption['kelas']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <!-- Interactive Card Preview Container (Depan & Belakang) -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-extrabold text-white flex items-center gap-2">
                <i class="fa-solid fa-eye text-indigo-400"></i> Tampilan Preview Kartu (Sisi Depan & Sisi Belakang)
            </h3>
            <span class="text-xs text-slate-400 font-mono">Format Standard ID Card (85.6mm × 54mm)</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center justify-items-center">

            <!-- Card Depan (Front Side Preview) -->
            <div class="w-full max-w-[360px] aspect-[1.58/1] rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-950 p-4 border-2 border-indigo-500/40 shadow-2xl relative overflow-hidden flex flex-col justify-between group hover:border-indigo-400 transition-all">
                <div class="absolute -right-12 -top-12 w-40 h-40 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

                <!-- Card Header -->
                <div class="flex items-center gap-3 border-b border-indigo-500/20 pb-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-500 p-0.5 shrink-0 shadow-md">
                        <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                            <i class="fa-solid fa-graduation-cap text-indigo-400 text-sm"></i>
                        </div>
                    </div>
                    <div class="leading-tight">
                        <h4 class="text-xs font-extrabold text-white uppercase tracking-wider"><?= htmlspecialchars(get_setting('school_name')); ?></h4>
                        <span class="text-[9px] text-indigo-300 font-bold uppercase tracking-widest block">KARTU TANDA PELAJAR DIGITAL</span>
                    </div>
                </div>

                <!-- Card Body -->
                <div class="flex items-center gap-3.5 py-1">
                    <img src="<?= htmlspecialchars($fotoSiswa); ?>" style="width: 74px; height: 92px; object-fit: cover;" class="w-[74px] h-[92px] rounded-xl object-cover border-2 border-indigo-400 shadow-lg shrink-0">
                    
                    <div class="space-y-1 min-w-0 flex-1">
                        <h3 class="text-sm font-extrabold text-white truncate leading-tight mb-1"><?= htmlspecialchars($siswa['nama']); ?></h3>
                        
                        <table class="w-full text-[10px] leading-tight border-collapse">
                            <tr>
                                <td class="font-semibold text-slate-400 py-0.5 w-11 align-top">NIS</td>
                                <td class="font-extrabold text-slate-100 py-0.5 align-top">: <?= htmlspecialchars($siswa['nis']); ?></td>
                            </tr>
                            <tr>
                                <td class="font-semibold text-slate-400 py-0.5 w-11 align-top">NISN</td>
                                <td class="font-extrabold text-slate-100 py-0.5 align-top">: <?= htmlspecialchars($siswa['nisn'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <td class="font-semibold text-slate-400 py-0.5 w-11 align-top">Kelas</td>
                                <td class="font-extrabold text-slate-100 py-0.5 align-top">: <?= htmlspecialchars($siswa['kelas']); ?></td>
                            </tr>
                            <tr>
                                <td class="font-semibold text-slate-400 py-0.5 w-11 align-top">TTL</td>
                                <td class="font-extrabold text-slate-100 py-0.5 align-top truncate">: <?= htmlspecialchars($siswa['tempat_lahir'] ?? 'Jakarta'); ?>, <?= !empty($siswa['tanggal_lahir']) ? date('d/m/Y', strtotime($siswa['tanggal_lahir'])) : '-'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="flex items-end justify-between border-t border-indigo-500/20 pt-2">
                    <div>
                        <span class="inline-block px-2 py-0.5 rounded text-[8px] font-extrabold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 uppercase">
                            ● STATUS: <?= htmlspecialchars($siswa['status_siswa'] ?? 'Aktif'); ?> (<?= htmlspecialchars($siswa['tahun_ajaran'] ?? '2025/2026'); ?>)
                        </span>
                    </div>
                    <div class="p-1 bg-white rounded-lg shadow-md shrink-0">
                        <img src="<?= htmlspecialchars($qrCodeUrl); ?>" style="width: 36px; height: 36px;" class="w-9 h-9" alt="QR Code">
                    </div>
                </div>
            </div>

            <!-- Card Belakang (Back Side Preview) -->
            <div class="w-full max-w-[360px] aspect-[1.58/1] rounded-3xl bg-slate-900 p-4 border border-slate-800 shadow-2xl relative overflow-hidden flex flex-col justify-between">
                <div>
                    <h4 class="text-xs font-extrabold text-indigo-400 uppercase tracking-wider border-b border-slate-800 pb-1.5 mb-2">
                        Ketentuan Penggunaan Kartu
                    </h4>
                    <ol class="text-[9.5px] text-slate-400 leading-snug list-decimal pl-4 space-y-1">
                        <li>Kartu ini adalah identitas resmi siswa <?= htmlspecialchars(get_setting('school_name')); ?>.</li>
                        <li>Wajib dibawa selama kegiatan pembelajaran di sekolah.</li>
                        <li>Scan QR Code untuk memverifikasi keaktifan siswa secara real-time.</li>
                        <li>Apabila menemukan kartu ini, harap mengembalikan ke pihak sekolah.</li>
                    </ol>
                </div>

                <div class="flex items-end justify-between text-[9px] text-slate-400 border-t border-slate-800 pt-2">
                    <div>
                        <div>Diterbitkan: Jakarta</div>
                        <div class="font-bold text-white">SIAKAD Digital System</div>
                    </div>
                    <div class="text-right">
                        <div>Kepala Sekolah,</div>
                        <div class="font-extrabold text-white underline mt-2">Prof. Dr. Bambang S., M.Ed.</div>
                        <div class="text-[8px] text-slate-500">NIP. 19750812 200003 1 002</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Verification Info Box -->
    <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col md:flex-row items-center gap-5">
        <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 text-indigo-400 flex items-center justify-center text-2xl shrink-0 border border-indigo-500/20">
            <i class="fa-solid fa-qrcode"></i>
        </div>
        <div class="space-y-1 text-center md:text-left flex-1 min-w-0">
            <h4 class="text-sm font-extrabold text-white">Fitur Verifikasi QR Code Kartu Pelajar</h4>
            <p class="text-xs text-slate-400 leading-relaxed">
                Setiap kartu dilengkapi dengan QR Code unik yang terhubung langsung ke URL verifikasi publik: 
                <a href="<?= htmlspecialchars($verifyUrl); ?>" target="_blank" class="text-indigo-400 hover:underline font-mono truncate block"><?= htmlspecialchars($verifyUrl); ?></a>
            </p>
        </div>
        <a href="<?= htmlspecialchars($verifyUrl); ?>" target="_blank" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-indigo-300 font-bold text-xs shrink-0 border border-slate-700 transition-all">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Tes Verifikasi QR
        </a>
    </div>

</div>
