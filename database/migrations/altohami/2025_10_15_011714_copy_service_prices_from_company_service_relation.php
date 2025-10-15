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
        // Copy service prices from altohami.company_service_relation to current database services
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
            
            // Get service prices from company_service_relation where insu_id = 1
            $servicePrices = DB::connection('altohami_temp')
                ->table('company_service_relation')
                ->where('insu_id', 1)
                ->get();
            
            if ($servicePrices->count() > 0) {
                $updatedCount = 0;
                
                // Update prices in current database services table
                foreach ($servicePrices as $priceRecord) {
                    $serviceId = $priceRecord->service_id;
                    $price = $priceRecord->price; // Use original price without any conversion
                    
                    // Update the price in services table where id matches service_id
                    $updated = DB::table('services')
                        ->where('id', $serviceId)
                        ->update(['price' => $price]);
                    
                    if ($updated) {
                        $updatedCount++;
                    }
                }
                
                echo "Successfully updated prices for {$updatedCount} services with original altohami prices (no conversion)\n";
            } else {
                echo "No service prices found in altohami.company_service_relation where insu_id = 1\n";
            }
        } catch (\Exception $e) {
            echo "Error updating service prices: " . $e->getMessage() . "\n";
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
