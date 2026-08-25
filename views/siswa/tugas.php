<?php
// views/siswa/tugas.php
check_role(['siswa', 'admin']);

$user = current_user();
$userId = (int)($user['id'] ?? 0);

// Handler Upload Jawaban Tugas Siswa (PDF, Word, PPT, Foto)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_tugas') {
    verify_csrf_token();
    $tugas_id = (int)($_POST['tugas_id'] ?? 0);
    $catatan = trim($_POST['catatan'] ?? '');
    $userName = $user['name'] ?? 'Siswa';

    if ($tugas_id > 0 && $userId > 0 && isset($_FILES['tugas_file']) && $_FILES['tugas_file']['error'] === UPLOAD_ERR_OK) {
        $tmp_name  = $_FILES['tugas_file']['tmp_name'];
        $orig_name = $_FILES['tugas_file']['name'];
        $file_size = $_FILES['tugas_file']['size'];
        $ext       = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        $allowed_exts = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed_exts)) {
            $_SESSION['flash_error'] = "Format file tidak didukung! Pilih file berformat PDF, Word (DOC/DOCX), PPT (PPT/PPTX), atau Foto (JPG/PNG/WEBP).";
        } elseif ($file_size > 15 * 1024 * 1024) { // 15MB
            $_SESSION['flash_error'] = "Ukuran file terlalu besar! Maksimal ukuran file adalah 15MB.";
        } else {
            $upload_dir = 'uploads/tugas/';
            if (!file_exists($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }

            $new_filename = 'jawaban_tugas_' . $tugas_id . '_user_' . $userId . '_' . time() . '.' . $ext;
            $target_file  = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $target_file)) {
                // Check if existing submission exists
                $stmtCheck = $pdo->prepare("SELECT id FROM tugas_jawaban WHERE tugas_id = ? AND user_id = ?");
                $stmtCheck->execute([$tugas_id, $userId]);
                $existing = $stmtCheck->fetch();

                $now = date('Y-m-d H:i:s');
                if ($existing) {
                    $stmtUpd = $pdo->prepare("UPDATE tugas_jawaban SET file_path = ?, file_name = ?, file_type = ?, file_size = ?, catatan = ?, created_at = ? WHERE id = ?");
                    $stmtUpd->execute([$target_file, $orig_name, $ext, $file_size, $catatan, $now, $existing['id']]);
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO tugas_jawaban (tugas_id, user_id, siswa_nama, catatan, file_path, file_name, file_type, file_size, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtIns->execute([$tugas_id, $userId, $userName, $catatan, $target_file, $orig_name, $ext, $file_size, $now]);
                }

                $_SESSION['flash_success'] = "Berhasil! Lembar jawaban tugas '$orig_name' telah terkirim.";
                log_activity("Siswa unggah tugas ID $tugas_id: $orig_name");
            } else {
                $_SESSION['flash_error'] = "Gagal mengunggah file lembar jawaban ke server!";
            }
        }
    } else {
        $_SESSION['flash_error'] = "Harap pilih file lembar jawaban tugas dari perangkat Anda!";
    }

    header("Location: dashboard.php?page=tugas");
    exit();
}

// Fetch list of tasks with dynamic official teacher lookup from guru & mapel tables
$sqlTugas = "SELECT t.*, 
                    COALESCE(g.nama, m.pengampu, u.name, t.created_by) AS official_guru_nama
             FROM tugas t
             LEFT JOIN guru g ON (LOWER(g.mata_pelajaran) = LOWER(t.mapel) OR LOWER(g.nama) = LOWER(t.created_by))
             LEFT JOIN mapel m ON LOWER(m.nama_mapel) = LOWER(t.mapel)
             LEFT JOIN users u ON t.user_id = u.id
             GROUP BY t.id
             ORDER BY t.id DESC";
$tugasList = $pdo->query($sqlTugas)->fetchAll();

// Fetch student's submitted answers
$submittedAnswers = [];
if ($userId > 0) {
    $stmtAns = $pdo->prepare("SELECT * FROM tugas_jawaban WHERE user_id = ?");
    $stmtAns->execute([$userId]);
    $rows = $stmtAns->fetchAll();
    foreach ($rows as $r) {
        $submittedAnswers[$r['tugas_id']] = $r;
    }
}

