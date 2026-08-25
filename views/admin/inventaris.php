<?php
// views/admin/inventaris.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'tambah_inventaris') {
        $nama_barang = trim($_POST['nama_barang']);
        $kode_barang = trim($_POST['kode_barang']);
        $jumlah = intval($_POST['jumlah']);
        $kondisi = trim($_POST['kondisi']);
        $lokasi = trim($_POST['lokasi']);

        try {
            $stmt = $pdo->prepare("INSERT INTO inventaris (nama_barang, kode_barang, jumlah, kondisi, lokasi) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama_barang, $kode_barang, $jumlah, $kondisi, $lokasi]);
            $msg = "Barang inventaris $nama_barang berhasil dicatat!";
            log_activity("Admin registered inventory item: $kode_barang");
        } catch (Exception $e) {
            $msg = "Gagal mencatat inventaris: Kode Barang sudah terdaftar!";
        }
    } elseif ($_POST['action'] === 'hapus_inventaris') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM inventaris WHERE id = ?")->execute([$id]);
        $msg = "Data barang inventaris berhasil dihapus!";
    }
}

$invList = $pdo->query("SELECT * FROM inventaris ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-boxes-stacked text-indigo-400"></i> Inventaris Sarana & Prasarana
            </h1>
            <p class="text-xs text-slate-400 mt-1">Pencatatan aset sekolah, peralatan laboratorium, komputer, dan kondisi barang</p>
        </div>
        <button type="button" onclick="openModal('modal-add-inv')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-plus"></i> Tambah Inventaris
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Kode Barang</th>
                        <th class="p-3.5">Nama Barang / Aset</th>
                        <th class="p-3.5">Jumlah</th>
                        <th class="p-3.5">Kondisi</th>
                        <th class="p-3.5">Lokasi Penempatan</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($invList as $iv): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-mono text-indigo-400 font-bold"><?= htmlspecialchars($iv['kode_barang']); ?></td>
                            <td class="p-3.5 font-extrabold text-white"><?= htmlspecialchars($iv['nama_barang']); ?></td>
                            <td class="p-3.5 font-mono text-emerald-400 font-bold"><?= $iv['jumlah']; ?> Unit</td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <?= htmlspecialchars($iv['kondisi']); ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-slate-300"><i class="fa-solid fa-location-dot text-rose-400 mr-1"></i><?= htmlspecialchars($iv['lokasi']); ?></td>
                            <td class="p-3.5 text-center">
                                <form action="dashboard.php?page=inventaris" method="POST" onsubmit="return confirm('Hapus barang inventaris ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="hapus_inventaris">
                                    <input type="hidden" name="id" value="<?= $iv['id']; ?>">
                                    <button type="submit" class="px-3 py-1 bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white rounded-lg border border-rose-500/20 text-xs font-bold transition-all">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Inventaris -->
    <div id="modal-add-inv" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Catat Barang Inventaris Baru</h3>
                <button type="button" onclick="closeModal('modal-add-inv')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=inventaris" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_inventaris">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Barang</label>
                        <input type="text" name="kode_barang" required placeholder="INV-PRJ-03" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jumlah (Unit)</label>
                        <input type="number" name="jumlah" value="5" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Barang / Aset</label>
                    <input type="text" name="nama_barang" required placeholder="Smart TV LED 55 Inch" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kondisi</label>
                        <select name="kondisi" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="Baik">Sangat Baik</option>
                            <option value="Perlu Perbaikan">Perlu Perbaikan</option>
                            <option value="Rusak Ringan">Rusak Ringan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Lokasi Ruangan</label>
                        <input type="text" name="lokasi" required placeholder="Lab Multimedia" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-inv')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Aset</button>
                </div>
            </form>
        </div>
    </div>
</div>
