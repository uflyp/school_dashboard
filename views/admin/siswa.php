<?php
// views/admin/siswa.php - Admin Management Data Siswa & Kartu Pelajar
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'add_siswa') {
        $nis = trim($_POST['nis']);
        $nisn = trim($_POST['nisn']);
        $nama = trim($_POST['nama']);
        $kelas = trim($_POST['kelas']);
        $jk = trim($_POST['jenis_kelamin']);
        $tempat_lahir = trim($_POST['tempat_lahir'] ?? 'Jakarta');
        $tg = trim($_POST['tanggal_lahir']);
        $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '2025/2026');
        $status_siswa = trim($_POST['status_siswa'] ?? 'Aktif');
        $alamat = trim($_POST['alamat']);
        $ortu = trim($_POST['nama_ortu']);

        // Handle Photo Upload
        $fotoPath = '';
        if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $uploadDir = 'uploads/avatars/';
                if (!file_exists($uploadDir)) @mkdir($uploadDir, 0777, true);
                $targetFile = $uploadDir . 'siswa_' . $nis . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_file']['tmp_name'], $targetFile)) {
                    $fotoPath = $targetFile;
                }
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO siswa (nis, nisn, nama, kelas, jenis_kelamin, tempat_lahir, tanggal_lahir, tahun_ajaran, status_siswa, foto, alamat, nama_ortu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nis, $nisn, $nama, $kelas, $jk, $tempat_lahir, $tg, $tahun_ajaran, $status_siswa, $fotoPath, $alamat, $ortu]);
            $msg = "Siswa $nama berhasil ditambahkan!";
            log_activity("Admin added student: $nama");
        } catch (PDOException $e) {
            $msg = "Gagal menambah siswa: NIS sudah terdaftar!";
        }
    } elseif ($_POST['action'] === 'edit_siswa') {
        $id = intval($_POST['id']);
        $nis = trim($_POST['nis']);
        $nisn = trim($_POST['nisn']);
        $nama = trim($_POST['nama']);
        $kelas = trim($_POST['kelas']);
        $jk = trim($_POST['jenis_kelamin']);
        $tempat_lahir = trim($_POST['tempat_lahir'] ?? 'Jakarta');
        $tg = trim($_POST['tanggal_lahir']);
        $tahun_ajaran = trim($_POST['tahun_ajaran'] ?? '2025/2026');
        $status_siswa = trim($_POST['status_siswa'] ?? 'Aktif');
        $alamat = trim($_POST['alamat']);
        $ortu = trim($_POST['nama_ortu']);

        // Fetch current photo
        $currentSiswa = $pdo->prepare("SELECT foto, nis FROM siswa WHERE id = ?");
        $currentSiswa->execute([$id]);
        $existingData = $currentSiswa->fetch();
        $fotoPath = $existingData['foto'] ?? '';

        // Handle Photo Upload if updated
        if (isset($_FILES['foto_file']) && $_FILES['foto_file']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                $uploadDir = 'uploads/avatars/';
                if (!file_exists($uploadDir)) @mkdir($uploadDir, 0777, true);
                $targetFile = $uploadDir . 'siswa_' . $nis . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['foto_file']['tmp_name'], $targetFile)) {
                    $fotoPath = $targetFile;
                }
            }
        }

        try {
            $stmt = $pdo->prepare("UPDATE siswa SET nis = ?, nisn = ?, nama = ?, kelas = ?, jenis_kelamin = ?, tempat_lahir = ?, tanggal_lahir = ?, tahun_ajaran = ?, status_siswa = ?, foto = ?, alamat = ?, nama_ortu = ? WHERE id = ?");
            $stmt->execute([$nis, $nisn, $nama, $kelas, $jk, $tempat_lahir, $tg, $tahun_ajaran, $status_siswa, $fotoPath, $alamat, $ortu, $id]);

            // Sync student name, gender & avatar to user account if exists
            $jkShort = str_starts_with(strtoupper(trim($jk)), 'L') ? 'L' : 'P';
            if (!empty($fotoPath)) {
                $stmtUser = $pdo->prepare("UPDATE users SET name = ?, jenis_kelamin = ?, avatar = ? WHERE username = ?");
                $stmtUser->execute([$nama, $jkShort, $fotoPath, $nis]);
            } else {
                $stmtUser = $pdo->prepare("UPDATE users SET name = ?, jenis_kelamin = ? WHERE username = ?");
                $stmtUser->execute([$nama, $jkShort, $nis]);
            }

            $msg = "Data & Kartu Pelajar Siswa $nama berhasil diperbarui!";
            log_activity("Admin updated student: $nama");
        } catch (PDOException $e) {
            $msg = "Gagal mengedit siswa: NIS atau NISN sudah terdaftar!";
        }
    } elseif ($_POST['action'] === 'delete_siswa') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM siswa WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Data siswa berhasil dihapus!";
    }
}

