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
            
            array (
                'id' => 1,
                'name' => 'الباطنيه',
                'firestore_id' => NULL,
                'created_at' => '2026-01-29 18:48:02',
                'updated_at' => '2026-01-29 18:48:02',
            ),
        ));
        
        
    }
}