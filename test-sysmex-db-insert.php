<?php

// Bootstrap Laravel application
require_once 'vendor/autoload.php';

// Create Laravel application instance
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\HL7\Devices\SysmexCbcInserter;
use App\Models\Doctorvisit;
use App\Models\SysmexResult;
use Illuminate\Support\Facades\DB;

echo "Sysmex CBC Database Insertion Test\n";
echo "===================================\n\n";

try {
    // Create instance
    $sysmexInserter = new SysmexCbcInserter();

    // Check if doctor visit ID 1 exists
    echo "1. Checking Doctor Visit ID 1\n";
    echo "------------------------------\n";
    $doctorVisit = Doctorvisit::find(1);
    if (!$doctorVisit) {
        echo "❌ Doctor visit ID 1 not found. Creating a test record...\n";
        
        // Create a test doctor visit record
        $doctorVisit = Doctorvisit::create([
            'id' => 1,
            'patient_id' => 1,
            'doctor_id' => 1,
            'visit_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ Test doctor visit created with ID 1\n";
    } else {
        echo "✅ Doctor visit ID 1 found\n";
        echo "   Patient ID: " . ($doctorVisit->patient_id ?? 'N/A') . "\n";
        echo "   Doctor ID: " . ($doctorVisit->doctor_id ?? 'N/A') . "\n";
        echo "   Visit Date: " . ($doctorVisit->visit_date ?? 'N/A') . "\n";
    }

    // Sample CBC data (from ACON device)
    $cbcResults = [
        'WBC' => ['value' => '7.0', 'unit' => '10*9/L', 'reference_range' => '4.0-10.0', 'abnormal_flag' => '', 'status' => 'F'],
        'LYM#' => ['value' => '2.8', 'unit' => '10*9/L', 'reference_range' => '0.8-5.5', 'abnormal_flag' => '', 'status' => 'F'],
        'MXD#' => ['value' => '0.2', 'unit' => '10*9/L', 'reference_range' => '0.1-1.5', 'abnormal_flag' => '', 'status' => 'F'],
        'NEUT#' => ['value' => '4.0', 'unit' => '10*9/L', 'reference_range' => '2.0-7.0', 'abnormal_flag' => '', 'status' => 'F'],
        'LYM%' => ['value' => '40.6', 'unit' => '%', 'reference_range' => '20.0-55.0', 'abnormal_flag' => '', 'status' => 'F'],
        'MXD%' => ['value' => '2.8', 'unit' => '%', 'reference_range' => '3.0-15.0', 'abnormal_flag' => '↓', 'status' => 'F'],
        'NEUT%' => ['value' => '56.6', 'unit' => '%', 'reference_range' => '50.0-70.0', 'abnormal_flag' => '', 'status' => 'F'],
        'RBC' => ['value' => '4.61', 'unit' => '10*12/L', 'reference_range' => '3.50-5.50', 'abnormal_flag' => '', 'status' => 'F'],
        'HGB' => ['value' => '10.7', 'unit' => 'g/dL', 'reference_range' => '11.0-16.0', 'abnormal_flag' => '↓', 'status' => 'F'],
        'HCT' => ['value' => '36.8', 'unit' => '%', 'reference_range' => '35.0-54.0', 'abnormal_flag' => '', 'status' => 'F'],
        'MCV' => ['value' => '79.8', 'unit' => 'fL', 'reference_range' => '80.0-100.0', 'abnormal_flag' => '↓', 'status' => 'F'],
        'MCH' => ['value' => '23.2', 'unit' => 'pg', 'reference_range' => '27.0-34.0', 'abnormal_flag' => '↓', 'status' => 'F'],
        'MCHC' => ['value' => '29.0', 'unit' => 'g/dL', 'reference_range' => '32.0-36.0', 'abnormal_flag' => '↓', 'status' => 'F'],
        'RDW-CV' => ['value' => '14.3', 'unit' => '%', 'reference_range' => '11.0-16.0', 'abnormal_flag' => '', 'status' => 'F'],
        'RDW-SD' => ['value' => '42.2', 'unit' => 'fL', 'reference_range' => '35.0-56.0', 'abnormal_flag' => '', 'status' => 'F'],
        'PLT' => ['value' => '464', 'unit' => '10*9/L', 'reference_range' => '100-300', 'abnormal_flag' => '↑', 'status' => 'F'],
        'MPV' => ['value' => '7.8', 'unit' => 'fL', 'reference_range' => '6.5-12.0', 'abnormal_flag' => '', 'status' => 'F'],
        'PDW' => ['value' => '8.9', 'unit' => 'fL', 'reference_range' => '9.0-18.0', 'abnormal_flag' => '↓', 'status' => 'F'],
        'PCT' => ['value' => '0.361', 'unit' => '%', 'reference_range' => '0.150-0.350', 'abnormal_flag' => '↑', 'status' => 'F'],
        'PLCC' => ['value' => '59', 'unit' => '10*9/L', 'reference_range' => '30-90', 'abnormal_flag' => '', 'status' => 'F'],
        'PLCR' => ['value' => '12.6', 'unit' => '%', 'reference_range' => '11.0-45.0', 'abnormal_flag' => '', 'status' => 'F'],
    ];

    // Sample patient information
    $patientInfo = [
        'patient_id' => 'P123456',
        'name' => 'John Doe',
        'dob' => '1980-01-15',
        'gender' => 'M'
    ];

    echo "\n2. Testing CBC Data Validation\n";
    echo "-------------------------------\n";
    $validation = $sysmexInserter->validateCbcData($cbcResults);
    if ($validation['valid']) {
        echo "✅ CBC data validation passed\n";
    } else {
        echo "❌ CBC data validation failed:\n";
        foreach ($validation['errors'] as $error) {
            echo "   - {$error}\n";
        }
        exit(1);
    }

    echo "\n3. Testing Database Insertion\n";
    echo "-----------------------------\n";
    
    // Check current Sysmex records count
    $beforeCount = SysmexResult::count();
    echo "Sysmex records before insertion: {$beforeCount}\n";
    
    // Insert CBC data
    $result = $sysmexInserter->insertCbcData($cbcResults, 1, $patientInfo);
    
    if ($result['success']) {
        echo "✅ CBC data inserted successfully!\n";
        echo "   Sysmex ID: " . $result['sysmex_id'] . "\n";
        echo "   Message: " . $result['message'] . "\n";
        
        // Check new count
        $afterCount = SysmexResult::count();
        echo "Sysmex records after insertion: {$afterCount}\n";
        echo "Records added: " . ($afterCount - $beforeCount) . "\n";
        
    } else {
        echo "❌ Insertion failed: " . $result['message'] . "\n";
        exit(1);
    }

    echo "\n4. Verifying Inserted Data\n";
    echo "--------------------------\n";
    
    // Get the inserted record
    $insertedRecord = SysmexResult::find($result['sysmex_id']);
    if ($insertedRecord) {
        echo "✅ Record found in database\n";
        echo "   ID: " . $insertedRecord->id . "\n";
        echo "   Doctor Visit ID: " . $insertedRecord->doctorvisit_id . "\n";
        echo "   Patient ID: " . ($insertedRecord->patient_id ?? 'N/A') . "\n";
        echo "   Patient Name: " . ($insertedRecord->patient_name ?? 'N/A') . "\n";
        echo "   Created At: " . $insertedRecord->created_at . "\n";
        
        // Show some key CBC values
        echo "\n   Key CBC Values:\n";
        echo "   WBC: " . ($insertedRecord->wbc ?? 'N/A') . "\n";
        echo "   RBC: " . ($insertedRecord->rbc ?? 'N/A') . "\n";
        echo "   HGB: " . ($insertedRecord->hgb ?? 'N/A') . "\n";
        echo "   PLT: " . ($insertedRecord->plt ?? 'N/A') . "\n";
        
    } else {
        echo "❌ Record not found in database\n";
    }

    echo "\n5. Testing Data Retrieval\n";
    echo "-------------------------\n";
    
    // Test getting latest result
    $latestResult = $sysmexInserter->getLatestSysmexResult(1);
    if ($latestResult) {
        echo "✅ Latest result retrieved successfully\n";
        echo "   Sysmex ID: " . $latestResult->id . "\n";
        echo "   WBC: " . ($latestResult->wbc ?? 'N/A') . "\n";
        echo "   HGB: " . ($latestResult->hgb ?? 'N/A') . "\n";
    } else {
        echo "❌ No results found for doctor visit ID 1\n";
    }

    echo "\n6. Testing CBC Data Extraction\n";
    echo "------------------------------\n";
    
    if ($latestResult) {
        $extractedCbcData = $sysmexInserter->getCbcDataFromSysmex($latestResult);
        echo "✅ CBC data extracted successfully\n";
        echo "   Parameters extracted: " . count($extractedCbcData) . "\n";
        
        // Show first few parameters
        $count = 0;
        foreach ($extractedCbcData as $parameter => $data) {
            if ($count < 5) {
                echo "   {$parameter}: {$data['value']}\n";
                $count++;
            }
        }
        if (count($extractedCbcData) > 5) {
            echo "   ... and " . (count($extractedCbcData) - 5) . " more parameters\n";
        }
    }

    echo "\n✅ All database tests completed successfully!\n";
    echo "\nSummary:\n";
    echo "- Doctor visit ID 1: " . ($doctorVisit ? "Found/Created" : "Not found") . "\n";
    echo "- CBC data validation: Passed\n";
    echo "- Database insertion: " . ($result['success'] ? "Success" : "Failed") . "\n";
    echo "- Data verification: " . ($insertedRecord ? "Success" : "Failed") . "\n";
    echo "- Data retrieval: " . ($latestResult ? "Success" : "Failed") . "\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
