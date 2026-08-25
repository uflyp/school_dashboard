<?php
// views/admin/settings.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    verify_csrf_token();
    $allowedKeys = [
        'school_name', 'school_tagline', 'school_email', 'school_phone',
        'school_address', 'hero_title', 'hero_subtitle', 'ppdb_status', 'footer_text'
    ];
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key = ?");
    $insertStmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (?, ?)");
    $updateStmt = $pdo->prepare("UPDATE settings SET value = ? WHERE key = ?");

    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $val) {
            if (in_array($key, $allowedKeys, true)) {
                $val = trim($val);
                $checkStmt->execute([$key]);
                if ($checkStmt->fetchColumn() > 0) {
                    $updateStmt->execute([$val, $key]);
                } else {
                    $insertStmt->execute([$key, $val]);
                }
            }
        }
    }
    log_activity("Admin updated website identity settings");
    $msg = "Pengaturan identitas website sekolah berhasil diperbarui!";
}
?>
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Pengaturan Identitas Website</h1>
        <p class="text-xs text-slate-400">Konfigurasi nama sekolah, kontak, email, tagline, dan footer global</p>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-xl">
        <form action="dashboard.php?page=settings" method="POST" class="space-y-6">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="save_settings">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Nama Resmi Sekolah</label>
                    <input type="text" name="settings[school_name]" value="<?= htmlspecialchars(get_setting('school_name')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Slogan / Tagline Sekolah</label>
                    <input type="text" name="settings[school_tagline]" value="<?= htmlspecialchars(get_setting('school_tagline')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Email Resmi Sekolah</label>
                    <input type="email" name="settings[school_email]" value="<?= htmlspecialchars(get_setting('school_email')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">No. Telepon / Hotline</label>
                    <input type="text" name="settings[school_phone]" value="<?= htmlspecialchars(get_setting('school_phone')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Alamat Lengkap Sekolah</label>
                <textarea name="settings[school_address]" rows="2" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"><?= htmlspecialchars(get_setting('school_address')); ?></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Judul Utama Banner Hero</label>
                    <input type="text" name="settings[hero_title]" value="<?= htmlspecialchars(get_setting('hero_title')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Sub-judul Banner Hero</label>
                    <input type="text" name="settings[hero_subtitle]" value="<?= htmlspecialchars(get_setting('hero_subtitle')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Status Gelombang PPDB</label>
                    <input type="text" name="settings[ppdb_status]" value="<?= htmlspecialchars(get_setting('ppdb_status')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1.5">Teks Hak Cipta (Footer)</label>
                <input type="text" name="settings[footer_text]" value="<?= htmlspecialchars(get_setting('footer_text')); ?>" class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
            </div>

            <div class="pt-4 border-t border-slate-800 flex justify-end">
                <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30">
                    Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>