// Search, Filter & Pagination Parameters
$search = trim($_GET['search'] ?? '');
$filter_kelas = trim($_GET['kelas'] ?? '');
$page_num = max(1, intval($_GET['p'] ?? 1));
$per_page = 20;

$whereSql = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (nis LIKE ? OR nisn LIKE ? OR nama LIKE ? OR alamat LIKE ? OR nama_ortu LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if ($filter_kelas !== '') {
    $whereSql .= " AND kelas = ?";
    $params[] = $filter_kelas;
}

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM siswa" . $whereSql);
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalFiltered / $per_page));
$page_num = min($page_num, $totalPages);
$offset = ($page_num - 1) * $per_page;

$sql = "SELECT * FROM siswa" . $whereSql . " ORDER BY id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

$allKelasList = $pdo->query("SELECT nama_kelas FROM kelas ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
$totalSiswaCount = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
?>

<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-user-graduate text-indigo-400"></i> Manajemen Data Siswa & Kartu Pelajar
            </h1>
            <p class="text-xs text-slate-400 mt-1">Kelola data induk siswa, foto identitas, NIS/NISN, serta penerbitan Kartu Pelajar Digital</p>
        </div>
        <button type="button" onclick="openModal('modal-tambah-siswa')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition-all shrink-0">
            <i class="fa-solid fa-user-plus"></i> Tambah Siswa Baru
        </button>
    </div>

    <!-- Notification Toast -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-indigo-400"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-indigo-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Bar -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <form action="dashboard.php" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="page" value="siswa">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari NIS, NISN, Nama Siswa, Alamat, atau Ortu..." class="w-full pl-9 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500">
            </div>
            <select name="kelas" class="px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                <option value="">-- Semua Kelas --</option>
                <?php foreach ($allKelasList as $kOpt): ?>
                    <option value="<?= htmlspecialchars($kOpt); ?>" <?= $filter_kelas === $kOpt ? 'selected' : ''; ?>><?= htmlspecialchars($kOpt); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all">
                Filter Data
            </button>
            <?php if ($search !== '' || $filter_kelas !== ''): ?>
                <a href="dashboard.php?page=siswa" class="px-4 py-2.5 bg-slate-800 text-slate-300 font-bold text-xs rounded-xl flex items-center justify-center">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Siswa Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">No</th>
                        <th class="p-3.5">NIS / NISN</th>
                        <th class="p-3.5">Nama Lengkap Siswa</th>
                        <th class="p-3.5">Kelas</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5">Nama Ortu / Wali</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi & Kartu</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($siswaList)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">
                                Tidak ada data siswa yang sesuai dengan filter pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $no = $offset + 1; foreach ($siswaList as $s): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-3.5 text-slate-500 font-mono"><?= $no++; ?></td>
                                <td class="p-3.5 font-mono text-indigo-400 font-bold">
                                    <div><?= htmlspecialchars($s['nis']); ?></div>
                                    <div class="text-[10px] text-slate-500 font-normal">NISN: <?= htmlspecialchars($s['nisn'] ?: '-'); ?></div>
                                </td>
                                <td class="p-3.5 font-bold text-white flex items-center gap-3">
                                    <img src="<?= htmlspecialchars($s['foto'] ?: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150'); ?>" style="width: 32px; height: 32px; object-fit: cover;" class="w-8 h-8 rounded-lg object-cover border border-slate-700 shrink-0">
                                    <span><?= htmlspecialchars($s['nama']); ?></span>
                                </td>
                                <td class="p-3.5"><span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 text-indigo-400 font-mono font-bold text-[11px]"><?= htmlspecialchars($s['kelas']); ?></span></td>
                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <?= htmlspecialchars($s['status_siswa'] ?? 'Aktif'); ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-slate-300"><?= htmlspecialchars($s['nama_ortu']); ?></td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="dashboard.php?page=kartu_pelajar&nis=<?= urlencode($s['nis']); ?>" class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-600 hover:text-white transition-colors flex items-center justify-center" title="Lihat & Cetak Kartu Pelajar">
                                            <i class="fa-solid fa-id-card text-xs"></i>
                                        </a>
                                        <button type="button" onclick='openEditSiswa(<?= json_encode($s, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>)' class="w-8 h-8 rounded-lg bg-indigo-500/10 text-indigo-400 hover:bg-indigo-600 hover:text-white transition-colors flex items-center justify-center" title="Edit Data & Foto Siswa">
                                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                                        </button>
                                        <form action="dashboard.php?page=siswa" method="POST" onsubmit="return confirm('Yakin ingin menghapus siswa ini?');" class="inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_siswa">
                                            <input type="hidden" name="id" value="<?= $s['id']; ?>">
                                            <button type="submit" class="w-8 h-8 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white transition-colors flex items-center justify-center" title="Hapus Siswa">
                                                <i class="fa-solid fa-trash text-xs"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination UI Bar -->
        <?php if ($totalFiltered > 0): ?>
            <?php
            $queryParams = $_GET;
            unset($queryParams['p']);
            $baseUrl = 'dashboard.php?' . http_build_query($queryParams);
            $startShow = $offset + 1;
            $endShow = min($offset + $per_page, $totalFiltered);
            ?>
            <div class="pt-4 flex flex-col sm:flex-row items-center justify-between gap-4 border-t border-slate-800/80 text-xs text-slate-400">
                <div>
                    Menampilkan <span class="font-bold text-white"><?= $startShow; ?></span> - <span class="font-bold text-white"><?= $endShow; ?></span> dari <span class="font-bold text-white"><?= $totalFiltered; ?></span> siswa (Total: <?= $totalSiswaCount; ?>)
                </div>
                <div class="flex items-center gap-1.5 font-medium">
                    <?php if ($page_num > 1): ?>
                        <a href="<?= $baseUrl . '&p=' . ($page_num - 1); ?>" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold transition-all">
                            <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-lg bg-slate-950 text-slate-600 font-bold cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                        </span>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page_num - 2);
                    $endPage = min($totalPages, $page_num + 2);
                    if ($startPage > 1) {
                        echo '<a href="' . $baseUrl . '&p=1" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">1</a>';
                        if ($startPage > 2) echo '<span class="px-1 text-slate-600">...</span>';
                    }
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i === $page_num) {
                            echo '<span class="px-3 py-1.5 rounded-lg bg-indigo-600 text-white font-extrabold shadow-md">' . $i . '</span>';
                        } else {
                            echo '<a href="' . $baseUrl . '&p=' . $i . '" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold transition-all">' . $i . '</a>';
                        }
                    }
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) echo '<span class="px-1 text-slate-600">...</span>';
                        echo '<a href="' . $baseUrl . '&p=' . $totalPages . '" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold">' . $totalPages . '</a>';
                    }
                    ?>

                    <?php if ($page_num < $totalPages): ?>
                        <a href="<?= $baseUrl . '&p=' . ($page_num + 1); ?>" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold transition-all">
                            Next <i class="fa-solid fa-chevron-right ml-1"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-lg bg-slate-950 text-slate-600 font-bold cursor-not-allowed">
                            Next <i class="fa-solid fa-chevron-right ml-1"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Tambah Siswa -->
    <div id="modal-tambah-siswa" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3 sticky top-0 bg-slate-900 z-10">
                <h3 class="text-lg font-bold text-white">Tambah Siswa Baru</h3>
                <button type="button" onclick="closeModal('modal-tambah-siswa')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=siswa" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_siswa">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">NIS</label>
                        <input type="text" name="nis" required placeholder="20241006" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">NISN</label>
                        <input type="text" name="nisn" required placeholder="0061234573" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap Siswa</label>
                    <input type="text" name="nama" required placeholder="Rafi Maulana" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kelas</label>
                        <select name="kelas" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="XII IPA 1">XII IPA 1</option>
                            <option value="XI IPA 1">XI IPA 1</option>
                            <option value="X IPA 1">X IPA 1</option>
                            <option value="XII IPS 1">XII IPS 1</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" value="Jakarta" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="2008-01-01" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tahun Ajaran Kartu</label>
                        <input type="text" name="tahun_ajaran" value="2025/2026" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Status Keaktifan</label>
                        <select name="status_siswa" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="Aktif">Aktif</option>
                            <option value="Non-Aktif">Non-Aktif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Foto Formal Siswa (Format Kartu)</label>
                    <input type="file" name="foto_file" accept="image/jpeg,image/png,image/webp" class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:bg-indigo-600 file:text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Orang Tua / Wali</label>
                    <input type="text" name="nama_ortu" required placeholder="Ir. Budi Santoso" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Alamat</label>
                    <textarea name="alamat" rows="2" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white" placeholder="Alamat rumah..."></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800 sticky bottom-0 bg-slate-900">
                    <button type="button" onclick="closeModal('modal-tambah-siswa')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Siswa -->
    <div id="modal-edit-siswa" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg max-h-[90vh] overflow-y-auto shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3 sticky top-0 bg-slate-900 z-10">
                <h3 class="text-lg font-bold text-white">Edit Data & Foto Kartu Pelajar Siswa</h3>
                <button type="button" onclick="closeModal('modal-edit-siswa')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=siswa" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_siswa">
                <input type="hidden" name="id" id="edit_siswa_id">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">NIS</label>
                        <input type="text" name="nis" id="edit_siswa_nis" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">NISN</label>
                        <input type="text" name="nisn" id="edit_siswa_nisn" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap Siswa</label>
                    <input type="text" name="nama" id="edit_siswa_nama" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Kelas</label>
                        <select name="kelas" id="edit_siswa_kelas" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="XII IPA 1">XII IPA 1</option>
                            <option value="XI IPA 1">XI IPA 1</option>
                            <option value="X IPA 1">X IPA 1</option>
                            <option value="XII IPS 1">XII IPS 1</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Kelamin</label>
                        <select name="jenis_kelamin" id="edit_siswa_jk" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="Laki-laki">Laki-laki</option>
                            <option value="Perempuan">Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" id="edit_siswa_tempat_lahir" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" id="edit_siswa_tg" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tahun Ajaran</label>
                        <input type="text" name="tahun_ajaran" id="edit_siswa_tahun_ajaran" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Status Keaktifan</label>
                        <select name="status_siswa" id="edit_siswa_status" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="Aktif">Aktif</option>
                            <option value="Non-Aktif">Non-Aktif</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Foto Formal Siswa Baru (Opsional)</label>
                    <div class="flex items-center gap-3">
                        <img id="edit_siswa_foto_preview" src="https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150" style="width: 48px; height: 48px; object-fit: cover;" class="w-12 h-12 rounded-xl object-cover border border-slate-700 bg-slate-950 shrink-0">
                        <input type="file" name="foto_file" accept="image/jpeg,image/png,image/webp" onchange="previewEditSiswaFoto(this)" class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:bg-indigo-600 file:text-white hover:file:bg-indigo-500 cursor-pointer">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Orang Tua / Wali</label>
                    <input type="text" name="nama_ortu" id="edit_siswa_ortu" required class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Alamat</label>
                    <textarea name="alamat" id="edit_siswa_alamat" rows="2" class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800 sticky bottom-0 bg-slate-900 pb-1">
                    <button type="button" onclick="closeModal('modal-edit-siswa')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-all">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 transition-all">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function openEditSiswa(data) {
        document.getElementById('edit_siswa_id').value = data.id || '';
        document.getElementById('edit_siswa_nis').value = data.nis || '';
        document.getElementById('edit_siswa_nisn').value = data.nisn || '';
        document.getElementById('edit_siswa_nama').value = data.nama || '';
        document.getElementById('edit_siswa_kelas').value = data.kelas || 'XII IPA 1';
        document.getElementById('edit_siswa_jk').value = data.jenis_kelamin || 'Laki-laki';
        document.getElementById('edit_siswa_tempat_lahir').value = data.tempat_lahir || 'Jakarta';
        document.getElementById('edit_siswa_tg').value = data.tanggal_lahir || '';
        document.getElementById('edit_siswa_tahun_ajaran').value = data.tahun_ajaran || '2025/2026';
        document.getElementById('edit_siswa_status').value = data.status_siswa || 'Aktif';
        document.getElementById('edit_siswa_ortu').value = data.nama_ortu || '';
        document.getElementById('edit_siswa_alamat').value = data.alamat || '';
        
        const preview = document.getElementById('edit_siswa_foto_preview');
        if (preview) {
            preview.src = data.foto || 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150';
        }
        openModal('modal-edit-siswa');
    }

    function previewEditSiswaFoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('edit_siswa_foto_preview');
                if (preview) preview.src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>
