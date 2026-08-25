<?php
// views/admin/alumni.php - Manajemen Alumni & Hasil Tracer Study
check_role(['admin']);

// Handle Export CSV Tracer Study
if (isset($_GET['export']) && $_GET['export'] === 'tracer_csv') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Tracer_Study_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'ID', 'NIS', 'Nama Alumni', 'Tahun Lulus', 'Status Aktivitas',
        'Pendidikan Lanjutan', 'Nama Perusahaan', 'Bidang', 'Posisi',
        'Kesesuaian', 'Waktu Tunggu Kerja', 'Feedback', 'Saran', 'Tanggal Submit'
    ]);
    $rows = $pdo->query("SELECT id, nis, nama, tahun_lulus, status_aktivitas, pendidikan_lanjutan, nama_perusahaan, bidang_pekerjaan, posisi_jabatan, kesesuaian_pekerjaan, waktu_memperoleh_kerja, feedback_sekolah, saran_sekolah, updated_at FROM tracer_study ORDER BY tahun_lulus DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit();
}

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'tambah_alumni') {
        $nis        = trim($_POST['nis']);
        $nama       = trim($_POST['nama']);
        $tahun_lulus = trim($_POST['tahun_lulus']);
        $kuliah_kerja = trim($_POST['kuliah_kerja']);
        $kontak     = trim($_POST['kontak']);

        if (!$nis || !$nama || !$tahun_lulus || !$kuliah_kerja) {
            $msg = 'NIS, Nama, Tahun Lulus, dan Instansi wajib diisi!';
            $msgType = 'error';
        } else {
            $stmt = $pdo->prepare("INSERT INTO alumni (nis, nama, tahun_lulus, kuliah_kerja, kontak) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nis, $nama, $tahun_lulus, $kuliah_kerja, $kontak]);
            $msg = "Data alumni <strong>" . htmlspecialchars($nama) . "</strong> berhasil disimpan!";
            log_activity("Admin registered alumni: $nama");
        }
    } elseif ($_POST['action'] === 'hapus_alumni') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM alumni WHERE id = ?")->execute([$id]);
        $msg = "Data alumni berhasil dihapus.";
        log_activity("Admin deleted alumni ID: $id");
    } elseif ($_POST['action'] === 'hapus_tracer') {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM tracer_study WHERE id = ?")->execute([$id]);
        $msg = "Data respon tracer study berhasil dihapus.";
        log_activity("Admin deleted tracer record ID: $id");
    }
}

