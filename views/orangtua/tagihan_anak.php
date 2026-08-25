<?php
// views/orangtua/tagihan_anak.php - Modul Pembayaran & Tagihan SPP Siswa untuk Orang Tua
check_role(['orangtua', 'admin']);

$uploadDir = 'uploads/pembayaran/';
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

// Identifikasi Orang Tua yang sedang login
$userName = $user['name'] ?? ($_SESSION['name'] ?? '');
$userEmail = $user['email'] ?? ($_SESSION['email'] ?? '');

$stmtOrtu = $pdo->prepare("SELECT * FROM orangtua WHERE nama = ? OR no_hp = ? LIMIT 1");
$stmtOrtu->execute([$userName, $userName]);
$ortuData = $stmtOrtu->fetch();

if (!$ortuData) {
    // Fallback: cari dari tabel siswa yang memiliki nama_ortu mirip
    $stmtSiswaOrtu = $pdo->prepare("SELECT * FROM siswa WHERE nama_ortu LIKE ? OR nama_ortu = ? LIMIT 1");
    $stmtSiswaOrtu->execute(['%' . $userName . '%', $userName]);
    $siswaMatch = $stmtSiswaOrtu->fetch();
    if ($siswaMatch) {
        $ortuData = [
            'nis_anak' => $siswaMatch['nis'],
            'nama_anak' => $siswaMatch['nama'],
            'nama' => $userName
        ];
    } else {
        $firstSiswa = $pdo->query("SELECT * FROM siswa ORDER BY id ASC LIMIT 1")->fetch();
        if ($firstSiswa) {
            $ortuData = [
                'nis_anak' => $firstSiswa['nis'],
                'nama_anak' => $firstSiswa['nama'],
                'nama' => $userName
            ];
        }
    }
}

$nisAnak = $ortuData['nis_anak'] ?? '';
$namaAnak = $ortuData['nama_anak'] ?? 'Siswa Terdaftar';

$msg = '';
$msgType = 'success';

// Handle Submit Pembayaran & Upload Bukti oleh Orang Tua
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();

    if ($_POST['action'] === 'submit_pembayaran') {
        $tagihan_id = intval($_POST['tagihan_id'] ?? 0);
        $metode = trim($_POST['metode_pembayaran'] ?? '');
        $catatan = trim($_POST['catatan'] ?? '');
        
        // Verifikasi keberadaan tagihan
        $stmtCheck = $pdo->prepare("SELECT * FROM spp_transaksi WHERE id = ?");
        $stmtCheck->execute([$tagihan_id]);
        $tagihanRow = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        $isAllowed = $tagihanRow && ($tagihanRow['nis'] === $nisAnak || in_array(($_SESSION['role'] ?? ''), ['admin', 'keuangan', 'orangtua']));

        if (!$tagihanRow || !$isAllowed) {
            $msg = "Tagihan tidak ditemukan atau tidak sesuai dengan data siswa!";
            $msgType = 'error';
        } else {
            $buktiPath = $tagihanRow['bukti_bayar'] ?? '';

            // Handle Upload File Bukti
            if (isset($_FILES['bukti_foto']) && $_FILES['bukti_foto']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['bukti_foto'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

                if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                    $uniqueName = 'bukti_' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6) . '.' . $ext;
                    $target = $uploadDir . $uniqueName;

                    $moved = is_uploaded_file($file['tmp_name']) ? move_uploaded_file($file['tmp_name'], $target) : @copy($file['tmp_name'], $target);
                    if ($moved) {
                        $buktiPath = $target;
                    }
                } else {
                    $msg = "Format file tidak valid (Gunakan JPG, PNG, WEBP, PDF) atau ukuran melebihi 5MB!";
                    $msgType = 'error';
                }
            }

            if ($msgType !== 'error') {
                $tglBayar = date('Y-m-d H:i:s');
                $stmtUpdate = $pdo->prepare("
                    UPDATE spp_transaksi 
                    SET status = 'Menunggu Verifikasi', 
                        metode_pembayaran = ?, 
                        tanggal_bayar = ?, 
                        bukti_bayar = ?, 
                        catatan = ? 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$metode, $tglBayar, $buktiPath, $catatan, $tagihan_id]);

                $msg = "Pembayaran untuk periode " . htmlspecialchars($tagihanRow['bulan'] . ' ' . $tagihanRow['tahun']) . " berhasil dikirim! Status saat ini: Menunggu Verifikasi Staf Keuangan.";
                log_activity("Parent submitted payment for SPP ID #$tagihan_id ($metode)");
            }
        }
    }
}

