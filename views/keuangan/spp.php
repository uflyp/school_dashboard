<?php
// views/keuangan/spp.php
check_role(['keuangan', 'admin']);

// Handle Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_SPP_' . date('Ymd_His') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID Transaksi', 'NIS', 'Nama Siswa', 'Bulan', 'Tahun', 'Nominal (Rp)', 'Tanggal Bayar', 'Status', 'Metode Pembayaran']);
    $rows = $pdo->query("SELECT id, nis, nama_siswa, bulan, tahun, nominal, tanggal_bayar, status, metode_pembayaran FROM spp_transaksi ORDER BY id DESC")->fetchAll();
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit();
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'bayar_spp') {
        $id = intval($_POST['id']);
        $metode = trim($_POST['metode']);
        $tgl = date('Y-m-d');

        $stmt = $pdo->prepare("UPDATE spp_transaksi SET status = 'Lunas', tanggal_bayar = ?, metode_pembayaran = ? WHERE id = ?");
        $stmt->execute([$tgl, $metode, $id]);
        log_activity("Financial staff processed SPP payment ID #$id ($metode)");
        $msg = "Pembayaran SPP berhasil diproses dan status menjadi LUNAS!";
    } elseif ($_POST['action'] === 'verifikasi_spp') {
        $id = intval($_POST['id']);
        $status_verifikasi = trim($_POST['status_verifikasi']); // 'Lunas' atau 'Ditolak'
        $catatan = trim($_POST['catatan'] ?? '');
        $tgl = date('Y-m-d H:i:s');

        if ($status_verifikasi === 'Lunas') {
            $stmt = $pdo->prepare("UPDATE spp_transaksi SET status = 'Lunas', tanggal_bayar = ?, catatan = ? WHERE id = ?");
            $stmt->execute([$tgl, $catatan, $id]);
            log_activity("Financial staff approved SPP verification ID #$id");
            $msg = "Bukti pembayaran SPP #$id berhasil DISETUJUI dan berstatus LUNAS!";
        } else {
            $stmt = $pdo->prepare("UPDATE spp_transaksi SET status = 'Ditolak', catatan = ? WHERE id = ?");
            $stmt->execute([$catatan, $id]);
            log_activity("Financial staff rejected SPP verification ID #$id");
            $msg = "Pembayaran SPP #$id DITOLAK. Orang tua dapat mengunggah ulang bukti yang valid.";
        }
    } elseif ($_POST['action'] === 'tambah_tagihan') {
        $nis = trim($_POST['nis']);
        $namaSiswa = trim($_POST['nama_siswa']);
        $bulan = trim($_POST['bulan']);
        $tahun = trim($_POST['tahun']);
        $nominal = intval($_POST['nominal']);

        $stmt = $pdo->prepare("INSERT INTO spp_transaksi (nis, nama_siswa, bulan, tahun, nominal, status) VALUES (?, ?, ?, ?, ?, 'Belum Lunas')");
        $stmt->execute([$nis, $namaSiswa, $bulan, $tahun, $nominal]);
        log_activity("Financial staff added SPP bill for $namaSiswa ($bulan $tahun)");
        $msg = "Tagihan SPP baru berhasil ditambahkan!";
    }
}

