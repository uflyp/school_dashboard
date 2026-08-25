<?php
// views/guru/pengumuman.php
check_role(['guru', 'admin']);

$pengumumanGuru = $pdo->query("SELECT * FROM pengumuman ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <h1 class="text-xl font-extrabold text-white flex items-center gap-2">
            <i class="fa-solid fa-bullhorn text-indigo-400"></i> Pengumuman Guru & Pengajar
        </h1>
        <p class="text-xs text-slate-400 mt-1">Informasi penting edaran dinas dan manajemen sekolah untuk para pendidik</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($pengumumanGuru as $p): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 relative overflow-hidden shadow-xl">
                <div class="flex items-center justify-between mb-3">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                        <?= htmlspecialchars($p['kategori']); ?>
                    </span>
                    <span class="text-xs text-slate-500 font-mono"><i class="fa-regular fa-calendar mr-1"></i><?= date('d M Y', strtotime($p['tanggal'])); ?></span>
                </div>
                <h3 class="text-base font-bold text-white mb-2"><?= htmlspecialchars($p['judul']); ?></h3>
                <p class="text-xs text-slate-400 leading-relaxed"><?= htmlspecialchars($p['isi']); ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
