<?php
// views/siswa/materi.php
check_role(['siswa', 'admin']);

$materiList = $pdo->query("SELECT * FROM materi ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
            <i class="fa-solid fa-book-bookmark text-cyan-400"></i> Materi & Bahan Ajar Pelajaran
        </h1>
        <p class="text-xs text-slate-400 mt-1">Unduh bahan ajar, modul digital, dan ringkasan materi pembelajaran</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($materiList as $m): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20"><?= htmlspecialchars($m['mapel']); ?></span>
                        <span class="text-xs font-mono text-slate-500"><?= date('d M Y', strtotime($m['tanggal'])); ?></span>
                    </div>
                    <h3 class="text-base font-bold text-white mb-1"><?= htmlspecialchars($m['judul']); ?></h3>
                    <p class="text-xs text-slate-400 leading-relaxed mb-4"><?= htmlspecialchars($m['deskripsi']); ?></p>
                </div>
                <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                    <span class="text-[10px] text-slate-500">Pengampu: <?= htmlspecialchars($m['created_by']); ?></span>
                    <?php if (!empty($m['file_path']) && file_exists($m['file_path'])): ?>
                        <a href="<?= htmlspecialchars($m['file_path']); ?>" download class="px-3.5 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-md shadow-cyan-600/30 flex items-center gap-1.5 transition-all">
                            <i class="fa-solid fa-download"></i> Unduh Berkas
                        </a>
                    <?php else: ?>
                        <button type="button" onclick="openPreviewMateri('<?= htmlspecialchars($m['judul'], ENT_QUOTES); ?>', '<?= htmlspecialchars($m['mapel'], ENT_QUOTES); ?>', '<?= htmlspecialchars($m['created_by'], ENT_QUOTES); ?>', '<?= htmlspecialchars(addslashes(str_replace(["\r", "\n"], ' ', $m['deskripsi']))); ?>')" class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-cyan-600/20 text-cyan-300 border border-cyan-500/30 font-bold text-xs transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-book-open"></i> Baca Modul
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Modal Baca Ringkasan Modul -->
    <div id="modal-preview-materi" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <div>
                    <span id="prev-mapel" class="px-2.5 py-0.5 rounded-lg text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20"></span>
                    <h3 id="prev-judul" class="text-base font-bold text-white mt-1"></h3>
                    <p id="prev-guru" class="text-[11px] text-slate-400"></p>
                </div>
                <button type="button" onclick="closeModal('modal-preview-materi')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs text-slate-300 leading-relaxed max-h-60 overflow-y-auto">
                <p id="prev-deskripsi"></p>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button" onclick="closeModal('modal-preview-materi')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function openPreviewMateri(judul, mapel, guru, deskripsi) {
    document.getElementById('prev-judul').innerText = judul;
    document.getElementById('prev-mapel').innerText = mapel;
    document.getElementById('prev-guru').innerText = 'Pengampu: ' + guru;
    document.getElementById('prev-deskripsi').innerText = deskripsi;
    openModal('modal-preview-materi');
}
</script>
