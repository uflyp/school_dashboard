<?php
// views/admin/prestasi.php
check_role(['admin', 'kepala_sekolah']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'tambah_prestasi') {
        $judul = trim($_POST['judul']);
        $tingkat = trim($_POST['tingkat']);
        $peraih = trim($_POST['peraih']);
        $tahun = trim($_POST['tahun']);
        $kategori = trim($_POST['kategori']);

        $stmt = $pdo->prepare("INSERT INTO prestasi (judul, tingkat, peraih, tahun, kategori) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$judul, $tingkat, $peraih, $tahun, $kategori]);
        $msg = "Prestasi baru berhasil ditambahkan!";
        log_activity("Admin added achievement: $judul");
    } elseif ($_POST['action'] === 'hapus_prestasi') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM prestasi WHERE id = ?")->execute([$id]);
        $msg = "Data prestasi berhasil dihapus!";
    }
}

$prestasiList = $pdo->query("SELECT * FROM prestasi ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-trophy text-amber-400"></i> Rekap Prestasi Siswa & Guru
            </h1>
            <p class="text-xs text-slate-400 mt-1">Penghargaan kompetisi akademik, sains, seni, dan olahraga</p>
        </div>
        <button type="button" onclick="openModal('modal-add-prestasi')" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-plus"></i> Tambah Prestasi
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <?php foreach ($prestasiList as $pr): ?>
            <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 flex items-start gap-4 shadow-xl relative">
                <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-xl shrink-0 border border-amber-500/20">
                    <i class="fa-solid fa-medal"></i>
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-1">
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-amber-500/10 text-amber-400 border border-amber-500/20"><?= htmlspecialchars($pr['tingkat']); ?></span>
                        <span class="text-xs font-mono text-slate-500"><?= htmlspecialchars($pr['tahun']); ?></span>
                    </div>
                    <h3 class="text-sm font-extrabold text-white mb-1"><?= htmlspecialchars($pr['judul']); ?></h3>
                    <p class="text-xs text-slate-400"><i class="fa-solid fa-user-graduate text-indigo-400 mr-1"></i>Peraih: <strong class="text-white"><?= htmlspecialchars($pr['peraih']); ?></strong></p>
                </div>
                <form action="dashboard.php?page=prestasi" method="POST" onsubmit="return confirm('Hapus prestasi ini?');">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="hapus_prestasi">
                    <input type="hidden" name="id" value="<?= $pr['id']; ?>">
                    <button type="submit" class="text-slate-600 hover:text-rose-400 text-xs"><i class="fa-solid fa-trash"></i></button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Tambah Prestasi -->
    <div id="modal-add-prestasi" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Tambah Data Prestasi</h3>
                <button type="button" onclick="closeModal('modal-add-prestasi')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=prestasi" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_prestasi">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Penghargaan / Kejuaraan</label>
                    <input type="text" name="judul" required placeholder="Juara 1 Lomba Karya Tulis Ilmiah" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tingkat</label>
                        <select name="tingkat" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="Kota / Kabupaten">Kota / Kab</option>
                            <option value="Provinsi">Provinsi</option>
                            <option value="Nasional">Nasional</option>
                            <option value="Internasional">Internasional</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tahun Perolehan</label>
                        <input type="text" name="tahun" value="2026" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Peraih / Tim</label>
                    <input type="text" name="peraih" required placeholder="Bintang Pratama / Tim Robotik" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kategori Peraih</label>
                    <select name="kategori" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="Siswa">Siswa</option>
                        <option value="Guru">Guru / Pengajar</option>
                        <option value="Sekolah">Kelembagaan Sekolah</option>
                    </select>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-prestasi')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-amber-500 text-slate-950 rounded-xl text-xs font-bold">Simpan Prestasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