// File icon helper
function get_file_icon_class($ext) {
    switch (strtolower($ext)) {
        case 'pdf':
            return 'fa-file-pdf text-rose-400';
        case 'doc':
        case 'docx':
            return 'fa-file-word text-blue-400';
        case 'ppt':
        case 'pptx':
            return 'fa-file-powerpoint text-amber-400';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'webp':
            return 'fa-file-image text-emerald-400';
        default:
            return 'fa-file text-slate-400';
    }
}
?>

<div class="space-y-6">

    <!-- Header Card -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 border border-slate-800 p-6 sm:p-8 rounded-3xl shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-cyan-500/10 border border-cyan-500/20 text-cyan-400 text-xs font-bold mb-2">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload Jawaban Tugas Siswa
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-list-check text-cyan-400"></i> Tugas & Assignment Siswa
            </h1>
            <p class="text-xs text-slate-400 mt-1">Unduh instruksi tugas dan unggah jawaban tugas Anda (Format: PDF, Word, PowerPoint, atau Foto)</p>
        </div>

        <div class="flex items-center gap-3">
            <div class="px-4 py-2 rounded-2xl bg-slate-950/80 border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Total Penugasan</span>
                <span class="text-lg font-extrabold text-white"><?= count($tugasList); ?></span>
            </div>
            <div class="px-4 py-2 rounded-2xl bg-slate-950/80 border border-slate-800 text-center">
                <span class="text-[10px] text-slate-400 uppercase font-bold block">Sudah Dikirim</span>
                <span class="text-lg font-extrabold text-emerald-400"><?= count($submittedAnswers); ?></span>
            </div>
        </div>
    </div>

    <!-- Task List Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($tugasList as $tg): ?>
            <?php 
            $tugasId = (int)$tg['id'];
            $ans = $submittedAnswers[$tugasId] ?? null;
            $isSubmitted = !empty($ans);
            ?>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl flex flex-col justify-between hover:border-slate-700 transition-all">
                <div>
                    <!-- Card Top Header -->
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
                            <i class="fa-solid fa-book-bookmark mr-1"></i><?= htmlspecialchars($tg['mapel']); ?> (<?= htmlspecialchars($tg['kelas']); ?>)
                        </span>
                        <span class="text-xs font-mono text-rose-400 font-bold flex items-center gap-1">
                            <i class="fa-regular fa-clock"></i> Deadline: <?= date('d M Y', strtotime($tg['deadline'])); ?>
                        </span>
                    </div>

                    <!-- Title & Instructions -->
                    <h3 class="text-base font-bold text-white mb-1.5"><?= htmlspecialchars($tg['judul']); ?></h3>
                    <p class="text-xs text-slate-400 leading-relaxed mb-4"><?= htmlspecialchars($tg['instruksi']); ?></p>
                </div>

                <!-- Submission Status Box -->
                <div class="pt-4 border-t border-slate-800 space-y-3">
                    <?php if ($isSubmitted): ?>
                        <!-- Already Submitted Box -->
                        <div class="p-3 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-extrabold text-emerald-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check"></i> Sudah Dikirim
                                </span>
                                <span class="text-[10px] text-slate-400 font-mono">
                                    <?= date('d M Y H:i', strtotime($ans['created_at'])); ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between gap-2 bg-slate-950/80 p-2.5 rounded-xl border border-slate-800">
                                <div class="flex items-center gap-2 min-w-0">
                                    <i class="fa-solid <?= get_file_icon_class($ans['file_type']); ?> text-lg shrink-0"></i>
                                    <div class="truncate">
                                        <a href="<?= htmlspecialchars($ans['file_path']); ?>" target="_blank" download class="text-xs font-bold text-slate-200 hover:text-cyan-400 truncate block">
                                            <?= htmlspecialchars($ans['file_name']); ?>
                                        </a>
                                        <span class="text-[9px] text-slate-500 uppercase font-mono">
                                            <?= strtoupper($ans['file_type']); ?> • <?= round($ans['file_size'] / 1024, 1); ?> KB
                                        </span>
                                    </div>
                                </div>
                                <a href="<?= htmlspecialchars($ans['file_path']); ?>" target="_blank" download class="px-2 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-cyan-400 font-bold shrink-0">
                                    <i class="fa-solid fa-download"></i>
                                </a>
                            </div>

                            <?php if (!empty($ans['catatan'])): ?>
                                <p class="text-[11px] text-slate-400 italic">"<?= htmlspecialchars($ans['catatan']); ?>"</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Not Submitted Yet -->
                        <div class="flex items-center justify-between text-xs text-slate-400">
                            <span class="text-amber-400 font-bold text-[11px] flex items-center gap-1">
                                <i class="fa-solid fa-circle-exclamation"></i> Belum Dikirim
                            </span>
                            <span class="text-[10px] text-slate-500">Guru: <?= htmlspecialchars($tg['official_guru_nama'] ?? $tg['created_by']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Action Button -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] text-slate-500">Pengampu: <?= htmlspecialchars($tg['official_guru_nama'] ?? $tg['created_by']); ?></span>
                        <button type="button" 
                                onclick="openSubmitModal(<?= $tugasId; ?>, '<?= htmlspecialchars(addslashes($tg['judul'])); ?>', '<?= htmlspecialchars(addslashes($tg['mapel'])); ?>')" 
                                class="px-4 py-2 rounded-xl <?= $isSubmitted ? 'bg-slate-800 hover:bg-slate-700 text-slate-300' : 'bg-cyan-600 hover:bg-cyan-500 text-white shadow-lg shadow-cyan-600/30' ?> font-bold text-xs flex items-center gap-1.5 transition-all">
                            <i class="fa-solid <?= $isSubmitted ? 'fa-pen-to-square' : 'fa-cloud-arrow-up' ?>"></i>
                            <span><?= $isSubmitted ? 'Kirim Ulang / Ganti File' : 'Unggah Jawaban Tugas' ?></span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal Upload Jawaban Tugas -->
