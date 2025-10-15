<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Copy data from altohami.users to current database users
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
            $sourceData = DB::connection('altohami_temp')->table('users')->get();
            
            if ($sourceData->count() > 0) {
                $insertedCount = 0;
                
                // Insert data into current database
                foreach ($sourceData as $record) {
                    // Convert to array and map columns correctly
                    $data = (array) $record;
                    
                    // Map user type from altohami to current database
                    $userType = $this->mapUserType($data['type'] ?? '');
                    
                    // Map columns from altohami to current database structure
                    $mappedData = [
                        'id' => $data['user_id'], // user_id -> id
                        'username' => $data['user_name'] ?: 'user_' . $data['user_id'], // user_name -> username
                        'password' => Hash::make($data['password']), // Hash the password
                        'name' => $data['title'] ?: $data['user_name'] ?: 'User ' . $data['user_id'], // title -> name
                        'user_type' => $userType, // type -> user_type (mapped)
                        'is_active' => 1, // Default to active
                        'is_nurse' => 0, // Default value
                        'is_supervisor' => 0, // Default value
                        'doctor_id' => null, // Default value
                        'user_money_collector_type' => 'all', // Default value
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    // Check if record already exists to avoid duplicates
                    $exists = DB::table('users')->where('id', $mappedData['id'])->exists();
                    
                    if (!$exists) {
                        DB::table('users')->insert($mappedData);
                        $insertedCount++;
                    }
                }
                
                echo "Successfully copied {$insertedCount} users from altohami.users to users\n";
            } else {
                echo "No data found in altohami.users table\n";
            }
        } catch (\Exception $e) {
            echo "Error copying users data: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Map user type from altohami to current database
     */
    private function mapUserType($altohamiType)
    {
        $typeMapping = [
            'lab' => 'ادخال نتائج',
            'account' => 'خزنه موحده',
            'collection' => 'استقبال معمل',
            'all' => 'استقبال عياده',
            'all2' => 'استقبال عياده',
            'clinic' => 'استقبال عياده',
            'insurance' => 'تامين',
        ];
        
        return $typeMapping[$altohamiType] ?? 'استقبال معمل'; // Default to lab reception
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
