<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class ChildTestOptionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('child_test_options')->delete();
        
        \DB::table('child_test_options')->insert(array (
            
            array (
                'id' => 1,
                'name' => 'Yellow',
                'child_test_id' => 2,
            ),
            
            array (
                'id' => 2,
                'name' => 'Deep Yellow',
                'child_test_id' => 2,
            ),
            
            array (
                'id' => 3,
                'name' => 'Reddish',
                'child_test_id' => 2,
            ),
            
            array (
                'id' => 4,
                'name' => 'Brown',
                'child_test_id' => 2,
            ),
            
            array (
                'id' => 5,
                'name' => 'Pale Yellow',
                'child_test_id' => 2,
            ),
            
            array (
                'id' => 6,
                'name' => 'Dark',
                'child_test_id' => 2,
            ),
            
            array (
                'id' => 14,
                'name' => 'Acidic',
                'child_test_id' => 3,
            ),
            
            array (
                'id' => 15,
                'name' => 'Alkaline',
                'child_test_id' => 3,
            ),
            
            array (
                'id' => 16,
                'name' => 'Uncountable',
                'child_test_id' => 8,
            ),
            
            array (
                'id' => 17,
                'name' => 'Uncountable',
                'child_test_id' => 9,
            ),
            
            array (
                'id' => 27,
                'name' => 'Hyaline Cast +',
                'child_test_id' => 12,
            ),
            
            array (
                'id' => 28,
                'name' => 'Cellular Cast +',
                'child_test_id' => 12,
            ),
            
            array (
                'id' => 29,
                'name' => 'Granular Cast +',
                'child_test_id' => 12,
            ),
            
            array (
                'id' => 30,
                'name' => 'Trichomonas vaginalis',
                'child_test_id' => 14,
            ),
            
            array (
                'id' => 32,
                'name' => 'Malaria Ag is Detected [P.F]',
                'child_test_id' => 38,
            ),
            
            array (
                'id' => 33,
                'name' => 'Malaria Ag is Detected [P.V]',
                'child_test_id' => 38,
            ),
            
            array (
                'id' => 34,
                'name' => 'Serum is Reactive ',
                'child_test_id' => 39,
            ),
            
            array (
                'id' => 35,
                'name' => 'Detected in Stool sample',
                'child_test_id' => 40,
            ),
            
            array (
                'id' => 36,
                'name' => 'Positive',
                'child_test_id' => 42,
            ),
            
            array (
                'id' => 37,
                'name' => 'Detected in Stool sample',
                'child_test_id' => 43,
            ),
            
            array (
                'id' => 74,
                'name' => '',
                'child_test_id' => 3,
            ),
            
            array (
                'id' => 82,
                'name' => '',
                'child_test_id' => 9,
            ),
            
            array (
                'id' => 83,
                'name' => '',
                'child_test_id' => 10,
            ),
            
            array (
                'id' => 92,
                'name' => '',
                'child_test_id' => 12,
            ),
            
            array (
                'id' => 98,
                'name' => '',
                'child_test_id' => 60,
            ),
            
            array (
                'id' => 126,
                'name' => 'Mucus++',
                'child_test_id' => 14,
            ),
            
            array (
                'id' => 156,
                'name' => 'Brown',
                'child_test_id' => 58,
            ),
            
            array (
                'id' => 157,
                'name' => 'Black',
                'child_test_id' => 58,
            ),
            
            array (
                'id' => 158,
                'name' => 'Yellow',
                'child_test_id' => 58,
            ),
            
            array (
                'id' => 159,
                'name' => 'Bloody',
                'child_test_id' => 58,
            ),
            
            array (
                'id' => 160,
                'name' => 'Mucoid',
                'child_test_id' => 58,
            ),
            
            array (
                'id' => 161,
                'name' => 'diarrhea',
                'child_test_id' => 58,
            ),
            
            array (
                'id' => 162,
                'name' => 'formed',
                'child_test_id' => 58,
            ),
            
            array (
                'id' => 163,
                'name' => 'alkaline',
                'child_test_id' => 59,
            ),
            
            array (
                'id' => 164,
                'name' => 'acidic',
                'child_test_id' => 59,
            ),
            
            array (
                'id' => 228,
                'name' => 'Mucus+',
                'child_test_id' => 14,
            ),
            
            array (
                'id' => 229,
                'name' => 'M.Bacteria+',
                'child_test_id' => 14,
            ),
            
            array (
                'id' => 238,
                'name' => 'formed',
                'child_test_id' => 60,
            ),
            
            array (
                'id' => 239,
                'name' => 'semi-formed',
                'child_test_id' => 60,
            ),
            
            array (
                'id' => 240,
                'name' => 'diarrhoea',
                'child_test_id' => 60,
            ),
            
            array (
                'id' => 241,
                'name' => 'mucoid',
                'child_test_id' => 60,
            ),
            
            array (
                'id' => 242,
                'name' => 'loose',
                'child_test_id' => 60,
            ),
            
            array (
                'id' => 243,
                'name' => 'fluid',
                'child_test_id' => 60,
            ),
            
            array (
                'id' => 246,
                'name' => 'A.Phosphate +',
                'child_test_id' => 11,
            ),
            
            array (
                'id' => 247,
                'name' => 'A.Urate +',
                'child_test_id' => 11,
            ),
            
            array (
                'id' => 248,
                'name' => 'Ca.Oxalate +',
                'child_test_id' => 11,
            ),
            
            array (
                'id' => 249,
                'name' => 'Uric Acid +',
                'child_test_id' => 11,
            ),
            
            array (
                'id' => 251,
                'name' => 'few',
                'child_test_id' => 10,
            ),
            
            array (
                'id' => 252,
                'name' => '+',
                'child_test_id' => 10,
            ),
            
            array (
                'id' => 253,
                'name' => '++',
                'child_test_id' => 10,
            ),
            
            array (
                'id' => 254,
                'name' => '+++',
                'child_test_id' => 10,
            ),
            
            array (
                'id' => 466,
                'name' => 'new!',
                'child_test_id' => 38,
            ),
        ));
        
        
    }
}