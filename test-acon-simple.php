<?php

require_once 'vendor/autoload.php';

use Aranyasen\HL7\Message;

// Sample ACON HL7 message
$hl7Message = "MSH|^~\\&|ACON|HA-360|389C0002C48||20250723203219||ORU^R01|1|P|2.3.1||||||UNICODE
PID|1|||| |||U
PV1|1||^|
OBR|1||5676|00001^Automated Count^ACC|||20250723195102|||||||||||||||||HM||||||||administrator
OBX|5|ST|6690-2^WBC^LN||7.0|10*9/L|4.0-10.0||||F
OBX|6|ST|731-0^LYM#^LN||2.8|10*9/L|0.8-5.5||||F
OBX|13|ST|718-7^HGB^LN||10.7|g/dL|11.0-16.0|↓|||F
OBX|20|ST|777-3^PLT^LN||464|10*9/L|100-300|↑|||F";

echo "Testing ACON HL7 Message Parsing (Simple)\n";
echo "=========================================\n\n";

try {
    // Parse the HL7 message
    $msg = new Message($hl7Message);
    $msh = new \Aranyasen\HL7\Segments\MSH($msg->getSegmentByIndex(0)->getFields());
    
    echo "✅ HL7 Message parsed successfully\n";
    echo "Message Type: " . $msh->getField(8) . "\n";
    echo "Sending Application: " . $msh->getField(2) . "\n";
    echo "Sending Facility: " . $msh->getField(3) . "\n\n";
    
    // Define CBC parameter mappings
    $cbcMappings = [
        '6690-2' => 'WBC',
        '731-0' => 'LYM#',
        '718-7' => 'HGB',
        '777-3' => 'PLT',
    ];
    
    $cbcResults = [];
    
    // Process OBX segments
    foreach ($msg->getSegments() as $segment) {
        if ($segment->getName() === 'OBX') {
            $fields = $segment->getFields();
            
            echo "OBX Fields Debug:\n";
            foreach ($fields as $i => $field) {
                if (is_array($field)) {
                    echo "  Field $i: [" . implode('^', $field) . "]\n";
                } else {
                    echo "  Field $i: '$field'\n";
                }
            }
            
            // Handle array fields properly - OBX structure is different
            $observationIdentifier = is_array($fields[3] ?? '') ? implode('^', $fields[3]) : ($fields[3] ?? '');
            $observationValue = is_array($fields[5] ?? '') ? implode('^', $fields[5]) : ($fields[5] ?? '');
            $units = is_array($fields[6] ?? '') ? implode('^', $fields[6]) : ($fields[6] ?? '');
            $referenceRange = is_array($fields[7] ?? '') ? implode('^', $fields[7]) : ($fields[7] ?? '');
            $abnormalFlag = is_array($fields[8] ?? '') ? implode('^', $fields[8]) : ($fields[8] ?? '');
            
            echo "  Parsed - ID: '$observationIdentifier', Value: '$observationValue'\n";
            
            // Extract test code and name from observation identifier
            $testParts = explode('^', $observationIdentifier);
            $testCode = $testParts[0] ?? '';
            $testName = $testParts[1] ?? $testCode;
            
            echo "  Test Code: '$testCode', Test Name: '$testName'\n";
            
            if (isset($cbcMappings[$testCode])) {
                $parameterName = $cbcMappings[$testCode];
                
                $cbcResults[$parameterName] = [
                    'test_code' => $testCode,
                    'test_name' => $testName,
                    'value' => $observationValue,
                    'unit' => $units,
                    'reference_range' => $referenceRange,
                    'abnormal_flag' => $abnormalFlag,
                ];
                
                echo "  ✅ Added $parameterName to results\n";
            } else {
                echo "  ❌ Test code '$testCode' not in mappings\n";
            }
            echo "\n";
        }
    }
    
    echo "CBC Parameters Extracted:\n";
    echo "========================\n";
    foreach ($cbcResults as $parameter => $result) {
        echo "$parameter:\n";
        echo "  Test Code: " . $result['test_code'] . "\n";
        echo "  Test Name: " . $result['test_name'] . "\n";
        echo "  Value: " . $result['value'] . "\n";
        echo "  Unit: " . $result['unit'] . "\n";
        echo "  Reference Range: " . $result['reference_range'] . "\n";
        echo "  Abnormal Flag: " . $result['abnormal_flag'] . "\n\n";
    }
    
    echo "✅ ACON parsing test completed successfully!\n";
    echo "Total CBC parameters extracted: " . count($cbcResults) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
