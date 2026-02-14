<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class DoctorsTableSeeder extends Seeder
{
    /**
     * Create the default outpatient doctor for system setup.
     */
    public function run(): void
    {
        $exists = \DB::table('doctors')->where('name', 'outpatient')->exists();
        if ($exists) {
            return;
        }

        \DB::table('doctors')->insert([
            'name' => 'outpatient',
            'phone' => '0',
            'cash_percentage' => 0,
            'company_percentage' => 0,
            'static_wage' => 0,
            'lab_percentage' => 0,
            'specialist_id' => 1,
            'sub_specialist_id' => null,
            'start' => 1,
            'image' => null,
            'finance_account_id' => null,
            'finanace_account_id_insurance' => null,
            'calc_insurance' => 0,
            'is_default' => 1,
            'firebase_id' => null,
            'category_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
