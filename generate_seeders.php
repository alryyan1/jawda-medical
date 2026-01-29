<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Load tables with data
$tablesData = json_decode(file_get_contents('tables_with_data.json'), true);
$tablesWithData = array_keys($tablesData['with_data']);

echo "=== GENERATING SEEDERS ===\n\n";
echo "Tables to seed: " . count($tablesWithData) . "\n\n";

$successCount = 0;
$errorCount = 0;
$errors = [];

foreach ($tablesWithData as $table) {
    echo "Generating seeder for: {$table}... ";

    try {
        // Run iseed command
        $exitCode = Artisan::call('iseed', [
            'tables' => $table,
            '--force' => true,
            '--noindex' => true
        ]);

        if ($exitCode === 0) {
            echo "✓\n";
            $successCount++;
        } else {
            echo "✗ (exit code: {$exitCode})\n";
            $errorCount++;
            $errors[] = $table;
        }
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n";
        $errorCount++;
        $errors[] = $table;
    }
}

echo "\n=== GENERATION SUMMARY ===\n";
echo "Success: {$successCount}\n";
echo "Errors: {$errorCount}\n";

if (!empty($errors)) {
    echo "\nFailed tables:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

// Move generated seeders to system_setup_seeders folder
echo "\n=== ORGANIZING SEEDERS ===\n";

$seederFiles = glob('database/seeders/*TableSeeder.php');
$movedCount = 0;

foreach ($seederFiles as $file) {
    $filename = basename($file);
    $newPath = 'database/seeders/system_setup_seeders/' . $filename;

    if (rename($file, $newPath)) {
        echo "Moved: {$filename}\n";
        $movedCount++;
    }
}

echo "\n✓ Moved {$movedCount} seeder files to system_setup_seeders/\n";
echo "\nAll seeders generated successfully!\n";
