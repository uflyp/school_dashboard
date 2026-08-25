<?php
// views/admin/galeri.php - Galeri Dokumentasi Sekolah dengan Upload Local Storage
check_role(['admin']);

$uploadDir = 'uploads/galeri/';
if (!file_exists($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

$msg = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    verify_csrf_token();

    if ($_POST['action'] === 'upload_galeri') {
        $kategori = trim($_POST['kategori'] ?? 'Kegiatan');
        $judulInput = trim($_POST['judul'] ?? '');
        $tgl = date('Y-m-d');
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxFileSize = 5 * 1024 * 1024; // 5 MB per file
        
        $uploadedCount = 0;
        $errorMessages = [];

        // 1. Cek apakah ada file upload dari Local Storage
        if (isset($_FILES['foto_files']) && !empty($_FILES['foto_files']['name'][0])) {
            $files = $_FILES['foto_files'];
            $totalFiles = count($files['name']);

            for ($i = 0; $i < $totalFiles; $i++) {
                $origName = $files['name'][$i];
                $tmpName = $files['tmp_name'][$i];
                $fileError = $files['error'][$i];
                $fileSize = $files['size'][$i];

                if ($fileError === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($fileError !== UPLOAD_ERR_OK) {
                    $errorMessages[] = "File '$origName' gagal diunggah (Error code: $fileError).";
                    continue;
                }

                if ($fileSize > $maxFileSize) {
                    $errorMessages[] = "File '$origName' melebihi batas ukuran maksimal 5MB!";
                    continue;
                }

                // Cek ekstensi
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExtensions)) {
                    $errorMessages[] = "File '$origName' ditolak: Format .$ext tidak didukung (Hanya JPG, JPEG, PNG, WEBP).";
                    continue;
                }

                // Cek MIME type aktual
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);

                if (!in_array($mime, $allowedMimes)) {
                    $errorMessages[] = "File '$origName' bukan file gambar yang valid!";
                    continue;
                }

                // Generate nama file unik aman: galeri_YYYYMMDD_uniqid.ext
                $uniqueHash = substr(bin2hex(random_bytes(4)), 0, 6);
                $newFileName = 'galeri_' . date('Ymd') . '_' . $uniqueHash . '.' . $ext;
                $targetPath = $uploadDir . $newFileName;

                // Pastikan tidak collision
                while (file_exists($targetPath)) {
                    $uniqueHash = substr(bin2hex(random_bytes(4)), 0, 6);
                    $newFileName = 'galeri_' . date('Ymd') . '_' . $uniqueHash . '.' . $ext;
                    $targetPath = $uploadDir . $newFileName;
                }

                $moved = is_uploaded_file($tmpName) ? move_uploaded_file($tmpName, $targetPath) : @copy($tmpName, $targetPath);
                if ($moved) {
                    // Tentukan judul foto
                    $finalTitle = $judulInput;
                    if (empty($finalTitle) || $totalFiles > 1) {
                        $baseNameClean = pathinfo($origName, PATHINFO_FILENAME);
                        $finalTitle = !empty($judulInput) ? ($judulInput . ' (' . ($uploadedCount + 1) . ')') : ucwords(str_replace(['_', '-'], ' ', $baseNameClean));
                    }

                    $stmt = $pdo->prepare("INSERT INTO galeri (judul, kategori, url_gambar, tanggal) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$finalTitle, $kategori, $targetPath, $tgl]);
                    $uploadedCount++;
                } else {
                    $errorMessages[] = "Gagal memindahkan file '$origName' ke folder penyimpanan.";
                }
            }
        }

        // 2. Opsi alternatif: Input URL Foto jika ada
        $urlGambar = trim($_POST['url_gambar'] ?? '');
        if (!empty($urlGambar)) {
            $finalTitle = !empty($judulInput) ? $judulInput : 'Dokumentasi Sekolah';
            $stmt = $pdo->prepare("INSERT INTO galeri (judul, kategori, url_gambar, tanggal) VALUES (?, ?, ?, ?)");
            $stmt->execute([$finalTitle, $kategori, $urlGambar, $tgl]);
            $uploadedCount++;
        }

        if ($uploadedCount > 0) {
            $msg = "$uploadedCount foto dokumentasi berhasil ditambahkan ke galeri!";
            if (!empty($errorMessages)) {
                $msg .= " (Catatan: " . implode(', ', $errorMessages) . ")";
            }
            log_activity("Admin uploaded $uploadedCount photos to gallery");
        } else {
            $msg = !empty($errorMessages) ? implode('<br>', $errorMessages) : "Silakan pilih file foto dari komputer atau masukkan URL gambar!";
            $msgType = 'error';
        }
    } elseif ($_POST['action'] === 'hapus_galeri') {
        $id = intval($_POST['id'] ?? 0);
        
        // Ambil data foto sebelum dihapus untuk pembersihan file fisik
        $stmtFoto = $pdo->prepare("SELECT * FROM galeri WHERE id = ?");
        $stmtFoto->execute([$id]);
        $fotoData = $stmtFoto->fetch(PDO::FETCH_ASSOC);

        if ($fotoData) {
            $path = $fotoData['url_gambar'] ?? '';
            // Hapus file fisik dari storage jika merupakan file lokal
            if (!empty($path) && file_exists($path) && str_starts_with($path, 'uploads/galeri/')) {
                @unlink($path);
            }

            $pdo->prepare("DELETE FROM galeri WHERE id = ?")->execute([$id]);
            $msg = "Foto '" . htmlspecialchars($fotoData['judul']) . "' berhasil dihapus beserta file fisiknya!";
            log_activity("Admin deleted gallery photo ID: $id");
        }
    }
}

