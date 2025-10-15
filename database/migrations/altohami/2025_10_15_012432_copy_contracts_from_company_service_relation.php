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
        // Copy contracts data from altohami.company_service_relation where insu_id != 1 to current database company_service
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
            
            // Get contracts data from company_service_relation where insu_id != 1
            $contractsData = DB::connection('altohami_temp')
                ->table('company_service_relation')
                ->where('insu_id', '!=', 1)
                ->get();
            
            if ($contractsData->count() > 0) {
                $insertedCount = 0;
                
                // Insert data into current database
                foreach ($contractsData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Check if service and company exist in current database
                    $serviceExists = DB::table('services')->where('id', $data['service_id'])->exists();
                    $companyExists = DB::table('companies')->where('id', $data['insu_id'])->exists();
                    
                    // Only insert if both service and company exist
                    if ($serviceExists && $companyExists) {
                        // Map columns from altohami to current database structure
                        $mappedData = [
                            'service_id' => $data['service_id'], // service_id -> service_id
                            'company_id' => $data['insu_id'], // insu_id -> company_id
                            'price' => $data['price'], // price -> price
                            'static_endurance' => 0, // Default value (not in altohami)
                            'percentage_endurance' => $data['percentage'] ?? 0, // percentage -> percentage_endurance
                            'static_wage' => 0, // Default value (not in altohami)
                            'percentage_wage' => $data['doc_perc'] ?? 0, // doc_perc -> percentage_wage
                            'use_static' => 0, // Default value (not in altohami)
                            'approval' => $data['req_approve'] ?? 0, // req_approve -> approval
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        
                        // Check if record already exists to avoid duplicates
                        $exists = DB::table('company_service')
                            ->where('service_id', $mappedData['service_id'])
                            ->where('company_id', $mappedData['company_id'])
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('company_service')->insert($mappedData);
                            $insertedCount++;
                        }
                    }
                }
                
                echo "Successfully copied {$insertedCount} contracts from altohami.company_service_relation to company_service\n";
            } else {
                echo "No contracts data found in altohami.company_service_relation where insu_id != 1\n";
            }
        } catch (\Exception $e) {
            echo "Error copying contracts data: " . $e->getMessage() . "\n";
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
