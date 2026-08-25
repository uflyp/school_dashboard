<?php
// views/keuangan/kwitansi.php
check_role(['keuangan', 'admin']);

$sppLunas = $pdo->query("SELECT * FROM spp_transaksi WHERE status = 'Lunas' ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-receipt text-emerald-400"></i> Cetak Kwitansi Resmi Pembayaran SPP
            </h1>
            <p class="text-xs text-slate-400 mt-1">Daftar transaksi SPP terverifikasi LUNAS yang dapat dicetak resmi per transaksi</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-3.5 py-2 rounded-xl">
                Total Lunas: <?= count($sppLunas); ?> Transaksi
            </span>
        </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">No. Kwitansi</th>
                        <th class="p-3.5">NIS & Nama Siswa</th>
                        <th class="p-3.5">Periode Iuran</th>
                        <th class="p-3.5">Tanggal Bayar</th>
                        <th class="p-3.5">Nominal Bayar</th>
                        <th class="p-3.5">Metode Bayar</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi Kwitansi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($sppLunas)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-500 font-semibold">Belum ada data pembayaran SPP berstatus lunas.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($sppLunas as $kw): ?>
                            <tr class="hover:bg-slate-800/40">
                                <td class="p-3.5 font-mono text-emerald-400 font-bold">KWT-2026-<?= str_pad($kw['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td class="p-3.5 font-extrabold text-white"><?= htmlspecialchars($kw['nama_siswa']); ?> <span class="text-xs font-normal text-slate-400">(<?= htmlspecialchars($kw['nis']); ?>)</span></td>
                                <td class="p-3.5 text-slate-300 font-semibold"><?= htmlspecialchars($kw['bulan']); ?> <?= htmlspecialchars($kw['tahun']); ?></td>
                                <td class="p-3.5 text-slate-400 font-mono text-[11px]"><?= $kw['tanggal_bayar'] ? date('d M Y', strtotime($kw['tanggal_bayar'])) : '-'; ?></td>
                                <td class="p-3.5 font-extrabold text-emerald-400">Rp <?= number_format($kw['nominal'], 0, ',', '.'); ?></td>
                                <td class="p-3.5 text-slate-400"><?= htmlspecialchars($kw['metode_pembayaran'] ?: 'Tunai / Kasir'); ?></td>
                                <td class="p-3.5 text-center">
                                    <button type="button" onclick='openSingleReceipt(<?= json_encode($kw); ?>)' class="px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 inline-flex items-center gap-1.5 transition-all">
                                        <i class="fa-solid fa-print"></i> Cetak Kwitansi
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Cetak Kwitansi Tunggal -->
    <div id="modal-receipt-single" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3 no-print">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice text-emerald-400"></i> Pratinjau Kwitansi Pembayaran
                </h3>
                <button type="button" onclick="closeModal('modal-receipt-single')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <!-- Printable Receipt Paper -->
            <div id="receipt-paper" class="bg-white text-slate-900 p-6 sm:p-8 rounded-2xl border-2 border-dashed border-slate-300 font-sans shadow-inner">
                <div class="text-center border-b-2 border-slate-800 pb-4 mb-4">
                    <h2 class="text-lg font-black tracking-wide uppercase text-slate-900"><?= htmlspecialchars(get_setting('school_name', 'SMA NUSANTARA JAKARTA')); ?></h2>
                    <p class="text-[10px] text-slate-600"><?= htmlspecialchars(get_setting('school_address', 'Jl. Pendidikan Karakter No. 45, Jakarta Selatan')); ?></p>
                    <p class="text-[10px] text-slate-600">Telp: <?= htmlspecialchars(get_setting('school_phone', '(021) 7890123')); ?> • Email: <?= htmlspecialchars(get_setting('school_email', 'admin@sekolah.sch.id')); ?></p>
                    <div class="mt-2 inline-block px-3 py-0.5 bg-slate-900 text-white font-extrabold text-xs uppercase tracking-widest rounded">
                        BUKTI PEMBAYARAN RESMI (KWITANSI)
                    </div>
                </div>

                <div class="space-y-2 text-xs">
                    <div class="flex justify-between py-1 border-b border-slate-200">
                        <span class="text-slate-500 font-semibold">Nomor Transaksi:</span>
                        <span id="rcp-no" class="font-mono font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200">
                        <span class="text-slate-500 font-semibold">Tanggal Pembayaran:</span>
                        <span id="rcp-date" class="font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200">
                        <span class="text-slate-500 font-semibold">Nama Siswa:</span>
                        <span id="rcp-name" class="font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200">
                        <span class="text-slate-500 font-semibold">Nomor Induk Siswa (NIS):</span>
                        <span id="rcp-nis" class="font-mono font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200">
                        <span class="text-slate-500 font-semibold">Untuk Pembayaran:</span>
                        <span id="rcp-desc" class="font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between py-1 border-b border-slate-200">
                        <span class="text-slate-500 font-semibold">Metode Pembayaran:</span>
                        <span id="rcp-method" class="font-bold text-slate-900"></span>
                    </div>
                    <div class="flex justify-between py-2 bg-emerald-50 px-3 rounded-lg border border-emerald-200 mt-2">
                        <span class="text-emerald-800 font-extrabold text-sm">TOTAL NOMINAL:</span>
                        <span id="rcp-amount" class="text-emerald-800 font-black text-base font-mono"></span>
                    </div>
                    <div class="flex justify-between py-1 text-[11px] text-emerald-600 font-bold">
                        <span>Status Verifikasi:</span>
                        <span>LUNAS (TERVALIDASI)</span>
                    </div>
                </div>

                <div class="mt-8 pt-4 border-t border-slate-200 flex justify-between items-end text-[11px]">
                    <div>
                        <p class="text-slate-400 italic text-[9px]">*Simpan bukti pembayaran ini sebagai tanda lunas yang sah.</p>
                        <p class="text-slate-400 font-mono text-[9px]">Dicetak otomatis oleh Sistem WebSekolah V2.0</p>
                    </div>
                    <div class="text-center">
                        <p class="text-slate-500">Bendahara / Kasir Sekolah,</p>
                        <div class="h-10"></div>
                        <p class="font-bold text-slate-900 underline"><?= htmlspecialchars(current_user()['name']); ?></p>
                        <p class="text-[9px] text-slate-500">Bagian Administrasi Keuangan</p>
                    </div>
                </div>
            </div>

            <div class="mt-5 pt-3 border-t border-slate-800 flex justify-end gap-3 no-print">
                <button type="button" onclick="closeModal('modal-receipt-single')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Tutup</button>
                <button type="button" onclick="printReceiptPaper()" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 flex items-center gap-1.5 transition-all">
                    <i class="fa-solid fa-print"></i> Cetak Kwitansi Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function openSingleReceipt(data) {
    document.getElementById('rcp-no').innerText = 'KWT-2026-' + String(data.id).padStart(4, '0');
    document.getElementById('rcp-date').innerText = data.tanggal_bayar || '<?= date('d F Y'); ?>';
    document.getElementById('rcp-name').innerText = data.nama_siswa || '-';
    document.getElementById('rcp-nis').innerText = data.nis || '-';
    document.getElementById('rcp-desc').innerText = 'Iuran SPP Periode ' + (data.bulan || '-') + ' ' + (data.tahun || '2026');
    document.getElementById('rcp-method').innerText = data.metode_pembayaran || 'Kasir Tunai';
    document.getElementById('rcp-amount').innerText = 'Rp ' + Number(data.nominal || 0).toLocaleString('id-ID');
    openModal('modal-receipt-single');
}

function printReceiptPaper() {
    const paper = document.getElementById('receipt-paper').innerHTML;
    const printWin = window.open('', '', 'width=700,height=600');
    printWin.document.write(`
        <html>
            <head>
                <title>Cetak Kwitansi Pembayaran SPP</title>
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <script src="https://cdn.tailwindcss.com"><\/script>
                <style>
                    body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #fff; color: #000; padding: 20px; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                <div style="max-width: 550px; margin: 0 auto;">
                    ${paper}
                </div>
                <script>
                    window.onload = function() { window.print(); window.close(); }
                <\/script>
            </body>
        </html>
    `);
    printWin.document.close();
}
</script>
