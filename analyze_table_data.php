<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== ANALYZING TABLES WITH DATA ===\n\n";

// Get all tables
$tables = DB::select('SHOW TABLES');
$tablesWithData = [];
$emptyTables = [];

foreach ($tables as $table) {
    $tableName = array_values((array) $table)[0];

    // Skip migrations table
    if ($tableName === 'migrations') {
        continue;
    }

    // Count rows
    $count = DB::table($tableName)->count();

    if ($count > 0) {
        $tablesWithData[$tableName] = $count;
        echo "✓ {$tableName}: {$count} rows\n";
    } else {
        $emptyTables[] = $tableName;
    }
}

echo "\n=== SUMMARY ===\n";
echo "Tables with data: " . count($tablesWithData) . "\n";
echo "Empty tables: " . count($emptyTables) . "\n";

// Save to JSON for later use
file_put_contents('tables_with_data.json', json_encode([
    'with_data' => $tablesWithData,
    'empty' => $emptyTables
], JSON_PRETTY_PRINT));

echo "\n✓ Data saved to tables_with_data.json\n";

// Show top 10 tables by row count
echo "\nTop 10 tables by row count:\n";
arsort($tablesWithData);
$top10 = array_slice($tablesWithData, 0, 10, true);
foreach ($top10 as $table => $count) {
    echo "  - {$table}: " . number_format($count) . " rows\n";
}
