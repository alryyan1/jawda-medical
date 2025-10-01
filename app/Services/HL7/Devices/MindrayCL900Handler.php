<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MindrayCL900Handler
{
    protected array $chemistryMapping;

    public function __construct()
    {
        $this->chemistryMapping = config('hl7.test_mappings.chemistry', []);
    }

    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            $msgType = $msh->getMessageType(10);
            $barcode = $msh->getField(29);

            if ($msgType === 'QRY') {
                $this->handleQuery($msg, $barcode, $connection);
            } elseif ($msgType === 'ORU') {
                $this->handleResult($msg, $connection);
            }

        } catch (\Exception $e) {
            Log::error('MindrayCL900 processing error: ' . $e->getMessage());
        }
    }

    protected function handleQuery(Message $msg, string $barcode, $connection): void
    {
        try {
            if (!is_numeric($barcode)) {
                $ack = $this->getAckNoDataFound();
                $connection->write($ack);
                return;
            }

            $hasChemistryOrHormone = DB::table('requested')
                ->join('main_tests', 'requested.main_test_id', '=', 'main_tests.id')
                ->join('package', 'main_tests.pack_id', '=', 'package.package_id')
                ->where('requested.patient_id', $barcode)
                ->whereIn('package.package_id', [2, 3])
                ->count();

            if ($hasChemistryOrHormone > 0) {
                $tests = $this->buildTestString($barcode);
                $patientData = $this->getPatientData($barcode);
                
                $ack = $this->getAck();
                $dsp = $this->generateDspMessage($barcode, $tests, $patientData);
                
                $connection->write($ack);
                $connection->write($dsp);
            } else {
                $ack = $this->getAckNoDataFound();
                $connection->write($ack);
            }

        } catch (\Exception $e) {
            Log::error('MindrayCL900 QRY error: ' . $e->getMessage());
        }
    }

    protected function handleResult(Message $msg, $connection): void
    {
        try {
            $ack = $this->getRespond();
            $connection->write($ack);
            
            // Process chemistry or hormone results
            $this->processResults($msg);

        } catch (\Exception $e) {
            Log::error('MindrayCL900 ORU error: ' . $e->getMessage());
        }
    }

    protected function buildTestString(string $barcode): string
    {
        $dataTests = DB::table('requested')
            ->join('main_tests', 'requested.main_test_id', '=', 'main_tests.id')
            ->join('package', 'main_tests.pack_id', '=', 'package.package_id')
            ->where('requested.patient_id', $barcode)
            ->whereIn('package.package_id', [2, 3])
            ->get(['main_test_id']);

        $tests = "";
        $obrIndex = 29;
        $profiles = [];
        $count = 0;
        $newLine = true;

        // First pass: identify profiles and count individual tests
        foreach ($dataTests as $test) {
            if (array_key_exists($test->main_test_id, $this->chemistryMapping)) {
                $children = DB::table('child_tests')
                    ->where('main_id', $test->main_test_id)
                    ->count();
                
                if ($children > 1) {
                    $newLine = false;
                    $profiles[] = $test->main_test_id;
                    continue;
                }
                $count++;
            }
        }

        // Second pass: build test string
        $start = 1;
        foreach ($dataTests as $test) {
            if (array_key_exists($test->main_test_id, $this->chemistryMapping)) {
                $key = $test->main_test_id;
                
                if (!in_array($key, $profiles)) {
                    $name = strtolower($this->chemistryMapping[$key]);
                    $delimiter = ($start === $count && $newLine) ? "" : "\r";
                    $tests .= "DSP|{$obrIndex}||{$name}^^^|||{$delimiter}";
                    $obrIndex++;
                    $start++;
                }
            }
        }

        // Add profile tests
        foreach ($profiles as $profile) {
            $tests .= $this->getProfileTests($profile, $obrIndex, $profiles);
        }

        return $tests;
    }

    protected function getProfileTests(int $profile, int &$obrIndex, array $profiles): string
    {
        $tests = "";
        
        switch ($profile) {
            case 12: // RFT
                $tests .= "DSP|{$obrIndex}||xoxurea^^^|||\r";
                $obrIndex++;
                $delimiter = (in_array(13, $profiles) || in_array(14, $profiles)) ? "\r" : "";
                $tests .= "DSP|{$obrIndex}||xoxcrea^^^|||{$delimiter}";
                $obrIndex++;
                break;
                
            case 13: // Liver
                $liverTests = ['xoxtb', 'xoxdb', 'xoxtp', 'xoxalb', 'xoxast', 'xoxalt', 'xoxalp'];
                $delimiter = in_array(14, $profiles) ? "\r" : "";
                
                foreach ($liverTests as $index => $test) {
                    $testDelimiter = ($index === count($liverTests) - 1) ? $delimiter : "\r";
                    $tests .= "DSP|{$obrIndex}||{$test}^^^|||{$testDelimiter}";
                    $obrIndex++;
                }
                break;
                
            case 14: // Lipid
                $lipidTests = ['xoxtc', 'xoxtg', 'xoxhdl', 'xoxldl'];
                foreach ($lipidTests as $index => $test) {
                    $testDelimiter = ($index === count($lipidTests) - 1) ? "" : "\r";
                    $tests .= "DSP|{$obrIndex}||{$test}^^^|||{$testDelimiter}";
                    $obrIndex++;
                }
                break;
        }
        
        return $tests;
    }

    protected function getPatientData(string $barcode): array
    {
        $patient = DB::table('patients')
            ->where('patient_Id', $barcode)
            ->first();

        if (!$patient) {
            return ['visit_id' => '', 'patient_name' => '', 'shift' => ''];
        }

        $shift = DB::table('shift')
            ->where('shift_id', $patient->shift_id)
            ->value('type');

        $type = $shift === 'M' ? 'AM' : 'PM';
        $visitId = $type . $patient->visit_id;

        return [
            'visit_id' => $visitId,
            'patient_name' => $patient->patient_name,
            'shift' => $patient->shift_id
        ];
    }

    protected function generateDspMessage(string $barcode, string $tests, array $patientData): string
    {
        return "MSH|^~\\&|CL-900|Lab|LIS|Hospital|" . date('YmdHis') . "||DSP^D01|1|P|2.5.1\r" .
               "PID|1||{$barcode}||{$patientData['patient_name']}|||\r" .
               "PV1|1|||{$patientData['visit_id']}|||\r" .
               $tests;
    }

    protected function getAck(): string
    {
        return "MSH|^~\\&|LIS|Hospital|CL-900|Lab|" . date('YmdHis') . "||ACK^A01|1|P|2.5.1\r" .
               "MSA|AA|1|Message accepted||";
    }

    protected function getAckNoDataFound(): string
    {
        return "MSH|^~\\&|LIS|Hospital|CL-900|Lab|" . date('YmdHis') . "||ACK^A01|1|P|2.5.1\r" .
               "MSA|AE|1|No data found||";
    }

    protected function getRespond(): string
    {
        return "MSH|^~\\&|LIS|Hospital|CL-900|Lab|" . date('YmdHis') . "||ACK^A01|1|P|2.5.1\r" .
               "MSA|AA|1|Results received||";
    }

    protected function processResults(Message $msg): void
    {
        // Process chemistry or hormone results and save to database
        Log::info('MindrayCL900: Processing results');
        // Implementation would parse OBR/OBX segments and save results
    }
}
