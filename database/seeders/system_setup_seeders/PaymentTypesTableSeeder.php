<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class PaymentTypesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('payment_types')->delete();
        
        \DB::table('payment_types')->insert(array (
            
            array (
                'id' => 1,
                'name' => 'Cash',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            
            array (
                'id' => 2,
                'name' => 'Transfer',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
            
            array (
                'id' => 3,
                'name' => 'Bank',
                'created_at' => NULL,
                'updated_at' => NULL,
            ),
        ));
        
        
    }
}