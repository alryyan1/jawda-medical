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
        // Copy data from altohami.main_tests to current database main_tests
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
            $sourceData = DB::connection('altohamil_temp')->table('main_tests')->get();
            
            if ($sourceData->count() > 0) {
                // Insert data into current database
                foreach ($sourceData as $record) {
                    // Convert to array and filter only columns that exist in current database
                    $data = (array) $record;
                    
                    // Only keep columns that exist in both tables
                    $allowedColumns = ['id', 'main_test_name', 'pack_id', 'pageBreak', 'container_id'];
                    $filteredData = array_intersect_key($data, array_flip($allowedColumns));
                    
                    // Check if record already exists to avoid duplicates
                    $exists = DB::table('main_tests')->where('id', $filteredData['id'])->exists();
                    
                    if (!$exists) {
                        DB::table('main_tests')->insert($filteredData);
                    }
                }
                
                echo "Successfully copied " . $sourceData->count() . " records from altohami.main_tests to main_tests\n";
            } else {
                echo "No data found in altohami.main_tests table\n";
            }
        } catch (\Exception $e) {
            echo "Error copying main_tests data: " . $e->getMessage() . "\n";
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
