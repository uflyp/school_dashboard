<?php
// views/admin/cms.php - CMS Manager Website Sekolah
check_role(['admin']);

$msg = '';
$msgType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    $action = $_POST['action'];

    if ($action === 'save_running_text') {
        $content = trim($_POST['content'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $pdo->exec("UPDATE running_text SET is_active = 0;");
        $stmt = $pdo->prepare("INSERT INTO running_text (content, is_active) VALUES (?, ?)");
        $stmt->execute([$content, $is_active]);
        $msg = "Running Announcement Text berhasil disimpan dan diperbarui!";
        log_activity("Admin updated Running Text CMS: " . substr($content, 0, 40));
    } elseif ($action === 'save_event_countdown') {
        $title = trim($_POST['title'] ?? '');
        $date = trim($_POST['event_date'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if (empty($title) || empty($date)) {
            $msg = "Judul event dan tanggal kedaluwarsa wajib diisi!";
            $msgType = 'error';
        } else {
            // Nonaktifkan countdown lama
            $pdo->exec("UPDATE events SET is_countdown = 0;");

            // Format tanggal standar
            $formattedDate = date('Y-m-d H:i:s', strtotime($date));

            // Simpan event countdown baru yang aktif
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, is_countdown, is_popup, is_active) VALUES (?, ?, ?, 1, 0, ?)");
            $stmt->execute([$title, $desc, $formattedDate, $is_active]);
            $msg = "Countdown Event Timer berhasil diperbarui dan disimpan!";
            log_activity("Admin updated Countdown Event Timer: $title");
        }
    } elseif ($action === 'toggle_countdown_status') {
        $status = intval($_POST['status'] ?? 0);
        $pdo->prepare("UPDATE events SET is_active = ? WHERE is_countdown = 1")->execute([$status]);
        $msg = $status ? "Countdown Event berhasil diaktifkan di Beranda!" : "Countdown Event berhasil dinonaktifkan dari Beranda!";
        log_activity("Admin changed Countdown Event status to: " . ($status ? 'Active' : 'Inactive'));
    } elseif ($action === 'add_berita') {
        $title = trim($_POST['title'] ?? '');
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title))) . '-' . time();
        $content = trim($_POST['content'] ?? '');
        $thumb = trim($_POST['thumbnail'] ?? '');
        $author = $user['name'] ?? 'Admin';

        if (!empty($title) && !empty($content)) {
            $stmt = $pdo->prepare("INSERT INTO berita (title, slug, content, thumbnail, author, created_at) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $slug, $content, $thumb, $author, date('Y-m-d')]);
            $msg = "Berita baru berhasil dipublikasikan di Homepage!";
            log_activity("Admin published news: $title");
        } else {
            $msg = "Judul dan konten berita wajib diisi!";
            $msgType = 'error';
        }
    } elseif ($action === 'edit_berita') {
        $id = intval($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $thumb = trim($_POST['thumbnail'] ?? '');

        if ($id > 0 && !empty($title) && !empty($content)) {
            $stmt = $pdo->prepare("UPDATE berita SET title = ?, content = ?, thumbnail = ? WHERE id = ?");
            $stmt->execute([$title, $content, $thumb, $id]);
            $msg = "Berita berhasil diperbarui!";
            log_activity("Admin updated news ID: $id ($title)");
        } else {
            $msg = "Gagal memperbarui berita: data tidak lengkap!";
            $msgType = 'error';
        }
    } elseif ($action === 'delete_berita') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM berita WHERE id = ?");
            $stmt->execute([$id]);
            $msg = "Berita berhasil dihapus!";
            log_activity("Admin deleted news ID: $id");
        }
    }
}

// Fetch Data Terkini
$currentRt = $pdo->query("SELECT * FROM running_text ORDER BY id DESC LIMIT 1")->fetch();
$currentCd = $pdo->query("SELECT * FROM events WHERE is_countdown = 1 ORDER BY id DESC LIMIT 1")->fetch();
$beritaList = $pdo->query("SELECT * FROM berita ORDER BY id DESC")->fetchAll();

