<?php
// views/admin/users.php
check_role(['admin']);

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();
    if ($_POST['action'] === 'add_user') {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $name = trim($_POST['name']);
        $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
        $role_id = intval($_POST['role_id']);
        $jk = trim($_POST['jenis_kelamin'] ?? 'L');
        $avatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150';

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, name, role_id, jenis_kelamin, avatar, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $email, $password, $name, $role_id, $jk, $avatar]);
            $msg = "Pengguna baru $name ($username) berhasil ditambahkan!";
            log_activity("Admin created new user: $username");
        } catch (Exception $e) {
            $msg = "Gagal menambah user: Username/Email sudah terdaftar!";
        }
    } elseif ($_POST['action'] === 'edit_user') {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $role_id = intval($_POST['role_id']);
        $jk = trim($_POST['jenis_kelamin']);
        $new_password = trim($_POST['new_password'] ?? '');
        $nowStr = date('Y-m-d H:i:s');

        try {
            if (!empty($new_password)) {
                $passHash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role_id = ?, jenis_kelamin = ?, password = ?, last_activity = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $role_id, $jk, $passHash, $nowStr, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role_id = ?, jenis_kelamin = ?, last_activity = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $role_id, $jk, $nowStr, $id]);
            }

            // Also update teacher or student record if associated
            $pdo->prepare("UPDATE guru SET nama = ?, jenis_kelamin = ? WHERE email = ?")->execute([$name, $jk, $email]);

            $msg = "Data pengguna $name ($username) berhasil diperbarui pada " . date('H:i:s') . " WIB!";
            log_activity("Admin edited user: $username");
        } catch (Exception $e) {
            $msg = "Gagal mengedit user: Username atau Email sudah terdaftar!";
        }
    } elseif ($_POST['action'] === 'delete_user') {
        $id = intval($_POST['id']);
        if ($id === intval($_SESSION['user_id'])) {
            $msg = "Gagal menghapus: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $msg = "Akun pengguna berhasil dihapus dari sistem!";
            log_activity("Admin deleted user account ID: $id");
        }
    }
}

