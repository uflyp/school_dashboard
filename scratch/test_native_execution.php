<?php
chdir(__DIR__ . '/..');

echo "=== TESTING NATIVE PHP EXECUTION ===\n";

ob_start();
try {
    include 'config.php';
    echo "1. config.php include: SUCCESS\n";
} catch (Throwable $e) {
    echo "1. config.php ERROR: " . $e->getMessage() . "\n";
}

try {
    include 'index.php';
    echo "2. index.php include: SUCCESS\n";
} catch (Throwable $e) {
    echo "2. index.php ERROR: " . $e->getMessage() . "\n";
}
ob_end_clean();

echo "=== NATIVE TEST COMPLETED ===\n";
