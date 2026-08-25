<?php
// views/admin/pengumuman.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'add_pengumuman') {
        $judul = trim($_POST['judul']);
        $isi = trim($_POST['isi']);
        $kategori = trim($_POST['kategori']);
        $tanggal = date('Y-m-d');
        $createdBy = $user['name'];

        $stmt = $pdo->prepare("INSERT INTO pengumuman (judul, isi, kategori, tanggal, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$judul, $isi, $kategori, $tanggal, $createdBy]);
        log_activity("Admin published announcement: $judul");
        $msg = "Pengumuman berhasil diterbitkan!";
    } elseif ($_POST['action'] === 'delete_pengumuman') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM pengumuman WHERE id = ?");
        $stmt->execute([$id]);
        log_activity("Admin deleted announcement ID: $id");
        $msg = "Pengumuman berhasil dihapus!";
    }
}

$pengumumanList = $pdo->query("SELECT * FROM pengumuman ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Kelola Pengumuman Sekolah</h1>
            <p class="text-xs text-slate-400">Publikasi kabar, info ujian, dan kegiatan sekolah ke landing page & dashboard</p>
        </div>
        <button type="button" onclick="openModal('modal-tambah-pengumuman')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition-all">
            <i class="fa-solid fa-plus"></i> Buat Pengumuman Baru
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="space-y-4">
        <?php foreach ($pengumumanList as $p): ?>
            <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-2 max-w-3xl">
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <?= htmlspecialchars($p['kategori']); ?>
                        </span>
                        <span class="text-xs text-slate-500"><i class="fa-regular fa-calendar mr-1"></i><?= date('d M Y', strtotime($p['tanggal'])); ?></span>
                        <span class="text-xs text-slate-500">• Oleh: <strong class="text-slate-300"><?= htmlspecialchars($p['created_by']); ?></strong></span>
                    </div>
                    <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($p['judul']); ?></h3>
                    <p class="text-xs text-slate-400 leading-relaxed"><?= htmlspecialchars($p['isi']); ?></p>
                </div>
                <div class="shrink-0">
                    <form action="dashboard.php?page=pengumuman" method="POST" onsubmit="return confirm('Hapus pengumuman ini?');">
                        <?= csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_pengumuman">
                        <input type="hidden" name="id" value="<?= $p['id']; ?>">
                        <button type="submit" class="px-3.5 py-2 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white transition-all text-xs font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-trash"></i> Hapus
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Tambah Pengumuman -->
    <div id="modal-tambah-pengumuman" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-4">
                <h3 class="text-lg font-bold text-white">Buat Pengumuman Baru</h3>
                <button type="button" onclick="closeModal('modal-tambah-pengumuman')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=pengumuman" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_pengumuman">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Pengumuman</label>
                    <input type="text" name="judul" required placeholder="Judul..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kategori</label>
                    <select name="kategori" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="Akademik">Akademik</option>
                        <option value="Kegiatan">Kegiatan</option>
                        <option value="Info Penting">Info Penting</option>
                        <option value="Umum">Umum</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Isi Pengumuman</label>
                    <textarea name="isi" rows="4" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white" placeholder="Tuliskan detail pengumuman..."></textarea>
                </div>
                <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-tambah-pengumuman')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Terbitkan</button>
                </div>
            </form>
        </div>
    </div>
</div>
