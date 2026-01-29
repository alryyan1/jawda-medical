<?php

namespace Database\Seeders\system_setup_seeders;

use Illuminate\Database\Seeder;

class CbcBindingsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('cbc_bindings')->delete();
        
        \DB::table('cbc_bindings')->insert(array (
            
            array (
                'id' => 2,
                'child_id_array' => '44,72,11,120',
                'name_in_sysmex_table' => 'wbc',
            ),
            
            array (
                'id' => 3,
                'child_id_array' => '45',
                'name_in_sysmex_table' => 'rbc',
            ),
            
            array (
                'id' => 4,
                'child_id_array' => '46,70',
                'name_in_sysmex_table' => 'hgb',
            ),
            
            array (
                'id' => 5,
                'child_id_array' => '47,71',
                'name_in_sysmex_table' => 'hct',
            ),
            
            array (
                'id' => 6,
                'child_id_array' => '48',
                'name_in_sysmex_table' => 'mcv',
            ),
            
            array (
                'id' => 7,
                'child_id_array' => '52',
                'name_in_sysmex_table' => 'mch',
            ),
            
            array (
                'id' => 8,
                'child_id_array' => '53',
                'name_in_sysmex_table' => 'mchc',
            ),
            
            array (
                'id' => 9,
                'child_id_array' => '54',
                'name_in_sysmex_table' => 'plt',
            ),
            
            array (
                'id' => 10,
                'child_id_array' => '55,122',
                'name_in_sysmex_table' => 'lym_p',
            ),
            
            array (
                'id' => 11,
                'child_id_array' => '56,121',
                'name_in_sysmex_table' => 'neut_p',
            ),
            
            array (
                'id' => 12,
                'child_id_array' => '57',
                'name_in_sysmex_table' => 'mxd_p',
            ),
            
            array (
                'id' => 13,
                'child_id_array' => '193',
                'name_in_sysmex_table' => 'lym_c',
            ),
            
            array (
                'id' => 14,
                'child_id_array' => '194',
                'name_in_sysmex_table' => 'mxd_c',
            ),
            
            array (
                'id' => 15,
                'child_id_array' => '195',
                'name_in_sysmex_table' => 'rdw_sd',
            ),
            
            array (
                'id' => 16,
                'child_id_array' => '196',
                'name_in_sysmex_table' => 'rdw_cv',
            ),
            
            array (
                'id' => 17,
                'child_id_array' => '197',
                'name_in_sysmex_table' => 'pdw',
            ),
            
            array (
                'id' => 18,
                'child_id_array' => '198',
                'name_in_sysmex_table' => 'plcr',
            ),
            
            array (
                'id' => 19,
                'child_id_array' => '200',
                'name_in_sysmex_table' => 'neut_c',
            ),
            
            array (
                'id' => 20,
                'child_id_array' => '201',
                'name_in_sysmex_table' => 'mpv',
            ),
        ));
        
        
    }
}