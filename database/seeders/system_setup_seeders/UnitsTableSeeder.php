<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class UnitsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('units')->delete();
        
        \DB::table('units')->insert(array (
            
            array (
                'id' => 1,
                'name' => 'mg/dl',
            ),
            
            array (
                'id' => 2,
                'name' => 'g/dl',
            ),
            
            array (
                'id' => 3,
                'name' => 'mmol/l',
            ),
            
            array (
                'id' => 4,
                'name' => 'IU/l',
            ),
            
            array (
                'id' => 5,
                'name' => 'mEq/L',
            ),
            
            array (
                'id' => 6,
                'name' => 'mU/ml',
            ),
            
            array (
                'id' => 7,
                'name' => 'nmol/l',
            ),
            
            array (
                'id' => 8,
                'name' => 'ng/ml',
            ),
            
            array (
                'id' => 9,
                'name' => 'second',
            ),
            
            array (
                'id' => 10,
                'name' => 'x 10^3 µL',
            ),
            
            array (
                'id' => 11,
                'name' => 'million/µl',
            ),
            
            array (
                'id' => 12,
                'name' => '%',
            ),
            
            array (
                'id' => 13,
                'name' => 'F/l',
            ),
            
            array (
                'id' => 14,
                'name' => 'Pg',
            ),
            
            array (
                'id' => 15,
                'name' => 'x 10.000 /uL',
            ),
            
            array (
                'id' => 16,
                'name' => 'U/L',
            ),
            
            array (
                'id' => 17,
                'name' => 'min',
            ),
            
            array (
                'id' => 18,
                'name' => 'mm/hour',
            ),
            
            array (
                'id' => 19,
                'name' => 'U/L',
            ),
            
            array (
                'id' => 20,
                'name' => 'µg/ml',
            ),
            
            array (
                'id' => 21,
                'name' => 'cells/µl',
            ),
            
            array (
                'id' => 22,
                'name' => 'meq/L',
            ),
            
            array (
                'id' => 23,
                'name' => 'titer',
            ),
            
            array (
                'id' => 24,
                'name' => 'pg/ml',
            ),
            
            array (
                'id' => 25,
                'name' => 'ug/24r',
            ),
            
            array (
                'id' => 26,
                'name' => 'ng/l',
            ),
            
            array (
                'id' => 27,
                'name' => 'ug/24h',
            ),
            
            array (
                'id' => 28,
                'name' => 'IU/mL',
            ),
            
            array (
                'id' => 29,
                'name' => 'μg/dL',
            ),
            
            array (
                'id' => 30,
                'name' => 'mOsmol/kg H2O',
            ),
            
            array (
                'id' => 31,
                'name' => 'mm/hour',
            ),
            
            array (
                'id' => 32,
                'name' => 'N',
            ),
            
            array (
                'id' => 33,
                'name' => 'col/l',
            ),
            
            array (
                'id' => 34,
                'name' => '',
            ),
            
            array (
                'id' => 35,
                'name' => 'AU/ml',
            ),
            
            array (
                'id' => 36,
                'name' => 'unit/ml',
            ),
            
            array (
                'id' => 37,
                'name' => 'mEq/L',
            ),
            
            array (
                'id' => 38,
                'name' => '',
            ),
            
            array (
                'id' => 39,
                'name' => ' μIU/ml',
            ),
            
            array (
                'id' => 40,
                'name' => 'mIU/ml',
            ),
            
            array (
                'id' => 41,
                'name' => 'µg/dL',
            ),
            
            array (
                'id' => 42,
                'name' => 'µg/L',
            ),
            
            array (
                'id' => 43,
                'name' => 'pg/ml',
            ),
            
            array (
                'id' => 44,
                'name' => 'ng/dl',
            ),
            
            array (
                'id' => 45,
                'name' => 'µg/dl',
            ),
            
            array (
                'id' => 46,
                'name' => 'unit/ml',
            ),
            
            array (
                'id' => 47,
                'name' => 'ug FEU/ml',
            ),
            
            array (
                'id' => 48,
                'name' => 'µmol/L',
            ),
            
            array (
                'id' => 49,
                'name' => 'mUI/L',
            ),
            
            array (
                'id' => 50,
                'name' => 'mUI/L',
            ),
            
            array (
                'id' => 51,
                'name' => 'pmol/l',
            ),
            
            array (
                'id' => 52,
                'name' => 'pmol/l',
            ),
            
            array (
                'id' => 53,
                'name' => 'ng/ml',
            ),
            
            array (
                'id' => 54,
                'name' => 'ng/ml',
            ),
            
            array (
                'id' => 55,
                'name' => 'ng/ml',
            ),
            
            array (
                'id' => 56,
                'name' => 'ng/ml',
            ),
            
            array (
                'id' => 57,
                'name' => 'MIU/ml',
            ),
            
            array (
                'id' => 58,
                'name' => 'MIU/ml',
            ),
            
            array (
                'id' => 59,
                'name' => 'mg/dl',
            ),
            
            array (
                'id' => 60,
                'name' => 'mg/dl',
            ),
            
            array (
                'id' => 61,
                'name' => 'mg/L',
            ),
            
            array (
                'id' => 62,
                'name' => 'mg/day',
            ),
            
            array (
                'id' => 63,
                'name' => 'g/day',
            ),
            
            array (
                'id' => 64,
                'name' => 'umol/ml',
            ),
            
            array (
                'id' => 65,
                'name' => 'U/ML',
            ),
            
            array (
                'id' => 66,
                'name' => 'Ru/Ml',
            ),
            
            array (
                'id' => 67,
                'name' => '10^9/L',
            ),
            
            array (
                'id' => 68,
                'name' => '10^12/L',
            ),
            
            array (
                'id' => 69,
                'name' => 'pg/ml',
            ),
            
            array (
                'id' => 70,
                'name' => '0',
            ),
            
            array (
                'id' => 71,
                'name' => 'mg/g',
            ),
            
            array (
                'id' => 72,
                'name' => 'index/ml',
            ),
            
            array (
                'id' => 73,
                'name' => 'Molecular Biology',
            ),
            
            array (
                'id' => 74,
                'name' => 'mg/24h',
            ),
            
            array (
                'id' => 75,
                'name' => 'ug/day',
            ),
            
            array (
                'id' => 76,
                'name' => 'AU/ml',
            ),
            
            array (
                'id' => 77,
                'name' => 'IU/ml',
            ),
            
            array (
                'id' => 78,
                'name' => 'IU/l',
            ),
            
            array (
                'id' => 79,
                'name' => 'g/l',
            ),
            
            array (
                'id' => 80,
                'name' => 'U/ml',
            ),
            
            array (
                'id' => 81,
                'name' => 'IU/ml',
            ),
            
            array (
                'id' => 82,
                'name' => 'IU/ml',
            ),
            
            array (
                'id' => 83,
                'name' => 'mg/ml',
            ),
            
            array (
                'id' => 84,
                'name' => '%',
            ),
            
            array (
                'id' => 85,
                'name' => '%',
            ),
            
            array (
                'id' => 86,
                'name' => 'IU/ml',
            ),
            
            array (
                'id' => 87,
                'name' => '/L ',
            ),
            
            array (
                'id' => 88,
                'name' => 'mg albumin/mmol crea',
            ),
        ));
        
        
    }
}