// Format datetime-local untuk input form
$currentCdDate = '';
if (!empty($currentCd['event_date'])) {
    $currentCdDate = date('Y-m-d\TH:i', strtotime($currentCd['event_date']));
} else {
    $currentCdDate = date('Y-m-d\TH:i', strtotime('+7 days 23:59'));
}

// Hitung status sisa waktu countdown
$cdRemainingText = '';
$isCdExpired = false;
if (!empty($currentCd['event_date'])) {
    $cdTimestamp = strtotime($currentCd['event_date']);
    $diffSec = $cdTimestamp - time();
    if ($diffSec > 0) {
        $days = floor($diffSec / 86400);
        $hours = floor(($diffSec % 86400) / 3600);
        $mins = floor(($diffSec % 3600) / 60);
        $cdRemainingText = "Sisa Waktu: {$days} Hari {$hours} Jam {$mins} Menit";
    } else {
        $isCdExpired = true;
        $cdRemainingText = "Waktu Countdown Telah Berakhir";
    }
}
?>
<div class="space-y-8 animate-fade-in">
    <!-- Header Page -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
        <div>
            <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-sliders text-indigo-400"></i>
                <span>CMS Website & Homepage Manager</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">Atur dan perbarui konten Beranda, Running Text Pengumuman, Countdown Event, serta Berita Sekolah secara langsung.</p>
        </div>
        <a href="index.php" target="_blank" class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white rounded-xl text-xs font-bold transition-all border border-slate-700 shadow-sm shrink-0">
            <i class="fa-solid fa-arrow-up-right-from-square text-indigo-400"></i>
            <span>Lihat Homepage</span>
        </a>
    </div>

    <!-- Flash Alert Message -->
    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-300' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300'; ?> border text-xs font-bold flex items-center justify-between shadow-lg">
            <div class="flex items-center gap-2.5">
                <i class="fa-solid <?= $msgType === 'error' ? 'fa-triangle-exclamation text-rose-400' : 'fa-circle-check text-emerald-400'; ?> text-base"></i>
                <span><?= htmlspecialchars($msg); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <!-- 📢 1. Kelola Running Text Announcement -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl flex flex-col justify-between relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none"></div>
            <div>
                <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-400 flex items-center justify-center text-base border border-indigo-500/20">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Running Announcement Text</h3>
                            <p class="text-[11px] text-slate-400">Teks berjalan di bawah navbar homepage</p>
                        </div>
                    </div>
                    <?php if (!empty($currentRt['is_active'])): ?>
                        <span class="px-2.5 py-1 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-full text-[10px] font-extrabold uppercase tracking-wider flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Aktif
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-1 bg-slate-800 text-slate-400 border border-slate-700 rounded-full text-[10px] font-bold">
                            Nonaktif
                        </span>
                    <?php endif; ?>
                </div>

                <form action="dashboard.php?page=cms" method="POST" class="space-y-4">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="save_running_text">
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Teks Pengumuman Berjalan</label>
                        <textarea name="content" rows="4" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder:text-slate-600 focus:ring-2 focus:ring-indigo-500 focus:outline-none transition-all leading-relaxed"><?= htmlspecialchars($currentRt['content'] ?? ''); ?></textarea>
                    </div>

                    <div class="flex items-center gap-2 bg-slate-950/60 p-3 rounded-xl border border-slate-800/80">
                        <input type="checkbox" id="rt_is_active" name="is_active" value="1" <?= (!empty($currentRt['is_active']) || empty($currentRt)) ? 'checked' : ''; ?> class="w-4 h-4 text-indigo-600 rounded bg-slate-900 border-slate-700 focus:ring-indigo-500 cursor-pointer">
                        <label for="rt_is_active" class="text-xs text-slate-300 font-medium cursor-pointer">Tampilkan teks berjalan di Homepage</label>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Simpan Running Text</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ⏳ 2. Kelola Countdown Event Widget (FULL EDITABLE) -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl flex flex-col justify-between relative overflow-hidden">
            <div class="absolute top-0 right-0 w-32 h-32 bg-amber-500/5 rounded-full blur-3xl pointer-events-none"></div>
            <div>
                <div class="flex items-center justify-between mb-4 border-b border-slate-800/80 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-base border border-amber-500/20">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-white">Countdown Event Timer</h3>
                            <p class="text-[11px] text-slate-400">Widget hitung mundur event di banner utama</p>
                        </div>
                    </div>
                    <?php if (!empty($currentCd['is_active'])): ?>
                        <span class="px-2.5 py-1 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-full text-[10px] font-extrabold uppercase tracking-wider flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping"></span> Live di Beranda
                        </span>
                    <?php else: ?>
                        <span class="px-2.5 py-1 bg-slate-800 text-slate-400 border border-slate-700 rounded-full text-[10px] font-bold">
                            Nonaktif
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Live Status Info Box -->
                <div class="mb-4 p-3 rounded-2xl bg-slate-950/80 border border-slate-800 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2 text-slate-300">
                        <i class="fa-regular fa-clock <?= $isCdExpired ? 'text-rose-400' : 'text-amber-400'; ?>"></i>
                        <span class="font-semibold"><?= htmlspecialchars($cdRemainingText); ?></span>
                    </div>
                    <span class="text-[10px] font-mono text-slate-400">
                        <?= !empty($currentCd['event_date']) ? date('d/m/Y H:i', strtotime($currentCd['event_date'])) : '-'; ?> WIB
                    </span>
                </div>

                <form action="dashboard.php?page=cms" method="POST" class="space-y-4">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="save_event_countdown">
                    
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Judul Event Countdown <span class="text-rose-400">*</span></label>
                        <input type="text" name="title" required value="<?= htmlspecialchars($currentCd['title'] ?? 'Pendaftaran PPDB Gelombang I Dibuka'); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all placeholder:text-slate-600">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Batas Waktu Expiration (Tanggal & Jam) <span class="text-rose-400">*</span></label>
                        <input type="datetime-local" name="event_date" required value="<?= htmlspecialchars($currentCdDate); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all font-mono">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">Keterangan Singkat</label>
                        <input type="text" name="description" value="<?= htmlspecialchars($currentCd['description'] ?? 'Batas akhir penyerahan berkas dan verifikasi data calon siswa baru.'); ?>" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-amber-500 focus:outline-none transition-all placeholder:text-slate-600">
                    </div>

                    <div class="flex items-center justify-between gap-2 bg-slate-950/60 p-3 rounded-xl border border-slate-800/80">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="cd_is_active" name="is_active" value="1" <?= (!empty($currentCd['is_active']) || empty($currentCd)) ? 'checked' : ''; ?> class="w-4 h-4 text-amber-600 rounded bg-slate-900 border-slate-700 focus:ring-amber-500 cursor-pointer">
                            <label for="cd_is_active" class="text-xs text-slate-300 font-medium cursor-pointer">Aktifkan & Tampilkan Widget di Homepage</label>
                        </div>
                    </div>

                    <div class="pt-2 flex flex-wrap gap-2.5">
                        <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-amber-600/30 transition-all flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>Simpan & Terapkan Countdown</span>
                        </button>

                        <?php if (!empty($currentCd['is_active'])): ?>
                            <form action="dashboard.php?page=cms" method="POST" class="inline">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="toggle_countdown_status">
                                <input type="hidden" name="status" value="0">
                                <button type="submit" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white font-bold text-xs rounded-xl transition-all border border-slate-700">
                                    Sembunyikan dari Beranda
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <!-- 📰 3. Kelola Berita Sekolah (CRUD Lengkap) -->
    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-7 shadow-xl space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800/80 pb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-purple-500/10 text-purple-400 flex items-center justify-center text-lg border border-purple-500/20">
                    <i class="fa-solid fa-newspaper"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Manajemen Berita & Publikasi Sekolah</h3>
                    <p class="text-xs text-slate-400">Publikasi, edit, dan kelola artikel kabar berita yang muncul pada landing page.</p>
                </div>
            </div>
            <button type="button" onclick="openModal('modal-berita')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 transition-all flex items-center gap-1.5 shrink-0">
                <i class="fa-solid fa-plus"></i>
                <span>Tulis Berita Baru</span>
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">Thumbnail</th>
                        <th class="p-3.5">Judul Berita</th>
                        <th class="p-3.5">Ringkasan Konten</th>
                        <th class="p-3.5">Penulis</th>
                        <th class="p-3.5">Tanggal</th>
                        <th class="p-3.5 rounded-r-xl text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php if (empty($beritaList)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                <i class="fa-solid fa-newspaper text-2xl mb-2 block text-slate-600"></i>
                                Belum ada artikel berita yang dipublikasikan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($beritaList as $b): ?>
                            <tr class="hover:bg-slate-800/40 transition-colors">
                                <td class="p-3.5">
                                    <img src="<?= htmlspecialchars($b['thumbnail'] ?: 'https://images.unsplash.com/photo-1561557944-6e7860d1a7eb?w=600&q=80'); ?>" alt="thumb" class="w-14 h-10 object-cover rounded-xl border border-slate-800 shrink-0">
                                </td>
                                <td class="p-3.5 font-bold text-white max-w-xs">
                                    <?= htmlspecialchars($b['title']); ?>
                                </td>
                                <td class="p-3.5 text-slate-400 max-w-sm line-clamp-2 leading-relaxed">
                                    <?= htmlspecialchars($b['content']); ?>
                                </td>
                                <td class="p-3.5 text-slate-400 whitespace-nowrap">
                                    <i class="fa-regular fa-user mr-1 text-slate-500"></i><?= htmlspecialchars($b['author'] ?: 'Admin'); ?>
                                </td>
                                <td class="p-3.5 font-mono text-slate-400 whitespace-nowrap">
                                    <?= date('d M Y', strtotime($b['created_at'])); ?>
                                </td>
                                <td class="p-3.5 whitespace-nowrap text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <button type="button" onclick='openEditBerita(<?= json_encode($b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)' class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-indigo-400 hover:text-indigo-300 rounded-lg text-xs font-bold transition-all border border-slate-700">
                                            <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                                        </button>
                                        <form action="dashboard.php?page=cms" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus berita ini?')" class="inline">
                                            <?= csrf_field(); ?>
                                            <input type="hidden" name="action" value="delete_berita">
                                            <input type="hidden" name="id" value="<?= $b['id']; ?>">
                                            <button type="submit" class="px-2.5 py-1.5 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 rounded-lg text-xs font-bold transition-all border border-rose-500/20">
                                                <i class="fa-solid fa-trash"></i>
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

    <!-- Modal Tulis Berita Baru -->
    <div id="modal-berita" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-newspaper text-indigo-400"></i> Tulis Berita Baru
                </h3>
                <button type="button" onclick="closeModal('modal-berita')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=cms" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_berita">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Artikel Berita <span class="text-rose-400">*</span></label>
                    <input type="text" name="title" required placeholder="Contoh: Prestasi Juara 1 Olimpiade Sains Nasional" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">URL Gambar Thumbnail</label>
                    <input type="text" name="thumbnail" value="https://images.unsplash.com/photo-1561557944-6e7860d1a7eb?w=600&q=80" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Isi Konten Berita <span class="text-rose-400">*</span></label>
                    <textarea name="content" rows="5" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none leading-relaxed" placeholder="Tuliskan berita lengkap..."></textarea>
                </div>
                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-berita')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30">Publikasikan Berita</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Berita -->
    <div id="modal-edit-berita" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-indigo-400"></i> Edit Berita
                </h3>
                <button type="button" onclick="closeModal('modal-edit-berita')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form action="dashboard.php?page=cms" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_berita">
                <input type="hidden" name="id" id="edit_berita_id">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul Artikel Berita <span class="text-rose-400">*</span></label>
                    <input type="text" name="title" id="edit_berita_title" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">URL Gambar Thumbnail</label>
                    <input type="text" name="thumbnail" id="edit_berita_thumb" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Isi Konten Berita <span class="text-rose-400">*</span></label>
                    <textarea name="content" id="edit_berita_content" rows="5" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:ring-2 focus:ring-indigo-500 focus:outline-none leading-relaxed"></textarea>
                </div>
                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-berita')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditBerita(data) {
    if (!data) return;
    document.getElementById('edit_berita_id').value = data.id || '';
    document.getElementById('edit_berita_title').value = data.title || '';
    document.getElementById('edit_berita_thumb').value = data.thumbnail || '';
    document.getElementById('edit_berita_content').value = data.content || '';
    openModal('modal-edit-berita');
}
</script>

