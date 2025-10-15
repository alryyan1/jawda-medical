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
        // Copy data from altohami.doctors to current database doctors
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
            $sourceData = DB::connection('altohami_temp')->table('doctors')->get();
            
            if ($sourceData->count() > 0) {
                $insertedCount = 0;
                
                // Insert data into current database
                foreach ($sourceData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Check if specialist exists in current database
                    $specialistId = $data['spci_id'] ?? null;
                    $validSpecialistId = null;
                    
                    if ($specialistId) {
                        $specialistExists = DB::table('specialists')->where('id', $specialistId)->exists();
                        if ($specialistExists) {
                            $validSpecialistId = $specialistId;
                        } else {
                            // Map to a default specialist (ID 2 = عمومي) if specialist doesn't exist
                            $validSpecialistId = 2;
                        }
                    }
                    
                    // Map columns from altohami to current database structure
                    $mappedData = [
                        'id' => $data['doc_id'], // doc_id -> id
                        'name' => $data['doc_name'], // doc_name -> name
                        'phone' => $data['phone'] ?? null,
                        'specialist_id' => $validSpecialistId, // Use valid specialist ID or null
                        'cash_percentage' => $data['perc'] ?? 0, // perc -> cash_percentage
                        'company_percentage' => 0, // Default value
                        'static_wage' => 0, // Default value
                        'lab_percentage' => 0, // Default value
                        'is_default' => 0, // Default value
                        'start' => 0, // Default value
                        'calc_insurance' => $data['allow_doc_perc'] ?? 0, // allow_doc_perc -> calc_insurance
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    // Check if record already exists to avoid duplicates
                    $exists = DB::table('doctors')->where('id', $mappedData['id'])->exists();
                    
                    if (!$exists) {
                        DB::table('doctors')->insert($mappedData);
                        $insertedCount++;
                    }
                }
                
                echo "Successfully copied {$insertedCount} doctors from altohami.doctors to doctors\n";
            } else {
                echo "No data found in altohami.doctors table\n";
            }
        } catch (\Exception $e) {
            echo "Error copying doctors data: " . $e->getMessage() . "\n";
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
