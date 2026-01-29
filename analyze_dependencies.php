<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get all tables
$tables = DB::select('SHOW TABLES');

$tableData = [];

foreach ($tables as $table) {
    $tableName = array_values((array) $table)[0];

    // Skip migrations table
    if ($tableName === 'migrations') {
        continue;
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

    $dependencies = [];
    foreach ($foreignKeys as $fk) {
        $dependencies[] = $fk->REFERENCED_TABLE_NAME;
    }

    $tableData[$tableName] = [
        'dependencies' => array_unique($dependencies),
        'foreign_keys' => $foreignKeys
    ];
}

// Output table summary
echo "=== TABLES AND DEPENDENCIES ===\n\n";
foreach ($tableData as $tableName => $data) {
    echo "Table: $tableName\n";
    if (!empty($data['dependencies'])) {
        echo "  Depends on: " . implode(', ', $data['dependencies']) . "\n";
        echo "  Foreign Keys:\n";
        foreach ($data['foreign_keys'] as $fk) {
            echo "    - {$fk->COLUMN_NAME} -> {$fk->REFERENCED_TABLE_NAME}({$fk->REFERENCED_COLUMN_NAME})\n";
        }
    } else {
        echo "  No dependencies (independent table)\n";
    }
    echo "\n";
}

// Output in JSON for easier parsing
file_put_contents('table_dependencies.json', json_encode($tableData, JSON_PRETTY_PRINT));
echo "\n=== JSON data saved to table_dependencies.json ===\n";
