<?php

// Bootstrap Laravel application
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "Checking Sysmex Table Structure\n";
echo "===============================\n\n";

try {
    // Check if table exists
    $tableExists = DB::select("SHOW TABLES LIKE 'sysmex'");
    
    if (empty($tableExists)) {
        echo "❌ Sysmex table does not exist\n";
        exit(1);
    }
    
    echo "✅ Sysmex table exists\n\n";
    
    // Get table structure
    $columns = DB::select('DESCRIBE sysmex');
    
    echo "Table Structure:\n";
    echo "----------------\n";
    foreach ($columns as $column) {
        echo sprintf("%-20s %-20s %-10s %-10s %-10s %-10s\n", 
            $column->Field, 
            $column->Type, 
            $column->Null, 
            $column->Key, 
            $column->Default, 
            $column->Extra
        );
    }
    
    echo "\nCurrent Records Count: " . DB::table('sysmex')->count() . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
