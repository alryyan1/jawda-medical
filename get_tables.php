<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all tables
$tables = DB::select('SHOW TABLES');

echo "=== ALL TABLES ===\n";
foreach ($tables as $table) {
    $tableName = array_values((array) $table)[0];
    echo $tableName . "\n";
}

echo "\n=== TABLE STRUCTURES WITH FOREIGN KEYS ===\n";
foreach ($tables as $table) {
    $tableName = array_values((array) $table)[0];

    echo "\n--- Table: $tableName ---\n";

    // Get table structure
    $columns = DB::select("SHOW COLUMNS FROM `$tableName`");
    echo "Columns:\n";
    foreach ($columns as $column) {
        echo "  - {$column->Field} ({$column->Type}) " . ($column->Null === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }

    // Get foreign keys
    $foreignKeys = DB::select("
        SELECT 
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            CONSTRAINT_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = ?
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ", [env('DB_DATABASE'), $tableName]);

    if (!empty($foreignKeys)) {
        echo "Foreign Keys:\n";
        foreach ($foreignKeys as $fk) {
            echo "  - {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}({$fk->REFERENCED_COLUMN_NAME})\n";
        }
    }
}