// Fetch Alumni & Tracer Data
$alumniList = $pdo->query("SELECT * FROM alumni ORDER BY tahun_lulus DESC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);
$tracerList = $pdo->query("SELECT * FROM tracer_study ORDER BY tahun_lulus DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Unique years for filter
$tahunListAlumni = array_unique(array_column($alumniList, 'tahun_lulus'));
$tahunListTracer = array_unique(array_column($tracerList, 'tahun_lulus'));
$tahunList = array_unique(array_merge($tahunListAlumni, $tahunListTracer));
rsort($tahunList);

// Stats Tracer Study
$totalTracer = count($tracerList);
$countBekerja = 0;
$countKuliah = 0;
$countWirausaha = 0;
$countSesuai = 0;

foreach ($tracerList as $tr) {
    $st = strtolower($tr['status_aktivitas'] ?? '');
    if (str_contains($st, 'bekerja')) {
        $countBekerja++;
    } elseif (str_contains($st, 'kuliah') || str_contains($st, 'studi')) {
        $countKuliah++;
    } elseif (str_contains($st, 'wirausaha') || str_contains($st, 'bisnis')) {
        $countWirausaha++;
    }

    $ks = strtolower($tr['kesesuaian_pekerjaan'] ?? '');
    if (str_contains($ks, 'sesuai') && !str_contains($ks, 'tidak') && !str_contains($ks, 'kurang')) {
        $countSesuai++;
    }
}

$persenBekerja = $totalTracer > 0 ? round(($countBekerja / $totalTracer) * 100) : 0;
$persenKuliah = $totalTracer > 0 ? round(($countKuliah / $totalTracer) * 100) : 0;
$persenSesuai = $totalTracer > 0 ? round(($countSesuai / $totalTracer) * 100) : 0;
?>

<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-graduation-cap text-teal-400"></i> Hasil Tracer Study &amp; Data Alumni
            </h1>
            <p class="text-xs text-slate-400 mt-1">Evaluasi rekam jejak lulusan, studi lanjut perguruan tinggi, serapan karir industri, dan saran mutu</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="dashboard.php?page=alumni&export=tracer_csv" class="px-4 py-2.5 bg-slate-800 hover:bg-emerald-600/20 hover:border-emerald-500/40 text-slate-300 hover:text-emerald-300 font-bold text-xs rounded-xl border border-slate-700 flex items-center gap-2 transition-all shadow-sm">
                <i class="fa-solid fa-file-excel text-emerald-400"></i> Export CSV Tracer
            </a>
            <button type="button" onclick="openModal('modal-add-alumni')" class="px-4 py-2.5 bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-teal-600/30 flex items-center gap-2 transition-all">
                <i class="fa-solid fa-plus"></i> Tambah Data Alumni
            </button>
        </div>
    </div>

    <!-- Alert Notifikasi -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border text-xs font-semibold flex items-center justify-between shadow-lg <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
                <span><?= $msg; ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Statistik Ringkasan Tracer Study -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-400 text-lg shrink-0">
                <i class="fa-solid fa-users-rectangle"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Respon Tracer</span>
                <p class="text-xl font-black text-white font-mono mt-0.5"><?= $totalTracer; ?></p>
                <span class="text-[10px] text-slate-500 font-mono block">Dari <?= count($alumniList); ?> Alumni</span>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-lg shrink-0">
                <i class="fa-solid fa-briefcase"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Terserap Bekerja</span>
                <p class="text-xl font-black text-indigo-300 font-mono mt-0.5"><?= $persenBekerja; ?>%</p>
                <span class="text-[10px] text-slate-500 block"><?= $countBekerja; ?> Alumni</span>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 text-lg shrink-0">
                <i class="fa-solid fa-building-columns"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Studi Lanjut PT</span>
                <p class="text-xl font-black text-purple-300 font-mono mt-0.5"><?= $persenKuliah; ?>%</p>
                <span class="text-[10px] text-slate-500 block"><?= $countKuliah; ?> Alumni</span>
            </div>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Kesesuaian Karir</span>
                <p class="text-xl font-black text-emerald-400 font-mono mt-0.5"><?= $persenSesuai; ?>%</p>
                <span class="text-[10px] text-slate-500 block">Sangat Sesuai/Sesuai</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 shadow-xl flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="flex-1 relative">
            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-500 text-xs"></i>
            <input type="text" id="filter-search" placeholder="Cari NIS, nama alumni, perusahaan, atau kampus..." oninput="applyFilters()" class="w-full pl-9 pr-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
        </div>

        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2.5">
            <select id="filter-tahun" onchange="applyFilters()" class="px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-teal-500">
                <option value="">Semua Tahun Lulus</option>
                <?php foreach ($tahunList as $th): ?>
                    <option value="<?= htmlspecialchars($th); ?>">Angkatan <?= htmlspecialchars($th); ?></option>
                <?php endforeach; ?>
            </select>

            <select id="filter-status" onchange="applyFilters()" class="px-3 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-teal-500">
                <option value="">Semua Status Aktivitas</option>
                <option value="Bekerja">Bekerja</option>
                <option value="Kuliah">Kuliah / Studi Lanjut</option>
                <option value="Wirausaha">Wirausaha</option>
                <option value="Mencari Kerja">Mencari Kerja</option>
            </select>
        </div>
    </div>

    <!-- Main Table: Hasil Kuesioner Tracer Study -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-teal-400"></i> Hasil Kuesioner Tracer Study Alumni
            </h3>
            <span class="text-xs text-slate-400">Total: <strong id="tracer-count" class="text-white"><?= $totalTracer; ?></strong> Respon</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300" id="table-tracer">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Alumni &amp; NIS</th>
                        <th class="p-3.5">Tahun Lulus</th>
                        <th class="p-3.5">Status Aktivitas</th>
                        <th class="p-3.5">Instansi / Kampus / Perusahaan</th>
                        <th class="p-3.5">Posisi / Jurusan</th>
                        <th class="p-3.5">Kesesuaian</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    <?php if (empty($tracerList)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500">
                                Belum ada data respon tracer study dari alumni.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tracerList as $tr): ?>
                            <?php
                            $st = $tr['status_aktivitas'] ?? 'Bekerja';
                            $instansi = !empty($tr['nama_perusahaan']) ? $tr['nama_perusahaan'] : (!empty($tr['pendidikan_lanjutan']) ? $tr['pendidikan_lanjutan'] : '-');
                            $posisi = !empty($tr['posisi_jabatan']) ? $tr['posisi_jabatan'] : (!empty($tr['bidang_pekerjaan']) ? $tr['bidang_pekerjaan'] : '-');
                            ?>
                            <tr class="tracer-row hover:bg-slate-800/40 transition-colors"
                                data-search="<?= strtolower(htmlspecialchars($tr['nama'] . ' ' . $tr['nis'] . ' ' . $instansi . ' ' . $posisi)); ?>"
                                data-tahun="<?= htmlspecialchars($tr['tahun_lulus']); ?>"
                                data-status="<?= htmlspecialchars($st); ?>">
                                
                                <td class="p-3.5">
                                    <div class="font-bold text-white"><?= htmlspecialchars($tr['nama']); ?></div>
                                    <span class="text-[10px] text-teal-400 font-mono">NIS: <?= htmlspecialchars($tr['nis']); ?></span>
                                </td>

                                <td class="p-3.5 font-mono font-bold text-slate-200">
                                    <?= htmlspecialchars($tr['tahun_lulus']); ?>
                                </td>

                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold bg-teal-500/10 text-teal-400 border border-teal-500/20">
                                        <?= htmlspecialchars($st); ?>
                                    </span>
                                </td>

                                <td class="p-3.5 font-semibold text-white">
                                    <?= htmlspecialchars($instansi); ?>
                                </td>

                                <td class="p-3.5 text-slate-300">
                                    <?= htmlspecialchars($posisi); ?>
                                </td>

                                <td class="p-3.5">
                                    <span class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                        <?= htmlspecialchars($tr['kesesuaian_pekerjaan'] ?: 'Sesuai'); ?>
                                    </span>
                                </td>

                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button type="button" onclick="viewTracerDetail(<?= htmlspecialchars(json_encode($tr), ENT_QUOTES); ?>)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-semibold flex items-center gap-1">
                                            <i class="fa-solid fa-eye text-[11px]"></i> Detail
                                        </button>
                                        <form action="dashboard.php?page=alumni" method="POST" onsubmit="return confirm('Hapus respon tracer study ini?');" class="inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="hapus_tracer">
                                            <input type="hidden" name="id" value="<?= $tr['id']; ?>">
                                            <button type="submit" class="w-7 h-7 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all">
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
    </div>

    <!-- Modal Tambah Data Alumni -->
    <div id="modal-add-alumni" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-teal-400"></i> Tambah Data Alumni
                </h3>
                <button type="button" onclick="closeModal('modal-add-alumni')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=alumni" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_alumni">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nomor Induk Siswa (NIS)</label>
                    <input type="text" name="nis" required placeholder="Contoh: 202401001" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap Alumni</label>
                    <input type="text" name="nama" required placeholder="Nama Lengkap" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tahun Lulus</label>
                        <input type="text" name="tahun_lulus" required placeholder="2024" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">No. Kontak / WA</label>
                        <input type="text" name="kontak" placeholder="08123456789" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Instansi / Tempat Kuliah / Kantor</label>
                    <input type="text" name="kuliah_kerja" required placeholder="Contoh: ITB - Teknik Elektro / PT Telkom" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-teal-500">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-alumni')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-bold">Simpan Alumni</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail Respon Kuesioner Tracer Study -->
    <div id="modal-detail-tracer" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-xl shadow-2xl relative space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <h3 id="detail-nama" class="text-base font-extrabold text-white"></h3>
                    <span id="detail-nis-tahun" class="text-xs text-teal-400 font-mono"></span>
                </div>
                <button type="button" onclick="closeModal('modal-detail-tracer')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-3 text-xs text-slate-300">
                <div class="p-3.5 bg-slate-950 rounded-2xl border border-slate-800 grid grid-cols-2 gap-3">
                    <div>
                        <span class="text-slate-500 text-[10px] uppercase font-bold block">Status Aktivitas</span>
                        <strong id="detail-status" class="text-white"></strong>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] uppercase font-bold block">Waktu Tunggu Kerja</span>
                        <span id="detail-waktu-tunggu" class="text-white font-mono"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] uppercase font-bold block">Nama Perusahaan / Kampus</span>
                        <span id="detail-instansi" class="text-teal-300 font-semibold"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] uppercase font-bold block">Posisi / Program Studi</span>
                        <span id="detail-posisi" class="text-white"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] uppercase font-bold block">Bidang Pekerjaan</span>
                        <span id="detail-bidang" class="text-slate-300"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 text-[10px] uppercase font-bold block">Kesesuaian Kompetensi</span>
                        <span id="detail-kesesuaian" class="text-emerald-400 font-bold"></span>
                    </div>
                </div>

                <div class="p-4 bg-slate-950 rounded-2xl border border-slate-800 space-y-1.5">
                    <span class="text-slate-400 font-bold block"><i class="fa-solid fa-comment-dots text-teal-400 mr-1"></i> Feedback terhadap Pembelajaran Sekolah:</span>
                    <p id="detail-feedback" class="text-slate-300 italic whitespace-pre-wrap"></p>
                </div>

                <div class="p-4 bg-slate-950 rounded-2xl border border-slate-800 space-y-1.5">
                    <span class="text-slate-400 font-bold block"><i class="fa-solid fa-lightbulb text-amber-400 mr-1"></i> Saran &amp; Masukan untuk Sekolah:</span>
                    <p id="detail-saran" class="text-slate-300 italic whitespace-pre-wrap"></p>
                </div>
            </div>

            <div class="pt-3 flex justify-end border-t border-slate-800">
                <button type="button" onclick="closeModal('modal-detail-tracer')" class="px-4 py-2 bg-slate-800 text-slate-300 hover:text-white rounded-xl text-xs font-bold">Tutup</button>
            </div>
        </div>
    </div>

