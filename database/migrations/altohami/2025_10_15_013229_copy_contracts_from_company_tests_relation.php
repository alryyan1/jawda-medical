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
        // Copy contracts data from altohami.company_tests_relation where insu_id != 1 to current database company_main_test
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
            
            // Get contracts data from company_tests_relation where insu_id != 1
            $contractsData = DB::connection('altohami_temp')
                ->table('company_tests_relation')
                ->where('insu_id', '!=', 1)
                ->get();
            
            if ($contractsData->count() > 0) {
                $insertedCount = 0;
                
                // Insert data into current database
                foreach ($contractsData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Check if main_test and company exist in current database
                    $mainTestExists = DB::table('main_tests')->where('id', $data['test_id'])->exists();
                    $companyExists = DB::table('companies')->where('id', $data['insu_id'])->exists();
                    
                    // Only insert if both main_test and company exist (skip insu_id = 0 as it's not a valid company)
                    if ($mainTestExists && $companyExists && $data['insu_id'] != 0) {
                        // Map columns from altohami to current database structure
                        $mappedData = [
                            'main_test_id' => $data['test_id'], // test_id -> main_test_id
                            'company_id' => $data['insu_id'], // insu_id -> company_id
                            'status' => 1, // Default to active (not in altohami)
                            'price' => $data['price'], // price -> price
                            'approve' => $data['agree'] ?? 0, // agree -> approve
                            'endurance_static' => 0, // Default value (not in altohami)
                            'endurance_percentage' => 0, // Default value (not in altohami)
                            'use_static' => 0, // Default value (not in altohami)
                        ];
                        
                        // Check if record already exists to avoid duplicates
                        $exists = DB::table('company_main_test')
                            ->where('main_test_id', $mappedData['main_test_id'])
                            ->where('company_id', $mappedData['company_id'])
                            ->exists();
                        
                        if (!$exists) {
                            DB::table('company_main_test')->insert($mappedData);
                            $insertedCount++;
                        }
                    }
                }
                
                echo "Successfully copied {$insertedCount} contracts from altohami.company_tests_relation to company_main_test\n";
            } else {
                echo "No contracts data found in altohami.company_tests_relation where insu_id != 1\n";
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
