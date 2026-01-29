<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class RoomsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('rooms')->delete();
        
        \DB::table('rooms')->insert(array (
            
            array (
                'id' => 1,
                'ward_id' => 1,
                'room_number' => 'R-001',
                'room_type' => 'vip',
                'price_per_day' => '150000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 2,
                'ward_id' => 2,
                'room_number' => 'R-002',
                'room_type' => 'vip',
                'price_per_day' => '150000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 3,
                'ward_id' => 3,
                'room_number' => 'R-003',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 4,
                'ward_id' => 2,
                'room_number' => 'R-004',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 5,
                'ward_id' => 1,
                'room_number' => 'R-005',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 6,
                'ward_id' => 2,
                'room_number' => 'R-006',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 7,
                'ward_id' => 1,
                'room_number' => 'R-007',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 8,
                'ward_id' => 3,
                'room_number' => 'R-008',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 9,
                'ward_id' => 1,
                'room_number' => 'R-009',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 10,
                'ward_id' => 1,
                'room_number' => 'R-010',
                'room_type' => 'normal',
                'price_per_day' => '50000.00',
                'capacity' => 10,
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
        ));
        
        
    }
}