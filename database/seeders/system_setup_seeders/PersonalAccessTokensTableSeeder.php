<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class PersonalAccessTokensTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('personal_access_tokens')->delete();
        
        \DB::table('personal_access_tokens')->insert(array (
            
            array (
                'id' => 1,
                'tokenable_type' => 'App\\Models\\User',
                'tokenable_id' => 1,
            'name' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36',
                'token' => 'b762fb11900d1c6d6d5cce9c3b8afaa08a644e776def00467c09e1cb637b6211',
                'abilities' => '["*"]',
                'last_used_at' => '2026-01-29 20:28:22',
                'expires_at' => NULL,
                'created_at' => '2026-01-29 16:49:12',
                'updated_at' => '2026-01-29 20:28:22',
            ),
        ));
        
        
    }
}