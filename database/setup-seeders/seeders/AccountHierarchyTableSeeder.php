<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AccountHierarchyTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('account_hierarchy')->delete();
        
        \DB::table('account_hierarchy')->insert(array (
            0 => 
            array (
                'parent_id' => 1,
                'child_id' => 2,
                'level' => 1,
            ),
            1 => 
            array (
                'parent_id' => 1,
                'child_id' => 3,
                'level' => 1,
            ),
            2 => 
            array (
                'parent_id' => 1,
                'child_id' => 4,
                'level' => 1,
            ),
            3 => 
            array (
                'parent_id' => 1,
                'child_id' => 6,
                'level' => NULL,
            ),
            4 => 
            array (
                'parent_id' => 1,
                'child_id' => 17,
                'level' => NULL,
            ),
            5 => 
            array (
                'parent_id' => 1,
                'child_id' => 42,
                'level' => 1,
            ),
            6 => 
            array (
                'parent_id' => 1,
                'child_id' => 46,
                'level' => 1,
            ),
            7 => 
            array (
                'parent_id' => 1,
                'child_id' => 60,
                'level' => 1,
            ),
            8 => 
            array (
                'parent_id' => 3,
                'child_id' => 55,
                'level' => 1,
            ),
            9 => 
            array (
                'parent_id' => 3,
                'child_id' => 56,
                'level' => 1,
            ),
            10 => 
            array (
                'parent_id' => 3,
                'child_id' => 57,
                'level' => 1,
            ),
            11 => 
            array (
                'parent_id' => 4,
                'child_id' => 19,
                'level' => 1,
            ),
            12 => 
            array (
                'parent_id' => 5,
                'child_id' => 9,
                'level' => 1,
            ),
            13 => 
            array (
                'parent_id' => 5,
                'child_id' => 10,
                'level' => 1,
            ),
            14 => 
            array (
                'parent_id' => 5,
                'child_id' => 58,
                'level' => 1,
            ),
            15 => 
            array (
                'parent_id' => 7,
                'child_id' => 11,
                'level' => 1,
            ),
            16 => 
            array (
                'parent_id' => 7,
                'child_id' => 12,
                'level' => 1,
            ),
            17 => 
            array (
                'parent_id' => 8,
                'child_id' => 13,
                'level' => 1,
            ),
            18 => 
            array (
                'parent_id' => 8,
                'child_id' => 15,
                'level' => 1,
            ),
            19 => 
            array (
                'parent_id' => 8,
                'child_id' => 51,
                'level' => 1,
            ),
            20 => 
            array (
                'parent_id' => 8,
                'child_id' => 54,
                'level' => 1,
            ),
            21 => 
            array (
                'parent_id' => 11,
                'child_id' => 34,
                'level' => 1,
            ),
            22 => 
            array (
                'parent_id' => 11,
                'child_id' => 35,
                'level' => 1,
            ),
            23 => 
            array (
                'parent_id' => 11,
                'child_id' => 36,
                'level' => 1,
            ),
            24 => 
            array (
                'parent_id' => 19,
                'child_id' => 20,
                'level' => 1,
            ),
            25 => 
            array (
                'parent_id' => 19,
                'child_id' => 21,
                'level' => 1,
            ),
            26 => 
            array (
                'parent_id' => 19,
                'child_id' => 22,
                'level' => 1,
            ),
            27 => 
            array (
                'parent_id' => 19,
                'child_id' => 23,
                'level' => 1,
            ),
            28 => 
            array (
                'parent_id' => 19,
                'child_id' => 24,
                'level' => 1,
            ),
            29 => 
            array (
                'parent_id' => 19,
                'child_id' => 25,
                'level' => 1,
            ),
            30 => 
            array (
                'parent_id' => 19,
                'child_id' => 26,
                'level' => 1,
            ),
            31 => 
            array (
                'parent_id' => 19,
                'child_id' => 27,
                'level' => 1,
            ),
            32 => 
            array (
                'parent_id' => 19,
                'child_id' => 28,
                'level' => 1,
            ),
            33 => 
            array (
                'parent_id' => 19,
                'child_id' => 29,
                'level' => 1,
            ),
            34 => 
            array (
                'parent_id' => 19,
                'child_id' => 30,
                'level' => 1,
            ),
            35 => 
            array (
                'parent_id' => 19,
                'child_id' => 31,
                'level' => 1,
            ),
            36 => 
            array (
                'parent_id' => 19,
                'child_id' => 32,
                'level' => 1,
            ),
            37 => 
            array (
                'parent_id' => 19,
                'child_id' => 33,
                'level' => 1,
            ),
            38 => 
            array (
                'parent_id' => 42,
                'child_id' => 43,
                'level' => 1,
            ),
            39 => 
            array (
                'parent_id' => 42,
                'child_id' => 44,
                'level' => 1,
            ),
            40 => 
            array (
                'parent_id' => 42,
                'child_id' => 45,
                'level' => 1,
            ),
            41 => 
            array (
                'parent_id' => 46,
                'child_id' => 47,
                'level' => 1,
            ),
            42 => 
            array (
                'parent_id' => 46,
                'child_id' => 48,
                'level' => 1,
            ),
            43 => 
            array (
                'parent_id' => 46,
                'child_id' => 49,
                'level' => 1,
            ),
            44 => 
            array (
                'parent_id' => 46,
                'child_id' => 50,
                'level' => 1,
            ),
            45 => 
            array (
                'parent_id' => 47,
                'child_id' => 37,
                'level' => NULL,
            ),
            46 => 
            array (
                'parent_id' => 47,
                'child_id' => 39,
                'level' => NULL,
            ),
            47 => 
            array (
                'parent_id' => 47,
                'child_id' => 41,
                'level' => NULL,
            ),
            48 => 
            array (
                'parent_id' => 51,
                'child_id' => 52,
                'level' => 1,
            ),
            49 => 
            array (
                'parent_id' => 51,
                'child_id' => 53,
                'level' => 1,
            ),
        ));
        
        
    }
}