<?php

require_once 'vendor/autoload.php';

use Aranyasen\HL7\Message;
use App\Services\HL7\Devices\ACONHandler;

// Sample ACON HL7 message
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

echo "Testing ACON HL7 Message Parsing\n";
echo "================================\n\n";

try {
    // Parse the HL7 message
    $msg = new Message($hl7Message);
    $msh = new \Aranyasen\HL7\Segments\MSH($msg->getSegmentByIndex(0)->getFields());
    
    echo "✅ HL7 Message parsed successfully\n";
    echo "Message Type: " . $msh->getField(8) . "\n";
    echo "Sending Application: " . $msh->getField(2) . "\n";
    echo "Sending Facility: " . $msh->getField(3) . "\n";
    echo "Device Identifier: " . $msh->getField(3) . "\n\n";
    
    // Create ACON handler
    $aconHandler = new ACONHandler();
    
    // Extract patient info
    $reflection = new ReflectionClass($aconHandler);
    $method = $reflection->getMethod('extractPatientInfo');
    $method->setAccessible(true);
    $patientInfo = $method->invoke($aconHandler, $msg);
    
    echo "Patient Information:\n";
    echo "-------------------\n";
    foreach ($patientInfo as $key => $value) {
        echo "$key: " . ($value ?? 'N/A') . "\n";
    }
    echo "\n";
    
    // Debug: Show all segments
    echo "All Segments in Message:\n";
    echo "=======================\n";
    foreach ($msg->getSegments() as $index => $segment) {
        echo "Segment $index: " . $segment->getName() . "\n";
        if ($segment->getName() === 'OBX') {
            $fields = $segment->getFields();
            $displayFields = [];
            foreach (array_slice($fields, 0, 8) as $field) {
                if (is_array($field)) {
                    $displayFields[] = implode('^', $field);
                } else {
                    $displayFields[] = $field;
                }
            }
            echo "  OBX Fields: " . implode('|', $displayFields) . "\n";
        }
    }
    echo "\n";
    
    // Extract CBC parameters
    $method = $reflection->getMethod('extractCBCParameters');
    $method->setAccessible(true);
    $cbcResults = $method->invoke($aconHandler, $msg);
    
    echo "CBC Parameters Extracted:\n";
    echo "========================\n";
    foreach ($cbcResults as $parameter => $result) {
        echo "$parameter:\n";
        echo "  Test Code: " . $result['test_code'] . "\n";
        echo "  Test Name: " . $result['test_name'] . "\n";
        echo "  Value: " . $result['value'] . "\n";
        echo "  Unit: " . $result['unit'] . "\n";
        echo "  Reference Range: " . $result['reference_range'] . "\n";
        echo "  Abnormal Flag: " . $result['abnormal_flag'] . "\n";
        echo "  Status: " . $result['status'] . "\n\n";
    }
    
    echo "✅ ACON parsing test completed successfully!\n";
    echo "Total CBC parameters extracted: " . count($cbcResults) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
