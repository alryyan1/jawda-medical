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
        // Copy data from altohami.insurance to current database companies
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
            Config::set('database.connections.altohami_temp', $altohamiConfig);
            DB::purge('altohami_temp');
            
            // Get all data from the source database
            $sourceData = DB::connection('altohami_temp')->table('insurance')->get();
            
            if ($sourceData->count() > 0) {
                $insertedCount = 0;
                
                // Insert data into current database
                foreach ($sourceData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Map columns from altohami to current database structure
                    $mappedData = [
                        'id' => $data['insu_id'], // insu_id -> id
                        'name' => trim($data['insu_name']), // insu_name -> name (trim whitespace)
                        'lab_endurance' => 0, // Default value (not in altohami)
                        'service_endurance' => 0, // Default value (not in altohami)
                        'status' => $data['status'] ?? 1, // status -> status
                        'lab_roof' => 0, // Default value (not in altohami)
                        'service_roof' => $data['roof'] ?? 0, // roof -> service_roof
                        'phone' => '', // Default empty string (required field)
                        'email' => '', // Default empty string (required field)
                        'created_at' => now(),
                        'updated_at' => now(),
                        'finance_account_id' => null, // Default value (not in altohami)
                    ];
                    
                    // Check if record already exists to avoid duplicates
                    $exists = DB::table('companies')->where('id', $mappedData['id'])->exists();
                    
                    if (!$exists) {
                        DB::table('companies')->insert($mappedData);
                        $insertedCount++;
                    }
                }
                
                echo "Successfully copied {$insertedCount} companies from altohami.insurance to companies\n";
            } else {
                echo "No data found in altohami.insurance table\n";
            }
        } catch (\Exception $e) {
            echo "Error copying companies data: " . $e->getMessage() . "\n";
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
