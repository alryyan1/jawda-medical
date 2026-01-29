<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check table columns
$columns = \DB::select("DESCRIBE admissions");

echo "Admissions Table Columns:\n";
echo "========================\n";
foreach ($columns as $column) {
    echo $column->Field . " - " . $column->Type . "\n";
}
