<?php
// views/admin/mapel.php - Manajemen Mata Pelajaran dengan Auto-Generate Kode
check_role(['admin']);

// Helper function untuk generate kode mapel otomatis
if (!function_exists('generate_kode_mapel')) {
    function generate_kode_mapel($nama_mapel, $pdo, $exclude_id = 0) {
    $clean = trim($nama_mapel);
    $words = preg_split('/\s+/', $clean);
    $lower = strtolower($clean);
    
    // Peta singkatan mata pelajaran standar Indonesia
    $aliasMap = [
        'bahasa indonesia' => 'BIN',
        'bahasa inggris' => 'BIG',
        'bahasa arab' => 'BAR',
        'bahasa jepang' => 'BJP',
        'bahasa mandarin' => 'BMD',
        'bahasa sunda' => 'BSD',
        'bahasa jawa' => 'BJW',
        'pendidikan agama islam' => 'PAI',
        'pendidikan agama kristen' => 'PAK',
        'pendidikan agama katolik' => 'PAKT',
        'pendidikan agama hindu' => 'PAH',
        'pendidikan agama buddha' => 'PAB',
        'pendidikan kewarganegaraan' => 'PKN',
        'pendidikan pancasila' => 'PPKN',
        'pendidikan pancasila dan kewarganegaraan' => 'PPKN',
        'pendidikan jasmani' => 'PJOK',
        'pendidikan jasmani dan kesehatan' => 'PJOK',
        'pendidikan jasmani olahraga dan kesehatan' => 'PJOK',
        'pendidikan jasmani, olahraga, dan kesehatan' => 'PJOK',
        'penjasorkes' => 'PJOK',
        'penjas' => 'PJOK',
        'olahraga' => 'OLR',
        'matematika' => 'MTK',
        'matematika wajib' => 'MTKW',
        'matematika peminatan' => 'MTKP',
        'fisika' => 'FIS',
        'kimia' => 'KIM',
        'biologi' => 'BIO',
        'sejarah' => 'SEJ',
        'sejarah indonesia' => 'SEJ',
        'sejarah peminatan' => 'SEJP',
        'geografi' => 'GEO',
        'sosiologi' => 'SOS',
        'ekonomi' => 'EKO',
        'akuntansi' => 'AKT',
        'seni budaya' => 'SBD',
        'seni musik' => 'SNM',
        'seni rupa' => 'SNR',
        'seni tari' => 'SNT',
        'prakarya' => 'PKR',
        'prakarya dan kewirausahaan' => 'PKWU',
        'bimbingan konseling' => 'BK',
        'informatika' => 'INF',
        'teknologi informasi' => 'TIK',
        'teknologi informasi dan komunikasi' => 'TIK'
    ];
    
    $prefix = '';
    if (isset($aliasMap[$lower])) {
        $prefix = $aliasMap[$lower];
    } else {
        if (count($words) >= 2) {
            // Gabungan inisial kata
            foreach ($words as $w) {
                if (strlen($w) > 0) $prefix .= strtoupper($w[0]);
            }
        } else {
            $w = preg_replace('/[^a-zA-Z0-9]/', '', $words[0]);
            if (strlen($w) <= 3) {
                $prefix = strtoupper($w);
            } else {
                $prefix = strtoupper(substr($w, 0, 3));
            }
        }
    }

    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper($prefix));
    if (empty($prefix)) $prefix = 'MPL';

    // Ambil semua kode mapel yang ada untuk mencari nomor urut tertinggi
    $stmtCodes = $pdo->query("SELECT id, kode FROM mapel");
    $existingCodes = [];
    $maxNum = 100;

    while ($row = $stmtCodes->fetch(PDO::FETCH_ASSOC)) {
        if ($exclude_id > 0 && (int)$row['id'] === (int)$exclude_id) {
            continue;
        }
        $cd = trim($row['kode']);
        $existingCodes[] = strtoupper($cd);
        if (preg_match('/-(\d+)$/', $cd, $m)) {
            $n = (int)$m[1];
            if ($n > $maxNum) {
                $maxNum = $n;
            }
        }
    }

    // Nomor urut berikutnya
    $nextNum = $maxNum + 1;
    $finalCode = $prefix . '-' . $nextNum;

    // Pastikan tidak ada collision
    while (in_array(strtoupper($finalCode), $existingCodes)) {
        $nextNum++;
        $finalCode = $prefix . '-' . $nextNum;
    }

    return $finalCode;
}
}

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    
    if ($_POST['action'] === 'tambah_mapel') {
        $nama_mapel = trim($_POST['nama_mapel'] ?? '');
        $pengampu = trim($_POST['pengampu'] ?? '');

        if (empty($nama_mapel)) {
            $msg = "Nama mata pelajaran tidak boleh kosong!";
            $msgType = 'error';
        } else {
            // Cek duplikasi nama mapel (case-insensitive)
            $checkName = $pdo->prepare("SELECT id FROM mapel WHERE LOWER(nama_mapel) = LOWER(?) LIMIT 1");
            $checkName->execute([$nama_mapel]);
            if ($checkName->fetch()) {
                $msg = "Gagal: Mata Pelajaran '$nama_mapel' sudah terdaftar!";
                $msgType = 'error';
            } else {
                // Generate kode otomatis oleh sistem
                $kode = generate_kode_mapel($nama_mapel, $pdo);

                try {
                    $stmt = $pdo->prepare("INSERT INTO mapel (nama_mapel, kode, pengampu) VALUES (?, ?, ?)");
                    $stmt->execute([$nama_mapel, $kode, $pengampu]);
                    $msg = "Mata Pelajaran '$nama_mapel' berhasil ditambahkan dengan Kode Otomatis: $kode!";
                    log_activity("Admin created mapel: $nama_mapel with code: $kode");
                } catch (Exception $e) {
                    $msg = "Gagal menambah mapel: " . $e->getMessage();
                    $msgType = 'error';
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_mapel') {
        $id = intval($_POST['id'] ?? 0);
        $nama_mapel = trim($_POST['nama_mapel'] ?? '');
        $pengampu = trim($_POST['pengampu'] ?? '');

        if ($id <= 0 || empty($nama_mapel)) {
            $msg = "Data mata pelajaran tidak valid!";
            $msgType = 'error';
        } else {
            // Cek duplikasi nama mapel pada ID lain
            $checkName = $pdo->prepare("SELECT id FROM mapel WHERE LOWER(nama_mapel) = LOWER(?) AND id != ? LIMIT 1");
            $checkName->execute([$nama_mapel, $id]);
            if ($checkName->fetch()) {
                $msg = "Gagal: Mata Pelajaran '$nama_mapel' sudah digunakan pada data lain!";
                $msgType = 'error';
            } else {
                // Ambil kode existing agar tidak berubah saat edit nama
                $orig = $pdo->prepare("SELECT nama_mapel, kode FROM mapel WHERE id = ?");
                $orig->execute([$id]);
                $oldRow = $orig->fetch(PDO::FETCH_ASSOC);
                $old_name = $oldRow['nama_mapel'] ?? '';
                $kode = $oldRow['kode'] ?? '';

                if (empty($kode)) {
                    $kode = generate_kode_mapel($nama_mapel, $pdo, $id);
                }

                try {
                    $stmt = $pdo->prepare("UPDATE mapel SET nama_mapel = ?, pengampu = ? WHERE id = ?");
                    $stmt->execute([$nama_mapel, $pengampu, $id]);

                    // Sinkronisasi pembaruan nama mapel & pengampu ke jadwal pelajaran
                    if (!empty($old_name)) {
                        $syncStmt = $pdo->prepare("UPDATE jadwal_pelajaran SET mapel_nama = ?, guru_nama = ? WHERE mapel_nama = ?");
                        $syncStmt->execute([$nama_mapel, $pengampu, $old_name]);
                    }

                    $msg = "Mata pelajaran '$nama_mapel' (Kode: $kode) berhasil diperbarui dan disinkronkan ke Jadwal Pelajaran!";
                    log_activity("Admin updated mapel: $nama_mapel");
                } catch (Exception $e) {
                    $msg = "Gagal memperbarui mapel: " . $e->getMessage();
                    $msgType = 'error';
                }
            }
        }
    } elseif ($_POST['action'] === 'hapus_mapel') {
        $id = intval($_POST['id'] ?? 0);
        $orig = $pdo->prepare("SELECT nama_mapel FROM mapel WHERE id = ?");
        $orig->execute([$id]);
        $old_name = $orig->fetchColumn();

        $pdo->prepare("DELETE FROM mapel WHERE id = ?")->execute([$id]);
        if (!empty($old_name)) {
            $pdo->prepare("DELETE FROM jadwal_pelajaran WHERE mapel_nama = ?")->execute([$old_name]);
        }
        $msg = "Mata pelajaran '$old_name' berhasil dihapus beserta jadwal terkait!";
        log_activity("Admin deleted mapel ID $id");
    }
}

$mapelList = $pdo->query("SELECT * FROM mapel ORDER BY id ASC")->fetchAll();
$guruList = $pdo->query("SELECT nama FROM guru ORDER BY nama ASC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-book-bookmark text-indigo-400"></i> Kurikulum & Mata Pelajaran
            </h1>
            <p class="text-xs text-slate-400 mt-1">Daftar mata pelajaran dengan kode otomatis yang terintegrasi ke Jadwal & Guru</p>
        </div>
        <button type="button" onclick="openAddMapelModal()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-plus"></i> Tambah Mapel Baru
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border text-xs font-semibold flex items-center justify-between shadow-lg <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Kode Mapel</th>
                        <th class="p-3.5">Nama Mata Pelajaran</th>
                        <th class="p-3.5">Guru Pengampu Utama</th>
                        <th class="p-3.5 rounded-r-xl text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    <?php if (empty($mapelList)): ?>
                        <tr>
                            <td colspan="4" class="p-8 text-center text-slate-500">
                                Belum ada data mata pelajaran. Klik 'Tambah Mapel Baru' untuk menambahkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mapelList as $mp): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-3.5 font-mono text-indigo-400 font-bold flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-500/10 border border-indigo-500/20"><?= htmlspecialchars($mp['kode']); ?></span>
                                </td>
                                <td class="p-3.5 font-bold text-white"><?= htmlspecialchars($mp['nama_mapel']); ?></td>
                                <td class="p-3.5 text-slate-300">
                                    <?php if (!empty($mp['pengampu'])): ?>
                                        <i class="fa-solid fa-chalkboard-user text-indigo-400 mr-1.5"></i><?= htmlspecialchars($mp['pengampu']); ?>
                                    <?php else: ?>
                                        <span class="text-slate-500 italic">Belum Ditentukan</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" onclick='openEditMapel(<?= json_encode($mp); ?>)' class="px-3 py-1 bg-amber-500/10 hover:bg-amber-500 hover:text-white text-amber-400 rounded-lg border border-amber-500/20 text-xs font-bold transition-all flex items-center gap-1" title="Edit Mapel & Guru">
                                            <i class="fa-solid fa-pen-to-square"></i> Edit
                                        </button>
                                        <form action="dashboard.php?page=mapel" method="POST" onsubmit="return confirm('Hapus mata pelajaran ini beserta jadwalnya?');" class="inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="hapus_mapel">
                                            <input type="hidden" name="id" value="<?= $mp['id']; ?>">
                                            <button type="submit" class="px-3 py-1 bg-rose-500/10 hover:bg-rose-500 hover:text-white text-rose-400 rounded-lg border border-rose-500/20 text-xs font-bold transition-all flex items-center gap-1" title="Hapus Mapel">
                                                <i class="fa-solid fa-trash text-xs"></i> Hapus
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

    <!-- Modal Tambah Mapel (Kode Otomatis) -->
    <div id="modal-add-mapel" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-book-medical text-indigo-400"></i> Tambah Mata Pelajaran Baru
                </h3>
                <button type="button" onclick="closeModal('modal-add-mapel')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=mapel" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_mapel">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Mata Pelajaran</label>
                    <input type="text" id="add_nama_mapel" name="nama_mapel" required placeholder="Contoh: Olahraga, Biologi, Seni Musik" oninput="updateAutoCodePreview(this.value)" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500">
                    <span class="text-[10px] text-slate-400 mt-1 block">Cukup masukkan nama, kode mapel dibuat otomatis oleh sistem.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Mapel (Otomatis)</label>
                    <div class="relative">
                        <input type="text" id="add_kode_preview" readonly value="[ Otomatis saat disimpan ]" class="w-full px-3.5 py-2.5 bg-slate-950/60 border border-slate-800 rounded-xl text-xs text-indigo-400 font-mono font-bold cursor-not-allowed">
                        <span class="absolute right-3 top-2.5 text-[10px] text-emerald-400 font-bold flex items-center gap-1">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Auto
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Guru Pengampu Utama</label>
                    <select name="pengampu" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-indigo-500">
                        <option value="">-- Pilih Guru Pengampu (Opsional) --</option>
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?= htmlspecialchars($g['nama']); ?>"><?= htmlspecialchars($g['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-mapel')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/30">Simpan Mapel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Mapel -->
    <div id="modal-edit-mapel" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-amber-400"></i> Edit Mata Pelajaran
                </h3>
                <button type="button" onclick="closeModal('modal-edit-mapel')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=mapel" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_mapel">
                <input type="hidden" name="id" id="edit_mapel_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kode Mapel (Tersimpan)</label>
                    <input type="text" id="edit_mapel_kode" readonly class="w-full px-3.5 py-2.5 bg-slate-950/60 border border-slate-800 rounded-xl text-xs text-indigo-400 font-mono font-bold cursor-not-allowed">
                    <span class="text-[10px] text-slate-500 mt-1 block">*Kode yang sudah tersimpan bersifat permanen.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Mata Pelajaran</label>
                    <input type="text" name="nama_mapel" id="edit_mapel_nama" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Guru Pengampu Utama</label>
                    <select name="pengampu" id="edit_mapel_pengampu" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-indigo-500">
                        <option value="">-- Belum Ditentukan --</option>
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?= htmlspecialchars($g['nama']); ?>"><?= htmlspecialchars($g['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-mapel')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/30">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openAddMapelModal() {
    document.getElementById('add_nama_mapel').value = '';
    document.getElementById('add_kode_preview').value = '[ Otomatis saat disimpan ]';
    openModal('modal-add-mapel');
}

function updateAutoCodePreview(val) {
    const preview = document.getElementById('add_kode_preview');
    const text = val.trim().toLowerCase();
    if (!text) {
        preview.value = '[ Otomatis saat disimpan ]';
        return;
    }
    
    // Perkiraan prefix di frontend
    const map = {
        'olahraga': 'OLR',
        'bahasa indonesia': 'BIN',
        'bahasa inggris': 'BIG',
        'matematika': 'MTK',
        'fisika': 'FIS',
        'kimia': 'KIM',
        'biologi': 'BIO',
        'sejarah': 'SEJ',
        'geografi': 'GEO',
        'sosiologi': 'SOS',
        'ekonomi': 'EKO',
        'pendidikan agama islam': 'PAI',
        'seni budaya': 'SBD'
    };
    
    let p = map[text];
    if (!p) {
        const words = text.split(/\s+/);
        if (words.length >= 2) {
            p = words.map(w => w[0].toUpperCase()).join('');
        } else {
            p = text.substring(0, 3).toUpperCase();
        }
    }
    preview.value = (p || 'MPL') + '-[Auto-ID]';
}

function openEditMapel(data) {
    document.getElementById('edit_mapel_id').value = data.id || '';
    document.getElementById('edit_mapel_kode').value = data.kode || '';
    document.getElementById('edit_mapel_nama').value = data.nama_mapel || '';
    document.getElementById('edit_mapel_pengampu').value = data.pengampu || '';
    openModal('modal-edit-mapel');
}
</script>
