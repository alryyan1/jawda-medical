<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== MIGRATION VERIFICATION REPORT ===\n\n";

// Load table dependencies
$tableData = json_decode(file_get_contents('table_dependencies.json'), true);

// Get all migration files
$migrationFiles = glob('database/migrations/system_setup/*.php');
sort($migrationFiles);

echo "Total migration files: " . count($migrationFiles) . "\n";
echo "Total tables in database: " . count($tableData) . "\n\n";

// Verify all tables have migrations
echo "1. CHECKING TABLE COVERAGE\n";
echo "----------------------------------------\n";

$migratedTables = [];
foreach ($migrationFiles as $file) {
    if (preg_match('/_create_(.+)_table\.php$/', basename($file), $matches)) {
        $migratedTables[] = $matches[1];
    }
}

$missingTables = array_diff(array_keys($tableData), $migratedTables);
$extraMigrations = array_diff($migratedTables, array_keys($tableData));

if (empty($missingTables)) {
    echo "✓ All tables have migration files\n";
} else {
    echo "✗ Missing migrations for tables: " . implode(', ', $missingTables) . "\n";
}

if (empty($extraMigrations)) {
    echo "✓ No extra migration files\n";
} else {
    echo "⚠ Extra migrations (not in database): " . implode(', ', $extraMigrations) . "\n";
}

// Verify dependency order
echo "\n2. CHECKING DEPENDENCY ORDER\n";
echo "----------------------------------------\n";

$tableOrder = [];
foreach ($migrationFiles as $index => $file) {
    if (preg_match('/_create_(.+)_table\.php$/', basename($file), $matches)) {
        $tableOrder[$matches[1]] = $index;
    }
}

$orderViolations = [];
foreach ($tableData as $tableName => $data) {
    if (!isset($tableOrder[$tableName])) {
        continue;
    }

    $tablePosition = $tableOrder[$tableName];

    if (!empty($data['dependencies'])) {
        $dependencies = is_array($data['dependencies']) ? $data['dependencies'] : array_values((array)$data['dependencies']);

        foreach ($dependencies as $dependency) {
            if (isset($tableOrder[$dependency])) {
                $dependencyPosition = $tableOrder[$dependency];

                if ($dependencyPosition >= $tablePosition) {
                    $orderViolations[] = [
                        'table' => $tableName,
                        'dependency' => $dependency,
                        'table_position' => $tablePosition,
                        'dependency_position' => $dependencyPosition
                    ];
                }
            }
        }
    }
}

if (empty($orderViolations)) {
    echo "✓ All dependencies are ordered correctly\n";
} else {
    echo "✗ Found " . count($orderViolations) . " dependency order violations:\n";
    foreach (array_slice($orderViolations, 0, 10) as $violation) {
        echo "  - Table '{$violation['table']}' (position {$violation['table_position']}) depends on '{$violation['dependency']}' (position {$violation['dependency_position']})\n";
    }
    if (count($orderViolations) > 10) {
        echo "  ... and " . (count($orderViolations) - 10) . " more\n";
    }
}

// Sample some migrations
echo "\n3. SAMPLE MIGRATION VALIDATION\n";
echo "----------------------------------------\n";

$samplesToCheck = [
    'account_categories' => ['has_fk' => false, 'expected_columns' => ['id', 'name']],
    'patients' => ['has_fk' => true, 'expected_columns' => ['id', 'name', 'phone', 'gender']],
    'admissions' => ['has_fk' => true, 'expected_columns' => ['id', 'patient_id', 'ward_id', 'room_id']],
    'users' => ['has_fk' => false, 'expected_columns' => ['id', 'name', 'username']],
    'doctors' => ['has_fk' => true, 'expected_columns' => ['id', 'name']]
];

foreach ($samplesToCheck as $tableName => $config) {
    $migrationFile = null;
    foreach ($migrationFiles as $file) {
        if (strpos($file, "_create_{$tableName}_table.php") !== false) {
            $migrationFile = $file;
            break;
        }
    }

    if ($migrationFile) {
        $content = file_get_contents($migrationFile);

        echo "\nTable: {$tableName}\n";

        // Check for expected columns
        $foundColumns = [];
        foreach ($config['expected_columns'] as $column) {
            if (strpos($content, "'{$column}'") !== false || strpos($content, "\"{$column}\"") !== false) {
                $foundColumns[] = $column;
            }
        }

        if (count($foundColumns) === count($config['expected_columns'])) {
            echo "  ✓ All expected columns found\n";
        } else {
            echo "  ⚠ Expected columns: " . implode(', ', $config['expected_columns']) . "\n";
            echo "  ⚠ Found columns: " . implode(', ', $foundColumns) . "\n";
        }

        // Check for foreign keys if expected
        if ($config['has_fk']) {
            if (strpos($content, '->foreign(') !== false) {
                echo "  ✓ Foreign keys defined\n";
            } else {
                echo "  ✗ Expected foreign keys but none found\n";
            }
        }

        // Check for Schema::create
        if (strpos($content, "Schema::create('{$tableName}'") !== false) {
            echo "  ✓ Correct table name in Schema::create\n";
        } else {
            echo "  ✗ Table name mismatch in Schema::create\n";
        }
    } else {
        echo "\nTable: {$tableName}\n";
        echo "  ✗ Migration file not found\n";
    }
}

echo "\n\n=== VERIFICATION SUMMARY ===\n";
echo "Total Migrations: " . count($migrationFiles) . "\n";
echo "Dependency Violations: " . count($orderViolations) . "\n";
echo "Coverage: " . (count($missingTables) === 0 ? "Complete" : "Incomplete") . "\n";

if (empty($orderViolations) && empty($missingTables)) {
    echo "\n✓ All verifications passed! Migrations are ready to use.\n";
} else {
    echo "\n⚠ Some issues found. Please review the details above.\n";
}

echo "\nNext steps:\n";
echo "- Review the generated migration files in database/migrations/system_setup/\n";
echo "- Apply migrations using: php artisan migrate --path=database/migrations/system_setup\n";
echo "- Note: This will recreate all tables, so use with caution on existing databases\n";