$galeriList = $pdo->query("SELECT * FROM galeri ORDER BY id DESC")->fetchAll();
?>
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 border border-slate-800 p-6 rounded-3xl shadow-xl">
        <div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-images text-indigo-400"></i> Galeri Dokumentasi Sekolah
            </h1>
            <p class="text-xs text-slate-400 mt-1">Publikasi dan kelola dokumentasi kegiatan, fasilitas, dan prestasi sekolah</p>
        </div>
        <button type="button" onclick="openModal('modal-upload-galeri')" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-indigo-600/30 flex items-center gap-2 transition-all">
            <i class="fa-solid fa-cloud-arrow-up text-sm"></i> + Upload Foto
        </button>
    </div>

    <?php if ($msg): ?>
        <div class="p-4 rounded-2xl border text-xs font-semibold flex items-center justify-between shadow-lg <?= $msgType === 'error' ? 'bg-rose-500/10 border-rose-500/20 text-rose-400' : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-300' ?>">
            <div class="flex items-center gap-2">
                <i class="fa-solid <?= $msgType === 'error' ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i>
                <span><?= $msg; ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white"><i class="fa-solid fa-xmark"></i></button>
        </div>
    <?php endif; ?>

    <?php if (empty($galeriList)): ?>
        <div class="p-12 rounded-3xl bg-slate-900 border border-slate-800 text-center text-slate-500 text-xs space-y-2">
            <i class="fa-solid fa-images text-4xl text-slate-600 block"></i>
            <span class="text-sm font-bold text-slate-400 block">Belum Ada Foto di Galeri</span>
            <p>Klik tombol <strong>+ Upload Foto</strong> di atas untuk mengunggah foto dari komputer.</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($galeriList as $g): ?>
                <?php
                $imgUrl = $g['url_gambar'];
                $isLocal = str_starts_with($imgUrl, 'uploads/');
                $safeTitle = htmlspecialchars($g['judul'], ENT_QUOTES);
                $safeUrl = htmlspecialchars($imgUrl, ENT_QUOTES);
                $safeKategori = htmlspecialchars($g['kategori'], ENT_QUOTES);
                $safeTgl = date('d M Y', strtotime($g['tanggal']));
                ?>
                <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-xl group hover:border-indigo-500/40 transition-all flex flex-col justify-between">
                    <div class="relative h-48 overflow-hidden bg-slate-950">
                        <img src="<?= htmlspecialchars($imgUrl); ?>" alt="<?= $safeTitle; ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        
                        <span class="absolute top-3 right-3 px-2.5 py-1 rounded-xl text-[10px] font-extrabold bg-slate-950/80 backdrop-blur-md text-indigo-300 border border-indigo-500/20 shadow-md">
                            <?= htmlspecialchars($g['kategori']); ?>
                        </span>

                        <?php if ($isLocal): ?>
                            <span class="absolute top-3 left-3 px-2 py-0.5 rounded-lg text-[9px] font-mono font-bold bg-emerald-950/80 text-emerald-300 border border-emerald-500/30">
                                <i class="fa-solid fa-hard-drive mr-1"></i> Local
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="p-4 space-y-3 flex-1 flex flex-col justify-between">
                        <div>
                            <h4 class="text-xs sm:text-sm font-bold text-white line-clamp-1 group-hover:text-indigo-400 transition-colors" title="<?= $safeTitle; ?>">
                                <?= htmlspecialchars($g['judul']); ?>
                            </h4>
                            <span class="text-[10px] text-slate-500 font-mono block mt-1">
                                <i class="fa-regular fa-calendar text-indigo-400 mr-1"></i><?= $safeTgl; ?>
                            </span>
                        </div>

                        <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between gap-2">
                            <button type="button" onclick="previewImageModal('<?= $safeUrl; ?>', '<?= $safeTitle; ?>', '<?= $safeKategori; ?>', '<?= $safeTgl; ?>')" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all">
                                <i class="fa-solid fa-eye text-[10px]"></i> Preview
                            </button>

                            <form action="dashboard.php?page=galeri" method="POST" onsubmit="return confirm('Hapus foto ini dari galeri dan server?');" class="inline">
                                <?= csrf_field(); ?>
                                <input type="hidden" name="action" value="hapus_galeri">
                                <input type="hidden" name="id" value="<?= $g['id']; ?>">
                                <button type="submit" class="w-8 h-8 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-600 hover:text-white border border-rose-500/20 flex items-center justify-center transition-all" title="Hapus Foto">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal Upload Foto dari Local Storage (Google Style Picker) -->
    <div id="modal-upload-galeri" class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 w-full max-w-lg shadow-2xl relative space-y-4 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-base font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-cloud-arrow-up text-indigo-400"></i> Upload Foto ke Galeri
                </h3>
                <button type="button" onclick="closeModal('modal-upload-galeri')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form action="dashboard.php?page=galeri" method="POST" enctype="multipart/form-data" class="space-y-4">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="upload_galeri">

                <!-- File Picker Drop Zone -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Pilih Foto dari Komputer (Local Storage)</label>
                    <div class="border-2 border-dashed border-slate-700 hover:border-indigo-500 bg-slate-950/60 rounded-2xl p-5 text-center cursor-pointer transition-all" onclick="document.getElementById('foto_files_input').click()">
                        <input type="file" id="foto_files_input" name="foto_files[]" multiple accept="image/jpeg,image/png,image/webp,image/jpg" class="hidden" onchange="handleFileSelect(this)">
                        <i class="fa-solid fa-images text-3xl text-indigo-400 mb-2 block"></i>
                        <span class="text-xs font-bold text-white block">Klik untuk memilih foto dari komputer</span>
                        <span class="text-[10px] text-slate-400 mt-0.5 block">Format didukung: JPG, JPEG, PNG, WEBP (Maks. 5MB/file)</span>
                        <span class="text-[10px] text-indigo-400 font-semibold mt-1 block">Dapat memilih beberapa foto sekaligus</span>
                    </div>

                    <!-- Live Image Previews -->
                    <div id="preview-container" class="grid grid-cols-3 gap-2 mt-3 hidden"></div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Judul / Keterangan Foto</label>
                    <input type="text" name="judul" placeholder="Contoh: Upacara HUT RI ke-81 / Praktikum Biologi" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500">
                    <span class="text-[10px] text-slate-500 mt-1 block">*Jika dikosongkan, nama file asli akan digunakan sebagai judul.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Kategori Dokumentasi</label>
                    <select name="kategori" class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white cursor-pointer outline-none focus:border-indigo-500">
                        <option value="Kegiatan">Kegiatan Siswa & Ekstrakurikuler</option>
                        <option value="Akademik">Akademik & Praktikum Laboratorium</option>
                        <option value="Fasilitas">Sarana & Fasilitas Sekolah</option>
                        <option value="Prestasi">Prestasi & Penghargaan Siswa</option>
                        <option value="Event">Pentas Seni & Acara Resmi</option>
                    </select>
                </div>

                <!-- Opsi Alternatif: Link URL Gambar -->
                <div class="pt-2">
                    <details class="text-xs text-slate-400 cursor-pointer">
                        <summary class="font-semibold text-indigo-400 hover:text-indigo-300 select-none">
                            Opsi Tambahan: Gunakan Link URL Gambar
                        </summary>
                        <div class="pt-2">
                            <input type="url" name="url_gambar" placeholder="https://images.unsplash.com/..." class="w-full px-3.5 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white outline-none focus:border-indigo-500 font-mono">
                        </div>
                    </details>
                </div>

                <div class="pt-4 flex justify-end gap-3 border-t border-slate-800">
                    <button type="button" onclick="closeModal('modal-upload-galeri')" class="px-4 py-2 bg-slate-800 text-slate-300 rounded-xl text-xs font-bold">Batal</button>
                    <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold transition-all shadow-md shadow-indigo-600/30 flex items-center gap-2">
                        <i class="fa-solid fa-upload"></i> Unggah Foto
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Lightbox Preview Foto Fullscreen -->
    <div id="modal-preview-foto" class="fixed inset-0 bg-slate-950/90 backdrop-blur-md z-50 hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-5 w-full max-w-3xl shadow-2xl relative space-y-3">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div>
                    <span id="preview-kategori-badge" class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-wider"></span>
                    <h3 id="preview-modal-title" class="text-sm font-extrabold text-white mt-1"></h3>
                    <span id="preview-modal-tgl" class="text-[10px] text-slate-400 font-mono"></span>
                </div>
                <button type="button" onclick="closeModal('modal-preview-foto')" class="w-8 h-8 rounded-xl bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="w-full max-h-[65vh] flex items-center justify-center overflow-hidden rounded-2xl bg-slate-950">
                <img id="preview-modal-img" src="" class="max-w-full max-h-[60vh] object-contain rounded-xl shadow-lg">
            </div>

            <div class="flex justify-end pt-2">
                <button type="button" onclick="closeModal('modal-preview-foto')" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl text-xs font-bold transition-colors">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script>
function handleFileSelect(input) {
    const container = document.getElementById('preview-container');
    container.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        container.classList.remove('hidden');
        Array.from(input.files).forEach(file => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const div = document.createElement('div');
                    div.className = 'relative h-20 rounded-xl overflow-hidden border border-slate-700 bg-slate-950';
                    div.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover"><span class="absolute bottom-1 left-1 right-1 text-[8px] bg-slate-950/80 text-white font-mono px-1 rounded truncate block">${file.name}</span>`;
                    container.appendChild(div);
                };
                reader.readAsDataURL(file);
            }
        });
    } else {
        container.classList.add('hidden');
    }
}

function previewImageModal(url, title, kategori, tgl) {
    document.getElementById('preview-modal-img').src = url;
    document.getElementById('preview-modal-title').innerText = title;
    document.getElementById('preview-kategori-badge').innerText = kategori;
    document.getElementById('preview-modal-tgl').innerText = 'Diupload pada: ' + tgl;
    openModal('modal-preview-foto');
}
</script>
