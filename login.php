<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';
if (isset($_SESSION['auth_error'])) {
    $error_message = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    
    $login_input = trim($_POST['username_email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (!empty($login_input) && !empty($password)) {
        // Query user by username OR email with role join
        $stmt = $pdo->prepare("
            SELECT u.*, r.name as role_name, r.display_name as role_display 
            FROM users u 
            JOIN roles r ON u.role_id = r.id 
            WHERE (u.username = ? OR u.email = ?) AND u.status = 'active'
        ");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $nowStr = date('Y-m-d H:i:s');
            // Update DB record with real-time server timestamp (Asia/Jakarta)
            $pdo->prepare("UPDATE users SET last_login = ?, last_activity = ? WHERE id = ?")->execute([$nowStr, $nowStr, $user['id']]);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['role_display'] = $user['role_display'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['avatar'] = $user['avatar'];
            $_SESSION['jenis_kelamin'] = $user['jenis_kelamin'] ?? 'L';
            $_SESSION['last_activity'] = time();

            if ($remember) {
                setcookie('remember_user', $user['username'], time() + (86400 * 30), "/");
            }

            log_activity("User '" . $user['username'] . "' logged in successfully (Role: " . $user['role_name'] . ")");

            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = "Username/Email atau kata sandi tidak valid!";
            log_activity("Failed login attempt for account: " . $login_input);
        }
    } else {
        $error_message = "Harap isi username/email dan kata sandi Anda!";
    }
}
?>
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Portal - <?= htmlspecialchars(get_setting('school_name')); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Output -->
    <link rel="stylesheet" href="assets/css/output.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">

    <!-- Top Header Navigation -->
    <header class="w-full bg-slate-900/80 backdrop-blur-md border-b border-slate-800 px-4 sm:px-8 py-4 flex items-center justify-between z-50">
        <a href="index.php" class="flex items-center gap-3 group">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-emerald-400 p-0.5 shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                <div class="w-full h-full bg-slate-950 rounded-[10px] flex items-center justify-center">
                    <i class="fa-solid fa-graduation-cap text-indigo-400 text-lg"></i>
                </div>
            </div>
            <div>
                <span class="text-base font-bold text-white block leading-none"><?= htmlspecialchars(get_setting('school_name')); ?></span>
                <span class="text-[10px] text-slate-400 font-medium tracking-wider uppercase">Portal Informasi Terpadu</span>
            </div>
        </a>

        <!-- Top Right Digital Clock -->
        <div class="digital-clock-display"></div>
    </header>

    <!-- Main Login Shell -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 my-6 relative overflow-hidden">
        
        <!-- Ambient Glowing Orbs -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-indigo-600/15 rounded-full blur-[140px] pointer-events-none"></div>

        <div class="w-full max-w-md relative z-10">
            
            <!-- Clean Glass Card -->
            <div class="bg-slate-900/90 backdrop-blur-xl border border-slate-800/90 rounded-3xl p-6 sm:p-8 shadow-2xl shadow-slate-950">
                
                <!-- Header Icon & Title -->
                <div class="text-center mb-6">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center mx-auto mb-3 text-2xl shadow-inner">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <h2 class="text-2xl font-extrabold text-white tracking-tight">Login Portal Sekolah</h2>
                    <p class="text-xs text-slate-400 mt-1">Masukkan kredensial akun Anda untuk mengakses sistem</p>
                </div>

                <!-- Error Notification -->
                <?php if ($error_message): ?>
                    <div class="mb-5 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold flex items-center gap-3">
                        <i class="fa-solid fa-circle-exclamation text-base shrink-0"></i>
                        <span><?= htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <!-- Clean Login Form -->
                <form action="login.php" method="POST" class="space-y-4">
                    <?= csrf_field(); ?>
                    
                    <!-- Username or Email Input -->
                    <div>
                        <label for="username_email" class="block text-xs font-semibold text-slate-300 mb-1.5">Username atau Email</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <i class="fa-solid fa-user text-xs"></i>
                            </span>
                            <input type="text" name="username_email" id="username_email" required placeholder="username atau email@sekolah.sch.id" value="<?= htmlspecialchars($_COOKIE['remember_user'] ?? ''); ?>" class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder:text-slate-600">
                        </div>
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold text-slate-300">Kata Sandi</label>
                            <a href="javascript:void(0)" onclick="openModal('modal-lupa-password')" class="text-[11px] text-indigo-400 hover:text-indigo-300 hover:underline">Lupa password?</a>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <i class="fa-solid fa-lock text-xs"></i>
                            </span>
                            <input type="password" name="password" id="password" required placeholder="••••••••" class="w-full pl-10 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all placeholder:text-slate-600">
                        </div>
                    </div>

                    <!-- Remember Me Checkbox -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 text-xs text-slate-400 cursor-pointer select-none">
                            <input type="checkbox" name="remember" <?= isset($_COOKIE['remember_user']) ? 'checked' : ''; ?> class="w-4 h-4 rounded bg-slate-950 border-slate-800 text-indigo-600 focus:ring-indigo-500">
                            <span>Ingat Saya di Perangkat Ini</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="w-full py-3 mt-2 bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-400 text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/30 hover:shadow-indigo-600/40 hover:scale-[1.01] active:scale-[0.99] transition-all duration-200 flex items-center justify-center gap-2">
                        <span>Masuk ke Dashboard</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </button>
                </form>

                <!-- Back to Home Link -->
                <div class="pt-4 border-t border-slate-800 text-center">
                    <a href="index.php" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-400 hover:text-white transition-colors">
                        <i class="fa-solid fa-house text-slate-500"></i>
                        <span>Kembali ke Beranda Website</span>
                    </a>
                </div>

            </div>
        </div>
    </main>

    <!-- Modal Bantuan Lupa Password -->
    <div id="modal-lupa-password" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Bantuan Lupa Password</h3>
                        <p class="text-[11px] text-slate-400">Petunjuk pemulihan akses akun portal sekolah</p>
                    </div>
                </div>
                <button type="button" onclick="closeModal('modal-lupa-password')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="space-y-3 text-xs text-slate-300">
                <p class="leading-relaxed">Demi keamanan data sekolah, pengaturan ulang kata sandi dilakukan secara terverifikasi melalui <strong>Administrator IT Sekolah</strong>.</p>
                <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                    <div class="flex items-center gap-2 text-slate-300">
                        <i class="fa-solid fa-school text-indigo-400"></i>
                        <span>Sekolah: <strong><?= htmlspecialchars(get_setting('school_name', 'SMA Nusantara')); ?></strong></span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-300">
                        <i class="fa-solid fa-envelope text-indigo-400"></i>
                        <span>Email: <a href="mailto:<?= htmlspecialchars(get_setting('school_email', 'admin@sekolah.sch.id')); ?>" class="text-indigo-400 hover:underline"><?= htmlspecialchars(get_setting('school_email', 'admin@sekolah.sch.id')); ?></a></span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-300">
                        <i class="fa-solid fa-phone text-indigo-400"></i>
                        <span>Hotline IT: <strong><?= htmlspecialchars(get_setting('school_phone', '(021) 7890123')); ?></strong></span>
                    </div>
                </div>
                <p class="text-[11px] text-slate-400">Sertakan <strong>Username / NIS / NIP</strong> dan identitas resmi saat menghubungi administrator.</p>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-800 flex items-center justify-between gap-3">
                <button type="button" onclick="closeModal('modal-lupa-password')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Tutup</button>
                <?php
                $hotline = preg_replace('/[^0-9]/', '', get_setting('school_phone', '081234567890'));
                if (str_starts_with($hotline, '0')) $hotline = '62' . substr($hotline, 1);
                $waMsg = urlencode("Halo Admin IT " . get_setting('school_name') . ", saya membutuhkan bantuan reset password akun portal saya.");
                ?>
                <a href="https://wa.me/<?= $hotline; ?>?text=<?= $waMsg; ?>" target="_blank" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-lg shadow-emerald-600/30 transition-all">
                    <i class="fa-brands fa-whatsapp"></i> Chat Admin WhatsApp
                </a>
            </div>
        </div>
    </div>

    <script>
    function openModal(id) {
        const el = document.getElementById(id);
        if (el) { el.classList.remove('hidden'); el.classList.add('flex'); }
    }
    function closeModal(id) {
        const el = document.getElementById(id);
        if (el) { el.classList.add('hidden'); el.classList.remove('flex'); }
    }
    </script>

    <!-- Footer -->
    <footer class="py-4 text-center text-xs text-slate-600 border-t border-slate-900">
        <?= htmlspecialchars(get_setting('school_name')); ?> © 2026 • Security Protected System V2.0
    </footer>

    <!-- Digital Clock Script -->
    <script src="assets/js/clock.js"></script>
</body>
</html>

