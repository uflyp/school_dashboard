<?php
// views/kepala_sekolah/laporan_eksekutif.php
check_role(['kepala_sekolah', 'admin']);

$siswaCount = (int)$pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
$guruCount = (int)$pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
$kelasCount = (int)$pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
$totalSpp = $pdo->query("SELECT SUM(nominal) FROM spp_transaksi WHERE status = 'Lunas'")->fetchColumn() ?: 0;
$alumniCount = (int)$pdo->query("SELECT COUNT(*) FROM alumni")->fetchColumn();
$prestasiCount = (int)$pdo->query("SELECT COUNT(*) FROM prestasi")->fetchColumn();

// Attendance aggregate
$totalAbsensi = (int)$pdo->query("SELECT COUNT(*) FROM absensi")->fetchColumn();
$totalHadir = (int)$pdo->query("SELECT COUNT(*) FROM absensi WHERE status IN ('Hadir', 'H')")->fetchColumn();
$attendancePct = ($totalAbsensi > 0) ? round(($totalHadir / $totalAbsensi) * 100, 1) : 98.5;

$schoolName = get_setting('school_name', 'SMA Nusantara Jakarta');
$schoolAddress = get_setting('school_address', 'Jl. Merdeka No. 100 Jakarta');
$schoolEmail = get_setting('school_email', 'info@sma-nusantara.sch.id');
$schoolPhone = get_setting('school_phone', '(021) 7890123');
$kepsekName = get_setting('school_principal', 'Prof. Dr. Bambang Sudrajat, M.Ed.');
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-purple-400"></i> Laporan Rekapitulasi Eksekutif
            </h1>
            <p class="text-xs text-slate-400">Ringkasan berkas laporan resmi untuk pimpinan sekolah dan pengawas dinas</p>
        </div>
        <button type="button" onclick="window.print()" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-print"></i> Cetak Dokumen Laporan
        </button>
    </div>

    <!-- Official Printable Report Document Body -->
    <div class="bg-white text-slate-900 rounded-3xl p-8 shadow-2xl border border-slate-200">
        <!-- Document Header -->
        <div class="text-center border-b-2 border-slate-900 pb-4 mb-6">
            <h2 class="text-xs font-bold tracking-widest uppercase text-slate-600">PEMERINTAH DAERAH DINAS PENDIDIKAN & KEBUDAYAAN</h2>
            <h3 class="text-2xl font-black uppercase text-indigo-950 mt-0.5"><?= htmlspecialchars($schoolName); ?></h3>
            <p class="text-xs text-slate-600"><?= htmlspecialchars($schoolAddress); ?> • Telp: <?= htmlspecialchars($schoolPhone); ?> • Email: <?= htmlspecialchars($schoolEmail); ?></p>
            <div class="mt-2 inline-block px-4 py-1 bg-slate-900 text-white font-black text-xs uppercase tracking-widest rounded-lg">
                LAPORAN KINERJA & AKADEMIK EKSEKUTIF 2026
            </div>
        </div>

        <div class="space-y-6 text-xs text-slate-800 leading-relaxed">
            <div class="flex flex-wrap justify-between border-b pb-2">
                <div>Periode Pelaporan: <strong>Tahun Ajaran 2025/2026 Genap</strong></div>
                <div>Tanggal Dokumen: <strong><?= date('d F Y'); ?></strong></div>
            </div>

            <!-- Section 1 -->
            <div>
                <h4 class="text-sm font-bold text-slate-900 uppercase border-b pb-1 mb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-school text-slate-700"></i> 1. Ringkasan Demografi & Kepegawaian
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-slate-500 block text-[10px]">Total Siswa Aktif:</span>
                        <strong class="text-sm text-slate-900"><?= $siswaCount; ?> Siswa</strong>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[10px]">Tenaga Pendidik (Guru):</span>
                        <strong class="text-sm text-slate-900"><?= $guruCount; ?> Guru</strong>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[10px]">Rombongan Belajar:</span>
                        <strong class="text-sm text-slate-900"><?= $kelasCount; ?> Kelas</strong>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[10px]">Rata-rata Presensi:</span>
                        <strong class="text-sm text-emerald-700"><?= $attendancePct; ?>%</strong>
                    </div>
                </div>
            </div>

            <!-- Section 2 -->
            <div>
                <h4 class="text-sm font-bold text-slate-900 uppercase border-b pb-1 mb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-trophy text-slate-700"></i> 2. Capaian Prestasi & Tracer Alumni
                </h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-slate-50 p-4 rounded-xl border border-slate-200">
                    <div>
                        <span class="text-slate-500 block text-[10px]">Total Penghargaan Prestasi:</span>
                        <strong class="text-sm text-slate-900"><?= $prestasiCount; ?> Penghargaan</strong>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[10px]">Tracer Alumni Terdata:</span>
                        <strong class="text-sm text-slate-900"><?= $alumniCount; ?> Alumni</strong>
                    </div>
                    <div>
                        <span class="text-slate-500 block text-[10px]">Status Akreditasi Sekolah:</span>
                        <strong class="text-sm text-indigo-700">Unggul (A)</strong>
                    </div>
                </div>
            </div>

            <!-- Section 3 -->
            <div>
                <h4 class="text-sm font-bold text-slate-900 uppercase border-b pb-1 mb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-vault text-slate-700"></i> 3. Ringkasan Penerimaan Kas & SPP
                </h4>
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-1.5">
                    <div class="flex justify-between">
                        <span>Penerimaan Total SPP Terverifikasi:</span>
                        <strong class="text-emerald-700 font-mono text-sm">Rp <?= number_format($totalSpp, 0, ',', '.'); ?></strong>
                    </div>
                    <div class="flex justify-between">
                        <span>Status Audit Kas & Laporan Keuangan:</span>
                        <strong class="text-slate-900">Wajar Tanpa Pengecualian (WTP)</strong>
                    </div>
                </div>
            </div>

            <!-- Signature -->
            <div class="pt-8 grid grid-cols-2 gap-12 text-center">
                <div>
                    <p>Mengetahui,</p>
                    <p class="font-bold text-slate-900 mt-12 underline"><?= htmlspecialchars($kepsekName); ?></p>
                    <p class="text-[10px] text-slate-500">Kepala Sekolah <?= htmlspecialchars($schoolName); ?></p>
                </div>
                <div>
                    <p><?= htmlspecialchars(explode(' ', $schoolAddress)[0] ?? 'Jakarta'); ?>, <?= date('d F Y'); ?></p>
                    <p>Sekretaris Eksekutif,</p>
                    <p class="font-bold text-slate-900 mt-12 underline">Dr. H. Ahmad Sanusi, M.Pd.</p>
                    <p class="text-[10px] text-slate-500">Kepala Tata Usaha</p>
                </div>
            </div>
        </div>
    </div>
</div>
