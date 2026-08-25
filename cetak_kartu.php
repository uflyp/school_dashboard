<?php
// cetak_kartu.php - Standalone Printable & PDF Generator Kartu Pelajar Digital
require_once 'config.php';
check_login();

$user = current_user();
$role = $user['role'];

// Server-side Authorization & NIS Resolution
$target_nis = '';
if ($role === 'admin') {
    $target_nis = trim($_GET['nis'] ?? '');
    if (empty($target_nis)) {
        $firstSiswa = $pdo->query("SELECT nis FROM siswa ORDER BY id ASC LIMIT 1")->fetch();
        $target_nis = $firstSiswa['nis'] ?? '';
    }
} else {
    // For Siswa: Strictly enforce logged-in student's NIS
    $stmtFind = $pdo->prepare("SELECT * FROM siswa WHERE nis = ? OR LOWER(nama) = LOWER(?) LIMIT 1");
    $stmtFind->execute([$user['username'], $user['name']]);
    $sData = $stmtFind->fetch();
    $target_nis = $sData['nis'] ?? '20241001';
}

$stmtSiswa = $pdo->prepare("SELECT * FROM siswa WHERE nis = ?");
$stmtSiswa->execute([$target_nis]);
$siswa = $stmtSiswa->fetch();

if (!$siswa) {
    die("<div style='font-family:sans-serif; padding:40px; text-align:center; color:#ef4444;'>Data Kartu Pelajar Siswa tidak ditemukan pada sistem.</div>");
}

// Resolve Student Photo
$fotoSiswa = '';
if (!empty($siswa['foto'])) {
    $fotoSiswa = $siswa['foto'];
} else {
    $stmtUser = $pdo->prepare("SELECT avatar FROM users WHERE name = ? OR username = ? LIMIT 1");
    $stmtUser->execute([$siswa['nama'], $siswa['nis']]);
    $uFetch = $stmtUser->fetch();
    $fotoSiswa = $uFetch['avatar'] ?? 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=150';
}

// Generate QR Code URL pointing to public verification page
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/";
$verifyUrl = $baseUrl . "verifikasi_kartu.php?nis=" . urlencode($siswa['nis']);
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($verifyUrl);