// Ambil Daftar Tagihan Siswa Anak
$stmtSpp = $pdo->prepare("SELECT * FROM spp_transaksi WHERE nis = ? ORDER BY id DESC");
$stmtSpp->execute([$nisAnak]);
$tagihanList = $stmtSpp->fetchAll(PDO::FETCH_ASSOC);

if (empty($tagihanList) && ($role ?? '') === 'admin') {
    $tagihanList = $pdo->query("SELECT * FROM spp_transaksi ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
}

// Hitung Statistik Tagihan
$totalTagihan = count($tagihanList);
$countLunas = 0;
$countMenunggu = 0;
$countBelum = 0;
$nominalBelum = 0;

foreach ($tagihanList as $tg) {
    $st = strtolower($tg['status']);
    if ($st === 'lunas') {
        $countLunas++;
    } elseif ($st === 'menunggu verifikasi') {
        $countMenunggu++;
    } else {
        $countBelum++;
        $nominalBelum += (int)$tg['nominal'];
    }
}
?>
<div class="space-y-6">

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                    <i class="fa-solid fa-child-reaching mr-1"></i> Data Siswa: <?= htmlspecialchars($namaAnak); ?> (NIS: <?= htmlspecialchars($nisAnak); ?>)
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-credit-card text-amber-400"></i> Tagihan &amp; Pembayaran SPP Sekolah
            </h1>
            <p class="text-xs text-slate-400 mt-0.5">Informasi administrasi SPP, pemilihan metode transfer, dan unggah bukti pembayaran</p>
        </div>

        <button onclick="window.print()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs rounded-xl border border-slate-700 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-print"></i> Cetak Rekap
        </button>
    </div>

    <!-- Alert Notifikasi -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border text-xs font-semibold flex items-center justify-between shadow-lg <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?> text-base"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <!-- Summary Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Belum Dibayar</span>
                <span class="text-lg font-black text-amber-400 font-mono"><?= $countBelum; ?> Bulan</span>
                <span class="text-[10px] text-slate-500 font-mono block">Rp <?= number_format($nominalBelum, 0, ',', '.'); ?></span>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Menunggu Verifikasi</span>
                <span class="text-lg font-black text-indigo-300 font-mono"><?= $countMenunggu; ?> Tagihan</span>
                <span class="text-[10px] text-slate-500 block">Sedang diperiksa</span>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Sudah Lunas</span>
                <span class="text-lg font-black text-emerald-400 font-mono"><?= $countLunas; ?> Bulan</span>
                <span class="text-[10px] text-slate-500 block">Terverifikasi</span>
            </div>
        </div>

        <div class="p-5 rounded-3xl bg-slate-900 border border-slate-800 shadow-xl flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center text-lg shrink-0">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Total Histori</span>
                <span class="text-lg font-black text-white font-mono"><?= $totalTagihan; ?> Record</span>
                <span class="text-[10px] text-slate-500 block">T.A. 2026/2027</span>
            </div>
        </div>
    </div>

    <!-- Informasi Rekening Pembayaran Resmi -->
    <div class="p-5 rounded-3xl bg-gradient-to-r from-slate-900 to-indigo-950/40 border border-indigo-500/20 shadow-xl space-y-3">
        <div class="flex items-center gap-2 text-indigo-300 text-xs font-bold uppercase tracking-wider">
            <i class="fa-solid fa-building-columns"></i> Rekening Pembayaran Resmi Sekolah
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
            <div class="p-3 bg-slate-950/80 rounded-2xl border border-slate-800">
                <span class="text-slate-400 block text-[10px]">Bank Mandiri</span>
                <strong class="text-white font-mono text-sm block">123-00-9876543-2</strong>
                <span class="text-indigo-400 text-[10px]">a.n. Yayasan Pendidikan Sekolah</span>
            </div>
            <div class="p-3 bg-slate-950/80 rounded-2xl border border-slate-800">
                <span class="text-slate-400 block text-[10px]">Bank BCA</span>
                <strong class="text-white font-mono text-sm block">789-012-3456</strong>
                <span class="text-indigo-400 text-[10px]">a.n. SMA Sekolah</span>
            </div>
            <div class="p-3 bg-slate-950/80 rounded-2xl border border-slate-800">
                <span class="text-slate-400 block text-[10px]">Loket Keuangan Sekolah</span>
                <strong class="text-white text-xs block">Tunai di Bagian Keuangan</strong>
                <span class="text-slate-400 text-[10px]">Senin - Jumat (07:30 - 15:00 WIB)</span>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Tagihan & Histori -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-amber-400"></i> Rincian Tagihan &amp; Histori Pembayaran
            </h3>
            <span class="text-xs text-slate-400">NIS Siswa: <strong class="text-white font-mono"><?= htmlspecialchars($nisAnak); ?></strong></span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Periode SPP</th>
                        <th class="p-3.5">Nominal Tagihan</th>
                        <th class="p-3.5">Tanggal Bayar / Submit</th>
                        <th class="p-3.5">Metode Pembayaran</th>
                        <th class="p-3.5">Status Pembayaran</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi / Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    <?php if (empty($tagihanList)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                Tidak ada tagihan SPP yang tercatat untuk siswa ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tagihanList as $sp): ?>
                            <?php
                            $status = $sp['status'] ?? 'Belum Lunas';
                            $statusLower = strtolower($status);
                            $badgeCls = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                            $statusText = 'BELUM DIBAYAR';

                            if ($statusLower === 'lunas') {
                                $badgeCls = 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
                                $statusText = 'LUNAS';
                            } elseif ($statusLower === 'menunggu verifikasi') {
                                $badgeCls = 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20';
                                $statusText = 'MENUNGGU VERIFIKASI';
                            } elseif ($statusLower === 'ditolak') {
                                $badgeCls = 'bg-rose-500/10 text-rose-400 border border-rose-500/20';
                                $statusText = 'DITOLAK';
                            }

                            $safePeriode = htmlspecialchars($sp['bulan'] . ' ' . $sp['tahun'], ENT_QUOTES);
                            $safeNominal = number_format($sp['nominal'], 0, ',', '.');
                            ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="p-3.5 font-bold text-white">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-regular fa-calendar text-indigo-400"></i>
                                        <span><?= htmlspecialchars($sp['bulan']); ?> <?= htmlspecialchars($sp['tahun']); ?></span>
                                    </div>
                                </td>
                                <td class="p-3.5 font-bold text-slate-200 font-mono">
                                    Rp <?= $safeNominal; ?>
                                </td>
                                <td class="p-3.5 text-slate-400 font-mono text-[11px]">
                                    <?= !empty($sp['tanggal_bayar']) ? date('d/m/Y H:i', strtotime($sp['tanggal_bayar'])) : '-'; ?>
                                </td>
                                <td class="p-3.5 text-slate-300">
                                    <?= htmlspecialchars($sp['metode_pembayaran'] ?: '-'); ?>
                                </td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold <?= $badgeCls; ?>">
                                        <?= $statusText; ?>
                                    </span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <?php if ($statusLower === 'belum dibayar' || $statusLower === 'belum lunas' || $statusLower === 'ditolak'): ?>
                                            <button type="button" onclick="openPaymentModal(<?= $sp['id']; ?>, '<?= $safePeriode; ?>', '<?= $safeNominal; ?>')" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-md shadow-indigo-600/30 flex items-center gap-1.5 transition-all">
                                                <i class="fa-solid fa-upload text-[11px]"></i> Bayar Sekarang
                                            </button>
                                        <?php elseif ($statusLower === 'menunggu verifikasi'): ?>
                                            <button type="button" onclick="viewPaymentDetail(<?= htmlspecialchars(json_encode($sp), ENT_QUOTES); ?>)" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-indigo-300 rounded-xl text-xs font-semibold border border-slate-700 flex items-center gap-1">
                                                <i class="fa-solid fa-eye text-[11px]"></i> Lihat Bukti
                                            </button>
                                        <?php elseif ($statusLower === 'lunas'): ?>
                                            <button type="button" onclick="viewPaymentDetail(<?= htmlspecialchars(json_encode($sp), ENT_QUOTES); ?>)" class="px-3 py-1.5 bg-emerald-950/40 text-emerald-300 border border-emerald-500/20 hover:bg-emerald-900/40 rounded-xl text-xs font-semibold flex items-center gap-1">
                                                <i class="fa-solid fa-receipt text-[11px]"></i> Kuitansi
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Form Bayar & Upload Bukti -->
    <div id="modal-bayar-tagihan" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-credit-card text-indigo-400"></i> Konfirmasi Pembayaran SPP
                </h3>
                <button type="button" onclick="closeModal('modal-bayar-tagihan')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=tagihan_anak" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="submit_pembayaran">
                <input type="hidden" name="tagihan_id" id="modal-tagihan-id">

                <!-- Detail Tagihan Box -->
                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-1.5">
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Periode Tagihan:</span>
                        <strong id="modal-tagihan-periode" class="text-white">Agustus 2026</strong>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="text-slate-400">Nominal Tagihan:</span>
                        <strong class="text-emerald-400 font-mono text-sm" id="modal-tagihan-nominal">Rp 350.000</strong>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Pilih Metode Pembayaran</label>
                    <select name="metode_pembayaran" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-indigo-500">
                        <option value="Transfer Bank Mandiri">Transfer Bank Mandiri (123-00-9876543-2)</option>
                        <option value="Transfer Bank BCA">Transfer Bank BCA (789-012-3456)</option>
                        <option value="Transfer Bank BRI">Transfer Bank BRI (0012-01-098765-50-1)</option>
                        <option value="Bayar Tunai di Loket Keuangan">Bayar Tunai di Loket Keuangan Sekolah</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Upload Bukti Transfer / Struk (Foto/PDF)</label>
                    <input type="file" name="bukti_foto" accept="image/jpeg,image/png,image/webp,application/pdf" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-300 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
                    <span class="text-[10px] text-slate-400 mt-1 block">Format: JPG, PNG, WEBP, atau PDF (Maks. 5MB)</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="catatan" placeholder="Contoh: Transfer a.n. Ayah Siswa / No Ref: 981273" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-bayar-tagihan')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/30">
                        Kirim Konfirmasi Pembayaran
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Lihat Detail Bukti / Status -->
    <div id="modal-detail-pembayaran" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative space-y-4">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-emerald-400"></i> Detail Status Pembayaran
                </h3>
                <button type="button" onclick="closeModal('modal-detail-pembayaran')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="space-y-3 text-xs text-slate-300">
                <div class="flex justify-between py-1 border-b border-slate-800/60">
                    <span class="text-slate-400">Periode:</span>
                    <strong id="detail-periode" class="text-white"></strong>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800/60">
                    <span class="text-slate-400">Nominal:</span>
                    <strong id="detail-nominal" class="text-emerald-400 font-mono"></strong>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800/60">
                    <span class="text-slate-400">Metode:</span>
                    <span id="detail-metode" class="text-white"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800/60">
                    <span class="text-slate-400">Waktu Bayar:</span>
                    <span id="detail-waktu" class="text-slate-300 font-mono"></span>
                </div>
                <div class="flex justify-between py-1 border-b border-slate-800/60">
                    <span class="text-slate-400">Status:</span>
                    <span id="detail-status-badge" class="px-2 py-0.5 rounded-lg text-[10px] font-bold"></span>
                </div>
                
                <div id="detail-bukti-container" class="pt-2 space-y-1.5 hidden">
                    <span class="text-slate-400 block font-semibold">Bukti Pembayaran Terlampir:</span>
                    <a id="detail-bukti-link" href="#" target="_blank" class="block p-2 rounded-xl bg-slate-950 border border-slate-800 text-indigo-400 hover:text-indigo-300 font-mono text-[11px] truncate text-center">
                        <i class="fa-solid fa-paperclip mr-1"></i> Buka File Bukti Pembayaran
                    </a>
                </div>
            </div>

            <div class="pt-3 flex justify-end border-t border-slate-800">
                <button type="button" onclick="closeModal('modal-detail-pembayaran')" class="px-4 py-2 bg-slate-800 text-slate-300 hover:text-white rounded-xl text-xs font-bold">Tutup</button>
            </div>
        </div>
    </div>

</div>

<script>
function openPaymentModal(id, periode, nominal) {
    document.getElementById('modal-tagihan-id').value = id;
    document.getElementById('modal-tagihan-periode').innerText = periode;
    document.getElementById('modal-tagihan-nominal').innerText = 'Rp ' + nominal;
    openModal('modal-bayar-tagihan');
}

function viewPaymentDetail(data) {
    document.getElementById('detail-periode').innerText = (data.bulan || '') + ' ' + (data.tahun || '');
    document.getElementById('detail-nominal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.nominal || 0);
    document.getElementById('detail-metode').innerText = data.metode_pembayaran || '-';
    document.getElementById('detail-waktu').innerText = data.tanggal_bayar || '-';
    
    const badge = document.getElementById('detail-status-badge');
    const st = (data.status || '').toLowerCase();
    if (st === 'lunas') {
        badge.className = 'px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
        badge.innerText = 'LUNAS';
    } else if (st === 'menunggu verifikasi') {
        badge.className = 'px-2 py-0.5 rounded-lg text-[10px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20';
        badge.innerText = 'MENUNGGU VERIFIKASI';
    } else if (st === 'ditolak') {
        badge.className = 'px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20';
        badge.innerText = 'DITOLAK';
    } else {
        badge.className = 'px-2 py-0.5 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20';
        badge.innerText = 'BELUM DIBAYAR';
    }

    const buktiCont = document.getElementById('detail-bukti-container');
    const buktiLink = document.getElementById('detail-bukti-link');
    if (data.bukti_bayar) {
        buktiCont.classList.remove('hidden');
        buktiLink.href = data.bukti_bayar;
    } else {
        buktiCont.classList.add('hidden');
    }

    openModal('modal-detail-pembayaran');
}
</script>
