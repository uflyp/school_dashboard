<?php
// views/kepala_sekolah/agenda.php
check_role(['kepala_sekolah', 'admin']);

$events = $pdo->query("SELECT * FROM events ORDER BY event_date ASC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-calendar-day text-purple-400"></i> Kalender Agenda Sekolah
            </h1>
            <p class="text-xs text-slate-400 mt-1">Jadwal agenda pimpinan, rapat dinas, dan upacara kebangsaan</p>
        </div>
        <button type="button" onclick="window.print()" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-print"></i> Cetak Agenda
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($events as $e): ?>
            <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 flex items-start gap-4 shadow-xl">
                <div class="w-14 h-14 rounded-2xl bg-purple-600 text-white flex flex-col items-center justify-center shrink-0 font-bold shadow-lg shadow-purple-600/30">
                    <span class="text-lg leading-none"><?= date('d', strtotime($e['event_date'])); ?></span>
                    <span class="text-[9px] uppercase"><?= date('M', strtotime($e['event_date'])); ?></span>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold text-white mb-1"><?= htmlspecialchars($e['title']); ?></h3>
                    <p class="text-xs text-slate-400 leading-relaxed"><?= htmlspecialchars($e['description']); ?></p>
                    <span class="inline-block mt-2 text-[10px] text-purple-400 font-mono"><i class="fa-regular fa-clock mr-1"></i>Waktu: <?= date('H:i', strtotime($e['event_date'])); ?> WIB</span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
