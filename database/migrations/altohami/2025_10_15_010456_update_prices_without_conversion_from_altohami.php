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
        // Update prices with original values from altohami.company_tests_relation (no conversion)
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
            
            // Get cash prices from company_tests_relation where insu_id = 1
            $cashPrices = DB::connection('altohami_temp')
                ->table('company_tests_relation')
                ->where('insu_id', 1)
                ->get();
            
            if ($cashPrices->count() > 0) {
                $updatedCount = 0;
                
                // Update prices in current database main_tests table with original values (no conversion)
                foreach ($cashPrices as $priceRecord) {
                    $testId = $priceRecord->test_id;
                    $price = $priceRecord->price; // Use original price without any conversion
                    
                    // Update the price in main_tests table where id matches test_id
                    $updated = DB::table('main_tests')
                        ->where('id', $testId)
                        ->update(['price' => $price]);
                    
                    if ($updated) {
                        $updatedCount++;
                    }
                }
                
                echo "Successfully updated prices for {$updatedCount} main tests with original altohami prices (no conversion)\n";
            } else {
                echo "No cash prices found in altohami.company_tests_relation where insu_id = 1\n";
            }
        } catch (\Exception $e) {
            echo "Error updating prices: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration updates prices, so we don't need to reverse it
        // If you want to revert the prices, you would need to implement that logic here
        echo "Price update migration cannot be reversed automatically. Manual price restoration required if needed.\n";
    }
};