// Join with orangtua/siswa to get parents phone number for WA gateway
$sppList = $pdo->query("
    SELECT sp.*, s.kelas, COALESCE(o.no_hp, '') as no_hp_ortu 
    FROM spp_transaksi sp 
    LEFT JOIN siswa s ON sp.nis = s.nis 
    LEFT JOIN orangtua o ON sp.nis = o.nis_anak 
    ORDER BY sp.id DESC
")->fetchAll();

$siswaList = $pdo->query("SELECT nis, nama, kelas FROM siswa ORDER BY nama ASC")->fetchAll();
$schoolName = get_setting('school_name', 'SMA Nusantara');
$schoolAddr = get_setting('school_address', 'Jl. Merdeka No. 100, Jakarta');
$schoolPhone = get_setting('school_phone', '(021) 7890123');
?>
<div class="space-y-6">

    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Manajemen Pembayaran SPP</h1>
            <p class="text-xs text-slate-400">Proses pencatatan iuran SPP siswa dan pembuatan bukti bayar (Kuitansi)</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="dashboard.php?page=spp&export=csv" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-emerald-600/20 hover:border-emerald-500/40 text-slate-300 hover:text-emerald-300 text-xs font-bold flex items-center gap-2 border border-slate-700 transition-all shadow-sm">
                <i class="fa-solid fa-file-excel text-emerald-400"></i> Export Excel (CSV)
            </a>
            <button type="button" onclick="openModal('modal-tambah-tagihan')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition-all">
                <i class="fa-solid fa-plus"></i> Buat Tagihan SPP
            </button>
        </div>
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
                        <th class="p-3.5 rounded-l-xl">ID</th>
                        <th class="p-3.5">NIS & Nama Siswa</th>
                        <th class="p-3.5">Periode</th>
                        <th class="p-3.5">Nominal</th>
                        <th class="p-3.5">Tanggal Bayar</th>
                        <th class="p-3.5">Metode</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($sppList as $sp): ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 font-mono text-slate-500">#<?= $sp['id']; ?></td>
                            <td class="p-3.5">
                                <div class="font-bold text-white"><?= htmlspecialchars($sp['nama_siswa']); ?></div>
                                <div class="text-[10px] text-indigo-400 font-mono">NIS: <?= htmlspecialchars($sp['nis']); ?></div>
                            </td>
                            <td class="p-3.5 font-semibold text-slate-300"><?= htmlspecialchars($sp['bulan']); ?> <?= htmlspecialchars($sp['tahun']); ?></td>
                            <td class="p-3.5 font-bold text-white">Rp <?= number_format($sp['nominal'], 0, ',', '.'); ?></td>
                            <td class="p-3.5 text-slate-400 font-mono text-[11px]"><?= $sp['tanggal_bayar'] ? date('d/m/Y H:i', strtotime($sp['tanggal_bayar'])) : '-'; ?></td>
                            <td class="p-3.5 text-slate-300">
                                <?= htmlspecialchars($sp['metode_pembayaran'] ?: '-'); ?>
                                <?php if (!empty($sp['bukti_bayar'])): ?>
                                    <a href="<?= htmlspecialchars($sp['bukti_bayar']); ?>" target="_blank" class="ml-1 text-indigo-400 hover:text-indigo-300" title="Lihat Bukti Foto"><i class="fa-solid fa-paperclip"></i></a>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5">
                                <?php
                                $st = strtolower($sp['status'] ?? 'belum lunas');
                                if ($st === 'lunas'):
                                ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">LUNAS</span>
                                <?php elseif ($st === 'menunggu verifikasi'): ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">MENUNGGU VERIFIKASI</span>
                                <?php elseif ($st === 'ditolak'): ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">DITOLAK</span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">BELUM LUNAS</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-center flex items-center justify-center gap-1.5">
                                <?php if ($st === 'menunggu verifikasi'): ?>
                                    <button type="button" onclick="bukaVerifikasiModal(<?= htmlspecialchars(json_encode($sp), ENT_QUOTES); ?>)" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] shadow-sm flex items-center gap-1">
                                        <i class="fa-solid fa-clipboard-check"></i> Verifikasi Bukti
                                    </button>
                                <?php elseif ($st === 'belum lunas' || $st === 'ditolak'): ?>
                                    <button type="button" onclick="prosesBayar(<?= $sp['id']; ?>, '<?= htmlspecialchars($sp['nama_siswa'], ENT_QUOTES); ?>', '<?= htmlspecialchars($sp['bulan'] . ' ' . $sp['tahun'], ENT_QUOTES); ?>')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shadow-sm">
                                        Proses Bayar
                                    </button>
                                    <?php
                                    $hpOrtu = trim($sp['no_hp_ortu'] ?? '');
                                    $cleanHp = preg_replace('/[^0-9]/', '', $hpOrtu);
                                    if (str_starts_with($cleanHp, '0')) {
                                        $cleanHp = '62' . substr($cleanHp, 1);
                                    }
                                    $pesanWa = "Yth. Bapak/Ibu Orang Tua/Wali dari {$sp['nama_siswa']} (NIS: {$sp['nis']}),\n\nKami menginformasikan tagihan iuran SPP untuk periode *{$sp['bulan']} {$sp['tahun']}* sebesar *Rp " . number_format($sp['nominal'], 0, ',', '.') . "* status saat ini: *BELUM LUNAS*.\n\nMohon untuk melakukan pembayaran melalui loket keuangan sekolah atau transfer bank.\n\nTerima kasih.\n_{$schoolName}_";
                                    $waLink = $cleanHp ? ("https://wa.me/" . $cleanHp . "?text=" . urlencode($pesanWa)) : ("https://wa.me/?text=" . urlencode($pesanWa));
                                    ?>
                                    <a href="<?= $waLink; ?>" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500 hover:text-white text-[11px] font-bold border border-emerald-500/20 transition-all flex items-center justify-center" title="Kirim Tagihan via WhatsApp">
                                        <i class="fa-brands fa-whatsapp"></i>
                                    </a>
                                <?php else: ?>
                                    <button type="button" onclick="cetakKuitansi('<?= htmlspecialchars($sp['id'], ENT_QUOTES); ?>', '<?= htmlspecialchars($sp['nama_siswa'], ENT_QUOTES); ?>', '<?= htmlspecialchars($sp['nis'], ENT_QUOTES); ?>', '<?= htmlspecialchars($sp['bulan'] . ' ' . $sp['tahun'], ENT_QUOTES); ?>', '<?= number_format($sp['nominal'], 0, ',', '.'); ?>', '<?= htmlspecialchars($sp['metode_pembayaran'] ?: 'Tunai', ENT_QUOTES); ?>', '<?= htmlspecialchars($sp['tanggal_bayar'] ?? '', ENT_QUOTES); ?>')" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-[11px] border border-slate-700">
                                        <i class="fa-solid fa-print mr-1"></i> Kuitansi
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Proses Bayar -->
    <div id="modal-proses-bayar" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Verifikasi Pembayaran SPP</h3>
                <button type="button" onclick="closeModal('modal-proses-bayar')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=spp" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="bayar_spp">
                <input type="hidden" name="id" id="bayar-id">

                <div class="p-3 bg-slate-950 rounded-xl border border-slate-800 text-xs space-y-1">
                    <div class="text-slate-400">Siswa: <strong id="bayar-nama" class="text-white"></strong></div>
                    <div class="text-slate-400">Periode: <strong id="bayar-periode" class="text-emerald-400"></strong></div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Metode Pembayaran</label>
                    <select name="metode" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="Tunai (Kasir Sekolah)">Tunai (Kasir Sekolah)</option>
                        <option value="Transfer Bank BCA">Transfer Bank BCA</option>
                        <option value="Transfer Bank Mandiri">Transfer Bank Mandiri</option>
                        <option value="QRIS Sekolah">QRIS Sekolah</option>
                    </select>
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-proses-bayar')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold">Konfirmasi Lunas</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Tambah Tagihan -->
    <div id="modal-tambah-tagihan" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Tambah Tagihan SPP Baru</h3>
                <button type="button" onclick="closeModal('modal-tambah-tagihan')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=spp" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="tambah_tagihan">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Pilih Siswa</label>
                    <select name="nis" id="select-siswa" onchange="updateSiswaName()" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <?php foreach ($siswaList as $sw): ?>
                            <option value="<?= $sw['nis']; ?>" data-nama="<?= htmlspecialchars($sw['nama']); ?>">
                                <?= htmlspecialchars($sw['nis']); ?> - <?= htmlspecialchars($sw['nama']); ?> (<?= htmlspecialchars($sw['kelas'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="nama_siswa" id="input-nama-siswa">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Bulan</label>
                        <select name="bulan" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="Januari">Januari</option>
                            <option value="Februari">Februari</option>
                            <option value="Maret">Maret</option>
                            <option value="April">April</option>
                            <option value="Mei">Mei</option>
                            <option value="Juni">Juni</option>
                            <option value="Juli">Juli</option>
                            <option value="Agustus">Agustus</option>
                            <option value="September">September</option>
                            <option value="Oktober">Oktober</option>
                            <option value="November">November</option>
                            <option value="Desember">Desember</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Tahun</label>
                        <input type="text" name="tahun" value="<?= date('Y'); ?>" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" value="500000" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-tambah-tagihan')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Tagihan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Kuitansi -->
    <div id="modal-kuitansi" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-white text-slate-900 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative font-sans">
            <div class="text-center border-b pb-4 mb-4">
                <div class="text-xs font-bold text-indigo-600 uppercase tracking-widest">KUITANSI PEMBAYARAN SPP</div>
                <h2 class="text-xl font-extrabold text-slate-900"><?= htmlspecialchars($schoolName); ?></h2>
                <p class="text-[10px] text-slate-500"><?= htmlspecialchars($schoolAddr); ?> • Telp: <?= htmlspecialchars($schoolPhone); ?></p>
            </div>

            <div class="space-y-2 text-xs mb-6">
                <div class="flex justify-between"><span class="text-slate-500">No. Transaksi:</span><strong id="kuitansi-no"></strong></div>
                <div class="flex justify-between"><span class="text-slate-500">Tanggal Bayar:</span><span id="kuitansi-tgl"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Siswa / NIS:</span><strong id="kuitansi-siswa"></strong></div>
                <div class="flex justify-between"><span class="text-slate-500">Pembayaran Periode:</span><span id="kuitansi-periode"></span></div>
                <div class="flex justify-between"><span class="text-slate-500">Metode Bayar:</span><span id="kuitansi-metode"></span></div>
                <div class="flex justify-between pt-2 border-t text-sm"><span class="font-bold">Total Pembayaran:</span><strong id="kuitansi-total" class="text-emerald-600 font-mono"></strong></div>
            </div>

            <div class="p-3 bg-emerald-50 rounded-xl text-center text-xs font-bold text-emerald-700 border border-emerald-200 mb-6">
                <i class="fa-solid fa-circle-check mr-1"></i> STATUS: LUNAS & DIVERIFIKASI KASIR
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('modal-kuitansi')" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl text-xs font-bold">Tutup</button>
                <button type="button" onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Cetak / PDF
                </button>
            </div>
        </div>
    </div>

</div>

    <!-- Modal Verifikasi Bukti Pembayaran dari Ortu -->
    <div id="modal-verifikasi-bukti" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-clipboard-check text-indigo-400"></i> Verifikasi Bukti Pembayaran Siswa
                </h3>
                <button type="button" onclick="closeModal('modal-verifikasi-bukti')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=spp" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="verifikasi_spp">
                <input type="hidden" name="id" id="verif-id">

                <div class="p-4 rounded-2xl bg-slate-950 border border-slate-800 space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Nama Siswa:</span>
                        <strong id="verif-nama" class="text-white"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Periode Tagihan:</span>
                        <strong id="verif-periode" class="text-emerald-400"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Nominal:</span>
                        <strong id="verif-nominal" class="text-white font-mono"></strong>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Metode Bayar:</span>
                        <span id="verif-metode" class="text-indigo-400 font-semibold"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Waktu Submit:</span>
                        <span id="verif-waktu" class="text-slate-400 font-mono"></span>
                    </div>
                </div>

                <!-- Preview Bukti Pembayaran -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-300">File Bukti Pembayaran</label>
                    <div id="verif-bukti-preview" class="p-3 bg-slate-950 rounded-2xl border border-slate-800 flex items-center justify-center min-h-[140px] max-h-[260px] overflow-hidden">
                        <img id="verif-bukti-img" src="" class="max-h-[240px] object-contain rounded-xl shadow-md hidden">
                        <div id="verif-bukti-pdf" class="text-center space-y-2 hidden">
                            <i class="fa-solid fa-file-pdf text-4xl text-rose-400 block"></i>
                            <a id="verif-bukti-pdf-link" href="#" target="_blank" class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold inline-block">
                                <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Buka Dokumen Bukti
                            </a>
                        </div>
                        <span id="verif-bukti-empty" class="text-xs text-slate-500 hidden">Tidak ada lampiran foto/file bukti.</span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Status Keputusan Verifikasi</label>
                    <select name="status_verifikasi" id="verif-status" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-indigo-500 font-bold">
                        <option value="Lunas" class="text-emerald-400 font-bold">✓ SETUJUI PEMBAYARAN (STATUS: LUNAS)</option>
                        <option value="Ditolak" class="text-rose-400 font-bold">✗ TOLAK PEMBAYARAN (STATUS: DITOLAK)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Catatan / Keterangan Verifikator (Opsional)</label>
                    <input type="text" name="catatan" id="verif-catatan" placeholder="Contoh: Dana telah masuk ke rekening Mandiri sekolah" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-verifikasi-bukti')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Tutup</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/30">
                        Simpan Keputusan Verifikasi
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
    function updateSiswaName() {
        const select = document.getElementById('select-siswa');
        if (select && select.options.length > 0) {
            const selectedOpt = select.options[select.selectedIndex];
            document.getElementById('input-nama-siswa').value = selectedOpt.getAttribute('data-nama') || '';
        }
    }
    updateSiswaName();

    function prosesBayar(id, nama, periode) {
        document.getElementById('bayar-id').value = id;
        document.getElementById('bayar-nama').innerText = nama;
        document.getElementById('bayar-periode').innerText = periode;
        openModal('modal-proses-bayar');
    }

    function bukaVerifikasiModal(data) {
        document.getElementById('verif-id').value = data.id || '';
        document.getElementById('verif-nama').innerText = (data.nama_siswa || '') + ' (NIS: ' + (data.nis || '') + ')';
        document.getElementById('verif-periode').innerText = (data.bulan || '') + ' ' + (data.tahun || '');
        document.getElementById('verif-nominal').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(data.nominal || 0);
        document.getElementById('verif-metode').innerText = data.metode_pembayaran || 'Transfer';
        document.getElementById('verif-waktu').innerText = data.tanggal_bayar || '-';
        document.getElementById('verif-catatan').value = data.catatan || '';

        const imgEl = document.getElementById('verif-bukti-img');
        const pdfEl = document.getElementById('verif-bukti-pdf');
        const pdfLink = document.getElementById('verif-bukti-pdf-link');
        const emptyEl = document.getElementById('verif-bukti-empty');

        imgEl.classList.add('hidden');
        pdfEl.classList.add('hidden');
        emptyEl.classList.add('hidden');

        if (data.bukti_bayar) {
            const ext = data.bukti_bayar.split('.').pop().toLowerCase();
            if (ext === 'pdf') {
                pdfEl.classList.remove('hidden');
                pdfLink.href = data.bukti_bayar;
            } else {
                imgEl.classList.remove('hidden');
                imgEl.src = data.bukti_bayar;
            }
        } else {
            emptyEl.classList.remove('hidden');
        }

        openModal('modal-verifikasi-bukti');
    }

    function cetakKuitansi(id, nama, nis, periode, nominal, metode, tgl) {
        document.getElementById('kuitansi-no').innerText = '#TX-' + id;
        document.getElementById('kuitansi-siswa').innerText = nama + ' (' + nis + ')';
        document.getElementById('kuitansi-periode').innerText = periode;
        document.getElementById('kuitansi-total').innerText = 'Rp ' + nominal;
        document.getElementById('kuitansi-metode').innerText = metode;
        document.getElementById('kuitansi-tgl').innerText = tgl || '-';
        openModal('modal-kuitansi');
    }
</script>
