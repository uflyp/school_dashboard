<?php
// views/alumni/profil.php - Profil Data Diri & Kontak Alumni
check_role(['alumni', 'admin']);

$userId = $user['id'] ?? ($_SESSION['user_id'] ?? 0);
$userName = $user['name'] ?? ($_SESSION['name'] ?? 'Alumni');
$userEmail = $user['email'] ?? ($_SESSION['email'] ?? '');

$msg = '';
$msgType = 'success';

// Handle Update Profil Alumni
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();

    if ($_POST['action'] === 'update_profil_alumni') {
        $nama = trim($_POST['nama'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $nis = trim($_POST['nis'] ?? '');
        $tahun_lulus = trim($_POST['tahun_lulus'] ?? '');
        $kontak = trim($_POST['kontak'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($nama)) {
            $msg = "Nama lengkap wajib diisi!";
            $msgType = 'error';
        } else {
            // Update tabel users
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                $stmtUser->execute([$nama, $email, $hash, $userId]);
            } else {
                $stmtUser = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmtUser->execute([$nama, $email, $userId]);
            }

            // Sync atau update di tabel alumni
            $stmtCheckAl = $pdo->prepare("SELECT id FROM alumni WHERE nis = ? OR nama = ? LIMIT 1");
            $stmtCheckAl->execute([$nis, $userName]);
            $alRow = $stmtCheckAl->fetch();

            if ($alRow) {
                $stmtUpAl = $pdo->prepare("UPDATE alumni SET nama = ?, nis = ?, tahun_lulus = ?, kontak = ? WHERE id = ?");
                $stmtUpAl->execute([$nama, $nis, $tahun_lulus, $kontak, $alRow['id']]);
            } else {
                $stmtInsAl = $pdo->prepare("INSERT INTO alumni (nis, nama, tahun_lulus, kuliah_kerja, kontak) VALUES (?, ?, ?, 'Alumni', ?)");
                $stmtInsAl->execute([$nis, $nama, $tahun_lulus, $kontak]);
            }

            // Update session name
            $_SESSION['name'] = $nama;
            $_SESSION['email'] = $email;
            $userName = $nama;
            $userEmail = $email;

            $msg = "Profil dan informasi kontak alumni berhasil diperbarui!";
            log_activity("Alumni updated profile: $nama");
        }
    }
}

// Fetch Data Terkini
$stmtAl = $pdo->prepare("SELECT * FROM alumni WHERE nama = ? OR nis = ? LIMIT 1");
$stmtAl->execute([$userName, $userName]);
$alumniData = $stmtAl->fetch(PDO::FETCH_ASSOC);

$nis = $alumniData['nis'] ?? '202401001';
$tahunLulus = $alumniData['tahun_lulus'] ?? '2024';
$kontak = $alumniData['kontak'] ?? '08123456789';
?>
<div class="space-y-6 max-w-4xl">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-id-card text-teal-400"></i> Profil &amp; Informasi Kontak Alumni
            </h1>
            <p class="text-xs text-slate-400 mt-1">Perbarui biodata, tahun kelulusan, dan nomor kontak yang dapat dihubungi</p>
        </div>
        <a href="dashboard.php?page=tracer" class="px-4 py-2.5 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-teal-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-list-check"></i> Kuesioner Tracer Study
        </a>
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

    <!-- Form Profil Card -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <form action="dashboard.php?page=profil" method="POST" class="space-y-5">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="update_profil_alumni">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nama Lengkap &amp; Gelar</label>
                    <input type="text" name="nama" value="<?= htmlspecialchars($userName); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nomor Induk Siswa (NIS Saat Sekolah)</label>
                    <input type="text" name="nis" value="<?= htmlspecialchars($nis); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Tahun Kelulusan (Angkatan)</label>
                    <input type="text" name="tahun_lulus" value="<?= htmlspecialchars($tahunLulus); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nomor WhatsApp / HP Aktif</label>
                    <input type="text" name="kontak" value="<?= htmlspecialchars($kontak); ?>" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Alamat Email Aktif</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($userEmail); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Ubah Password Akun (Opsional)</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak ingin mengubah password" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                </div>
            </div>

            <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                <button type="reset" class="px-4 py-2.5 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold hover:bg-slate-700 transition-colors">Reset</button>
                <button type="submit" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-teal-600/30 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Profil
                </button>
            </div>
        </form>
    </div>

</div>
