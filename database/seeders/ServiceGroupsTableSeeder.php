<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ServiceGroupsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('service_groups')->delete();
        
        \DB::table('service_groups')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'كشف',
            ),
            1 => 
            array (
                'id' => 4,
                'name' => 'الاشعه',
            ),
            2 => 
            array (
                'id' => 8,
                'name' => 'العمليات',
            ),
            3 => 
            array (
                'id' => 10,
                'name' => 'اللثة',
            ),
            4 => 
            array (
                'id' => 11,
                'name' => 'خلع',
            ),
            5 => 
            array (
                'id' => 12,
                'name' => 'الاطفال',
            ),
            6 => 
            array (
                'id' => 13,
                'name' => 'التقويم',
            ),
            7 => 
            array (
                'id' => 14,
                'name' => 'التركيب',
            ),
            8 => 
            array (
                'id' => 15,
                'name' => 'الحشوات',
            ),
            9 => 
            array (
                'id' => 16,
                'name' => 'الجراحه',
            ),
            10 => 
            array (
                'id' => 17,
                'name' => 'علاج جذور',
            ),
            11 => 
            array (
                'id' => 18,
                'name' => 'sedation',
            ),
        ));
        
        
    }
}