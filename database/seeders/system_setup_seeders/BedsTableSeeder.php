<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BedsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('beds')->delete();
        
        \DB::table('beds')->insert(array (
            
            array (
                'id' => 1,
                'room_id' => 1,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 2,
                'room_id' => 1,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 3,
                'room_id' => 1,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 4,
                'room_id' => 1,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 5,
                'room_id' => 1,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 6,
                'room_id' => 1,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 7,
                'room_id' => 1,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 8,
                'room_id' => 1,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 9,
                'room_id' => 1,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 10,
                'room_id' => 1,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 11,
                'room_id' => 2,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 12,
                'room_id' => 2,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 13,
                'room_id' => 2,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 14,
                'room_id' => 2,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 15,
                'room_id' => 2,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 16,
                'room_id' => 2,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 17,
                'room_id' => 2,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 18,
                'room_id' => 2,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 19,
                'room_id' => 2,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 20,
                'room_id' => 2,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 21,
                'room_id' => 3,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 22,
                'room_id' => 3,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 23,
                'room_id' => 3,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 24,
                'room_id' => 3,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 25,
                'room_id' => 3,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 26,
                'room_id' => 3,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 27,
                'room_id' => 3,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 28,
                'room_id' => 3,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 29,
                'room_id' => 3,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 30,
                'room_id' => 3,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 31,
                'room_id' => 4,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 32,
                'room_id' => 4,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 33,
                'room_id' => 4,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 34,
                'room_id' => 4,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 35,
                'room_id' => 4,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 36,
                'room_id' => 4,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 37,
                'room_id' => 4,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 38,
                'room_id' => 4,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 39,
                'room_id' => 4,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 40,
                'room_id' => 4,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 41,
                'room_id' => 5,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 42,
                'room_id' => 5,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 43,
                'room_id' => 5,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 44,
                'room_id' => 5,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 45,
                'room_id' => 5,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 46,
                'room_id' => 5,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 47,
                'room_id' => 5,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 48,
                'room_id' => 5,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 49,
                'room_id' => 5,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 50,
                'room_id' => 5,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 51,
                'room_id' => 6,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 52,
                'room_id' => 6,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 53,
                'room_id' => 6,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 54,
                'room_id' => 6,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 55,
                'room_id' => 6,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 56,
                'room_id' => 6,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 57,
                'room_id' => 6,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 58,
                'room_id' => 6,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 59,
                'room_id' => 6,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 60,
                'room_id' => 6,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 61,
                'room_id' => 7,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 62,
                'room_id' => 7,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 63,
                'room_id' => 7,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 64,
                'room_id' => 7,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 65,
                'room_id' => 7,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 66,
                'room_id' => 7,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 67,
                'room_id' => 7,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 68,
                'room_id' => 7,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 69,
                'room_id' => 7,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 70,
                'room_id' => 7,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 71,
                'room_id' => 8,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 72,
                'room_id' => 8,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 73,
                'room_id' => 8,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 74,
                'room_id' => 8,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 75,
                'room_id' => 8,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 76,
                'room_id' => 8,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 77,
                'room_id' => 8,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 78,
                'room_id' => 8,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 79,
                'room_id' => 8,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 80,
                'room_id' => 8,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 81,
                'room_id' => 9,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 82,
                'room_id' => 9,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 83,
                'room_id' => 9,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 84,
                'room_id' => 9,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 85,
                'room_id' => 9,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 86,
                'room_id' => 9,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 87,
                'room_id' => 9,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 88,
                'room_id' => 9,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 89,
                'room_id' => 9,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 90,
                'room_id' => 9,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 91,
                'room_id' => 10,
                'bed_number' => 'B-01',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 92,
                'room_id' => 10,
                'bed_number' => 'B-02',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 93,
                'room_id' => 10,
                'bed_number' => 'B-03',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 94,
                'room_id' => 10,
                'bed_number' => 'B-04',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 95,
                'room_id' => 10,
                'bed_number' => 'B-05',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 96,
                'room_id' => 10,
                'bed_number' => 'B-06',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 97,
                'room_id' => 10,
                'bed_number' => 'B-07',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 98,
                'room_id' => 10,
                'bed_number' => 'B-08',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 99,
                'room_id' => 10,
                'bed_number' => 'B-09',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 100,
                'room_id' => 10,
                'bed_number' => 'B-10',
                'status' => 'available',
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
        ));
        
        
    }
}