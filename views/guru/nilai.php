<?php
// views/guru/nilai.php
check_role(['guru', 'admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_nilai') {
    verify_csrf_token();
    $nis = trim($_POST['nis'] ?? '');
    $mapel = trim($_POST['mapel'] ?? '');
    $sem = trim($_POST['semester'] ?? 'Ganjil');
    $tugas = intval($_POST['nilai_tugas'] ?? 0);
    $uts = intval($_POST['nilai_uts'] ?? 0);
    $uas = intval($_POST['nilai_uas'] ?? 0);
    $predikat = trim($_POST['predikat'] ?? 'A');
    $catatan = trim($_POST['catatan'] ?? '');

    $stmt = $pdo->prepare("INSERT INTO nilai (nis, mata_pelajaran, semester, nilai_tugas, nilai_uts, nilai_uas, predikat, catatan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$nis, $mapel, $sem, $tugas, $uts, $uas, $predikat, $catatan]);
    $msg = "Nilai siswa berhasil diinput ke rapor digital!";
}

$siswaList = $pdo->query("SELECT nis, nama, kelas FROM siswa ORDER BY nama ASC")->fetchAll();
$nilaiList = $pdo->query("SELECT n.*, s.nama, s.kelas FROM nilai n JOIN siswa s ON n.nis = s.nis ORDER BY n.id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Input Nilai Siswa (Rapor Digital)</h1>
            <p class="text-xs text-slate-400">Pengisian pencapaian nilai tugas, UTS, UAS, dan predikat capaian</p>
        </div>
        <button type="button" onclick="openModal('modal-nilai')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/30 flex items-center gap-2">
            <i class="fa-solid fa-pen-to-square"></i> Input Nilai Siswa
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Siswa</th>
                        <th class="p-3.5">Mata Pelajaran</th>
                        <th class="p-3.5">Semester</th>
                        <th class="p-3.5 text-center">Tugas</th>
                        <th class="p-3.5 text-center">UTS</th>
                        <th class="p-3.5 text-center">UAS</th>
                        <th class="p-3.5 text-center">Predikat</th>
                        <th class="p-3.5 rounded-r-xl">Catatan Guru</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($nilaiList as $n): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($n['nama']); ?> (<?= htmlspecialchars($n['kelas']); ?>)</td>
                            <td class="p-3.5 text-blue-400 font-bold"><?= htmlspecialchars($n['mata_pelajaran']); ?></td>
                            <td class="p-3.5 text-slate-400"><?= htmlspecialchars($n['semester']); ?></td>
                            <td class="p-3.5 text-center font-mono"><?= $n['nilai_tugas']; ?></td>
                            <td class="p-3.5 text-center font-mono"><?= $n['nilai_uts']; ?></td>
                            <td class="p-3.5 text-center font-mono"><?= $n['nilai_uas']; ?></td>
                            <td class="p-3.5 text-center">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-blue-500/10 text-blue-400 border border-blue-500/20">
                                    <?= htmlspecialchars($n['predikat']); ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-slate-400 italic"><?= htmlspecialchars($n['catatan']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Input Nilai -->
    <div id="modal-nilai" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Input Nilai Rapor Siswa</h3>
                <button type="button" onclick="closeModal('modal-nilai')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=nilai" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="save_nilai">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Pilih Siswa</label>
                    <select name="nis" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <?php foreach ($siswaList as $sw): ?>
                            <option value="<?= $sw['nis']; ?>"><?= htmlspecialchars($sw['nama']); ?> - <?= htmlspecialchars($sw['kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Mata Pelajaran</label>
                        <input type="text" name="mapel" value="Matematika Wajib" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Semester</label>
                        <input type="text" name="semester" value="Genap 2025/2026" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Nilai Tugas</label>
                        <input type="number" name="nilai_tugas" value="88" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Nilai UTS</label>
                        <input type="number" name="nilai_uts" value="85" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Nilai UAS</label>
                        <input type="number" name="nilai_uas" value="90" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Predikat</label>
                        <select name="predikat" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="A">A (Sangat Baik)</option>
                            <option value="A-">A- (Sangat Baik)</option>
                            <option value="B+">B+ (Baik)</option>
                            <option value="B">B (Cukup)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Catatan Evaluasi Guru</label>
                        <input type="text" name="catatan" value="Penguasaan materi sangat memuaskan." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-nilai')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold">Simpan Nilai</button>
                </div>
            </form>
        </div>
    </div>
</div>
