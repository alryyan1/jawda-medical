<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AttendanceSettingsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('attendance_settings')->delete();
        
        \DB::table('attendance_settings')->insert(array (
            
            array (
                'id' => 1,
                'number_of_shifts_per_day' => 2,
                'created_at' => '2026-01-29 18:46:41',
                'updated_at' => '2026-01-29 18:46:41',
            ),
        ));
        
        
    }
}