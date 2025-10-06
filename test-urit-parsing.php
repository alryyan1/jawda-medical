<?php

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel so facades (config, log, db) are available
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\HL7\HL7MessageProcessor;
use App\Models\SysmexResult;
use App\Models\Doctorvisit;
use App\Services\HL7\Devices\SysmexCbcInserter;

// Minimal mock connection with write()
new class { public function __construct(){} };

class MockConnection {
    public array $writes = [];
    public function write(string $data): void { $this->writes[] = $data; }
}

// Choose doctor visit id: CLI arg > latest existing > fallback
$doctorVisitId = isset($argv[1]) ? (int)$argv[1] : (int)(Doctorvisit::max('id') ?? 40787);

// Build the URIT HL7 message with proper CR separators and MSH-4 = URIT
// Fields: MSH-3=2 (sending app), MSH-4=URIT (sending facility/device), MSH-5=LIS (receiving app)
$hl7 = "MSH|^~\\&|2|URIT|LIS|PC|20251003024800||ORU^R01|4|P|2.3.1||||||UNICODE\r"
    . "PID|7||10||||0|\r"
    . "OBR|5||" . $doctorVisitId . "|2^3|||20251003024700||||||||3|||||\r"
    . "OBX|1|NM|WBC||14.9|x10^9/L|3.5-11.0|H|||F|||||administrator|\r"
    . "OBX|1|NM|LYM%||20.6|%|20.0-40.0||||F|||||administrator|\r"
    . "OBX|1|NM|MID%||13.9|%|1.0-15.0||||F|||||administrator|\r"
    . "OBX|1|NM|GRAN%||65.5|%|50.0-70.0||||F|||||administrator|\r"
    . "OBX|1|NM|LYM#||3.0|x10^9/L|0.6-4.1||||F|||||administrator|\r"
    . "OBX|1|NM|MID#||2.0|x10^9/L|0.1-1.8|H|||F|||||administrator|\r"
    . "OBX|1|NM|GRAN#||9.9|x10^9/L|2.0-7.8|H|||F|||||administrator|\r"
    . "OBX|1|NM|RBC||3.71|x10^12/L|3.50-6.00||||F|||||administrator|\r"
    . "OBX|1|NM|HGB||10.7|g/dL|11.0-17.5|L|||F|||||administrator|\r"
    . "OBX|1|NM|HCT||29.8|%|35.0-54.0|L|||F|||||administrator|\r"
    . "OBX|1|NM|MCV||80.4|fL|80.0-100.0||||F|||||administrator|\r"
    . "OBX|1|NM|MCH||28.8|Pg|26.0-34.0||||F|||||administrator|\r"
    . "OBX|1|NM|MCHC||35.9|g/dL|31.5-36.0||||F|||||administrator|\r"
    . "OBX|1|NM|RDW_CV||15.5|%|11.0-16.0||||F|||||administrator|\r"
    . "OBX|1|NM|RDW_SD||49.0|fL|35.0-56.0||||F|||||administrator|\r"
    . "OBX|1|NM|PLT||471|x10^9/L|100-350|H|||F|||||administrator|\r"
    . "OBX|1|NM|MPV||6.7|fL|6.5-12.0||||F|||||administrator|\r"
    . "OBX|1|NM|PDW||8.3|fL|9.0-17.0|L|||F|||||administrator|\r"
    . "OBX|1|NM|PCT||0.31|%|0.10-0.28|H|||F|||||administrator|\r"
    . "OBX|1|NM|P_LCR||8.5|%|11.0-45.0|L|||F|||||administrator|\r"
    . "OBX|1|NM|P_LCC||40|x10^9/L|11-135||||F|||||administrator|\r"
    . "OBX|2|NM|WBCHistogram^LeftLine||17||||||F|||||administrator|\r"
    . "OBX|2|NM|WBCHistogram^RightLine||48||||||F|||||administrator|\r"
    . "OBX|2|NM|WBCHistogram^MiddleLine||72||||||F|||||administrator|\r"
    . "OBX|2|ED|WBCHistogram||3^Histogram^32Byte^HEX^0000||||||F|||||administrator|\r"
    . "OBX|2|NM|RBCHistogram^LeftLine||33||||||F|||||administrator|\r"
    . "OBX|2|NM|RBCHistogram^RightLine||208||||||F|||||administrator|\r"
    . "OBX|2|ED|RBCHistogram||3^Histogram^32Byte^HEX^0000||||||F|||||administrator|\r"
    . "OBX|2|NM|PLTHistogram^LeftLine||4||||||F|||||administrator|\r"
    . "OBX|2|NM|PLTHistogram^RightLine||126||||||F|||||administrator|\r"
    . "OBX|2|ED|PLTHistogram||3^Histogram^32Byte^HEX^0000||||||F|||||administrator|\r";

echo "Testing URIT HL7 Message → HL7MessageProcessor routing and insertion\n\n";
echo "Using doctorvisit_id: $doctorVisitId\n";

try {
    // Preview: extract CBC normalized
    $normalize = function(string $name): string {
        $map = [
            'RDW_CV' => 'RDW-CV',
            'RDW_SD' => 'RDW-SD',
            'P_LCR' => 'P-LCR',
            'P_LCC' => 'P-LCC',
            'GRAN%' => 'NEUT%',
            'GRAN#' => 'NEUT#',
            'NEU%' => 'NEUT%',
            'NEU#' => 'NEUT#',
        ];
        return $map[$name] ?? $name;
    };

    $msg = new \Aranyasen\HL7\Message($hl7);
    $preview = [];
    foreach ($msg->getSegments() as $segment) {
        if ($segment->getName() !== 'OBX') continue;
        $id = $segment->getField(3);
        if (is_array($id)) { $name = $id[0] ?? ''; } else { $name = explode('^', (string)$id)[0] ?? ''; }
        if ($name === '' || stripos($name, 'Histogram') !== false) continue;
        $name = $normalize($name);
        $val = $segment->getField(5);
        if (is_array($val)) { $val = $val[0] ?? null; }
        $preview[$name] = $val;
    }
    echo "Extracted CBC (normalized):\n";
    foreach ($preview as $k => $v) {
        echo "  $k = $v\n";
    }

    $exists = \App\Models\Doctorvisit::find($doctorVisitId) !== null;
    echo "doctorvisit exists: " . ($exists ? 'yes' : 'no') . "\n";
    $before = SysmexResult::count();
    echo "sysmex rows before: $before\n";

    $processor = new HL7MessageProcessor();
    $conn = new MockConnection();
    $processor->processMessage($hl7, $conn);
    echo "✅ URIT message processed.\n";
    $count = SysmexResult::count();
    echo "sysmex rows after: $count\n";
    $latest = SysmexResult::orderBy('id','desc')->first();
    if ($latest) {
        echo "latest doctorvisit_id: " . $latest->doctorvisit_id . "\n";
    }

    // Also try direct insertion via SysmexCbcInserter to surface any errors
    $inserter = new SysmexCbcInserter();
    $cbcForInsert = [];
    foreach ($preview as $k => $v) {
        $cbcForInsert[$k] = ['value' => is_numeric($v) ? (float)$v : $v];
    }
    $direct = $inserter->insertCbcData($cbcForInsert, (int)$doctorVisitId, ['patient_id' => 'test']);
    echo "Direct insert result: " . json_encode($direct) . "\n";
} catch (Throwable $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}


