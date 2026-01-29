<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class ClientsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('clients')->delete();
        
        \DB::table('clients')->insert(array (
            
            array (
                'id' => 1,
                'name' => 'المعمل',
                'phone' => '0',
                'address' => '',
                'email' => '',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
            
            array (
                'id' => 2,
                'name' => ' الصيدليه',
                'phone' => '0',
                'address' => '',
                'email' => '',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
            
            array (
                'id' => 3,
                'name' => ' العيادات ',
                'phone' => '0',
                'address' => '',
                'email' => '',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
        ));
        
        
    }
}