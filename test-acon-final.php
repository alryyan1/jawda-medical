<?php

require_once 'vendor/autoload.php';

use Aranyasen\HL7\Message;

// Full ACON HL7 message
$hl7Message = "MSH|^~\\&|ACON|HA-360|389C0002C48||20250723203219||ORU^R01|1|P|2.3.1||||||UNICODE
PID|1|||| |||U
PV1|1||^|
OBR|1||5676|00001^Automated Count^ACC|||20250723195102|||||||||||||||||HM||||||||administrator
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

echo "Testing ACON HL7 Message Parsing (Final)\n";
echo "========================================\n\n";

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
        '8005' => 'MXD#',
        '8006' => 'NEUT#',
        '736-9' => 'LYM%',
        '8007' => 'MXD%',
        '8008' => 'NEUT%',
        '789-8' => 'RBC',
        '718-7' => 'HGB',
        '4544-3' => 'HCT',
        '787-2' => 'MCV',
        '785-6' => 'MCH',
        '786-4' => 'MCHC',
        '788-0' => 'RDW-CV',
        '21000-5' => 'RDW-SD',
        '777-3' => 'PLT',
        '32623-1' => 'MPV',
        '32207-3' => 'PDW',
        '8002' => 'PCT',
        '8003' => 'PLCC',
        '8004' => 'PLCR',
    ];
    
    $cbcResults = [];
    
    // Process OBX segments
    foreach ($msg->getSegments() as $segment) {
        if ($segment->getName() === 'OBX') {
            $fields = $segment->getFields();
            
            // Handle array fields properly
            $observationIdentifier = is_array($fields[3] ?? '') ? implode('^', $fields[3]) : ($fields[3] ?? '');
            $observationValue = is_array($fields[5] ?? '') ? implode('^', $fields[5]) : ($fields[5] ?? '');
            $units = is_array($fields[6] ?? '') ? implode('^', $fields[6]) : ($fields[6] ?? '');
            $referenceRange = is_array($fields[7] ?? '') ? implode('^', $fields[7]) : ($fields[7] ?? '');
            $abnormalFlag = is_array($fields[8] ?? '') ? implode('^', $fields[8]) : ($fields[8] ?? '');
            
            // Extract test code and name from observation identifier
            $testParts = explode('^', $observationIdentifier);
            $testCode = $testParts[0] ?? '';
            $testName = $testParts[1] ?? $testCode;
            
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
            }
        }
    }
    
    echo "CBC Parameters Extracted:\n";
    echo "========================\n";
    foreach ($cbcResults as $parameter => $result) {
        $flag = $result['abnormal_flag'] ? " ({$result['abnormal_flag']})" : '';
        echo sprintf("%-8s: %-8s %-10s [%s]%s\n", 
            $parameter, 
            $result['value'], 
            $result['unit'], 
            $result['reference_range'],
            $flag
        );
    }
    
    echo "\n✅ ACON parsing test completed successfully!\n";
    echo "Total CBC parameters extracted: " . count($cbcResults) . "\n";
    
    // Summary of abnormal results
    $abnormalResults = array_filter($cbcResults, function($result) {
        return !empty($result['abnormal_flag']);
    });
    
    if (!empty($abnormalResults)) {
        echo "\nAbnormal Results:\n";
        echo "================\n";
        foreach ($abnormalResults as $parameter => $result) {
            echo "• $parameter: {$result['value']} {$result['unit']} ({$result['abnormal_flag']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
