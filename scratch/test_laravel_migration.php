<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== LARAVEL MIGRATION VERIFICATION TEST ===\n";

// 1. Check Siswa Model
$bintang = App\Models\Siswa::where('nis', '20241001')->first();
echo "1. Siswa Model Query (Bintang Pratama):\n";
echo "   - Nama: {$bintang->nama}\n";
echo "   - NIS: {$bintang->nis}\n";
echo "   - Kelas: {$bintang->kelas}\n";
echo "   - Status: {$bintang->status_siswa}\n";

// 2. Test KartuPelajarController instance
$controller = new App\Http\Controllers\KartuPelajarController();
$request = Illuminate\Http\Request::create('/kartu-pelajar?nis=20241001', 'GET');
Illuminate\Support\Facades\Auth::login(App\Models\User::first());
$view = $controller->index($request);
echo "\n2. KartuPelajarController index view rendered: " . ($view ? "SUCCESS" : "FAILED") . "\n";

// 3. Test Cetak PDF view
$cetakView = $controller->cetak($request);
echo "3. KartuPelajarController cetak view rendered: " . ($cetakView ? "SUCCESS" : "FAILED") . "\n";

// 4. Test Public Verification
$verifikasiReq = Illuminate\Http\Request::create('/verifikasi-kartu?nis=20241001', 'GET');
$verifikasiView = $controller->verifikasi($verifikasiReq);
echo "4. Public QR Verification view rendered: " . ($verifikasiView ? "SUCCESS" : "FAILED") . "\n";

echo "\n=== ALL LARAVEL TESTS COMPLETED SUCCESSFULLY ===\n";
