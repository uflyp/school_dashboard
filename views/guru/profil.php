<?php
// views/guru/profil.php - Universal User Profile Settings
check_role(['guru', 'admin', 'kepala_sekolah', 'keuangan', 'siswa', 'orangtua']);

$user_id = $_SESSION['user_id'] ?? 0;
$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    
    if ($_POST['action'] === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $newPassword = trim($_POST['new_password'] ?? '');
        $confirmPassword = trim($_POST['confirm_password'] ?? '');

        if (empty($name) || empty($email)) {
            $msg = "Nama lengkap dan alamat email tidak boleh kosong!";
            $msgType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = "Format email tidak valid!";
            $msgType = 'error';
        } else {
            // Check if email already used by another user
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $user_id]);
            if ($stmtCheck->fetch()) {
                $msg = "Email '$email' sudah digunakan oleh pengguna lain!";
                $msgType = 'error';
            } else {
                if (!empty($newPassword)) {
                    if (strlen($newPassword) < 6) {
                        $msg = "Password baru minimal 6 karakter!";
                        $msgType = 'error';
                    } elseif ($newPassword !== $confirmPassword) {
                        $msg = "Konfirmasi password baru tidak cocok!";
                        $msgType = 'error';
                    } else {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $hash, $user_id]);
                        $_SESSION['name'] = $name;
                        $_SESSION['email'] = $email;
                        $msg = "Profil dan kata sandi berhasil diperbarui!";
                        log_activity("User updated profile & password (User ID: $user_id)");
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $user_id]);
                    $_SESSION['name'] = $name;
                    $_SESSION['email'] = $email;
                    $msg = "Profil akun berhasil diperbarui!";
                    log_activity("User updated profile details (User ID: $user_id)");
                }
            }
        }
    } elseif ($_POST['action'] === 'upload_avatar') {
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $file_size = $_FILES['avatar_file']['size'];

            if (!in_array($ext, $allowed)) {
                $msg = "Format foto harus JPG, PNG, atau WEBP!";
                $msgType = 'error';
            } elseif ($file_size > 5 * 1024 * 1024) {
                $msg = "Ukuran file maksimal 5MB!";
                $msgType = 'error';
            } else {
                $uploadDir = 'uploads/avatars/';
                if (!file_exists($uploadDir)) @mkdir($uploadDir, 0777, true);
                $targetFile = $uploadDir . 'avatar_' . $user_id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $targetFile)) {
                    $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$targetFile, $user_id]);
                    $_SESSION['avatar'] = $targetFile;
                    $msg = "Foto profil berhasil diperbarui!";
                    log_activity("User updated avatar (User ID: $user_id)");
                } else {
                    $msg = "Gagal mengunggah file foto!";
                    $msgType = 'error';
                }
            }
        }
    }
}

// Fetch fresh user data from DB
$stmtFresh = $pdo->prepare("SELECT u.*, r.name as role_name, r.display_name as role_display FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?");
$stmtFresh->execute([$user_id]);
$u = $stmtFresh->fetch() ?: current_user();
?>

<div class="max-w-3xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Pengaturan Profil Pengguna</h1>
        <p class="text-xs text-slate-400">Kelola informasi akun, kata sandi, dan foto identitas Anda</p>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border text-xs font-semibold flex items-center gap-2
            <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
        <!-- Avatar & Summary -->
        <div class="flex flex-col sm:flex-row items-center gap-6 pb-6 border-b border-slate-800">
            <div class="relative group">
                <img src="<?= htmlspecialchars($u['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150'); ?>"
                     style="width: 96px; height: 96px; object-fit: cover;"
                     class="w-24 h-24 rounded-2xl object-cover border-2 border-indigo-500 shadow-xl"
                     alt="Foto Profil">
                <button type="button" onclick="openModal('modal-change-avatar')" title="Ubah Foto Profil" class="absolute inset-0 bg-slate-950/75 rounded-2xl opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center text-white text-xs font-bold transition-opacity">
                    <i class="fa-solid fa-camera text-base mb-1"></i>
                    <span>Ubah Foto</span>
                </button>
            </div>
            <div class="text-center sm:text-left flex-1">
                <h2 class="text-xl font-extrabold text-white"><?= htmlspecialchars($u['name']); ?></h2>
                <p class="text-xs text-indigo-400 font-semibold mt-0.5"><?= htmlspecialchars($u['role_display'] ?? ucfirst($u['role_name'] ?? 'User')); ?></p>
                <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-envelope mr-1.5"></i><?= htmlspecialchars($u['email']); ?></p>
                <button type="button" onclick="openModal('modal-change-avatar')" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white border border-indigo-500/30 text-xs font-bold transition-all">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload Foto Profil Baru
                </button>
            </div>
        </div>

        <!-- Form Edit Profil & Password -->
        <form action="dashboard.php?page=profil" method="POST" class="space-y-5">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nama Lengkap <span class="text-rose-400">*</span></label>
                    <input type="text" name="name" required value="<?= htmlspecialchars($u['name']); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl text-white text-xs outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Email Sekolah <span class="text-rose-400">*</span></label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($u['email']); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl text-white text-xs outline-none transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Username Login</label>
                    <input type="text" value="<?= htmlspecialchars($u['username']); ?>" disabled class="w-full px-4 py-2.5 bg-slate-950/60 border border-slate-800/80 rounded-xl text-slate-500 text-xs cursor-not-allowed">
                    <p class="text-[10px] text-slate-500 mt-1">Username login tidak dapat diubah.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Password Baru <span class="text-slate-500">(Opsional)</span></label>
                    <input type="password" name="new_password" placeholder="Kosongkan jika tidak ingin ganti" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl text-white text-xs outline-none transition-colors">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Konfirmasi Password Baru</label>
                    <input type="password" name="confirm_password" placeholder="Ulangi password baru jika mengganti" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 focus:border-indigo-500 rounded-xl text-white text-xs outline-none transition-colors">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Upload Avatar -->
    <div id="modal-change-avatar" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-camera text-indigo-400"></i> Ubah Foto Profil
                </h3>
                <button type="button" onclick="closeModal('modal-change-avatar')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=profil" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="upload_avatar">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Pilih Berkas Foto (JPG / PNG / WEBP, Max: 5MB)</label>
                    <input type="file" name="avatar_file" accept="image/*" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-change-avatar')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Unggah Foto</button>
                </div>
            </form>
        </div>
    </div>
</div>
