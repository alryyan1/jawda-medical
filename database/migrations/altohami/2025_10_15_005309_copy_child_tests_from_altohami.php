<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Copy data from altohami.child_tests to current database child_tests
        try {
            // Create a direct connection to the altohami database
            $altohamiConfig = [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '3306',
                'database' => 'altohami',
                'username' => 'root',
                'password' => '',
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
            ];
            
            // Create a temporary connection
            Config::set('database.connections.altohamil_temp', $altohamiConfig);
            
            // Get all data from the source database
            $sourceData = DB::connection('altohamil_temp')->table('child_tests')->get();
            
            if ($sourceData->count() > 0) {
                // Insert data into current database
                foreach ($sourceData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Map columns from altohami to current database structure
                    $mappedData = [
                        'child_test_name' => $data['child_test_name'] ?? null,
                        'low' => $data['low'] ?? null,
                        'upper' => $data['upper'] ?? null,
                        'main_test_id' => $data['main_id'] ?? null, // main_id -> main_test_id
                        'defval' => $data['defval'] ?? null,
                        'unit_id' => $data['Unit'] ?? null, // Unit -> unit_id
                        'normalRange' => $data['normalRange'] ?? null,
                        'max' => $data['max'] ?? null,
                        'lowest' => $data['lowest'] ?? null,
                    ];
                    
                    // Remove null values
                    $mappedData = array_filter($mappedData, function($value) {
                        return $value !== null;
                    });
                    
                    // Check if record already exists to avoid duplicates (using child_test_name and main_test_id)
                    $exists = DB::table('child_tests')
                        ->where('child_test_name', $mappedData['child_test_name'])
                        ->where('main_test_id', $mappedData['main_test_id'])
                        ->exists();
                    
                    if (!$exists && !empty($mappedData)) {
                        DB::table('child_tests')->insert($mappedData);
                    }
                }
                
                echo "Successfully copied " . $sourceData->count() . " records from altohami.child_tests to child_tests\n";
            } else {
                echo "No data found in altohami.child_tests table\n";
            }
        } catch (\Exception $e) {
            echo "Error copying child_tests data: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only copies data, so we don't need to reverse it
        // If you want to remove the copied data, you would need to implement that logic here
        echo "Data copy migration cannot be reversed automatically. Manual cleanup required if needed.\n";
    }
};
