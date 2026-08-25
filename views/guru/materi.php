<?php
// views/guru/materi.php
check_role(['guru', 'siswa', 'admin']);
$role = $role ?? ($_SESSION['role'] ?? 'guru');

$msg = '';
$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];

    if ($action === 'add_materi') {
        if (!in_array($role, ['guru', 'admin'])) {
            die("Akses Ditolak: Hanya Guru yang dapat menambahkan materi!");
        }

        $judul = trim($_POST['judul']);
        $mapel = trim($_POST['mapel']);
        $kelas = trim($_POST['kelas']);
        $deskripsi = trim($_POST['deskripsi']);
        $createdBy = $user['name'];
        $tanggal = date('Y-m-d');

        // Handle File Upload
        $filePath = '';
        if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/materi/';
            if (!file_exists($uploadDir)) @mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['file_materi']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'mp4', 'png', 'jpg'];
            if (in_array($ext, $allowed)) {
                $target = $uploadDir . 'materi_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['file_materi']['tmp_name'], $target)) {
                    $filePath = $target;
                }
            }
        }

        $stmt = $pdo->prepare("INSERT INTO materi (user_id, judul, mapel, kelas, deskripsi, file_path, created_by, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $judul, $mapel, $kelas, $deskripsi, $filePath, $createdBy, $tanggal]);
        $msg = "Materi pelajaran berhasil ditambahkan!";
        log_activity("Teacher uploaded new material: $judul");
    } elseif ($action === 'edit_materi') {
        if (!in_array($role, ['guru', 'admin'])) {
            die("Akses Ditolak!");
        }

        $id = intval($_POST['id']);
        $judul = trim($_POST['judul']);
        $mapel = trim($_POST['mapel']);
        $kelas = trim($_POST['kelas']);
        $deskripsi = trim($_POST['deskripsi']);

        // Strict backend ownership verification
        $stmtCheck = $pdo->prepare("SELECT * FROM materi WHERE id = ?");
        $stmtCheck->execute([$id]);
        $existing = $stmtCheck->fetch();

        if (!$existing) {
            die("Error: Materi tidak ditemukan!");
        }
        if ($role === 'guru' && intval($existing['user_id']) !== intval($user_id)) {
            die("Akses Ditolak: Anda tidak memiliki hak akses untuk mengedit materi milik guru lain!");
        }

        $filePath = $existing['file_path'] ?? '';
        if (isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/materi/';
            if (!file_exists($uploadDir)) @mkdir($uploadDir, 0777, true);
            $ext = strtolower(pathinfo($_FILES['file_materi']['name'], PATHINFO_EXTENSION));
            $allowed = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'zip', 'mp4', 'png', 'jpg'];
            if (in_array($ext, $allowed)) {
                $target = $uploadDir . 'materi_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['file_materi']['tmp_name'], $target)) {
                    $filePath = $target;
                }
            }
        }

        $stmt = $pdo->prepare("UPDATE materi SET judul = ?, mapel = ?, kelas = ?, deskripsi = ?, file_path = ? WHERE id = ?");
        $stmt->execute([$judul, $mapel, $kelas, $deskripsi, $filePath, $id]);
        $msg = "Materi pelajaran berhasil diperbarui!";
        log_activity("Teacher edited material ID: $id");
    } elseif ($action === 'delete_materi') {
        if (!in_array($role, ['guru', 'admin'])) {
            die("Akses Ditolak!");
        }

        $id = intval($_POST['id']);

        // Strict backend ownership verification
        $stmtCheck = $pdo->prepare("SELECT * FROM materi WHERE id = ?");
        $stmtCheck->execute([$id]);
        $existing = $stmtCheck->fetch();

        if (!$existing) {
            die("Error: Materi tidak ditemukan!");
        }
        if ($role === 'guru' && intval($existing['user_id']) !== intval($user_id)) {
            die("Akses Ditolak: Anda tidak memiliki hak akses untuk menghapus materi milik guru lain!");
        }

        $stmt = $pdo->prepare("DELETE FROM materi WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Materi pelajaran berhasil dihapus!";
        log_activity("Teacher deleted material ID: $id");
    }
}

