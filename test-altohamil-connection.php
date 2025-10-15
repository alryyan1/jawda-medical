<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

// Load Laravel environment
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Altohamil Database Connection...\n";
echo "=====================================\n\n";

try {
    // Test the altohamil connection
    $connection = DB::connection('altohamil');
    $connection->getPdo();
    echo "✅ Altohamil connection successful!\n";
    
    // Test if we can query the database
    $databases = $connection->select('SELECT DATABASE() as current_db');
    echo "✅ Connected to database: " . $databases[0]->current_db . "\n";
    
    // Check if main_tests table exists
    $mainTestsExists = $connection->select("SHOW TABLES LIKE 'main_tests'");
    if (count($mainTestsExists) > 0) {
        $count = $connection->table('main_tests')->count();
        echo "✅ main_tests table exists with {$count} records\n";
    } else {
        echo "❌ main_tests table does not exist\n";
    }
    
    // Check if child_tests table exists
    $childTestsExists = $connection->select("SHOW TABLES LIKE 'child_tests'");
    if (count($childTestsExists) > 0) {
        $count = $connection->table('child_tests')->count();
        echo "✅ child_tests table exists with {$count} records\n";
    } else {
        echo "❌ child_tests table does not exist\n";
    }
    
    echo "\n✅ All tests passed! You can now run the migrations.\n";
    
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
    echo "\nPlease check your .env file and ensure the following variables are set:\n";
    echo "- ALTOHAMIL_DB_HOST\n";
    echo "- ALTOHAMIL_DB_PORT\n";
    echo "- ALTOHAMIL_DB_DATABASE\n";
    echo "- ALTOHAMIL_DB_USERNAME\n";
    echo "- ALTOHAMIL_DB_PASSWORD\n";
}
