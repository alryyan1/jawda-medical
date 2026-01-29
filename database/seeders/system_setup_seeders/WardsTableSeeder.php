<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class WardsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('wards')->delete();
        
        \DB::table('wards')->insert(array (
            
            array (
                'id' => 1,
                'name' => 'الباطنية',
                'description' => 'قسم الأمراض الباطنية',
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 2,
                'name' => 'الجراحة',
                'description' => 'قسم الجراحة العامة',
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
            
            array (
                'id' => 3,
                'name' => 'العناية المركزة',
                'description' => 'وحدة العناية المركزة',
                'status' => 1,
                'created_at' => '2026-01-29 20:14:43',
                'updated_at' => '2026-01-29 20:14:43',
            ),
        ));
        
        
    }
}