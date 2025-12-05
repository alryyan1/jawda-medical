<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RoutesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('routes')->delete();

        \DB::table('routes')->insert(array (
            0 =>
            array (
                'id' => 2,
                'name' => 'pharmacy',
                'path' => 'pharma',
                'created_at' => '2024-07-14 09:22:58',
                'updated_at' => '2024-07-14 09:22:58',
                'icon' => '0',
                'is_multi' => 1,
            ),
            1 =>
            array (
                'id' => 3,
                'name' => 'audit',
                'path' => 'audit',
                'created_at' => '2024-07-14 09:22:58',
                'updated_at' => '2024-07-14 09:22:58',
                'icon' => '16',
                'is_multi' => 0,
            ),
            2 =>
            array (
                'id' => 4,
                'name' => 'lab',
                'path' => 'lab',
                'created_at' => '2024-07-14 09:22:58',
                'updated_at' => '2024-07-14 09:22:58',
                'icon' => '0',
                'is_multi' => 1,
            ),
            3 =>
            array (
                'id' => 5,
                'name' => 'clinic',
                'path' => 'clinic',
                'created_at' => '2024-07-14 09:22:58',
                'updated_at' => '2024-07-14 09:22:58',
                'icon' => '0',
                'is_multi' => 1,
            ),
            4 =>
            array (
                'id' => 6,
                'name' => 'insurance',
                'path' => 'insurance',
                'created_at' => '2024-07-14 09:22:58',
                'updated_at' => '2024-07-14 09:22:58',
                'icon' => '18',
                'is_multi' => 1,
            ),
            5 =>
            array (
                'id' => 8,
                'name' => 'settings',
                'path' => 'settings',
                'created_at' => '2024-07-14 09:22:58',
                'updated_at' => '2024-07-14 09:22:58',
                'icon' => '10',
                'is_multi' => 1,
            ),
            6 =>
            array (
                'id' => 9,
                'name' => 'dashboard',
                'path' => 'dashboard',
                'created_at' => '2024-07-14 09:22:58',
                'updated_at' => '2024-07-14 09:22:58',
                'icon' => '12',
                'is_multi' => 0,
            ),
            7 =>
            array (
                'id' => 10,
                'name' => 'moneyIncome',
                'path' => 'moneyIncome',
                'created_at' => '2024-08-13 09:22:58',
                'updated_at' => '2024-08-13 09:22:58',
                'icon' => '20',
                'is_multi' => 0,
            ),
            8 =>
            array (
                'id' => 12,
                'name' => 'finance',
                'path' => 'finance',
                'created_at' => '2024-08-14 09:22:58',
                'updated_at' => '2024-08-14 09:22:58',
                'icon' => '21',
                'is_multi' => 1,
            ),
            9 =>
            array (
                'id' => 13,
                'name' => 'patients',
                'path' => 'patients',
                'created_at' => NULL,
                'updated_at' => NULL,
                'icon' => '14',
                'is_multi' => 0,
            ),
            10 =>
            array (
                'id' => 14,
                'name' => 'doctor',
                'path' => 'doctor',
                'created_at' => NULL,
                'updated_at' => NULL,
                'icon' => '15',
                'is_multi' => 0,
            ),
            11 =>
            array (
                'id' => 15,
                'name' => 'allReports',
                'path' => 'allReports',
                'created_at' => NULL,
                'updated_at' => NULL,
                'icon' => '17',
                'is_multi' => 0,
            ),
            12 =>
            array (
                'id' => 16,
                'name' => 'denos',
                'path' => 'denos',
                'created_at' => NULL,
                'updated_at' => NULL,
                'icon' => '19',
                'is_multi' => 0,
            ),

            13 =>
            array (
                'id' => 17,
                'name' => 'stats',
                'path' => 'stats',
                'created_at' => NULL,
                'updated_at' => NULL,
                'icon' => '1',
                'is_multi' => 0,
            ),

            14 =>
            array (
                'id' => 18,
                'name' => 'doctorSchedule',
                'path' => 'doctorSchedule',
                'created_at' => NULL,
                'updated_at' => NULL,
                'icon' => '1',
                'is_multi' => 0,
            ),
        ));


    }
}
