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
        // Copy data from altohami.group_specialist to current database service_groups
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
            $sourceData = DB::connection('altohami_temp')->table('group_specialist')->get();
            
            if ($sourceData->count() > 0) {
                $insertedCount = 0;
                
                // Insert data into current database
                foreach ($sourceData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Map columns from altohami to current database structure
                    $mappedData = [
                        'id' => $data['id'], // id -> id
                        'name' => $data['name'], // name -> name
                    ];
                    
                    // Check if record already exists to avoid duplicates
                    $exists = DB::table('service_groups')->where('id', $mappedData['id'])->exists();
                    
                    if (!$exists) {
                        DB::table('service_groups')->insert($mappedData);
                        $insertedCount++;
                    }
                }
                
                echo "Successfully copied {$insertedCount} service groups from altohami.group_specialist to service_groups\n";
            } else {
                echo "No data found in altohami.group_specialist table\n";
            }
        } catch (\Exception $e) {
            echo "Error copying service groups data: " . $e->getMessage() . "\n";
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
