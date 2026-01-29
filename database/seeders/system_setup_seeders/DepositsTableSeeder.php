<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class DepositsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('deposits')->delete();
        
        \DB::table('deposits')->insert(array (
            
            array (
                'id' => 1,
                'supplier_id' => 1,
                'bill_number' => '123',
                'bill_date' => '2026-01-29',
                'complete' => 1,
                'paid' => 0,
                'user_id' => NULL,
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
                'payment_method' => '',
                'discount' => 0.0,
                'vat_sell' => 0.0,
                'vat_cost' => 0.0,
                'is_locked' => 0,
                'showAll' => 1,
            ),
        ));
        
        
    }
}