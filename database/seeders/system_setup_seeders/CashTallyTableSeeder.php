<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CashTallyTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('cash_tally')->delete();
        
        \DB::table('cash_tally')->insert(array (
            
            array (
                'id' => 1,
                'name' => '1000',
            ),
            
            array (
                'id' => 2,
                'name' => '500',
            ),
            
            array (
                'id' => 3,
                'name' => '200',
            ),
            
            array (
                'id' => 4,
                'name' => '100',
            ),
        ));
        
        
    }
}