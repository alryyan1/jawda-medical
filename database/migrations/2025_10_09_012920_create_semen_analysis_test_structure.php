<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the first available container ID
        $containerId = DB::table('containers')->first()?->id ?? 1;

        // Create the main test
        $mainTestId = DB::table('main_tests')->insertGetId([
            'main_test_name' => 'semen_analysis',
            'pack_id' => null,
            'pageBreak' => false,
            'container_id' => $containerId,
            'price' => null,
            'divided' => true,
            'available' => true,
            'is_special_test' => true,
        ]);

        // Create child groups
        $childGroups = [
            'PERSONAL INFORMATION',
            'PHYSICO – CHEMICAL PROPERTIES',
            'MORPHOLOGY',
            'STATISTICS'
        ];

        $groupIds = [];
        foreach ($childGroups as $groupName) {
            $groupId = DB::table('child_groups')->insertGetId(['name' => $groupName]);
            $groupIds[$groupName] = $groupId;
        }

        // Define child tests for each group
        $childTests = [
            'PERSONAL INFORMATION' => [
                'Ejaculate:',
                'Collection method:',
                'Collection site:',
                'Collection time',
                'Difficulties in collection:',
                'Complete sample:',
                'Abstinence days:'
            ],
            'PHYSICO – CHEMICAL PROPERTIES' => [
                'Volume (ml)',
                'Appearance',
                'Viscosity',
                'Liquefaction',
                'PH',
                'Semen fructose',
                'Post- ejaculatory urine analysis'
            ],
            'MORPHOLOGY' => [
                'Total counted as normal morphology',
                'Normal morphology %',
                'Total counted as abnormal sperm',
                'Abnormal morphology %',
                'Head',
                'Midpiece',
                'Principal piece',
                'Residual cytoplasm',
                'Total examined sperms',
                'TZI (Terato-Zoospermic Index)',
                'TZI calculation note',
                'Normal TZI value: > 1.6'
            ],
            'STATISTICS' => [
                'Total sperm number (x106  / ejaculate )',
                'Sperm concentration / ml',
                'Motility / PR+ NP (grades A ,B,C)',
                'Rapidly progressive PR (grade A)',
                'Slow progressive PR (grade B)',
                'NP-Sluggish (grade C)',
                'Immotile (grade D)',
                'Vitality',
                'WBCs'
            ]
        ];

        // Create child tests
        $testOrder = 1;
        foreach ($childTests as $groupName => $tests) {
            $groupId = $groupIds[$groupName];
            
            foreach ($tests as $testName) {
                DB::table('child_tests')->insert([
                    'main_test_id' => $mainTestId,
                    'child_test_name' => $testName,
                    'child_group_id' => $groupId,
                    'test_order' => $testOrder++,
                    'normalRange' => '',
                    'defval' => '',
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find and delete the semen_analysis main test and all related data
        $mainTest = DB::table('main_tests')->where('main_test_name', 'semen_analysis')->first();
        
        if ($mainTest) {
            // Delete child tests
            DB::table('child_tests')->where('main_test_id', $mainTest->id)->delete();
            
            // Delete the main test
            DB::table('main_tests')->where('id', $mainTest->id)->delete();
        }

        // Delete the child groups (only if they don't have other tests)
        $semenAnalysisGroups = [
            'PERSONAL INFORMATION',
            'PHYSICO – CHEMICAL PROPERTIES',
            'MORPHOLOGY',
            'STATISTICS'
        ];

        foreach ($semenAnalysisGroups as $groupName) {
            $group = DB::table('child_groups')->where('name', $groupName)->first();
            if ($group) {
                // Only delete if no other tests use this group
                $hasOtherTests = DB::table('child_tests')
                    ->where('child_group_id', $group->id)
                    ->exists();
                
                if (!$hasOtherTests) {
                    DB::table('child_groups')->where('id', $group->id)->delete();
                }
            }
        }
    }
};