// Safe filename format for PDF
$safeFileName = 'kartu_pelajar_' . preg_replace('/[^a-zA-Z0-9_]/', '_', str_replace(' ', '_', $siswa['nama']));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($safeFileName); ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: #020617;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .no-print-toolbar {
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
        }

        .btn-action {
            padding: 10px 20px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-print {
            background-color: #4f46e5;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        }

        .btn-print:hover {
            background-color: #4338ca;
        }

        .btn-back {
            background-color: #1e293b;
            color: #cbd5e1;
            border: 1px solid #334155;
        }

        .btn-back:hover {
            background-color: #334155;
            color: #ffffff;
        }

        /* Container ID Card Pair */
        .cards-container {
            display: flex;
            flex-direction: column;
            gap: 24px;
            align-items: center;
        }

        @media (min-width: 768px) {
            .cards-container {
                flex-direction: row;
            }
        }

        /* Standard ID Card Dimensions: 85.6mm x 54mm -> Ratio ~ 1.58 -> 340px x 215px */
        .id-card {
            width: 340px;
            height: 215px;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            border: 1.5px solid rgba(99, 102, 241, 0.4);
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.5);
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #020617 100%);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 12px 14px;
        }

        /* Card Front Layout */
        .card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(99, 102, 241, 0.3);
            padding-bottom: 8px;
        }

        .card-logo {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, #4f46e5, #a855f7);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 16px;
            shrink: 0;
        }

        .card-school-info {
            line-height: 1.2;
        }

        .card-school-name {
            font-size: 11px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .card-title-tag {
            font-size: 8px;
            font-weight: 700;
            color: #818cf8;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .card-body {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 6px;
        }

        .card-photo {
            width: 68px;
            height: 85px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #818cf8;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            shrink: 0;
        }

        .card-details {
            flex: 1;
            font-size: 9.5px;
            line-height: 1.45;
        }

        .card-student-name {
            font-size: 12px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }

        .card-field {
            display: flex;
            gap: 4px;
            color: #94a3b8;
        }

        .card-field-label {
            width: 50px;
            font-weight: 600;
            color: #64748b;
        }

        .card-field-val {
            font-weight: 700;
            color: #e2e8f0;
        }

        .card-footer {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            padding-top: 6px;
            margin-top: 4px;
        }

        .card-status-badge {
            font-size: 7.5px;
            font-weight: 800;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(52, 211, 153, 0.3);
            text-transform: uppercase;
        }

        .card-qr {
            width: 42px;
            height: 42px;
            border-radius: 6px;
            background: #ffffff;
            padding: 2px;
        }

        /* Card Back Layout */
        .card-back-title {
            font-size: 9.5px;
            font-weight: 800;
            color: #818cf8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 4px;
        }

        .card-rules {
            font-size: 7.5px;
            color: #94a3b8;
            line-height: 1.4;
            padding-left: 12px;
        }

        .card-rules li {
            margin-bottom: 3px;
        }

        .card-signature-box {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 8px;
            font-size: 7.5px;
            color: #94a3b8;
        }

        .principal-sign {
            text-align: right;
        }

        .principal-sign img {
            height: 24px;
            opacity: 0.8;
            margin: 2px 0;
        }

        /* Print Media Styles for Standalone Clean PDF Export */
        @media print {
            @page {
                size: A4 landscape;
                margin: 15mm;
            }

            html, body {
                background: #ffffff !important;
                color: #020617 !important;
                padding: 0 !important;
                margin: 0 !important;
                min-height: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .no-print-toolbar {
                display: none !important;
            }

            .cards-container {
                flex-direction: row !important;
                gap: 24px !important;
                margin: auto !important;
                align-items: center !important;
                justify-content: center !important;
            }

            .id-card {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
                border: 1.5px solid #4f46e5 !important;
                background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #020617 100%) !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Toolbar -->
    <div class="no-print-toolbar">
        <a href="dashboard.php?page=kartu_pelajar" class="btn-action btn-back">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard
        </a>
        <button type="button" onclick="triggerPrintAndDownload()" class="btn-action btn-print">
            <i class="fa-solid fa-file-pdf"></i> Download PDF / Cetak Kartu
        </button>
    </div>

    <!-- Printable ID Card Pair Container -->
    <div class="cards-container" id="printable-cards">

        <!-- ID Card Depan (Front Side) -->
        <div class="id-card">
            <div class="card-header">
                <div class="card-logo">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div class="card-school-info">
                    <div class="card-school-name"><?= htmlspecialchars(get_setting('school_name')); ?></div>
                    <div class="card-title-tag">Kartu Tanda Pelajar Digital</div>
                </div>
            </div>

            <div class="card-body">
                <img src="<?= htmlspecialchars($fotoSiswa); ?>" class="card-photo" alt="Foto Siswa">
                <div class="card-details">
                    <div class="card-student-name"><?= htmlspecialchars($siswa['nama']); ?></div>
                    <table style="width:100%; border-collapse:collapse; font-size:9.5px; line-height:1.4;">
                        <tr>
                            <td style="width:42px; color:#94a3b8; font-weight:600; vertical-align:top; padding-bottom:1px;">NIS</td>
                            <td style="color:#ffffff; font-weight:700; vertical-align:top; padding-bottom:1px;">: <?= htmlspecialchars($siswa['nis']); ?></td>
                        </tr>
                        <tr>
                            <td style="width:42px; color:#94a3b8; font-weight:600; vertical-align:top; padding-bottom:1px;">NISN</td>
                            <td style="color:#ffffff; font-weight:700; vertical-align:top; padding-bottom:1px;">: <?= htmlspecialchars($siswa['nisn'] ?: '-'); ?></td>
                        </tr>
                        <tr>
                            <td style="width:42px; color:#94a3b8; font-weight:600; vertical-align:top; padding-bottom:1px;">Kelas</td>
                            <td style="color:#ffffff; font-weight:700; vertical-align:top; padding-bottom:1px;">: <?= htmlspecialchars($siswa['kelas']); ?></td>
                        </tr>
                        <tr>
                            <td style="width:42px; color:#94a3b8; font-weight:600; vertical-align:top; padding-bottom:1px;">TTL</td>
                            <td style="color:#ffffff; font-weight:700; vertical-align:top; padding-bottom:1px;">: <?= htmlspecialchars($siswa['tempat_lahir'] ?? 'Jakarta'); ?>, <?= !empty($siswa['tanggal_lahir']) ? date('d/m/Y', strtotime($siswa['tanggal_lahir'])) : '-'; ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card-footer">
                <div>
                    <span class="card-status-badge">● SISWA AKTIF (<?= htmlspecialchars($siswa['tahun_ajaran'] ?? '2025/2026'); ?>)</span>
                </div>
                <img src="<?= htmlspecialchars($qrCodeUrl); ?>" class="card-qr" alt="QR Verifikasi">
            </div>
        </div>

        <!-- ID Card Belakang (Back Side) -->
        <div class="id-card">
            <div class="card-back-title">Ketentuan Penggunaan Kartu Pelajar</div>
            
            <ol class="card-rules">
                <li>Kartu ini adalah identitas resmi siswa <?= htmlspecialchars(get_setting('school_name')); ?>.</li>
                <li>Wajib dibawa selama kegiatan pembelajaran di lingkungan sekolah.</li>
                <li>Tidak dapat dipindahtangankan kepada pihak manapun.</li>
                <li>Scan QR Code untuk verifikasi status keaktifan siswa.</li>
                <li>Apabila menemukan kartu ini, harap dikembalikan ke Sekretariat Sekolah.</li>
            </ol>

            <div class="card-signature-box">
                <div>
                    <div>Diterbitkan: Jakarta</div>
                    <div style="font-weight:700; color:#fff;">SIAKAD Digital System</div>
                </div>
                <div class="principal-sign">
                    <div>Kepala Sekolah,</div>
                    <div style="font-weight:800; color:#fff; margin-top:14px; text-decoration:underline;">Prof. Dr. Bambang S., M.Ed.</div>
                    <div style="font-size:6.5px;">NIP. 19750812 200003 1 002</div>
                </div>
            </div>
        </div>

    </div>

    <script>
        function triggerPrintAndDownload() {
            window.print();
        }

        // Auto trigger print if parameter download=1
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('download') === '1') {
            setTimeout(() => {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
