<?php
// views/admin/guru.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'add_guru') {
        $nip = trim($_POST['nip']);
        $nama = trim($_POST['nama']);
        $mapel = trim($_POST['mata_pelajaran']);
        $email = trim($_POST['email']);
        $hp = trim($_POST['no_hp']);
        $jk = trim($_POST['jenis_kelamin'] ?? 'L');

        try {
            $stmt = $pdo->prepare("INSERT INTO guru (nip, nama, mata_pelajaran, email, no_hp, jenis_kelamin) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nip, $nama, $mapel, $email, $hp, $jk]);
            $msg = "Data Guru $nama berhasil ditambahkan!";
            log_activity("Admin added teacher: $nama");
        } catch (PDOException $e) {
            $msg = "Gagal menambah guru: NIP atau email sudah ada!";
        }
    } elseif ($_POST['action'] === 'edit_guru') {
        $id = intval($_POST['id']);
        $nip = trim($_POST['nip']);
        $nama = trim($_POST['nama']);
        $mapel = trim($_POST['mata_pelajaran']);
        $email = trim($_POST['email']);
        $hp = trim($_POST['no_hp']);
        $jk = trim($_POST['jenis_kelamin']);

        try {
            $stmt = $pdo->prepare("UPDATE guru SET nip = ?, nama = ?, mata_pelajaran = ?, email = ?, no_hp = ?, jenis_kelamin = ? WHERE id = ?");
            $stmt->execute([$nip, $nama, $mapel, $email, $hp, $jk, $id]);

            // Sync user table so session name & sapaan stay 100% synced upon login
            if ($email) {
                $stmtUser = $pdo->prepare("UPDATE users SET name = ?, jenis_kelamin = ? WHERE email = ?");
                $stmtUser->execute([$nama, $jk, $email]);
            }

            $msg = "Data Guru $nama berhasil diperbarui!";
            log_activity("Admin updated teacher: $nama");
        } catch (PDOException $e) {
            $msg = "Gagal mengedit guru: NIP atau email sudah terdaftar!";
        }
    } elseif ($_POST['action'] === 'delete_guru') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM guru WHERE id = ?");
        $stmt->execute([$id]);
        $msg = "Data guru berhasil dihapus!";
        log_activity("Admin deleted teacher ID: $id");
    }
}

