<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DoctorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('doctors')->insert([
            'name' => 'Outpatient',
            'phone' => '1234567890',
            'cash_percentage' => 50.00,
            'company_percentage' => 40.00,
            'static_wage' => 0.00,
            'lab_percentage' => 10.00,
            'specialist_id' => 1, // Make sure this specialist exists or create one first
            'start' => 1,
            'image' => null,
            // 'finance_account_id' => null,
            // 'finance_account_id_insurance' => null,
            'calc_insurance' => 0,
            'is_default' => 1,
            'firebase_id' => null,
            'sub_specialist_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
