<?php

require_once 'vendor/autoload.php';

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;

echo "MSH Parsing Test\n";
echo "================\n\n";

// Your HL7 message
$hl7Message = "MSH|^~\\&|ACON|HA-360|389C0002C48||20250723203219||ORU^R01|1|P|2.3.1||||||UNICODE
PID|1|||| |||U
PV1|1||^|
OBR|1||5676|00001^Automated Count^ACC|||20250723195102|||||||||||||||||HM||||||||administrator
OBX|5|ST|6690-2^WBC^LN||7.0|10*9/L|4.0-10.0||||F";

try {
    // Parse the message
    $msg = new Message($hl7Message);
    $msh = new MSH($msg->getSegmentByIndex(0)->getFields());
    
    echo "✅ HL7 Message parsed successfully\n\n";
    
    // Display all MSH fields
    echo "MSH Segment Fields:\n";
    echo "------------------\n";
    for ($i = 1; $i <= 20; $i++) {
        $field = $msh->getField($i);
        if ($field !== null && $field !== '') {
            echo "Field {$i}: '{$field}'\n";
        }
    }
    
    echo "\nKey Fields for Device Detection:\n";
    echo "-------------------------------\n";
    echo "Field 1 (Field Separator): '" . $msh->getField(1) . "'\n";
    echo "Field 2 (Encoding Characters): '" . $msh->getField(2) . "'\n";
    echo "Field 3 (Sending Application): '" . $msh->getField(3) . "'\n";
    echo "Field 4 (Sending Facility): '" . $msh->getField(4) . "'\n";
    echo "Field 5 (Receiving Application): '" . $msh->getField(5) . "'\n";
    echo "Field 6 (Receiving Facility): '" . $msh->getField(6) . "'\n";
    echo "Field 7 (Date/Time): '" . $msh->getField(7) . "'\n";
    echo "Field 8 (Security): '" . $msh->getField(8) . "'\n";
    echo "Field 9 (Message Type): '" . $msh->getField(9) . "'\n";
    echo "Field 10 (Message Control ID): '" . $msh->getField(10) . "'\n";
    
    echo "\nDevice Detection:\n";
    echo "----------------\n";
    $device = $msh->getField(4);
    echo "Detected Device: '{$device}'\n";
    
    if ($device === 'ACON') {
        echo "✅ Device correctly identified as ACON\n";
    } else {
        echo "❌ Device not correctly identified\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
