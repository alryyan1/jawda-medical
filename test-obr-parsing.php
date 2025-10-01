<?php

require_once 'vendor/autoload.php';

use Aranyasen\HL7\Message;

echo "OBR Segment Parsing Test\n";
echo "========================\n\n";

// Your HL7 message
$hl7Message = "MSH|^~\\&|ACON|HA-360|389C0002C48||20250723203219||ORU^R01|1|P|2.3.1||||||UNICODE
PID|1|||| |||U
PV1|1||^|
OBR|1||5676|00001^Automated Count^ACC|||20250723195102|||||||||||||||||HM||||||||administrator
OBX|1|IS|5001^Take Mode^ACC||O||||||F";

try {
    // Parse the message
    $msg = new Message($hl7Message);
    
    echo "✅ HL7 Message parsed successfully\n\n";
    
    // Display all segments
    echo "All Segments:\n";
    echo "-------------\n";
    foreach ($msg->getSegments() as $index => $segment) {
        echo "Segment {$index}: " . $segment->getName() . "\n";
    }
    
    echo "\nMSH Segment Fields:\n";
    echo "------------------\n";
    $msh = $msg->getSegmentByIndex(0);
    for ($i = 1; $i <= 20; $i++) {
        $field = $msh->getField($i);
        if ($field !== null && $field !== '') {
            echo "MSH Field {$i}: '{$field}'\n";
        }
    }
    
    echo "\nOBR Segment Fields:\n";
    echo "------------------\n";
    $obr = $msg->getSegmentByIndex(3); // OBR is the 4th segment (index 3)
    if ($obr && $obr->getName() === 'OBR') {
        for ($i = 1; $i <= 20; $i++) {
            $field = $obr->getField($i);
            if ($field !== null && $field !== '') {
                echo "OBR Field {$i}: '{$field}'\n";
            }
        }
        
        echo "\nKey OBR Fields:\n";
        echo "---------------\n";
        echo "OBR Field 1 (Set ID): '" . $obr->getField(1) . "'\n";
        echo "OBR Field 2 (Placer Order Number): '" . $obr->getField(2) . "'\n";
        echo "OBR Field 3 (Filler Order Number): '" . $obr->getField(3) . "' ← This is 5676!\n";
        echo "OBR Field 4 (Universal Service ID): '" . $obr->getField(4) . "'\n";
        echo "OBR Field 5 (Priority): '" . $obr->getField(5) . "'\n";
        echo "OBR Field 6 (Requested Date/Time): '" . $obr->getField(6) . "'\n";
        echo "OBR Field 7 (Observation Date/Time): '" . $obr->getField(7) . "'\n";
        
    } else {
        echo "❌ OBR segment not found\n";
    }
    
    echo "\nHow to Extract Doctor Visit ID (5676):\n";
    echo "-------------------------------------\n";
    echo "The number 5676 is in OBR Field 3 (Filler Order Number)\n";
    echo "To get it, you need to:\n";
    echo "1. Find the OBR segment\n";
    echo "2. Get field 3 from the OBR segment\n";
    echo "\nCode example:\n";
    echo "\$obr = \$msg->getSegmentByName('OBR');\n";
    echo "\$doctorVisitId = \$obr->getField(3);\n";
    echo "// Result: '5676'\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
