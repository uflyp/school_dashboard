<?php
// views/admin/surat.php
check_role(['admin', 'kepala_sekolah']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'tambah_surat') {
        $nomor_surat = trim($_POST['nomor_surat']);
        $perihal = trim($_POST['perihal']);
        $jenis = trim($_POST['jenis']);
        $pengirim_penerima = trim($_POST['pengirim_penerima']);
        $tanggal = date('Y-m-d');

        try {
            $stmt = $pdo->prepare("INSERT INTO surat (nomor_surat, perihal, jenis, pengirim_penerima, tanggal) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nomor_surat, $perihal, $jenis, $pengirim_penerima, $tanggal]);
            $msg = "Dokumen e-Surat $nomor_surat berhasil diregistrasi!";
            log_activity("Admin registered official letter: $nomor_surat");
        } catch (Exception $e) {
            $msg = "Gagal mencatat surat: Nomor Surat sudah terdaftar!";
        }
    } elseif ($_POST['action'] === 'hapus_surat') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM surat WHERE id = ?")->execute([$id]);
        $msg = "Dokumen surat berhasil dihapus!";
    }
}

$suratList = $pdo->query("SELECT * FROM surat ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-file-signature text-purple-400"></i> Persuratan & E-Surat Resmi
            </h1>
            <p class="text-xs text-slate-400 mt-1">Arsip registrasi surat masuk, surat keluar, dan SK Kepala Sekolah</p>
        </div>
        <button type="button" onclick="openModal('modal-add-surat')" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-plus"></i> Registrasi Surat Baru
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Nomor Surat</th>
                        <th class="p-3.5">Jenis</th>
                        <th class="p-3.5">Perihal</th>
                        <th class="p-3.5">Pengirim / Penerima</th>
                        <th class="p-3.5">Tanggal</th>
                        <th class="p-3.5 rounded-r-xl text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($suratList as $sr): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-mono text-purple-400 font-bold"><?= htmlspecialchars($sr['nomor_surat']); ?></td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold <?= $sr['jenis'] === 'Surat Keluar' ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' ?>">
                                    <?= htmlspecialchars($sr['jenis']); ?>
                                </span>
                            </td>
                            <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($sr['perihal']); ?></td>
                            <td class="p-3.5 text-slate-300"><?= htmlspecialchars($sr['pengirim_penerima']); ?></td>
                            <td class="p-3.5 font-mono text-slate-400"><?= date('d/m/Y', strtotime($sr['tanggal'])); ?></td>
                            <td class="p-3.5 text-center flex items-center justify-center gap-2">
                                <button type="button" onclick="window.print()" class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 hover:text-white border border-slate-700 text-xs"><i class="fa-solid fa-print"></i></button>
                                <form action="dashboard.php?page=surat" method="POST" onsubmit="return confirm('Hapus registrasi surat ini?');">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="hapus_surat">
                                    <input type="hidden" name="id" value="<?= $sr['id']; ?>">
                                    <button type="submit" class="px-2.5 py-1 bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white rounded-lg border border-rose-500/20 text-xs font-bold transition-all"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah Surat -->
    <div id="modal-add-surat" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Registrasi Dokumen Surat</h3>
                <button type="button" onclick="closeModal('modal-add-surat')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=surat" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_surat">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nomor Surat Resmi</label>
                    <input type="text" name="nomor_surat" required placeholder="421.3/002/SMA-NS/2026" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Surat</label>
                    <select name="jenis" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="Surat Keluar">Surat Keluar</option>
                        <option value="Surat Masuk">Surat Masuk</option>
                        <option value="Surat Keputusan (SK)">Surat Keputusan (SK)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Perihal / Isi Ringkasan</label>
                    <input type="text" name="perihal" required placeholder="Pemberitahuan Ujian Akhir Semester" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Pengirim / Penerima</label>
                    <input type="text" name="pengirim_penerima" required placeholder="Kepada Seluruh Orang Tua Siswa" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-surat')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-xs font-bold">Simpan Registrasi</button>
                </div>
            </form>
        </div>
    </div>
</div>
