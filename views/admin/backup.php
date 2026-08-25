<?php
// views/admin/backup.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'download_backup') {
        $dbFile = __DIR__ . '/../../database.sqlite';
        if (file_exists($dbFile)) {
            $filename = 'database_backup_' . date('Ymd_His') . '.sqlite';
            header('Content-Description: File Transfer');
            header('Content-Type: application/x-sqlite3');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($dbFile));
            readfile($dbFile);
            exit();
        } else {
            $msg = "Error: File database.sqlite tidak ditemukan.";
        }
    }
}

$logs = $pdo->query("SELECT * FROM activity_logs ORDER BY id DESC LIMIT 15")->fetchAll();
?>
<div class="space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Keamanan System & Backup Database</h1>
        <p class="text-xs text-slate-400">Pengelolaan cadangan data SQLite, riwayat aktivitas login, dan proteksi sesi</p>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <!-- Backup & Restore -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-xl mb-4">
                    <i class="fa-solid fa-database"></i>
                </div>
                <h3 class="text-base font-bold text-white mb-1">Backup Database SQLite</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-6">
                    Buat salinan file database.sqlite instan untuk mengamankan seluruh data siswa, guru, akun, nilai, dan transaksi keuangan.
                </p>
            </div>
            <form action="dashboard.php?page=backup" method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="download_backup">
                <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center justify-center gap-2">
                    <i class="fa-solid fa-download"></i> Buat Backup Database Sekarang
                </button>
            </form>
        </div>

        <!-- Security Features Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-3">
            <h3 class="text-base font-bold text-white mb-2"><i class="fa-solid fa-shield-halved text-emerald-400 mr-2"></i>Status Keamanan V2.0</h3>
            <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">Password Hashing:</span>
                <span class="font-bold text-emerald-400">BCRYPT (Active)</span>
            </div>
            <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">Session Hijack Shield:</span>
                <span class="font-bold text-emerald-400">Active</span>
            </div>
            <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-between text-xs">
                <span class="text-slate-400">Login Role Auto-Resolution:</span>
                <span class="font-bold text-emerald-400">Active</span>
            </div>
        </div>

    </div>

    <!-- Activity Log Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <h3 class="text-base font-bold text-white mb-4">Activity & Audit Logs Terbaru</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Waktu</th>
                        <th class="p-3.5">User</th>
                        <th class="p-3.5">Aktivitas Sistem</th>
                        <th class="p-3.5 rounded-r-xl">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($logs as $l): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-mono text-slate-400 font-semibold"><?= format_log_timestamp($l['timestamp']); ?></td>
                            <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($l['username']); ?></td>
                            <td class="p-3.5 text-indigo-400 font-semibold"><?= htmlspecialchars($l['action']); ?></td>
                            <td class="p-3.5 font-mono text-slate-400"><?= htmlspecialchars($l['ip_address']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