</div>

<script>
function applyFilters() {
    const search = document.getElementById('filter-search').value.toLowerCase();
    const tahun = document.getElementById('filter-tahun').value;
    const status = document.getElementById('filter-status').value.toLowerCase();

    const rows = document.querySelectorAll('.tracer-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
        const rowTahun = row.getAttribute('data-tahun') || '';
        const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();

        const matchSearch = !search || rowSearch.includes(search);
        const matchTahun = !tahun || rowTahun === tahun;
        const matchStatus = !status || rowStatus.includes(status);

        if (matchSearch && matchTahun && matchStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('tracer-count').innerText = visibleCount;
}

function viewTracerDetail(data) {
    document.getElementById('detail-nama').innerText = data.nama || '-';
    document.getElementById('detail-nis-tahun').innerText = 'NIS: ' + (data.nis || '-') + ' • Angkatan ' + (data.tahun_lulus || '-');
    document.getElementById('detail-status').innerText = data.status_aktivitas || '-';
    document.getElementById('detail-instansi').innerText = data.nama_perusahaan || (data.pendidikan_lanjutan || '-');
    document.getElementById('detail-posisi').innerText = data.posisi_jabatan || '-';
    document.getElementById('detail-bidang').innerText = data.bidang_pekerjaan || '-';
    document.getElementById('detail-kesesuaian').innerText = data.kesesuaian_pekerjaan || '-';
    document.getElementById('detail-waktu-tunggu').innerText = data.waktu_memperoleh_kerja || '-';
    document.getElementById('detail-feedback').innerText = data.feedback_sekolah || '(Tidak ada feedback terlampir)';
    document.getElementById('detail-saran').innerText = data.saran_sekolah || '(Tidak ada saran terlampir)';
    
    openModal('modal-detail-tracer');
}
</script>
