<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class SubRoutesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('sub_routes')->delete();
        
        \DB::table('sub_routes')->insert(array (
            0 => 
            array (
                'id' => 1,
                'route_id' => 2,
                'name' => 'define',
                'path' => '/pharmacy/add',
                'icon' => '0',
            ),
            1 => 
            array (
                'id' => 2,
                'route_id' => 2,
                'name' => 'pos',
                'path' => '/pharmacy/sell',
                'icon' => '4',
            ),
            2 => 
            array (
                'id' => 3,
                'route_id' => 2,
                'name' => 'items',
                'path' => '/pharmacy/items',
                'icon' => '1',
            ),
            3 => 
            array (
                'id' => 4,
                'route_id' => 2,
                'name' => 'sales',
                'path' => '/pharmacy/reports',
                'icon' => '2',
            ),
            4 => 
            array (
                'id' => 5,
                'route_id' => 2,
                'name' => 'quantities',
                'path' => '/pharmacy/quantities',
                'icon' => '3',
            ),
            5 => 
            array (
                'id' => 6,
                'route_id' => 2,
                'name' => 'income',
                'path' => '/pharmacy/deposit',
                'icon' => '5',
            ),
            6 => 
            array (
                'id' => 8,
                'route_id' => 5,
                'name' => 'booking',
                'path' => '/clinic',
                'icon' => '0',
            ),
            7 => 
            array (
                'id' => 11,
                'route_id' => 4,
                'name' => 'register_lab_patient',
                'path' => 'laboratory/add',
                'icon' => '0',
            ),
            8 => 
            array (
                'id' => 12,
                'route_id' => 4,
                'name' => 'result_entry',
                'path' => 'laboratory/result',
                'icon' => '8',
            ),
            9 => 
            array (
                'id' => 13,
                'route_id' => 4,
                'name' => 'sample_collection',
                'path' => 'laboratory/sample',
                'icon' => '7',
            ),
            10 => 
            array (
                'id' => 14,
                'route_id' => 4,
                'name' => 'test_management',
                'path' => 'laboratory/tests',
                'icon' => '10',
            ),
            11 => 
            array (
                'id' => 15,
                'route_id' => 4,
                'name' => 'price_list',
                'path' => 'laboratory/price',
                'icon' => '9',
            ),
            12 => 
            array (
                'id' => 16,
                'route_id' => 4,
                'name' => 'CBC LIS',
                'path' => 'laboratory/cbc-lis',
                'icon' => '11',
            ),
            13 => 
            array (
                'id' => 17,
                'route_id' => 4,
                'name' => 'Chemistry LIS',
                'path' => 'laboratory/chemistry-lis',
                'icon' => '11',
            ),
            14 => 
            array (
                'id' => 18,
                'route_id' => 4,
                'name' => 'Hormone LIS',
                'path' => 'laboratory/hormone-lis',
                'icon' => '11',
            ),
            15 => 
            array (
                'id' => 19,
                'route_id' => 2,
                'name' => 'supplier',
                'path' => '/pharmacy/supplier/create',
                'icon' => '0',
            ),
            16 => 
            array (
                'id' => 20,
                'route_id' => 2,
                'name' => 'clients',
                'path' => '/pharmacy/client/create',
                'icon' => '0',
            ),
            17 => 
            array (
                'id' => 21,
                'route_id' => 8,
                'name' => 'doctors',
                'path' => 'settings/doctors',
                'icon' => '0',
            ),
            18 => 
            array (
                'id' => 22,
                'route_id' => 8,
                'name' => 'specialists',
                'path' => 'settings/specialists',
                'icon' => '0',
            ),
            19 => 
            array (
                'id' => 23,
                'route_id' => 8,
                'name' => 'users',
                'path' => 'settings/users',
                'icon' => '0',
            ),
            20 => 
            array (
                'id' => 24,
                'route_id' => 8,
                'name' => 'other',
                'path' => 'settings/paperConfig',
                'icon' => '0',
            ),
            21 => 
            array (
                'id' => 25,
                'route_id' => 5,
                'name' => 'doctorsReclaim',
                'path' => 'clinic/doctors',
                'icon' => '0',
            ),
            22 => 
            array (
                'id' => 26,
                'route_id' => 8,
                'name' => 'defineService',
                'path' => 'settings/create',
                'icon' => '0',
            ),
            23 => 
            array (
                'id' => 27,
                'route_id' => 8,
                'name' => 'serviceGroup',
                'path' => 'settings/serviceGroup/create',
                'icon' => '0',
            ),
            24 => 
            array (
                'id' => 28,
                'route_id' => 6,
                'name' => 'defineInsurance',
                'path' => 'insurance/create',
                'icon' => '0',
            ),
            25 => 
            array (
                'id' => 29,
                'route_id' => 6,
                'name' => 'labContract',
                'path' => '/insurance/lab',
                'icon' => '0',
            ),
            26 => 
            array (
                'id' => 30,
                'route_id' => 6,
                'name' => 'ServiceContract',
                'path' => '/insurance/service',
                'icon' => '0',
            ),
            27 => 
            array (
                'id' => 31,
                'route_id' => 6,
                'name' => 'subcompany',
                'path' => 'insurance/subcomapny',
                'icon' => '0',
            ),
            28 => 
            array (
                'id' => 32,
                'route_id' => 6,
                'name' => 'insuranceRelation',
                'path' => 'insurance/relation',
                'icon' => '0',
            ),
            29 => 
            array (
                'id' => 33,
                'route_id' => 6,
                'name' => 'insuranceCopy',
                'path' => '/insurance/copy',
                'icon' => '0',
            ),
            30 => 
            array (
                'id' => 34,
                'route_id' => 6,
                'name' => 'insuranceReclaim',
                'path' => 'insurance/reclaim',
                'icon' => '0',
            ),
            31 => 
            array (
                'id' => 35,
                'route_id' => 8,
                'name' => 'permissions',
                'path' => 'settings/permissions',
                'icon' => '0',
            ),
            32 => 
            array (
                'id' => 36,
                'route_id' => 2,
                'name' => 'salesTable',
                'path' => 'pharmacy/salesTable',
                'icon' => '0',
            ),
            33 => 
            array (
                'id' => 37,
                'route_id' => 12,
                'name' => 'accountsList',
                'path' => 'finance/account',
                'icon' => '1',
            ),
            34 => 
            array (
                'id' => 38,
                'route_id' => 12,
                'name' => 'entries',
                'path' => 'finance/entries',
                'icon' => '1',
            ),
            35 => 
            array (
                'id' => 39,
                'route_id' => 12,
                'name' => 'ledger',
                'path' => 'finance/ledger',
                'icon' => '1',
            ),
            36 => 
            array (
                'id' => 40,
                'route_id' => 12,
                'name' => 'section',
                'path' => 'finance/section',
                'icon' => '1',
            ),
            37 => 
            array (
                'id' => 41,
                'route_id' => 12,
                'name' => 'trialbalance',
                'path' => 'finance/trialbalance',
                'icon' => '1',
            ),
            38 => 
            array (
                'id' => 42,
                'route_id' => 12,
                'name' => 'tree',
                'path' => 'finance/tree',
                'icon' => '1',
            ),
            39 => 
            array (
                'id' => 43,
                'route_id' => 12,
                'name' => 'expense',
                'path' => 'finance/expense',
                'icon' => '1',
            ),
            40 => 
            array (
                'id' => 44,
                'route_id' => 12,
                'name' => 'gallary',
                'path' => 'finance/gallary',
                'icon' => '1',
            ),
            41 => 
            array (
                'id' => 45,
                'route_id' => 12,
                'name' => 'incomeStatement',
                'path' => 'finance/incomeStatement',
                'icon' => '1',
            ),
            42 => 
            array (
                'id' => 47,
                'route_id' => 12,
                'name' => 'BalanceSheet',
                'path' => 'finance/balanceSheet',
                'icon' => '1',
            ),
            43 => 
            array (
                'id' => 48,
                'route_id' => 2,
                'name' => 'مطالبه تامين الصيدليه',
                'path' => '/pharmacy/reclaim',
                'icon' => '1',
            ),
            44 => 
            array (
                'id' => 49,
                'route_id' => 12,
                'name' => 'financeTree2',
                'path' => 'finance/tree2',
                'icon' => '1',
            ),
        ));
        
        
    }
}