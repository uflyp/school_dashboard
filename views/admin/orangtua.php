<?php
// views/admin/orangtua.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'tambah_orangtua') {
        $nama = trim($_POST['nama']);
        $nis_anak = trim($_POST['nis_anak']);
        $nama_anak = trim($_POST['nama_anak']);
        $no_hp = trim($_POST['no_hp']);
        $pekerjaan = trim($_POST['pekerjaan']);

        $stmt = $pdo->prepare("INSERT INTO orangtua (nama, nis_anak, nama_anak, no_hp, pekerjaan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$nama, $nis_anak, $nama_anak, $no_hp, $pekerjaan]);
        $msg = "Data orang tua / wali $nama berhasil ditambahkan!";
        log_activity("Admin registered parent data: $nama");
    } elseif ($_POST['action'] === 'hapus_orangtua') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM orangtua WHERE id = ?")->execute([$id]);
        $msg = "Data orang tua berhasil dihapus!";
    }
}

$ortuList = $pdo->query("SELECT * FROM orangtua ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-people-roof text-amber-400"></i> Data Orang Tua & Wali Murid
            </h1>
            <p class="text-xs text-slate-400 mt-1">Direktori orang tua siswa, kontak WhatsApp, dan hubungan wali murid</p>
        </div>
        <button type="button" onclick="openModal('modal-add-ortu')" class="px-4 py-2.5 bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs rounded-xl shadow-lg shadow-amber-500/20 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-plus"></i> Tambah Orang Tua / Wali
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">ID</th>
                        <th class="p-3.5">Nama Orang Tua / Wali</th>
                        <th class="p-3.5">Siswa (NIS & Nama)</th>
                        <th class="p-3.5">Pekerjaan</th>
                        <th class="p-3.5">No. HP / WA</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($ortuList as $ot): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 text-slate-500 font-mono">#<?= $ot['id']; ?></td>
                            <td class="p-3.5 font-extrabold text-white"><?= htmlspecialchars($ot['nama']); ?></td>
                            <td class="p-3.5">
                                <div class="font-bold text-white"><?= htmlspecialchars($ot['nama_anak']); ?></div>
                                <div class="text-[10px] text-amber-400 font-mono">NIS: <?= htmlspecialchars($ot['nis_anak']); ?></div>
                            </td>
                            <td class="p-3.5 text-slate-300"><?= htmlspecialchars($ot['pekerjaan'] ?: '-'); ?></td>
                            <td class="p-3.5 text-emerald-400 font-bold"><i class="fa-brands fa-whatsapp mr-1"></i><?= htmlspecialchars($ot['no_hp'] ?: '-'); ?></td>
                            <td class="p-3.5 text-center">
                                <form action="dashboard.php?page=orangtua" method="POST" onsubmit="return confirm('Hapus data orang tua ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="hapus_orangtua">
                                    <input type="hidden" name="id" value="<?= $ot['id']; ?>">
                                    <button type="submit" class="px-3 py-1 bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white rounded-lg border border-rose-500/20 text-xs font-bold transition-all">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Orang Tua -->
    <div id="modal-add-ortu" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Tambah Data Orang Tua / Wali</h3>
                <button type="button" onclick="closeModal('modal-add-ortu')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=orangtua" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_orangtua">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap Orang Tua / Wali</label>
                    <input type="text" name="nama" required placeholder="Ir. Budi Santoso" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">NIS Anak</label>
                        <input type="text" name="nis_anak" required placeholder="2026001" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Anak</label>
                        <input type="text" name="nama_anak" required placeholder="Bintang Pratama" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">No. HP / WhatsApp</label>
                        <input type="text" name="no_hp" placeholder="081234567890" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Pekerjaan</label>
                        <input type="text" name="pekerjaan" placeholder="Wiraswasta" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-ortu')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-amber-500 text-slate-950 rounded-xl text-xs font-bold">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>