// SQL Query joining roles table
$userList = $pdo->query("
    SELECT u.*, r.name as role, r.display_name as role_display 
    FROM users u 
    JOIN roles r ON u.role_id = r.id 
    ORDER BY u.id ASC
")->fetchAll();

$rolesList = $pdo->query("SELECT * FROM roles ORDER BY id ASC")->fetchAll();

$role_badge = [
    'admin' => ['label' => 'ADMINISTRATOR', 'color' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20'],
    'kepala_sekolah' => ['label' => 'KEPALA SEKOLAH', 'color' => 'bg-purple-500/10 text-purple-400 border-purple-500/20'],
    'guru' => ['label' => 'GURU / PENGAJAR', 'color' => 'bg-blue-500/10 text-blue-400 border-blue-500/20'],
    'keuangan' => ['label' => 'STAF KEUANGAN', 'color' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20'],
    'siswa' => ['label' => 'SISWA', 'color' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20'],
    'orangtua' => ['label' => 'ORANG TUA / WALI', 'color' => 'bg-amber-500/10 text-amber-400 border-amber-500/20']
];
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-users-gear text-indigo-400"></i> Manajemen User & Hak Akses Role
            </h1>
            <p class="text-xs text-slate-400 mt-1">Pengelolaan nama akun pengguna, gender sapaan, kredensial password, tambah & hapus akun</p>
        </div>
        <button type="button" onclick="openModal('modal-add-user')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-user-plus"></i> Tambah User Baru
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-300 text-xs font-semibold">
            <?= htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="p-3.5 rounded-l-xl">ID</th>
                        <th class="p-3.5">Nama Pengguna</th>
                        <th class="p-3.5">Username / Email</th>
                        <th class="p-3.5">Gender / Sapaan</th>
                        <th class="p-3.5">Peran / Role</th>
                        <th class="p-3.5">Status Aktivitas</th>
                        <th class="p-3.5 rounded-r-xl text-center">Tindakan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    <?php foreach ($userList as $u): ?>
                        <?php 
                        $rKey = $u['role'] ?? 'siswa'; 
                        $lTime = !empty($u['last_activity']) ? strtotime($u['last_activity']) : (!empty($u['last_login']) ? strtotime($u['last_login']) : 0);
                        $isUserOnline = ($lTime > 0) && ((time() - $lTime) <= 300);
                        ?>
                        <tr class="hover:bg-slate-800/40">
                            <td class="p-3.5 text-slate-500 font-mono">#<?= $u['id']; ?></td>
                            <td class="p-3.5 font-bold text-white flex items-center gap-3">
                                <img src="<?= htmlspecialchars($u['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150'); ?>" class="w-9 h-9 rounded-xl object-cover border border-slate-700">
                                <div>
                                    <div class="font-extrabold text-white"><?= htmlspecialchars($u['name']); ?></div>
                                    <div class="text-[10px] text-slate-500">Last Login: <?= $u['last_login'] ? format_datetime($u['last_login']) : 'Belum Pernah'; ?></div>
                                </div>
                            </td>
                            <td class="p-3.5">
                                <div class="font-mono text-indigo-400 font-bold"><?= htmlspecialchars($u['username']); ?></div>
                                <div class="text-[11px] text-slate-400"><?= htmlspecialchars($u['email']); ?></div>
                            </td>
                            <td class="p-3.5">
                                <?php 
                                $isSiswa = ($rKey === 'siswa');
                                $jkRaw = $u['jenis_kelamin'] ?? 'L';
                                // Siswa: stored as 'Laki-laki'/'Perempuan', Guru/others: stored as 'L'/'P'
                                if ($isSiswa) {
                                    $isLaki = (strtolower($jkRaw) === 'laki-laki');
                                    $labelJk = $isLaki ? 'LAKI-LAKI' : 'PEREMPUAN';
                                } else {
                                    $isLaki = ($jkRaw === 'L');
                                    $labelJk = $isLaki ? 'Bapak' : 'Ibu';
                                }
                                ?>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold <?= $isLaki ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'; ?>">
                                    <?= $labelJk; ?>
                                </span>
                            </td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold border <?= $role_badge[$rKey]['color'] ?? 'bg-slate-800 text-slate-300'; ?>">
                                    <?= htmlspecialchars($u['role_display'] ?? strtoupper($rKey)); ?>
                                </span>
                            </td>
                            <td class="p-3.5">
                                <?php if ($isUserOnline): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 inline-flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> Online
                                    </span>
                                <?php else: ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-800/80 text-slate-400 border border-slate-700/60 inline-flex items-center gap-1.5">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span> Offline
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-3.5 text-center flex items-center justify-center gap-1.5">
                                <button type="button" onclick='openEditUser(<?= json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG); ?>)' class="px-2.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] shadow-sm transition-all" title="Edit User">
                                    <i class="fa-solid fa-user-pen mr-1"></i> Edit
                                </button>
                                <?php if ($u['id'] !== intval($_SESSION['user_id'])): ?>
                                    <form action="dashboard.php?page=users" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun pengguna <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8'); ?> ini?');" class="inline">
                                        <?= csrf_field(); ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $u['id']; ?>">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-400 hover:text-white font-bold text-[11px] border border-rose-500/20 transition-all" title="Hapus Akun">
                                            <i class="fa-solid fa-user-minus mr-1"></i> Hapus
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-[10px] text-emerald-400 font-bold px-2 py-1 bg-emerald-500/10 rounded-md border border-emerald-500/20">Akun Aktif Anda</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Tambah User Baru -->
    <div id="modal-add-user" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Tambah Pengguna Baru</h3>
                <button type="button" onclick="closeModal('modal-add-user')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=users" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="add_user">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap Pengguna</label>
                    <input type="text" name="name" required placeholder="Contoh: Drs. H. Suryanto" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Username</label>
                        <input type="text" name="username" required placeholder="suryanto" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Role / Peran</label>
                        <select name="role_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <?php foreach ($rolesList as $r): ?>
                                <option value="<?= $r['id']; ?>"><?= htmlspecialchars($r['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Kelamin / Sapaan</label>
                    <select name="jenis_kelamin" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="L">Laki-laki (Bapak)</option>
                        <option value="P">Perempuan (Ibu)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Email Sekolah</label>
                    <input type="email" name="email" required placeholder="suryanto@sekolah.sch.id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kata Sandi (Password)</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-add-user')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit User -->
    <div id="modal-edit-user" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-md shadow-2xl relative">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                <h3 class="text-lg font-bold text-white">Edit Nama & Data Pengguna</h3>
                <button type="button" onclick="closeModal('modal-edit-user')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=users" method="POST" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id" id="edit_user_id">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Nama Lengkap (Tampil di Dashboard)</label>
                    <input type="text" name="name" id="edit_user_name" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Username Login</label>
                        <input type="text" name="username" id="edit_user_username" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Role / Peran</label>
                        <select name="role_id" id="edit_user_role_id" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                            <?php foreach ($rolesList as $r): ?>
                                <option value="<?= $r['id']; ?>"><?= htmlspecialchars($r['display_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Jenis Kelamin / Sapaan</label>
                    <select name="jenis_kelamin" id="edit_user_jk" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                        <option value="L">Laki-laki (Bapak)</option>
                        <option value="P">Perempuan (Ibu)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Email Sekolah</label>
                    <input type="email" name="email" id="edit_user_email" required class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Reset Password Baru (Kosongkan jika tidak diubah)</label>
                    <input type="password" name="new_password" placeholder="••••••••" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white">
                </div>

                <div class="pt-3 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-edit-user')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function openEditUser(data) {
        document.getElementById('edit_user_id').value = data.id || '';
        document.getElementById('edit_user_name').value = data.name || '';
        document.getElementById('edit_user_username').value = data.username || '';
        document.getElementById('edit_user_email').value = data.email || '';
        document.getElementById('edit_user_role_id').value = data.role_id || 1;
        document.getElementById('edit_user_jk').value = data.jenis_kelamin || 'L';
        openModal('modal-edit-user');
    }
</script>
