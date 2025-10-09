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
            'pack_id' => 1,
            'pageBreak' => false,
            'container_id' => $container->id,
            'price' => 0,
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

        // Define child tests for each group with reference values
        // Format: 'test_name' => ['normalRange' => 'text', 'lower_limit' => float, 'mean' => float, 'upper_limit' => float]
        $childTests = [
            'PERSONAL INFORMATION' => [
                'Ejaculate:' => ['normalRange' => ''],
                'Collection method:' => ['normalRange' => ''],
                'Collection site:' => ['normalRange' => ''],
                'Collection time' => ['normalRange' => ''],
                'Difficulties in collection:' => ['normalRange' => ''],
                'Complete sample:' => ['normalRange' => ''],
                'Abstinence days:' => ['normalRange' => '']
            ],
            'PHYSICO – CHEMICAL PROPERTIES' => [
                'Volume (ml)' => ['normalRange' => '1.4'],
                'Appearance' => ['normalRange' => 'Clear – Gray – Milky'],
                'Viscosity' => ['normalRange' => '15-30 minutes'],
                'Liquefaction' => ['normalRange' => 'normal'],
                'PH' => ['normalRange' => '7.00 – 7.8'],
                'Semen fructose' => ['normalRange' => 'Present'],
                'Post- ejaculatory urine analysis' => ['normalRange' => 'NIL']
            ],
            'MORPHOLOGY' => [
                'Total counted as normal morphology' => ['normalRange' => ''],
                'Normal morphology %' => ['normalRange' => ''],
                'Total counted as abnormal sperm' => ['normalRange' => ''],
                'Abnormal morphology %' => ['normalRange' => ''],
                'Head' => ['normalRange' => ''],
                'Midpiece' => ['normalRange' => ''],
                'Principal piece' => ['normalRange' => ''],
                'Residual cytoplasm' => ['normalRange' => ''],
                'Total examined sperms' => ['normalRange' => ''],
                'TZI (Terato-Zoospermic Index)' => ['normalRange' => ''],
                'TZI calculation note' => ['normalRange' => ''],
                'Normal TZI value: > 1.6' => ['normalRange' => '']
            ],
            'STATISTICS' => [
                'Total sperm number (x106  / ejaculate )' => [
                    'normalRange' => '',
                    'lower_limit' => 39,
                    'mean' => 66,
                    'upper_limit' => 208
                ],
                'Sperm concentration / ml' => [
                    'normalRange' => '',
                    'lower_limit' => 16,
                    'mean' => 210,
                    'upper_limit' => 561
                ],
                'Motility / PR+ NP (grades A ,B,C)' => [
                    'normalRange' => '',
                    'lower_limit' => 42,
                    'mean' => 64,
                    'upper_limit' => 90
                ],
                'Rapidly progressive PR (grade A)' => [
                    'normalRange' => '',
                    'lower_limit' => 30,
                    'mean' => 55,
                    'upper_limit' => 77
                ],
                'Slow progressive PR (grade B)' => [
                    'normalRange' => ''
                ],
                'NP-Sluggish (grade C)' => [
                    'normalRange' => '1 (1-1)'
                ],
                'Immotile (grade D)' => [
                    'normalRange' => '20 (19-20)'
                ],
                'Vitality' => [
                    'normalRange' => '',
                    'lower_limit' => 54,
                    'mean' => 78,
                    'upper_limit' => 97
                ],
                'WBCs' => [
                    'normalRange' => '< 1'
                ]
            ]
        ];

        // Create child tests
        $testOrder = 1;
        $totalTests = 0;
        foreach ($childTests as $groupName => $tests) {
            $groupId = $groupIds[$groupName];
            $this->command->info("Creating tests for group: {$groupName}");
            
            foreach ($tests as $testName => $testData) {
                // Extract data from the array
                $normalRange = $testData['normalRange'] ?? '';
                $lowerLimit = $testData['lower_limit'] ?? null;
                $mean = $testData['mean'] ?? null;
                $upperLimit = $testData['upper_limit'] ?? null;
                
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
                        'normalRange' => $normalRange,
                        'lower_limit' => $lowerLimit,
                        'mean' => $mean,
                        'upper_limit' => $upperLimit,
                        'defval' => '',
                    ]);
                    
                    $limitsInfo = '';
                    if ($lowerLimit !== null || $mean !== null || $upperLimit !== null) {
                        $limitsInfo = " [Lower: {$lowerLimit}, Mean: {$mean}, Upper: {$upperLimit}]";
                    }
                    
                    $this->command->info("  Created: {$testName} - Reference: {$normalRange}{$limitsInfo}");
                    $totalTests++;
                } else {
                    // Update existing test with new values
                    $updateData = [];
                    
                    if (empty($existingTest->normalRange) && !empty($normalRange)) {
                        $updateData['normalRange'] = $normalRange;
                    }
                    if ($lowerLimit !== null) {
                        $updateData['lower_limit'] = $lowerLimit;
                    }
                    if ($mean !== null) {
                        $updateData['mean'] = $mean;
                    }
                    if ($upperLimit !== null) {
                        $updateData['upper_limit'] = $upperLimit;
                    }
                    
                    if (!empty($updateData)) {
                        $existingTest->update($updateData);
                        $this->command->info("  Updated: {$testName}");
                    } else {
                        $this->command->info("  Already exists: {$testName}");
                    }
                }
            }
        }

        $this->command->info("✅ Semen Analysis test created successfully!");
        $this->command->info("Main Test ID: {$mainTest->id}");
        $this->command->info("Total child tests created: {$totalTests}");
    }
}