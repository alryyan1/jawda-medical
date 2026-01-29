<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class DenosTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('denos')->delete();
        
        \DB::table('denos')->insert(array (
            
            array (
                'id' => 1,
                'name' => 1000,
                'display_order' => 1,
            ),
            
            array (
                'id' => 2,
                'name' => 500,
                'display_order' => 2,
            ),
            
            array (
                'id' => 3,
                'name' => 200,
                'display_order' => 3,
            ),
            
            array (
                'id' => 4,
                'name' => 100,
                'display_order' => 4,
            ),
            
            array (
                'id' => 5,
                'name' => 50,
                'display_order' => 5,
            ),
            
            array (
                'id' => 6,
                'name' => 20,
                'display_order' => 6,
            ),
            
            array (
                'id' => 7,
                'name' => 10,
                'display_order' => 7,
            ),
        ));
        
        
    }
}