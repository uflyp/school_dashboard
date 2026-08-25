<?php
// api.php - REST API Endpoint for WebSekolah V2.0 System
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$action = $_GET['action'] ?? 'stats';

try {
    switch ($action) {
        case 'stats':
            $totalSiswa = $pdo->query("SELECT COUNT(*) FROM siswa")->fetchColumn();
            $totalGuru = $pdo->query("SELECT COUNT(*) FROM guru")->fetchColumn();
            $totalBerita = $pdo->query("SELECT COUNT(*) FROM berita")->fetchColumn();
            $totalPengumuman = $pdo->query("SELECT COUNT(*) FROM pengumuman")->fetchColumn();
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'school_name' => get_setting('school_name'),
                    'total_siswa' => (int)$totalSiswa,
                    'total_guru' => (int)$totalGuru,
                    'total_berita' => (int)$totalBerita,
                    'total_pengumuman' => (int)$totalPengumuman,
                    'ppdb_status' => get_setting('ppdb_status')
                ]
            ]);
            break;

        case 'berita':
            $berita = $pdo->query("SELECT id, title, slug, thumbnail, author, created_at FROM berita ORDER BY id DESC LIMIT 10")->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $berita]);
            break;

        case 'events':
            $events = $pdo->query("SELECT id, title, description, event_date, is_countdown FROM events WHERE is_active = 1 ORDER BY event_date ASC")->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $events]);
            break;

        case 'pengumuman':
            $pengumuman = $pdo->query("SELECT id, judul, isi, kategori, tanggal FROM pengumuman ORDER BY id DESC LIMIT 10")->fetchAll();
            echo json_encode(['status' => 'success', 'data' => $pengumuman]);
            break;

        default:
            echo json_encode([
                'status' => 'error',
                'message' => 'Action tidak valid. Gunakan: stats, berita, events, pengumuman'
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
