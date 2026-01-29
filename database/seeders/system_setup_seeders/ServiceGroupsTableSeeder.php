<?php

namespace Database\Seeders\system_setup_seeders;

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
            
            array (
                'id' => 1,
                'name' => 'كشف',
            ),
            
            array (
                'id' => 4,
                'name' => 'الاشعه',
            ),
            
            array (
                'id' => 8,
                'name' => 'العمليات',
            ),
            
            array (
                'id' => 10,
                'name' => 'اللثة',
            ),
            
            array (
                'id' => 11,
                'name' => 'خلع',
            ),
            
            array (
                'id' => 12,
                'name' => 'الاطفال',
            ),
            
            array (
                'id' => 13,
                'name' => 'التقويم',
            ),
            
            array (
                'id' => 14,
                'name' => 'التركيب',
            ),
            
            array (
                'id' => 15,
                'name' => 'الحشوات',
            ),
            
            array (
                'id' => 16,
                'name' => 'الجراحه',
            ),
            
            array (
                'id' => 17,
                'name' => 'علاج جذور',
            ),
            
            array (
                'id' => 18,
                'name' => 'sedation',
            ),
        ));
        
        
    }
}