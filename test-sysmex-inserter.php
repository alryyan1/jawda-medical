<?php

require_once 'vendor/autoload.php';

use App\Services\HL7\Devices\SysmexCbcInserter;
use App\Services\HL7\Devices\ACONHandler;

echo "Sysmex CBC Inserter Test\n";
echo "========================\n\n";

try {
    // Create instances
    $sysmexInserter = new SysmexCbcInserter();
    $aconHandler = new ACONHandler();

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

    // Test doctor visit ID (you would get this from your system)
    $doctorVisitId = 1; // Replace with actual doctor visit ID

    echo "1. Testing CBC Data Validation\n";
    echo "-------------------------------\n";
    $validation = $sysmexInserter->validateCbcData($cbcResults);
    if ($validation['valid']) {
        echo "✅ CBC data validation passed\n";
    } else {
        echo "❌ CBC data validation failed:\n";
        foreach ($validation['errors'] as $error) {
            echo "   - {$error}\n";
        }
    }

    echo "\n2. Testing Field Mapping\n";
    echo "------------------------\n";
    $fieldMapping = $sysmexInserter->getCbcToSysmexFieldMapping();
    echo "CBC Parameter -> Sysmex Field Mapping:\n";
    foreach ($fieldMapping as $cbcParam => $sysmexField) {
        echo "   {$cbcParam} -> {$sysmexField}\n";
    }

    echo "\n3. Testing CBC Data Formatting\n";
    echo "-------------------------------\n";
    $formattedResults = $aconHandler->formatCBCResults($cbcResults);
    echo $formattedResults;

    echo "\n4. Testing Sysmex Field Names\n";
    echo "-----------------------------\n";
    $sysmexFields = $sysmexInserter->getSysmexFieldNames();
    echo "Sysmex table fields: " . implode(', ', $sysmexFields) . "\n";

    echo "\n5. Testing CBC Parameter Names\n";
    echo "------------------------------\n";
    $cbcParams = $sysmexInserter->getCbcParameterNames();
    echo "CBC parameters: " . implode(', ', $cbcParams) . "\n";

    echo "\n✅ All tests completed successfully!\n";
    echo "\nNote: To test actual database insertion, you need:\n";
    echo "1. A valid doctor visit ID\n";
    echo "2. Laravel application context\n";
    echo "3. Database connection\n";
    echo "\nExample usage in Laravel:\n";
    echo "\$result = \$sysmexInserter->insertCbcData(\$cbcResults, \$doctorVisitId, \$patientInfo);\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
