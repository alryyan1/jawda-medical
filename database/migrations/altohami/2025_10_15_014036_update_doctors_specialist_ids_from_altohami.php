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
        // Update doctors specialist_id from altohami database
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
            
            // Get all doctors from altohami with their specialist assignments
            $altohamiDoctors = DB::connection('altohami_temp')
                ->table('doctors')
                ->select('doc_id', 'spci_id')
                ->get();
            
            if ($altohamiDoctors->count() > 0) {
                $updatedCount = 0;
                $skippedCount = 0;
                
                // Update doctors specialist_id in current database
                foreach ($altohamiDoctors as $altohamiDoctor) {
                    $doctorId = $altohamiDoctor->doc_id;
                    $specialistId = $altohamiDoctor->spci_id;
                    
                    // Check if the specialist exists in current database
                    $specialistExists = DB::table('specialists')->where('id', $specialistId)->exists();
                    
                    if ($specialistExists) {
                        // Update the doctor's specialist_id
                        $updated = DB::table('doctors')
                            ->where('id', $doctorId)
                            ->update(['specialist_id' => $specialistId]);
                        
                        if ($updated) {
                            $updatedCount++;
                        }
                    } else {
                        // Skip if specialist doesn't exist
                        $skippedCount++;
                        echo "Skipped doctor ID {$doctorId} - specialist ID {$specialistId} doesn't exist\n";
                    }
                }
                
                echo "Successfully updated {$updatedCount} doctors with correct specialist IDs\n";
                echo "Skipped {$skippedCount} doctors due to missing specialists\n";
            } else {
                echo "No doctors found in altohami.doctors table\n";
            }
        } catch (\Exception $e) {
            echo "Error updating doctors specialist IDs: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration updates data, so we don't need to reverse it
        // If you want to revert the specialist assignments, you would need to implement that logic here
        echo "Specialist update migration cannot be reversed automatically. Manual specialist restoration required if needed.\n";
    }
};
