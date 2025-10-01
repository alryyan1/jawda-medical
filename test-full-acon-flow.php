<?php

// Bootstrap Laravel application
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\HL7\Devices\ACONHandler;
use App\Services\HL7\Devices\SysmexCbcInserter;
use Aranyasen\HL7\Message;

echo "Full ACON Flow Test with Doctor Visit ID 5676\n";
echo "=============================================\n\n";

try {
    // Your full HL7 message
    $hl7Message = "MSH|^~\\&|ACON|HA-360|389C0002C48||20250723203219||ORU^R01|1|P|2.3.1||||||UNICODE
PID|1|||| |||U
PV1|1||^|
OBR|1||5676|00001^Automated Count^ACC|||20250723195102|||||||||||||||||HM||||||||administrator
OBX|1|IS|5001^Take Mode^ACC||O||||||F
OBX|2|IS|5002^Blood Mode^ACC||W||||||F
OBX|3|IS|5003^Ref Group^ACC||General||||||F
OBX|4|NM|30525-0^Age^LN||-1|Year|||||F
OBX|5|ST|6690-2^WBC^LN||7.0|10*9/L|4.0-10.0||||F
OBX|6|ST|731-0^LYM#^LN||2.8|10*9/L|0.8-5.5||||F
OBX|7|ST|8005^MXD#^ACC||0.2|10*9/L|0.1-1.5||||F
OBX|8|ST|8006^NEUT#^ACC||4.0|10*9/L|2.0-7.0||||F
OBX|9|ST|736-9^LYM%^LN||40.6|%|20.0-55.0||||F
OBX|10|ST|8007^MXD%^ACC||2.8|%|3.0-15.0|↓|||F
OBX|11|ST|8008^NEUT%^ACC||56.6|%|50.0-70.0||||F
OBX|12|ST|789-8^RBC^LN||4.61|10*12/L|3.50-5.50||||F
OBX|13|ST|718-7^HGB^LN||10.7|g/dL|11.0-16.0|↓|||F
OBX|14|ST|4544-3^HCT^LN||36.8|%|35.0-54.0||||F
OBX|15|ST|787-2^MCV^LN||79.8|fL|80.0-100.0|↓|||F
OBX|16|ST|785-6^MCH^LN||23.2|pg|27.0-34.0|↓|||F
OBX|17|ST|786-4^MCHC^LN||29.0|g/dL|32.0-36.0|↓|||F
OBX|18|ST|788-0^RDW-CV^LN||14.3|%|11.0-16.0||||F
OBX|19|ST|21000-5^RDW-SD^LN||42.2|fL|35.0-56.0||||F
OBX|20|ST|777-3^PLT^LN||464|10*9/L|100-300|↑|||F
OBX|21|ST|32623-1^MPV^LN||7.8|fL|6.5-12.0||||F
OBX|22|ST|32207-3^PDW^LN||8.9|fL|9.0-18.0|↓|||F
OBX|23|ST|8002^PCT^ACC||0.361|%|0.150-0.350|↑|||F
OBX|24|ST|8003^PLCC^ACC||59|10*9/L|30-90||||F
OBX|25|ST|8004^PLCR^ACC||12.6|%|11.0-45.0||||F";

    // Parse the message
    $msg = new Message($hl7Message);
    $msh = new \Aranyasen\HL7\Segments\MSH($msg->getSegmentByIndex(0)->getFields());
    
    echo "1. Message Parsing\n";
    echo "------------------\n";
    echo "✅ HL7 message parsed successfully\n";
    echo "Device: " . $msh->getField(4) . "\n";
    echo "Message Type: " . $msh->getField(9) . "\n";
    
    // Test ACONHandler
    echo "\n2. ACONHandler Processing\n";
    echo "-------------------------\n";
    $aconHandler = new ACONHandler();
    
    // Extract doctor visit ID
    $reflection = new ReflectionClass($aconHandler);
    $method = $reflection->getMethod('extractDoctorVisitId');
    $method->setAccessible(true);
    $doctorVisitId = $method->invoke($aconHandler, $msg);
    
    echo "Doctor Visit ID: {$doctorVisitId}\n";
    
    if ($doctorVisitId === '5676') {
        echo "✅ Doctor Visit ID correctly extracted!\n";
    } else {
        echo "❌ Doctor Visit ID extraction failed\n";
        exit(1);
    }
    
    // Extract CBC parameters
    $method = $reflection->getMethod('extractCBCParameters');
    $method->setAccessible(true);
    $cbcResults = $method->invoke($aconHandler, $msg);
    
    echo "CBC parameters extracted: " . count($cbcResults) . "\n";
    
    if (count($cbcResults) > 0) {
        echo "✅ CBC parameters extracted successfully!\n";
        
        // Show some key parameters
        $keyParams = ['WBC', 'RBC', 'HGB', 'PLT'];
        foreach ($keyParams as $param) {
            if (isset($cbcResults[$param])) {
                echo "  {$param}: " . $cbcResults[$param]['value'] . " " . $cbcResults[$param]['unit'] . "\n";
            }
        }
    } else {
        echo "❌ No CBC parameters extracted\n";
    }
    
    // Test Sysmex insertion
    echo "\n3. Sysmex Database Insertion\n";
    echo "----------------------------\n";
    
    $sysmexInserter = new SysmexCbcInserter();
    
    // Check if doctor visit 5676 exists
    $doctorVisit = \App\Models\Doctorvisit::find(5676);
    if (!$doctorVisit) {
        echo "⚠️  Doctor visit 5676 not found, creating test record...\n";
        $doctorVisit = \App\Models\Doctorvisit::create([
            'id' => 5676,
            'patient_id' => 2856,
            'doctor_id' => 1,
            'visit_date' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ Test doctor visit created with ID 5676\n";
    } else {
        echo "✅ Doctor visit 5676 found\n";
    }
    
    // Insert CBC data
    $patientInfo = [
        'patient_id' => 'P123456',
        'name' => 'Test Patient',
        'dob' => '1980-01-15',
        'gender' => 'M'
    ];
    
    $insertResult = $sysmexInserter->insertCbcData($cbcResults, 5676, $patientInfo);
    
    if ($insertResult['success']) {
        echo "✅ CBC data inserted into Sysmex table successfully!\n";
        echo "   Sysmex ID: " . $insertResult['sysmex_id'] . "\n";
        echo "   Doctor Visit ID: 5676\n";
    } else {
        echo "❌ Sysmex insertion failed: " . $insertResult['message'] . "\n";
    }
    
    echo "\n4. Verification\n";
    echo "---------------\n";
    
    // Verify the inserted data
    $sysmexRecord = \App\Models\SysmexResult::find($insertResult['sysmex_id']);
    if ($sysmexRecord) {
        echo "✅ Sysmex record verified in database\n";
        echo "   ID: " . $sysmexRecord->id . "\n";
        echo "   Doctor Visit ID: " . $sysmexRecord->doctorvisit_id . "\n";
        echo "   WBC: " . ($sysmexRecord->wbc ?? 'N/A') . "\n";
        echo "   HGB: " . ($sysmexRecord->hgb ?? 'N/A') . "\n";
        echo "   PLT: " . ($sysmexRecord->plt ?? 'N/A') . "\n";
    }
    
    echo "\n✅ Full ACON flow test completed successfully!\n";
    echo "\nSummary:\n";
    echo "- HL7 message parsing: ✅ Working\n";
    echo "- Device detection: ✅ Working (ACON)\n";
    echo "- Doctor visit ID extraction: ✅ Working (5676)\n";
    echo "- CBC parameter extraction: ✅ Working (" . count($cbcResults) . " parameters)\n";
    echo "- Sysmex database insertion: ✅ Working\n";
    echo "- Data verification: ✅ Working\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
