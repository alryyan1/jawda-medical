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
        // Copy cash prices from altohami.company_tests_relation to current database main_tests
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
            
            // Clear the connection cache to ensure the new config is used
            DB::purge('altohamil_temp');
            
            // Get cash prices from company_tests_relation where insu_id = 1
            $cashPrices = DB::connection('altohamil_temp')
                ->table('company_tests_relation')
                ->where('insu_id', 1)
                ->get();
            
            if ($cashPrices->count() > 0) {
                $updatedCount = 0;
                
                // Update prices in current database main_tests table
                foreach ($cashPrices as $priceRecord) {
                    $testId = $priceRecord->test_id;
                    $price = $priceRecord->price;
                    
                    // Convert price from cents to the format used in current database (assuming it's in a different unit)
                    // The sample shows prices like 12000, 8000, 9000 - these might be in cents or a different currency unit
                    // We'll use them as-is, but you can adjust the conversion if needed
                    $convertedPrice = $price / 100; // Convert from cents to main currency unit
                    
                    // Update the price in main_tests table where id matches test_id
                    $updated = DB::table('main_tests')
                        ->where('id', $testId)
                        ->update(['price' => $convertedPrice]);
                    
                    if ($updated) {
                        $updatedCount++;
                    }
                }
                
                echo "Successfully updated prices for {$updatedCount} main tests from altohami cash prices\n";
            } else {
                echo "No cash prices found in altohami.company_tests_relation where insu_id = 1\n";
            }
        } catch (\Exception $e) {
            echo "Error copying cash prices: " . $e->getMessage() . "\n";
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
