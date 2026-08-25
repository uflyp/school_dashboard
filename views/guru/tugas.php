<?php
// views/guru/tugas.php
check_role(['guru', 'siswa', 'admin']);
$role = $role ?? ($_SESSION['role'] ?? 'guru');

$msg = '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];

    if ($action === 'add_tugas') {
        if (!in_array($role, ['guru', 'admin'])) {
            die("Akses Ditolak: Hanya Guru yang dapat membuat tugas!");
        }

        $judul = trim($_POST['judul']);
        $mapel = trim($_POST['mapel']);
        $kelas = trim($_POST['kelas']);
        $deadline = trim($_POST['deadline']);
        $instruksi = trim($_POST['instruksi']);
        $createdBy = $user['name'];

        $stmt = $pdo->prepare("INSERT INTO tugas (user_id, judul, mapel, kelas, deadline, instruksi, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $judul, $mapel, $kelas, $deadline, $instruksi, $createdBy]);
        $msg = "Tugas baru berhasil diterbitkan untuk siswa!";
        log_activity("Teacher published new assignment: $judul");
    } elseif ($action === 'edit_tugas') {
        if (!in_array($role, ['guru', 'admin'])) {
            die("Akses Ditolak!");
        }

        $id = intval($_POST['id']);
        $judul = trim($_POST['judul']);
        $mapel = trim($_POST['mapel']);
        $kelas = trim($_POST['kelas']);
        $deadline = trim($_POST['deadline']);
        $instruksi = trim($_POST['instruksi']);

        // Strict backend ownership verification
        $stmtCheck = $pdo->prepare("SELECT * FROM tugas WHERE id = ?");
        $stmtCheck->execute([$id]);
        $existing = $stmtCheck->fetch();

        if (!$existing) {
            die("Error: Tugas tidak ditemukan!");
        }
        if ($role === 'guru' && intval($existing['user_id']) !== intval($user_id)) {
            die("Akses Ditolak: Anda tidak memiliki hak akses untuk mengedit tugas milik guru lain!");
        }

        $stmt = $pdo->prepare("UPDATE tugas SET judul = ?, mapel = ?, kelas = ?, deadline = ?, instruksi = ? WHERE id = ?");
        $stmt->execute([$judul, $mapel, $kelas, $deadline, $instruksi, $id]);
        $msg = "Tugas pelajaran berhasil diperbarui!";
        log_activity("Teacher edited assignment ID: $id");
    } elseif ($action === 'delete_tugas') {
        if (!in_array($role, ['guru', 'admin'])) {
            die("Akses Ditolak!");
        }

        $id = intval($_POST['id']);

        // Strict backend ownership verification
        $stmtCheck = $pdo->prepare("SELECT * FROM tugas WHERE id = ?");
        $stmtCheck->execute([$id]);
        $existing = $stmtCheck->fetch();

        if (!$existing) {
            die("Error: Tugas tidak ditemukan!");
        }
        if ($role === 'guru' && intval($existing['user_id']) !== intval($user_id)) {
            die("Akses Ditolak: Anda tidak memiliki hak akses untuk menghapus tugas milik guru lain!");
        }

        $stmt = $pdo->prepare("DELETE FROM tugas WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Tugas pelajaran berhasil dihapus!";
        log_activity("Teacher deleted assignment ID: $id");
    }
}

// Scoped SELECT: Guru only views assignments created by themselves
if ($role === 'guru') {
    $stmtTug = $pdo->prepare("SELECT * FROM tugas WHERE user_id = ? ORDER BY id DESC");
    $stmtTug->execute([$user_id]);
    $tugasList = $stmtTug->fetchAll();
} else {
    // Admin & Siswa can view all active assignments
    $tugasList = $pdo->query("SELECT * FROM tugas ORDER BY id DESC")->fetchAll();
}
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-400"></i> Tugas & Assignment Pembelajaran Saya
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                <?= $role === 'guru' ? 'Kelola daftar penugasan, batas waktu deadline, dan edit/hapus tugas pribadi Anda' : 'Daftar penugasan siswa beserta instruksi dan deadline pengumpulan'; ?>
            </p>
        </div>
        <?php if (in_array($role, ['guru', 'admin'])): ?>
            <button type="button" onclick="openModal('modal-tugas')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-plus"></i> Buat Tugas Baru
            </button>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($tugasList)): ?>
        <div class="p-12 rounded-3xl bg-slate-900 border border-slate-800 text-center space-y-3">
            <div class="w-16 h-16 rounded-2xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-2xl mx-auto">
                <i class="fa-solid fa-clipboard-list"></i>
            </div>
            <h3 class="text-base font-bold text-white">Belum Ada Tugas Pembelajaran</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto">
                <?= $role === 'guru' ? 'Anda belum menerbitkan tugas apapun. Klik tombol di atas untuk membuat tugas baru.' : 'Belum terdapat penugasan aktif.'; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($tugasList as $t): ?>
                <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xl hover:border-indigo-500/40 transition-all">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20"><?= htmlspecialchars($t['mapel']); ?></span>
                            <span class="text-[10px] text-slate-400 font-mono font-bold bg-slate-800 px-2 py-0.5 rounded"><?= htmlspecialchars($t['kelas']); ?></span>
                            <span class="text-xs text-amber-400 font-mono font-bold"><i class="fa-regular fa-clock mr-1"></i>Deadline: <?= date('d F Y', strtotime($t['deadline'])); ?></span>
                            <span class="text-[10px] text-slate-500">Pembuat: <strong class="text-slate-300"><?= htmlspecialchars($t['created_by']); ?></strong></span>
                        </div>
                        <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($t['judul']); ?></h3>
                        <p class="text-xs text-slate-400 leading-relaxed max-w-3xl"><?= htmlspecialchars($t['instruksi']); ?></p>
                    </div>

                    <div class="shrink-0 flex flex-wrap items-center gap-2">
                        <?php
                        $stmtSubCount = $pdo->prepare("SELECT COUNT(*) FROM tugas_jawaban WHERE tugas_id = ?");
                        $stmtSubCount->execute([$t['id']]);
                        $subCount = (int)$stmtSubCount->fetchColumn();

                        // Fetch submissions for this task
                        $stmtSubs = $pdo->prepare("SELECT * FROM tugas_jawaban WHERE tugas_id = ? ORDER BY id DESC");
                        $stmtSubs->execute([$t['id']]);
                        $submissions = $stmtSubs->fetchAll();
                        ?>
                        <button type="button" onclick='openSubmissionsModal(<?= json_encode($t['judul']); ?>, <?= json_encode($submissions); ?>)' class="px-3.5 py-2 rounded-xl bg-cyan-600/10 text-cyan-400 hover:bg-cyan-600 hover:text-white border border-cyan-500/20 transition-all text-xs font-bold flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-file-arrow-down"></i> Pengumpulan (<?= $subCount; ?>)
                        </button>

                        <?php if (in_array($role, ['guru', 'admin']) && (intval($t['user_id']) === intval($user_id) || $role === 'admin')): ?>
                            <button type="button" onclick='openEditTugas(<?= json_encode($t); ?>)' class="px-3 py-2 rounded-xl bg-indigo-500/10 text-indigo-400 hover:bg-indigo-600 hover:text-white transition-all text-xs font-bold flex items-center gap-1.5" title="Edit Tugas">
                                <i class="fa-solid fa-pen-to-square"></i> Edit
                            </button>
                            <form action="dashboard.php?page=tugas" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tugas ini?');" class="inline">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_tugas">
                                <input type="hidden" name="id" value="<?= $t['id']; ?>">
                                <button type="submit" class="px-3 py-2 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white transition-all text-xs font-bold flex items-center gap-1.5" title="Hapus Tugas">
                                    <i class="fa-solid fa-trash"></i> Hapus
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal Buat Tugas -->
    <div id="modal-tugas" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Buat Tugas Baru</h3>
                <button type="button" onclick="closeModal('modal-tugas')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=tugas" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_tugas">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Tugas</label>
                    <input type="text" name="judul" required placeholder="Latihan Soal Bab 2..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Mata Pelajaran</label>
                        <input type="text" name="mapel" required placeholder="Matematika Wajib" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kelas Target</label>
                        <input type="text" name="kelas" required value="XII IPA 1" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Deadline Pengumpulan</label>
                    <input type="date" name="deadline" required value="<?= date('Y-m-d', strtotime('+7 days')); ?>" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Instruksi & Petunjuk Tugas</label>
                    <textarea name="instruksi" rows="3" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white" placeholder="Petunjuk pengerjaan..."></textarea>
                </div>
                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-tugas')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Terbitkan Tugas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Tugas -->
    <div id="modal-edit-tugas" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Edit Tugas Pelajaran</h3>
                <button type="button" onclick="closeModal('modal-edit-tugas')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=tugas" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_tugas">
                <input type="hidden" name="id" id="edit_tugas_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Tugas</label>
                    <input type="text" name="judul" id="edit_tugas_judul" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Mata Pelajaran</label>
                        <input type="text" name="mapel" id="edit_tugas_mapel" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kelas Target</label>
                        <input type="text" name="kelas" id="edit_tugas_kelas" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Deadline Pengumpulan</label>
                    <input type="date" name="deadline" id="edit_tugas_deadline" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Instruksi & Petunjuk Tugas</label>
                    <textarea name="instruksi" id="edit_tugas_instruksi" rows="3" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"></textarea>
                </div>
                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-tugas')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Daftar Pengumpulan Jawaban Siswa -->
    <div id="modal-submissions" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-2xl shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-folder-open text-cyan-400"></i> Pengumpulan Tugas: <span id="sub-task-title" class="text-cyan-300"></span>
                    </h3>
                    <p class="text-[11px] text-slate-400">Daftar siswa yang telah mengirimkan berkas lembar jawaban</p>
                </div>
                <button type="button" onclick="closeModal('modal-submissions')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="max-h-80 overflow-y-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold sticky top-0">
                        <tr>
                            <th class="p-3 rounded-l-xl">Nama Siswa</th>
                            <th class="p-3">Waktu Kirim</th>
                            <th class="p-3">Catatan Siswa</th>
                            <th class="p-3 rounded-r-xl text-center">Berkas Jawaban</th>
                        </tr>
                    </thead>
                    <tbody id="sub-table-body" class="divide-y divide-slate-800/60">
                    </tbody>
                </table>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-800 flex justify-end">
                <button type="button" onclick="closeModal('modal-submissions')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
    function openEditTugas(data) {
        document.getElementById('edit_tugas_id').value = data.id || '';
        document.getElementById('edit_tugas_judul').value = data.judul || '';
        document.getElementById('edit_tugas_mapel').value = data.mapel || '';
        document.getElementById('edit_tugas_kelas').value = data.kelas || '';
        document.getElementById('edit_tugas_deadline').value = data.deadline || '';
        document.getElementById('edit_tugas_instruksi').value = data.instruksi || '';
        openModal('modal-edit-tugas');
    }

    function openSubmissionsModal(title, subs) {
        document.getElementById('sub-task-title').innerText = title;
        const tbody = document.getElementById('sub-table-body');
        tbody.innerHTML = '';

        if (!subs || subs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="p-6 text-center text-slate-500">Belum ada siswa yang mengumpulkan tugas ini.</td></tr>';
        } else {
            subs.forEach(s => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-800/40';
                tr.innerHTML = `
                    <td class="p-3 font-bold text-white">${escapeHtml(s.siswa_nama || 'Siswa')}</td>
                    <td class="p-3 font-mono text-slate-400 text-[11px]">${s.created_at}</td>
                    <td class="p-3 text-slate-300 text-[11px]">${escapeHtml(s.catatan || '-')}</td>
                    <td class="p-3 text-center">
                        <a href="${escapeHtml(s.file_path)}" download class="px-3 py-1 bg-cyan-600 hover:bg-cyan-500 text-white rounded-lg font-bold text-[11px] inline-flex items-center gap-1">
                            <i class="fa-solid fa-download"></i> Unduh
                        </a>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
        openModal('modal-submissions');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
</script>
