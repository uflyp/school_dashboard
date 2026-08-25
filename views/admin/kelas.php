<?php
// views/admin/kelas.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'tambah_kelas') {
        $nama_kelas = trim($_POST['nama_kelas']);
        $wali_kelas = trim($_POST['wali_kelas']);
        $jumlah_siswa = intval($_POST['jumlah_siswa']);

        try {
            $stmt = $pdo->prepare("INSERT INTO kelas (nama_kelas, wali_kelas, jumlah_siswa) VALUES (?, ?, ?)");
            $stmt->execute([$nama_kelas, $wali_kelas, $jumlah_siswa]);
            $msg = "Kelas $nama_kelas berhasil ditambahkan!";
            log_activity("Admin created class: $nama_kelas");
        } catch (Exception $e) {
            $msg = "Gagal menambah kelas: Nama kelas sudah ada!";
        }
    } elseif ($_POST['action'] === 'hapus_kelas') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM kelas WHERE id = ?")->execute([$id]);
        $msg = "Data kelas berhasil dihapus!";
    }
}

$kelasList = $pdo->query("SELECT k.*, (SELECT COUNT(*) FROM siswa s WHERE s.kelas = k.nama_kelas) AS real_jumlah_siswa FROM kelas k ORDER BY k.nama_kelas ASC")->fetchAll();
$guruList = $pdo->query("SELECT nama FROM guru ORDER BY nama ASC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-school text-indigo-400"></i> Manajemen Data Kelas & Wali Kelas
            </h1>
            <p class="text-xs text-slate-400 mt-1">Daftar Rombongan Belajar (Rombel) dan penetapan Wali Kelas</p>
        </div>
        <button type="button" onclick="openModal('modal-add-kelas')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-plus"></i> Tambah Kelas Baru
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">
        <?php foreach ($kelasList as $k): ?>
            <?php $jSiswa = (int)($k['real_jumlah_siswa'] ?? $k['jumlah_siswa']); ?>
            <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col justify-between shadow-xl hover:border-indigo-500/40 transition-all group">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-3 py-1 rounded-xl text-xs font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <?= htmlspecialchars($k['nama_kelas']); ?>
                        </span>
                        <form action="dashboard.php?page=kelas" method="POST" onsubmit="return confirm('Hapus kelas ini?');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="hapus_kelas">
                            <input type="hidden" name="id" value="<?= $k['id']; ?>">
                            <button type="submit" class="text-slate-500 hover:text-rose-400 text-xs"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                    <h3 class="text-base font-extrabold text-white mb-1"><?= htmlspecialchars($k['nama_kelas']); ?></h3>
                    <p class="text-xs text-slate-400 mb-3"><i class="fa-solid fa-user-tie text-indigo-400 mr-1.5"></i>Wali: <strong class="text-white"><?= htmlspecialchars($k['wali_kelas']); ?></strong></p>
                </div>
                <div class="pt-3 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
                    <span>Siswa: <strong class="text-emerald-400"><?= $jSiswa; ?> Orang</strong></span>
                    <span class="text-indigo-400">Kapasitas Pas</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Tambah Kelas -->
    <div id="modal-add-kelas" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Tambah Kelas Baru</h3>
                <button type="button" onclick="closeModal('modal-add-kelas')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=kelas" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_kelas">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Kelas</label>
                    <input type="text" name="nama_kelas" required placeholder="Contoh: XII IPA 2" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Pilih Wali Kelas</label>
                    <select name="wali_kelas" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?= htmlspecialchars($g['nama']); ?>"><?= htmlspecialchars($g['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Estimasi Jumlah Siswa</label>
                    <input type="number" name="jumlah_siswa" value="32" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-kelas')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Kelas</button>
                </div>
            </form>
        </div>
    </div>
</div>
