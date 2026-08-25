<?php
// views/admin/ppdb.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];

    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = trim($_POST['status']);
        $catatan = trim($_POST['catatan_admin'] ?? '');

        $stmt = $pdo->prepare("UPDATE ppdb SET status = ?, catatan_admin = ? WHERE id = ?");
        $stmt->execute([$status, $catatan, $id]);
        $msg = "Status pendaftaran PPDB berhasil diperbarui menjadi '$status'!";
        log_activity("Admin updated PPDB status ID $id to $status");
    } elseif ($action === 'edit_ppdb') {
        $id = intval($_POST['id']);
        $nama = trim($_POST['nama_lengkap']);
        $nisn = trim($_POST['nisn']);
        $nik = trim($_POST['nik']);
        $school = trim($_POST['nama_sekolah_asal']);
        if ($school === 'Lainnya' && !empty($_POST['nama_sekolah_asal_custom'])) {
            $school = trim($_POST['nama_sekolah_asal_custom']);
        }
        $provinsi = trim($_POST['provinsi'] ?? '');
        $kabupaten = trim($_POST['kabupaten'] ?? '');
        $kecamatan = trim($_POST['kecamatan'] ?? '');
        $status = trim($_POST['status']);
        $catatan = trim($_POST['catatan_admin'] ?? '');

        $stmt = $pdo->prepare("UPDATE ppdb SET nama_lengkap = ?, nisn = ?, nik = ?, nama_sekolah_asal = ?, provinsi = ?, kabupaten = ?, kecamatan = ?, status = ?, catatan_admin = ? WHERE id = ?");
        $stmt->execute([$nama, $nisn, $nik, $school, $provinsi, $kabupaten, $kecamatan, $status, $catatan, $id]);
        $msg = "Data calon siswa '$nama' berhasil diperbarui!";
        log_activity("Admin edited PPDB applicant ID $id");
    } elseif ($action === 'delete_ppdb') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM ppdb WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Data pendaftar PPDB berhasil dihapus secara permanen!";
        log_activity("Admin deleted PPDB applicant ID $id");
    }
}

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=PPDB_2026_Export_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['No Registrasi', 'Nama Lengkap', 'NISN', 'NIK', 'Jenis Kelamin', 'Sekolah Asal', 'Provinsi', 'Kabupaten/Kota', 'Kecamatan', 'No HP Ortu', 'Email', 'Status', 'Tanggal Daftar']);

    $exportQuery = $pdo->query("SELECT no_pendaftaran, nama_lengkap, nisn, nik, jenis_kelamin, nama_sekolah_asal, provinsi, kabupaten, kecamatan, no_hp_ortu, email, status, created_at FROM ppdb ORDER BY id DESC");
    while ($row = $exportQuery->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// Filter and Search Parameters
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');
$page_num = max(1, intval($_GET['p'] ?? 1));
$per_page = 20;

$whereSql = " WHERE 1=1";
$params = [];

if ($search !== '') {
    $whereSql .= " AND (no_pendaftaran LIKE ? OR nama_lengkap LIKE ? OR nisn LIKE ? OR nik LIKE ? OR nama_sekolah_asal LIKE ? OR provinsi LIKE ? OR kabupaten LIKE ? OR kecamatan LIKE ?)";
    $term = "%$search%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term, $term]);
}

if ($filter_status !== '') {
    $whereSql .= " AND status = ?";
    $params[] = $filter_status;
}

// Total filtered count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ppdb" . $whereSql);
$countStmt->execute($params);
$totalFiltered = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalFiltered / $per_page));
$page_num = min($page_num, $totalPages);
$offset = ($page_num - 1) * $per_page;

$sql = "SELECT * FROM ppdb" . $whereSql . " ORDER BY id DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ppdbList = $stmt->fetchAll();

