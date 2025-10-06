<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';

use Illuminate\Support\Facades\DB;

try {
    $app = require __DIR__ . '/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $count = DB::table('sysmex')->count();
    $out = 'COUNT=' . (string)$count . PHP_EOL;
    echo $out;
    // Also write to file in case stdout is suppressed
    @file_put_contents(__DIR__ . '/../storage/logs/db-count.txt', $out);
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}


