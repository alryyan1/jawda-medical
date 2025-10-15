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
        // Copy data from altohami.services to current database services
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
            $sourceData = DB::connection('altohami_temp')->table('services')->get();
            
            if ($sourceData->count() > 0) {
                $insertedCount = 0;
                
                // Insert data into current database
                foreach ($sourceData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Check if service group exists in current database
                    $serviceGroupId = $data['group_id'] ?? null;
                    $validServiceGroupId = null;
                    
                    if ($serviceGroupId) {
                        $serviceGroupExists = DB::table('service_groups')->where('id', $serviceGroupId)->exists();
                        if ($serviceGroupExists) {
                            $validServiceGroupId = $serviceGroupId;
                        } else {
                            // Skip this service if the group doesn't exist
                            continue;
                        }
                    }
                    
                    // Map columns from altohami to current database structure
                    $mappedData = [
                        'id' => $data['id'], // id -> id
                        'name' => $data['name'], // name -> name
                        'service_group_id' => $validServiceGroupId, // group_id -> service_group_id
                        'price' => 0, // Default price (no price in altohami)
                        'activate' => 1, // Default to active
                        'variable' => $data['allow_price_change'] ?? 0, // allow_price_change -> variable
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    // Check if record already exists to avoid duplicates
                    $exists = DB::table('services')->where('id', $mappedData['id'])->exists();
                    
                    if (!$exists) {
                        DB::table('services')->insert($mappedData);
                        $insertedCount++;
                    }
                }
                
                echo "Successfully copied {$insertedCount} services from altohami.services to services\n";
            } else {
                echo "No data found in altohami.services table\n";
            }
        } catch (\Exception $e) {
            echo "Error copying services data: " . $e->getMessage() . "\n";
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
