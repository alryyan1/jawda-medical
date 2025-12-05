<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SpecialistsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('specialists')->delete();
        
        \DB::table('specialists')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'الاشعه',
                'created_at' => '2024-12-02 23:17:30',
                'updated_at' => '2025-01-13 18:47:00',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'عمومي',
                'created_at' => '2024-12-03 00:54:41',
                'updated_at' => '2024-12-03 00:54:41',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'اخصائي جراحه',
                'created_at' => '2024-12-04 20:36:36',
                'updated_at' => '2024-12-04 20:36:36',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'أخصائي تقويم',
                'created_at' => '2024-12-04 20:36:45',
                'updated_at' => '2024-12-04 20:36:45',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'أخصائي',
                'created_at' => '2024-12-04 20:36:54',
                'updated_at' => '2024-12-04 21:56:34',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'نائب اخصائي',
                'created_at' => '2025-01-06 19:19:37',
                'updated_at' => '2025-01-13 18:46:43',
            ),
        ));
        
        
    }
}