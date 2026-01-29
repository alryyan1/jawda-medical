<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('users')->delete();
        
        \DB::table('users')->insert(array (
            
            array (
                'id' => 1,
                'username' => 'admin',
                'password' => '$2y$12$91YL3vakYAUDJwCbF.V2Be7SGU8bkvbTGEMheOxAh19AB8ownQpr2',
                'remember_token' => NULL,
                'created_at' => '2026-01-29 18:48:02',
                'updated_at' => '2026-01-29 18:31:38',
                'doctor_id' => NULL,
                'is_nurse' => 0,
                'is_supervisor' => 0,
                'is_active' => 1,
                'user_type' => NULL,
                'nav_items' => '["\\/dashboard","\\/clinic","\\/lab-reception","\\/lab-sample-collection","\\/lab-workstation","\\/attendance\\/sheet","\\/patients","\\/admissions","\\/online-booking","\\/cash-reconciliation"]',
                'name' => '',
                'user_money_collector_type' => 'all',
            ),
        ));
        
        
    }
}