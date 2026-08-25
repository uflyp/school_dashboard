<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Asset CSS URL: " . asset('assets/css/output.css') . "\n";
echo "Public file exists: " . (file_exists(public_path('assets/css/output.css')) ? "YES" : "NO") . "\n";