<div id="modal-submit-tugas" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl relative">
        <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3 sticky top-0 bg-slate-900 z-10 pt-1">
            <div>
                <h3 class="text-lg font-extrabold text-white flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up text-cyan-400"></i> Unggah Lembar Jawaban Tugas
                </h3>
                <p class="text-xs text-slate-400">Format file yang didukung: PDF, Word, PowerPoint, atau Foto</p>
            </div>
            <button type="button" onclick="closeModal('modal-submit-tugas')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition-colors shrink-0">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="dashboard.php?page=tugas" method="POST" enctype="multipart/form-data" class="space-y-4">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="upload_tugas">
            <input type="hidden" name="tugas_id" id="submit_tugas_id" value="">

            <div class="p-3.5 rounded-2xl bg-slate-950 border border-slate-800 space-y-1">
                <span class="text-[10px] text-cyan-400 font-bold uppercase" id="submit_mapel_label">MATA PELAJARAN</span>
                <h4 class="text-sm font-extrabold text-white" id="submit_judul_label">Judul Tugas</h4>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1.5">Pilih File Jawaban (PDF / Word / PPT / Foto)</label>
                <input type="file" name="tugas_file" id="tugas_file_input" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.webp" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-cyan-600 file:text-white hover:file:bg-cyan-500 cursor-pointer">
                <div class="flex items-center justify-between text-[10px] text-slate-500 mt-1.5">
                    <span>Ekstensi: .pdf, .doc, .docx, .ppt, .pptx, .jpg, .png, .webp</span>
                    <span class="font-bold text-slate-400">Maks 15MB</span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 mb-1">Catatan / Keterangan Siswa (Opsional)</label>
                <textarea name="catatan" rows="3" placeholder="Tuliskan pesan atau catatan tambahan untuk guru..." class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:border-cyan-500 focus:outline-none transition-colors"></textarea>
            </div>

            <div class="pt-3 flex justify-end gap-3 border-t border-slate-800 sticky bottom-0 bg-slate-900 pb-1">
                <button type="button" onclick="closeModal('modal-submit-tugas')" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">Batal</button>
                <button type="submit" class="px-5 py-2.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-cyan-600/30 transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-paper-plane"></i> Kirim Jawaban Tugas
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openSubmitModal(tugasId, judul, mapel) {
        document.getElementById('submit_tugas_id').value = tugasId;
        document.getElementById('submit_judul_label').innerText = judul;
        document.getElementById('submit_mapel_label').innerText = mapel;
        openModal('modal-submit-tugas');
    }
</script>
