<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class ContainersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('containers')->delete();
        
        \DB::table('containers')->insert(array (
            
            array (
                'id' => 1,
                'container_name' => 'EDTA',
            ),
            
            array (
                'id' => 2,
                'container_name' => 'heparin',
            ),
            
            array (
                'id' => 3,
                'container_name' => 'flouride',
            ),
            
            array (
                'id' => 4,
                'container_name' => 'citrate',
            ),
            
            array (
                'id' => 5,
                'container_name' => 'Stool',
            ),
            
            array (
                'id' => 6,
                'container_name' => 'Urine',
            ),
            
            array (
                'id' => 7,
                'container_name' => 'esr',
            ),
            
            array (
                'id' => 8,
                'container_name' => 'plain',
            ),
            
            array (
                'id' => 9,
                'container_name' => 'ict',
            ),
            
            array (
                'id' => 10,
                'container_name' => 'urea breath test',
            ),
            
            array (
                'id' => 11,
                'container_name' => 'timer',
            ),
        ));
        
        
    }
}