$guruList = $pdo->query("
    SELECT g.*, u.avatar as user_avatar 
    FROM guru g 
    LEFT JOIN users u ON (g.email IS NOT NULL AND g.email != '' AND u.email = g.email) 
    ORDER BY g.id DESC
")->fetchAll();
$allMapel = $pdo->query("SELECT * FROM mapel ORDER BY nama_mapel ASC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-chalkboard-user text-indigo-400"></i> Kelola Data Tenaga Pendidik (Guru)
            </h1>
            <p class="text-xs text-slate-400 mt-1">Daftar guru pengajar, mata pelajaran terintegrasi, dan pengeditan nama & sapaan (Bapak/Ibu)</p>
        </div>
        <button type="button" onclick="openModal('modal-tambah-guru')" class="px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold flex items-center gap-2 shadow-lg shadow-indigo-600/30 transition-all">
            <i class="fa-solid fa-user-plus"></i> Tambah Guru Baru
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($guruList)): ?>
        <div class="p-8 rounded-3xl bg-slate-900 border border-slate-800 text-center text-slate-400 text-xs">
            Belum ada data guru terdaftar. Klik "+ Tambah Guru Baru" untuk menambahkan.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($guruList as $g): ?>
                <?php 
                $gAvatar = !empty($g['user_avatar']) ? $g['user_avatar'] : (!empty($g['avatar']) ? $g['avatar'] : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150');
                ?>
                <div class="p-6 rounded-3xl bg-slate-900 border border-slate-800 flex flex-col justify-between group hover:border-indigo-500/40 transition-all shadow-xl">
                    <div>
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex items-center gap-3">
                                <img src="<?= htmlspecialchars($gAvatar); ?>" class="w-11 h-11 rounded-2xl object-cover border border-indigo-500/30 shadow-md shrink-0 bg-slate-950">
                                <div>
                                    <h3 class="text-sm font-extrabold text-white group-hover:text-indigo-400 transition-colors"><?= htmlspecialchars($g['nama']); ?></h3>
                                    <span class="text-[10px] font-mono text-slate-400 block">NIP: <?= htmlspecialchars($g['nip']); ?></span>
                                </div>
                            </div>
                            <span class="px-2.5 py-1 rounded-xl text-[10px] font-extrabold <?= ($g['jenis_kelamin'] ?? 'L') === 'P' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20'; ?>">
                                <?= ($g['jenis_kelamin'] ?? 'L') === 'P' ? 'Ibu Guru' : 'Bapak Guru'; ?>
                            </span>
                        </div>

                        <div class="space-y-2 text-xs text-slate-300 mb-6 bg-slate-950 p-4 rounded-2xl border border-slate-800/80">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Mata Pelajaran:</span>
                                <span class="font-bold text-indigo-400"><?= htmlspecialchars($g['mata_pelajaran']); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Email:</span>
                                <span class="font-mono text-slate-400 text-[11px]"><?= htmlspecialchars($g['email'] ?: '-'); ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">No. WhatsApp:</span>
                                <span class="font-mono text-slate-400 text-[11px]"><?= htmlspecialchars($g['no_hp'] ?: '-'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
                        <button type="button" onclick='openEditGuru(<?= json_encode($g); ?>)' class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold flex items-center gap-1.5 transition-all">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </button>
                        <form action="dashboard.php?page=guru" method="POST" onsubmit="return confirm('Hapus data guru <?= htmlspecialchars($g['nama']); ?>?');">
                            <?= csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_guru">
                            <input type="hidden" name="id" value="<?= $g['id']; ?>">
                            <button type="submit" class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-600 hover:text-white flex items-center justify-center transition-all">
                                <i class="fa-solid fa-trash text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal Tambah Guru -->
    <div id="modal-tambah-guru" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-4">
                <h3 class="text-lg font-bold text-white">Tambah Data Guru Baru</h3>
                <button type="button" onclick="closeModal('modal-tambah-guru')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=guru" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_guru">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">NIP Guru</label>
                    <input type="text" name="nip" required placeholder="198501152010011002" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap & Gelar</label>
                    <input type="text" name="nama" required placeholder="Drs. Bambang Hermawan, M.Si" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Kelamin / Sapaan</label>
                        <select name="jenis_kelamin" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="L">Laki-laki (Bapak)</option>
                            <option value="P">Perempuan (Ibu)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Mata Pelajaran Utama</label>
                        <select name="mata_pelajaran" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-indigo-500">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach ($allMapel as $mp): ?>
                                <option value="<?= htmlspecialchars($mp['nama_mapel']); ?>"><?= htmlspecialchars($mp['nama_mapel']); ?> (<?= htmlspecialchars($mp['kode']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Email Log In</label>
                    <input type="email" name="email" placeholder="guru@sekolah.sch.id" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">No. HP / WhatsApp</label>
                    <input type="text" name="no_hp" placeholder="08123456789" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-tambah-guru')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Guru</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Data Guru -->
    <div id="modal-edit-guru" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative">
            <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-4">
                <h3 class="text-lg font-bold text-white">Edit Data & Nama Guru</h3>
                <button type="button" onclick="closeModal('modal-edit-guru')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=guru" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_guru">
                <input type="hidden" name="id" id="edit_guru_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">NIP Guru</label>
                    <input type="text" name="nip" id="edit_guru_nip" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap & Gelar (Dipakai pada Sesi Dashboard)</label>
                    <input type="text" name="nama" id="edit_guru_nama" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Kelamin (Sapaan)</label>
                        <select name="jenis_kelamin" id="edit_guru_jk" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <option value="L">Laki-laki (Bapak)</option>
                            <option value="P">Perempuan (Ibu)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Mata Pelajaran Utama</label>
                        <select name="mata_pelajaran" id="edit_guru_mapel" required class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-indigo-500">
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach ($allMapel as $mp): ?>
                                <option value="<?= htmlspecialchars($mp['nama_mapel']); ?>"><?= htmlspecialchars($mp['nama_mapel']); ?> (<?= htmlspecialchars($mp['kode']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Email Login Guru</label>
                    <input type="email" name="email" id="edit_guru_email" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">No. HP / WhatsApp</label>
                    <input type="text" name="no_hp" id="edit_guru_hp" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>
                <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-guru')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditGuru(data) {
        document.getElementById('edit_guru_id').value = data.id || '';
        document.getElementById('edit_guru_nip').value = data.nip || '';
        document.getElementById('edit_guru_nama').value = data.nama || '';
        document.getElementById('edit_guru_mapel').value = data.mata_pelajaran || '';
        document.getElementById('edit_guru_email').value = data.email || '';
        document.getElementById('edit_guru_hp').value = data.no_hp || '';
        document.getElementById('edit_guru_jk').value = data.jenis_kelamin || 'L';
        openModal('modal-edit-guru');
    }
</script>