// Counts for Badges
$countAll = $pdo->query("SELECT COUNT(*) FROM ppdb")->fetchColumn();
$countPending = $pdo->query("SELECT COUNT(*) FROM ppdb WHERE status = 'Menunggu Verifikasi'")->fetchColumn();
$countBerkas = $pdo->query("SELECT COUNT(*) FROM ppdb WHERE status = 'Berkas Kurang'")->fetchColumn();
$countLulusAdmin = $pdo->query("SELECT COUNT(*) FROM ppdb WHERE status = 'Lulus Administrasi'")->fetchColumn();
$countAccepted = $pdo->query("SELECT COUNT(*) FROM ppdb WHERE status = 'Diterima'")->fetchColumn();
$countRejected = $pdo->query("SELECT COUNT(*) FROM ppdb WHERE status = 'Ditolak'")->fetchColumn();

$status_badges = [
    'Menunggu Verifikasi' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    'Berkas Kurang' => 'bg-orange-500/10 text-orange-400 border-orange-500/30',
    'Lulus Administrasi' => 'bg-blue-500/10 text-blue-400 border-blue-500/30',
    'Diterima' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    'Ditolak' => 'bg-rose-500/10 text-rose-400 border-rose-500/30'
];
?>
<div class="space-y-6">
    <!-- Header Card -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-id-card text-amber-400"></i> Kelola Pendaftaran PPDB 2026/2027
            </h1>
            <p class="text-xs text-slate-400 mt-1">Verifikasi berkas, edit data domisili/sekolah asal, ubah status seleksi, dan kelola data pendaftar</p>
        </div>
        <a href="dashboard.php?page=ppdb&export=csv" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-emerald-600/30 transition-all">
            <i class="fa-solid fa-file-csv text-sm"></i> Export CSV Data PPDB
        </a>
    </div>

    <!-- Alert Message -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-xs font-semibold flex items-center justify-between shadow-lg">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400 text-base"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Stat Badges -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <a href="dashboard.php?page=ppdb" class="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 hover:border-slate-700 transition-all text-center">
            <span class="text-[10px] text-slate-400 font-bold uppercase block">Total Pendaftar</span>
            <span class="text-lg font-black text-white"><?= $countAll; ?></span>
        </a>
        <a href="dashboard.php?page=ppdb&status=Menunggu+Verifikasi" class="p-3.5 rounded-2xl bg-amber-500/10 border border-amber-500/20 hover:bg-amber-500/20 transition-all text-center">
            <span class="text-[10px] text-amber-400 font-bold uppercase block">Menunggu</span>
            <span class="text-lg font-black text-amber-400"><?= $countPending; ?></span>
        </a>
        <a href="dashboard.php?page=ppdb&status=Berkas+Kurang" class="p-3.5 rounded-2xl bg-orange-500/10 border border-orange-500/20 hover:bg-orange-500/20 transition-all text-center">
            <span class="text-[10px] text-orange-400 font-bold uppercase block">Berkas Kurang</span>
            <span class="text-lg font-black text-orange-400"><?= $countBerkas; ?></span>
        </a>
        <a href="dashboard.php?page=ppdb&status=Lulus+Administrasi" class="p-3.5 rounded-2xl bg-blue-500/10 border border-blue-500/20 hover:bg-blue-500/20 transition-all text-center">
            <span class="text-[10px] text-blue-400 font-bold uppercase block">Lulus Admin</span>
            <span class="text-lg font-black text-blue-400"><?= $countLulusAdmin; ?></span>
        </a>
        <a href="dashboard.php?page=ppdb&status=Diterima" class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 hover:bg-emerald-500/20 transition-all text-center">
            <span class="text-[10px] text-emerald-400 font-bold uppercase block">Diterima</span>
            <span class="text-lg font-black text-emerald-400"><?= $countAccepted; ?></span>
        </a>
        <a href="dashboard.php?page=ppdb&status=Ditolak" class="p-3.5 rounded-2xl bg-rose-500/10 border border-rose-500/20 hover:bg-rose-500/20 transition-all text-center">
            <span class="text-[10px] text-rose-400 font-bold uppercase block">Ditolak</span>
            <span class="text-lg font-black text-rose-400"><?= $countRejected; ?></span>
        </a>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <form action="dashboard.php" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="page" value="ppdb">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-500 text-xs"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($search); ?>" placeholder="Cari nama, NISN, NIK, No Registrasi, sekolah asal, atau domisili..." class="w-full pl-9 pr-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-500">
            </div>
            <select name="status" class="px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                <option value="">-- Semua Status --</option>
                <option value="Menunggu Verifikasi" <?= $filter_status === 'Menunggu Verifikasi' ? 'selected' : ''; ?>>Menunggu Verifikasi</option>
                <option value="Berkas Kurang" <?= $filter_status === 'Berkas Kurang' ? 'selected' : ''; ?>>Berkas Kurang</option>
                <option value="Lulus Administrasi" <?= $filter_status === 'Lulus Administrasi' ? 'selected' : ''; ?>>Lulus Administrasi</option>
                <option value="Diterima" <?= $filter_status === 'Diterima' ? 'selected' : ''; ?>>Diterima</option>
                <option value="Ditolak" <?= $filter_status === 'Ditolak' ? 'selected' : ''; ?>>Ditolak</option>
            </select>
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl transition-all">
                Filter Data
            </button>
            <?php if ($search !== '' || $filter_status !== ''): ?>
                <a href="dashboard.php?page=ppdb" class="px-4 py-2.5 bg-slate-800 text-slate-300 font-bold text-xs rounded-xl flex items-center justify-center">Reset</a>
            <?php endif; ?>
        </form>

        <!-- Applicants Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">No. Registrasi</th>
                        <th class="p-3.5">Nama Calon Siswa</th>
                        <th class="p-3.5">NISN / NIK</th>
                        <th class="p-3.5">Sekolah Asal</th>
                        <th class="p-3.5">Domisili (Kab/Prov)</th>
                        <th class="p-3.5">Status Seleksi</th>
                        <th class="p-3.5">Tgl Daftar</th>
                        <th class="p-3.5 rounded-r-xl text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    <?php if (empty($ppdbList)): ?>
                        <tr>
                            <td colspan="8" class="p-8 text-center text-slate-500">
                                Belum ada data pendaftar PPDB yang sesuai dengan pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ppdbList as $p): ?>
                            <?php $badge = $status_badges[$p['status']] ?? 'bg-slate-800 text-slate-300'; ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-3.5 font-mono text-amber-400 font-bold"><?= htmlspecialchars($p['no_pendaftaran']); ?></td>
                                <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($p['nama_lengkap']); ?></td>
                                <td class="p-3.5 font-mono text-slate-400">
                                    <div><?= htmlspecialchars($p['nisn'] ?: '-'); ?></div>
                                    <div class="text-[10px] text-slate-500">NIK: <?= htmlspecialchars($p['nik'] ?: '-'); ?></div>
                                </td>
                                <td class="p-3.5 text-slate-300"><?= htmlspecialchars($p['nama_sekolah_asal'] ?: '-'); ?></td>
                                <td class="p-3.5 text-slate-400 text-[11px]">
                                    <div><?= htmlspecialchars($p['kabupaten'] ?: 'Jakarta'); ?></div>
                                    <div class="text-[10px] text-slate-500"><?= htmlspecialchars($p['provinsi'] ?: 'DKI Jakarta'); ?></div>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold border <?= $badge; ?>">
                                        <?= htmlspecialchars($p['status']); ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-slate-400 text-[11px]"><?= date('d/m/Y H:i', strtotime($p['created_at'])); ?></td>
                                <td class="p-3.5 text-center flex items-center justify-center gap-1">
                                    <button type="button" onclick='openDetailPpdb(<?= json_encode($p); ?>)' class="px-2 py-1.5 rounded-lg bg-blue-500/10 text-blue-400 hover:bg-blue-600 hover:text-white transition-all text-[11px] font-bold" title="Detail Data">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </button>
                                    <button type="button" onclick='openEditPpdb(<?= json_encode($p); ?>)' class="px-2 py-1.5 rounded-lg bg-amber-500/10 text-amber-400 hover:bg-amber-600 hover:text-white transition-all text-[11px] font-bold" title="Edit Data">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </button>
                                    <button type="button" onclick='openStatusPpdb(<?= json_encode($p); ?>)' class="px-2 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition-all text-[11px] font-bold" title="Ubah Status">
                                        <i class="fa-solid fa-signature"></i> Status
                                    </button>
                                    <form action="dashboard.php?page=ppdb" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data pendaftar ini secara permanen?');" class="inline">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_ppdb">
                                        <input type="hidden" name="id" value="<?= $p['id']; ?>">
                                        <button type="submit" class="w-7 h-7 rounded-lg bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center" title="Hapus Data">
                                            <i class="fa-solid fa-trash text-xs"></i>
                                        </button>
                                    </form>
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
                    Menampilkan <span class="font-bold text-white"><?= $startShow; ?></span> - <span class="font-bold text-white"><?= $endShow; ?></span> dari <span class="font-bold text-white"><?= $totalFiltered; ?></span> pendaftar
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

    <!-- Modal Detail Pendaftar -->
    <div id="modal-detail-ppdb" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-3xl shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-id-card text-amber-400"></i> Detail Pendaftaran PPDB <span id="detail_no_reg" class="font-mono text-amber-400"></span>
                </h3>
                <button type="button" onclick="closeModal('modal-detail-ppdb')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="space-y-6 text-xs text-slate-300">
                <!-- Data Siswa -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                    <h4 class="font-extrabold text-indigo-400 uppercase text-[11px] tracking-wider mb-2">A. Data Calon Siswa</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div><span class="text-slate-500 block">Nama Lengkap:</span><strong id="det_nama" class="text-white"></strong></div>
                        <div><span class="text-slate-500 block">NISN:</span><span id="det_nisn" class="font-mono text-white"></span></div>
                        <div><span class="text-slate-500 block">NIK:</span><span id="det_nik" class="font-mono text-white"></span></div>
                        <div><span class="text-slate-500 block">Tempat/Tgl Lahir:</span><span id="det_ttl" class="text-white"></span></div>
                        <div><span class="text-slate-500 block">Jenis Kelamin:</span><span id="det_jk" class="text-white"></span></div>
                        <div><span class="text-slate-500 block">Agama:</span><span id="det_agama" class="text-white"></span></div>
                    </div>
                </div>

                <!-- Domisili & Sekolah Asal -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                    <h4 class="font-extrabold text-cyan-400 uppercase text-[11px] tracking-wider mb-2">B. Domisili Alamat & Sekolah Asal</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div><span class="text-slate-500 block">Sekolah Asal:</span><span id="det_sekolah" class="text-white font-bold"></span></div>
                        <div><span class="text-slate-500 block">Provinsi:</span><span id="det_provinsi" class="text-white"></span></div>
                        <div><span class="text-slate-500 block">Kabupaten/Kota:</span><span id="det_kabupaten" class="text-white"></span></div>
                        <div><span class="text-slate-500 block">Kecamatan:</span><span id="det_kecamatan" class="text-white"></span></div>
                        <div class="col-span-2"><span class="text-slate-500 block">Alamat Jalan:</span><span id="det_alamat" class="text-white"></span></div>
                    </div>
                </div>

                <!-- Data Orang Tua & Kontak -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                    <h4 class="font-extrabold text-amber-400 uppercase text-[11px] tracking-wider mb-2">C. Data Orang Tua & Kontak</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div><span class="text-slate-500 block">Nama Ayah:</span><span id="det_ayah" class="text-white"></span></div>
                        <div><span class="text-slate-500 block">Nama Ibu:</span><span id="det_ibu" class="text-white"></span></div>
                        <div><span class="text-slate-500 block">No HP Ortu:</span><span id="det_hp" class="font-mono text-white"></span></div>
                        <div><span class="text-slate-500 block">Email Kontak:</span><span id="det_email" class="font-mono text-white"></span></div>
                    </div>
                </div>

                <!-- Status & Catatan -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2">
                    <h4 class="font-extrabold text-emerald-400 uppercase text-[11px] tracking-wider mb-2">D. Status Verifikasi & Catatan Admin</h4>
                    <div class="flex items-center justify-between">
                        <div><span class="text-slate-500 block">Status Saat Ini:</span><span id="det_status" class="font-bold"></span></div>
                        <div><span class="text-slate-500 block">Catatan Admin:</span><span id="det_catatan" class="text-slate-300 italic"></span></div>
                    </div>
                </div>
            </div>

            <div class="pt-4 mt-6 border-t border-slate-800 flex justify-end gap-3">
                <button type="button" onclick="closeModal('modal-detail-ppdb')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Edit Data Pendaftar Admin -->
    <div id="modal-edit-ppdb" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-2xl shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-amber-400"></i> Edit Data Pendaftar PPDB
                </h3>
                <button type="button" onclick="closeModal('modal-edit-ppdb')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=ppdb" method="POST" class="space-y-4 text-xs text-slate-300">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_ppdb">
                <input type="hidden" name="id" id="edit_ppdb_id">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">Nama Lengkap Siswa</label>
                        <input type="text" name="nama_lengkap" id="edit_nama_lengkap" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">NISN (10 Digit)</label>
                        <input type="text" name="nisn" id="edit_nisn" required maxlength="10" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">NIK (16 Digit)</label>
                        <input type="text" name="nik" id="edit_nik" required maxlength="16" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white font-mono">
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">Nama Sekolah Asal (SMP/MTs)</label>
                        <select name="nama_sekolah_asal" id="edit_nama_sekolah_asal" onchange="toggleCustomSchool(this.value, 'edit_custom_school_box')" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white cursor-pointer">
                            <option value="SMP Negeri 1 Jakarta">SMP Negeri 1 Jakarta</option>
                            <option value="SMP Negeri 2 Jakarta">SMP Negeri 2 Jakarta</option>
                            <option value="SMP Negeri 3 Jakarta">SMP Negeri 3 Jakarta</option>
                            <option value="SMP Negeri 4 Jakarta">SMP Negeri 4 Jakarta</option>
                            <option value="SMP Negeri 19 Jakarta">SMP Negeri 19 Jakarta</option>
                            <option value="SMP Negeri 115 Jakarta">SMP Negeri 115 Jakarta</option>
                            <option value="SMP Negeri 1 Bandung">SMP Negeri 1 Bandung</option>
                            <option value="SMP Negeri 2 Bandung">SMP Negeri 2 Bandung</option>
                            <option value="SMP Negeri 1 Surabaya">SMP Negeri 1 Surabaya</option>
                            <option value="SMP Islam Al-Azhar 1">SMP Islam Al-Azhar 1</option>
                            <option value="MTs Negeri 1 Jakarta">MTs Negeri 1 Jakarta</option>
                            <option value="MTs Negeri 3 Jakarta">MTs Negeri 3 Jakarta</option>
                            <option value="SMP Taruna Bakti">SMP Taruna Bakti</option>
                            <option value="SMP Labschool Jakarta">SMP Labschool Jakarta</option>
                            <option value="SMP Santa Ursula">SMP Santa Ursula</option>
                            <option value="Lainnya">Lainnya (Input Manual Sekolah)</option>
                        </select>
                        <input type="text" name="nama_sekolah_asal_custom" id="edit_custom_school_box" placeholder="Ketik nama sekolah asal..." class="hidden mt-2 w-full px-3 py-2 bg-slate-950 border border-indigo-500/50 rounded-xl text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">Provinsi</label>
                        <select name="provinsi" id="edit_provinsi" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white cursor-pointer">
                            <option value="DKI Jakarta">DKI Jakarta</option>
                            <option value="Jawa Barat">Jawa Barat</option>
                            <option value="Jawa Tengah">Jawa Tengah</option>
                            <option value="Jawa Timur">Jawa Timur</option>
                            <option value="Banten">Banten</option>
                            <option value="D.I. Yogyakarta">D.I. Yogyakarta</option>
                            <option value="Sumatera Utara">Sumatera Utara</option>
                            <option value="Sumatera Barat">Sumatera Barat</option>
                            <option value="Riau">Riau</option>
                            <option value="Kepulauan Riau">Kepulauan Riau</option>
                            <option value="Lampung">Lampung</option>
                            <option value="Sumatera Selatan">Sumatera Selatan</option>
                            <option value="Bali">Bali</option>
                            <option value="Nusa Tenggara Barat">Nusa Tenggara Barat</option>
                            <option value="Kalimantan Timur">Kalimantan Timur</option>
                            <option value="Sulawesi Selatan">Sulawesi Selatan</option>
                            <option value="Provinsi Lainnya">Provinsi Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">Kabupaten/Kota</label>
                        <select name="kabupaten" id="edit_kabupaten" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white cursor-pointer">
                            <option value="Kota Jakarta Selatan">Kota Jakarta Selatan</option>
                            <option value="Kota Jakarta Timur">Kota Jakarta Timur</option>
                            <option value="Kota Jakarta Pusat">Kota Jakarta Pusat</option>
                            <option value="Kota Jakarta Barat">Kota Jakarta Barat</option>
                            <option value="Kota Jakarta Utara">Kota Jakarta Utara</option>
                            <option value="Kota Bandung">Kota Bandung</option>
                            <option value="Kota Bekasi">Kota Bekasi</option>
                            <option value="Kota Depok">Kota Depok</option>
                            <option value="Kota Tangerang">Kota Tangerang</option>
                            <option value="Kota Tangerang Selatan">Kota Tangerang Selatan</option>
                            <option value="Kota Bogor">Kota Bogor</option>
                            <option value="Kota Semarang">Kota Semarang</option>
                            <option value="Kota Surabaya">Kota Surabaya</option>
                            <option value="Kota Yogyakarta">Kota Yogyakarta</option>
                            <option value="Kota Malang">Kota Malang</option>
                            <option value="Kota Medan">Kota Medan</option>
                            <option value="Kabupaten/Kota Lainnya">Kabupaten/Kota Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">Kecamatan</label>
                        <select name="kecamatan" id="edit_kecamatan" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white cursor-pointer">
                            <option value="Kebayoran Baru">Kebayoran Baru</option>
                            <option value="Cilandak">Cilandak</option>
                            <option value="Jagakarsa">Jagakarsa</option>
                            <option value="Kebayoran Lama">Kebayoran Lama</option>
                            <option value="Mampang Prapatan">Mampang Prapatan</option>
                            <option value="Tebet">Tebet</option>
                            <option value="Pasar Minggu">Pasar Minggu</option>
                            <option value="Pancoran">Pancoran</option>
                            <option value="Setiabudi">Setiabudi</option>
                            <option value="Pesanggrahan">Pesanggrahan</option>
                            <option value="Matraman">Matraman</option>
                            <option value="Jatinegara">Jatinegara</option>
                            <option value="Duren Sawit">Duren Sawit</option>
                            <option value="Bandung Wetan">Bandung Wetan</option>
                            <option value="Coblong">Coblong</option>
                            <option value="Sumur Bandung">Sumur Bandung</option>
                            <option value="Wonokromo">Wonokromo</option>
                            <option value="Gubeng">Gubeng</option>
                            <option value="Kecamatan Lainnya">Kecamatan Lainnya</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">Status Seleksi PPDB</label>
                        <select name="status" id="edit_status" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white">
                            <option value="Menunggu Verifikasi">Menunggu Verifikasi</option>
                            <option value="Berkas Kurang">Berkas Kurang</option>
                            <option value="Lulus Administrasi">Lulus Administrasi</option>
                            <option value="Diterima">Diterima (Lulus Seleksi)</option>
                            <option value="Ditolak">Ditolak</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1 text-slate-300">Catatan Admin</label>
                        <input type="text" name="catatan_admin" id="edit_catatan_admin" placeholder="Catatan opsional panitia..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-white">
                    </div>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-ppdb')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Status & Verifikasi Admin -->
    <div id="modal-status-ppdb" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Verifikasi Status PPDB</h3>
                <button type="button" onclick="closeModal('modal-status-ppdb')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=ppdb" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="status_ppdb_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Status Seleksi PPDB</label>
                    <select name="status" id="status_ppdb_val" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="Menunggu Verifikasi">Menunggu Verifikasi</option>
                        <option value="Berkas Kurang">Berkas Kurang</option>
                        <option value="Lulus Administrasi">Lulus Administrasi</option>
                        <option value="Diterima">Diterima (Lulus Seleksi)</option>
                        <option value="Ditolak">Ditolak</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Catatan Admin / Alasan</label>
                    <textarea name="catatan_admin" id="status_ppdb_catatan" rows="3" placeholder="Contoh: Berkas Kartu Keluarga kurang jelas, harap unggah ulang..." class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white"></textarea>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-status-ppdb')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openDetailPpdb(data) {
        document.getElementById('detail_no_reg').innerText = '(' + (data.no_pendaftaran || '') + ')';
        document.getElementById('det_nama').innerText = data.nama_lengkap || '-';
        document.getElementById('det_nisn').innerText = data.nisn || '-';
        document.getElementById('det_nik').innerText = data.nik || '-';
        document.getElementById('det_ttl').innerText = (data.tempat_lahir || '') + ', ' + (data.tanggal_lahir || '');
        document.getElementById('det_jk').innerText = data.jenis_kelamin || '-';
        document.getElementById('det_agama').innerText = data.agama || '-';
        document.getElementById('det_sekolah').innerText = data.nama_sekolah_asal || '-';
        document.getElementById('det_provinsi').innerText = data.provinsi || 'DKI Jakarta';
        document.getElementById('det_kabupaten').innerText = data.kabupaten || 'Kota Jakarta Selatan';
        document.getElementById('det_kecamatan').innerText = data.kecamatan || 'Kebayoran Baru';
        document.getElementById('det_alamat').innerText = data.alamat_lengkap || '-';
        document.getElementById('det_ayah').innerText = data.nama_ayah || '-';
        document.getElementById('det_ibu').innerText = data.nama_ibu || '-';
        document.getElementById('det_hp').innerText = (data.no_hp_siswa || data.no_hp_ortu || '-');
        document.getElementById('det_email').innerText = data.email || '-';
        document.getElementById('det_status').innerText = data.status || '-';
        document.getElementById('det_catatan').innerText = data.catatan_admin || 'Tidak ada catatan.';
        openModal('modal-detail-ppdb');
    }

    function openEditPpdb(data) {
        document.getElementById('edit_ppdb_id').value = data.id || '';
        document.getElementById('edit_nama_lengkap').value = data.nama_lengkap || '';
        document.getElementById('edit_nisn').value = data.nisn || '';
        document.getElementById('edit_nik').value = data.nik || '';

        const schoolSelect = document.getElementById('edit_nama_sekolah_asal');
        const customInput = document.getElementById('edit_custom_school_box');
        let schoolFound = false;
        if (schoolSelect) {
            for (let i = 0; i < schoolSelect.options.length; i++) {
                if (schoolSelect.options[i].value === data.nama_sekolah_asal) {
                    schoolSelect.selectedIndex = i;
                    schoolFound = true;
                    break;
                }
            }
            if (!schoolFound && data.nama_sekolah_asal) {
                schoolSelect.value = 'Lainnya';
                if (customInput) {
                    customInput.classList.remove('hidden');
                    customInput.value = data.nama_sekolah_asal;
                }
            } else if (customInput) {
                customInput.classList.add('hidden');
                customInput.value = '';
            }
        }

        if (document.getElementById('edit_provinsi')) document.getElementById('edit_provinsi').value = data.provinsi || 'DKI Jakarta';
        if (document.getElementById('edit_kabupaten')) document.getElementById('edit_kabupaten').value = data.kabupaten || 'Kota Jakarta Selatan';
        if (document.getElementById('edit_kecamatan')) document.getElementById('edit_kecamatan').value = data.kecamatan || 'Kebayoran Baru';
        if (document.getElementById('edit_status')) document.getElementById('edit_status').value = data.status || 'Menunggu Verifikasi';
        if (document.getElementById('edit_catatan_admin')) document.getElementById('edit_catatan_admin').value = data.catatan_admin || '';

        openModal('modal-edit-ppdb');
    }

    function openStatusPpdb(data) {
        document.getElementById('status_ppdb_id').value = data.id || '';
        document.getElementById('status_ppdb_val').value = data.status || 'Menunggu Verifikasi';
        document.getElementById('status_ppdb_catatan').value = data.catatan_admin || '';
        openModal('modal-status-ppdb');
    }

    function toggleCustomSchool(val, targetId) {
        const el = document.getElementById(targetId);
        if (el) {
            if (val === 'Lainnya') {
                el.classList.remove('hidden');
                el.focus();
            } else {
                el.classList.add('hidden');
                el.value = '';
            }
        }
    }
</script>
