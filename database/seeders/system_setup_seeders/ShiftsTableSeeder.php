<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class ShiftsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('shifts')->delete();
        
        \DB::table('shifts')->insert(array (
            
            array (
                'id' => 1,
                'total' => 0.0,
                'bank' => 0.0,
                'expenses' => 0.0,
                'touched' => 0,
                'closed_at' => NULL,
                'is_closed' => 0,
                'user_id' => 1,
                'created_at' => '2026-01-29 18:48:02',
                'updated_at' => '2026-01-29 18:48:02',
                'pharmacy_entry' => NULL,
                'user_closed' => NULL,
            ),
        ));
        
        
    }
}