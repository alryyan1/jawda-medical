<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MainTest;
use App\Models\ChildGroup;
use App\Models\ChildTest;
use App\Models\Container;

class SemenAnalysisTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if semen_analysis test already exists
        if (MainTest::where('main_test_name', 'semen_analysis')->exists()) {
            $this->command->info('Semen Analysis test already exists. Skipping...');
            return;
        }

        $this->command->info('Creating Semen Analysis Test...');

        // Get the first available container
        $container = Container::first();
        if (!$container) {
            $this->command->error('No containers found. Please create a container first.');
            return;
        }

        // Create the main test
        $mainTest = MainTest::create([
            'main_test_name' => 'semen_analysis',
            'pack_id' => null,
            'pageBreak' => false,
            'container_id' => $container->id,
            'price' => null,
            'divided' => true,
            'available' => true,
            'is_special_test' => true,
        ]);

        $this->command->info("Created main test: semen_analysis (ID: {$mainTest->id})");

        // Create child groups
        $childGroups = [
            'PERSONAL INFORMATION',
            'PHYSICO – CHEMICAL PROPERTIES',
            'MORPHOLOGY',
            'STATISTICS'
        ];

        $groupIds = [];
        foreach ($childGroups as $groupName) {
            // Check if group already exists
            $group = ChildGroup::firstOrCreate(['name' => $groupName]);
            $groupIds[$groupName] = $group->id;
            $this->command->info("Created/found child group: {$groupName} (ID: {$group->id})");
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
        $totalTests = 0;
        foreach ($childTests as $groupName => $tests) {
            $groupId = $groupIds[$groupName];
            $this->command->info("Creating tests for group: {$groupName}");
            
            foreach ($tests as $testName) {
                // Check if test already exists for this main test
                $existingTest = ChildTest::where('main_test_id', $mainTest->id)
                    ->where('child_test_name', $testName)
                    ->first();
                
                if (!$existingTest) {
                    $childTest = ChildTest::create([
                        'main_test_id' => $mainTest->id,
                        'child_test_name' => $testName,
                        'child_group_id' => $groupId,
                        'test_order' => $testOrder++,
                        'normalRange' => '',
                        'defval' => '',
                    ]);
                    $this->command->info("  Created: {$testName} (ID: {$childTest->id})");
                    $totalTests++;
                } else {
                    $this->command->info("  Already exists: {$testName}");
                }
            }
        }

        $this->command->info("✅ Semen Analysis test created successfully!");
        $this->command->info("Main Test ID: {$mainTest->id}");
        $this->command->info("Total child tests created: {$totalTests}");
    }
}