// Scoped SELECT: Guru only views materials created by themselves
if ($role === 'guru') {
    $stmtMat = $pdo->prepare("SELECT * FROM materi WHERE user_id = ? ORDER BY id DESC");
    $stmtMat->execute([$user_id]);
    $materiList = $stmtMat->fetchAll();
} else {
    // Admin & Siswa can view all published materials
    $materiList = $pdo->query("SELECT * FROM materi ORDER BY id DESC")->fetchAll();
}
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-book-bookmark text-blue-400"></i> Modul & Materi Pelajaran Saya
            </h1>
            <p class="text-xs text-slate-400 mt-1">
                <?= $role === 'guru' ? 'Pusat pengelolaan materi bahan ajar pribadi milik Anda' : 'Bahan ajar digital terdaftar SMA Nusantara'; ?>
            </p>
        </div>
        <?php if (in_array($role, ['guru', 'admin'])): ?>
            <button type="button" onclick="openModal('modal-materi')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/30 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-upload"></i> Unggah Materi Baru
            </button>
        <?php endif; ?>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($materiList)): ?>
        <div class="p-12 rounded-3xl bg-slate-900 border border-slate-800 text-center space-y-3">
            <div class="w-16 h-16 rounded-2xl bg-blue-500/10 text-blue-400 flex items-center justify-center text-2xl mx-auto">
                <i class="fa-solid fa-folder-open"></i>
            </div>
            <h3 class="text-base font-bold text-white">Belum Ada Materi Pelajaran</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto">
                <?= $role === 'guru' ? 'Anda belum membuat materi pelajaran apapun. Klik tombol di atas untuk menambah materi baru.' : 'Belum terdapat materi pelajaran.'; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($materiList as $m): ?>
                <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col justify-between group hover:border-blue-500/50 transition-all shadow-xl">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-500/10 text-blue-400 border border-blue-500/20"><?= htmlspecialchars($m['mapel']); ?></span>
                            <span class="text-[10px] text-slate-400 font-mono font-bold bg-slate-800 px-2 py-0.5 rounded"><?= htmlspecialchars($m['kelas']); ?></span>
                        </div>
                        <h3 class="text-base font-bold text-white mb-2 group-hover:text-blue-400 transition-colors"><?= htmlspecialchars($m['judul']); ?></h3>
                        <p class="text-xs text-slate-400 leading-relaxed mb-4"><?= htmlspecialchars($m['deskripsi']); ?></p>
                    </div>

                    <div class="pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
                        <span class="text-slate-500 text-[10px]">Oleh: <strong class="text-slate-300"><?= htmlspecialchars($m['created_by']); ?></strong></span>
                        <div class="flex items-center gap-2">
                            <?php if (in_array($role, ['guru', 'admin']) && (intval($m['user_id']) === intval($user_id) || $role === 'admin')): ?>
                                <button type="button" onclick='openEditMateri(<?= json_encode($m); ?>)' class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 hover:bg-indigo-600 hover:text-white transition-all flex items-center justify-center" title="Edit Materi">
                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                </button>
                                <form action="dashboard.php?page=materi" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus materi ini?');" class="inline">
                                    <?= csrf_field(); ?>
                                    <input type="hidden" name="action" value="delete_materi">
                                    <input type="hidden" name="id" value="<?= $m['id']; ?>">
                                    <button type="submit" class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center" title="Hapus Materi">
                                        <i class="fa-solid fa-trash text-xs"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" onclick="alert('Mengunduh modul PDF...')" class="text-blue-400 hover:underline font-bold text-xs flex items-center gap-1">
                                    <i class="fa-solid fa-download"></i> Unduh
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal Tambah Materi -->
    <div id="modal-materi" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Tambah Modul Materi Baru</h3>
                <button type="button" onclick="closeModal('modal-materi')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=materi" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_materi">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Materi</label>
                    <input type="text" name="judul" required placeholder="Modul Bab 4..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
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
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Deskripsi Ringkas</label>
                    <textarea name="deskripsi" rows="3" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white" placeholder="Penjelasan isi materi..."></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Unggah Berkas / Modul (PDF/DOCX/PPT/MP4)</label>
                    <input type="file" name="file_materi" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-400">
                </div>
                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-materi')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-xl text-xs font-bold">Simpan Materi</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Materi -->
    <div id="modal-edit-materi" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Edit Modul Materi</h3>
                <button type="button" onclick="closeModal('modal-edit-materi')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=materi" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_materi">
                <input type="hidden" name="id" id="edit-materi-id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Materi</label>
                    <input type="text" name="judul" id="edit-materi-judul" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Mata Pelajaran</label>
                        <input type="text" name="mapel" id="edit-materi-mapel" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kelas Target</label>
                        <input type="text" name="kelas" id="edit-materi-kelas" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Deskripsi Ringkas</label>
                    <textarea name="deskripsi" id="edit-materi-deskripsi" rows="3" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Ganti Berkas (Opsional)</label>
                    <input type="file" name="file_materi" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-400">
                </div>
                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-materi')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Perbarui Materi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditMateri(item) {
    document.getElementById('edit-materi-id').value = item.id;
    document.getElementById('edit-materi-judul').value = item.judul;
    document.getElementById('edit-materi-mapel').value = item.mapel;
    document.getElementById('edit-materi-kelas').value = item.kelas;
    document.getElementById('edit-materi-deskripsi').value = item.deskripsi;
    openModal('modal-edit-materi');
}
</script>
