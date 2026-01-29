<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class DrugCategoriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('drug_categories')->delete();
        
        \DB::table('drug_categories')->insert(array (
            
            array (
                'id' => 1,
                'name' => 'الجهاز التنفسي',
            ),
            
            array (
                'id' => 2,
                'name' => 'antiacid',
            ),
            
            array (
                'id' => 3,
                'name' => 'antibiotic',
            ),
            
            array (
                'id' => 4,
                'name' => 'HAIR',
            ),
            
            array (
                'id' => 5,
                'name' => 'analgesic',
            ),
            
            array (
                'id' => 6,
                'name' => 'face care',
            ),
            
            array (
                'id' => 7,
                'name' => 'sleeping pills',
            ),
            
            array (
                'id' => 8,
                'name' => 'deodorant',
            ),
            
            array (
                'id' => 9,
                'name' => 'SKIN CARE',
            ),
            
            array (
                'id' => 10,
                'name' => 'baby food',
            ),
            
            array (
                'id' => 11,
                'name' => 'supplement facts',
            ),
            
            array (
                'id' => 12,
                'name' => 'DEVICES MACHINE',
            ),
            
            array (
                'id' => 13,
                'name' => 'ANTI HYPERTENSIVE',
            ),
            
            array (
                'id' => 14,
                'name' => 'HYIGENE',
            ),
            
            array (
                'id' => 15,
                'name' => 'ANTIFUNGAL',
            ),
            
            array (
                'id' => 16,
                'name' => 'ASTHMA DEVICE',
            ),
            
            array (
                'id' => 17,
                'name' => 'sun block',
            ),
            
            array (
                'id' => 18,
                'name' => 'oral cavity',
            ),
            
            array (
                'id' => 19,
                'name' => 'antiallergic',
            ),
            
            array (
                'id' => 20,
                'name' => 'antiviral',
            ),
            
            array (
                'id' => 21,
                'name' => 'lip care',
            ),
            
            array (
                'id' => 22,
                'name' => 'foot care',
            ),
            
            array (
                'id' => 23,
                'name' => 'antihyperuricemia',
            ),
            
            array (
                'id' => 24,
                'name' => 'ANTIDIABETICS',
            ),
            
            array (
                'id' => 25,
                'name' => 'REPRODUCTIVE SYSTEM',
            ),
            
            array (
                'id' => 26,
                'name' => 'HAIR CARE',
            ),
            
            array (
                'id' => 27,
                'name' => 'ANTIHEMORRHOIDS',
            ),
            
            array (
                'id' => 28,
                'name' => 'ANTI VARICOSE',
            ),
            
            array (
                'id' => 29,
                'name' => 'NAILS',
            ),
            
            array (
                'id' => 30,
                'name' => 'CANDY',
            ),
            
            array (
                'id' => 31,
                'name' => 'VAGINAL  WASH',
            ),
            
            array (
                'id' => 32,
                'name' => 'BABIES',
            ),
            
            array (
                'id' => 33,
                'name' => 'HYPOTHYROIDE',
            ),
            
            array (
                'id' => 34,
                'name' => 'CONTRACEPTIVE',
            ),
            
            array (
                'id' => 35,
                'name' => 'ANTI COUGH',
            ),
            
            array (
                'id' => 36,
                'name' => 'eye care',
            ),
            
            array (
                'id' => 37,
                'name' => 'BABY CARE',
            ),
            
            array (
                'id' => 38,
                'name' => 'تجميل',
            ),
            
            array (
                'id' => 39,
                'name' => 'FIRST AID',
            ),
        ));
        
        
    }
}