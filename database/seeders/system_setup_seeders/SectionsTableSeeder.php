<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SectionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('sections')->delete();
        
        \DB::table('sections')->insert(array (
            
            array (
                'id' => 1,
                'name' => ' محاليل ',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
            
            array (
                'id' => 2,
                'name' => 'عينات الروتين',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
            
            array (
                'id' => 3,
                'name' => ' كيمياء',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
            
            array (
                'id' => 4,
                'name' => 'هرمون',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
            
            array (
                'id' => 5,
                'name' => 'مستهلكات الهيماتولوجي',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
            
            array (
                'id' => 6,
                'name' => 'مياكرو',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
        ));
        
        
    }
}