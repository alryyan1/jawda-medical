<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SuppliersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('suppliers')->delete();
        
        \DB::table('suppliers')->insert(array (
            
            array (
                'id' => 1,
                'name' => 'مخزون افتتاحي',
                'phone' => '0',
                'address' => '',
                'email' => '',
                'created_at' => '2026-01-29 18:48:03',
                'updated_at' => '2026-01-29 18:48:03',
            ),
        ));
        
        
    }
}