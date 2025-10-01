<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Aranyasen\HL7\Segments\OBX;
use App\Services\HL7\Contracts\DeviceHandlerInterface;
use App\Services\HL7\Devices\SysmexCbcInserter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ACONHandler implements DeviceHandlerInterface
{
    protected SysmexCbcInserter $sysmexInserter;

    public function __construct()
    {
        $this->sysmexInserter = new SysmexCbcInserter();
    }
    /**
     * Process ACON device HL7 message
     */
    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            Log::info('ACON: Processing message',['msh' => $msh->getFields()]);
            $msh = new MSH($msg->getSegmentByIndex(0)->getFields());
            // Extract patient information
            $patientInfo = $this->extractPatientInfo($msg);
            
            // Extract CBC parameters
            $cbcResults = $this->extractCBCParameters($msg);
            
            Log::info('ACON: CBC results', ['results' => $cbcResults]);

            // Extract doctor visit ID from OBR segment field 3 (Filler Order Number)
            $doctorVisitId = $this->extractDoctorVisitId($msg);
            Log::info('ACON: Doctor visit ID extracted', ['doctorVisitId' => $doctorVisitId]);
            
            // Insert into Sysmex table (if doctor visit ID is available)
            if ($doctorVisitId && is_numeric($doctorVisitId)) {
                $insertResult = $this->sysmexInserter->insertCbcData(
                    $cbcResults, 
                    (int)$doctorVisitId, 
                    $patientInfo
                );

                if (!$insertResult['success']) {
                    Log::info('ACON: Insertion error', $insertResult);
                    // Handle insertion error
                } else {
                    Log::info('ACON: Successfully inserted CBC data', ['sysmex_id' => $insertResult['sysmex_id']]);
                }
            } else {
                Log::warning('ACON: No valid doctor visit ID found', ['doctorVisitId' => $doctorVisitId]);
            }
            
            // Log the results to ACON tables
            $this->logCBCResults($patientInfo, $cbcResults);
            
            // Send acknowledgment back to device
            $this->sendAcknowledgment($connection, $msh);
            
        } catch (\Exception $e) {
            // Error handling without logging
        }
    }

    /**
     * Extract doctor visit ID from OBR segment
     */
    protected function extractDoctorVisitId(Message $msg): ?string
    {
        try {
            // Find OBR segment
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'OBR') {
                    // Get field 3 (Filler Order Number) which contains the doctor visit ID
                    $doctorVisitId = $segment->getField(3);
                    return $doctorVisitId ?: null;
                }
            }
        } catch (\Exception $e) {
            // Error handling without logging
        }
        
        return null;
    }

    /**
     * Extract patient information from PID segment
     */
    protected function extractPatientInfo(Message $msg): array
    {
        $patientInfo = [];
        
        try {
            // Find PID segment
            $pidSegment = null;
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'PID') {
                    $pidSegment = $segment;
                    break;
                }
            }
            
            if ($pidSegment) {
                $fields = $pidSegment->getFields();
                $patientInfo = [
                    'patient_id' => $fields[3] ?? null, // Patient ID
                    'name' => $fields[5] ?? null, // Patient Name
                    'dob' => $fields[7] ?? null, // Date of Birth
                    'gender' => $fields[8] ?? null, // Gender
                ];
            }
        } catch (\Exception $e) {
            // Error handling without logging
        }
        
        return $patientInfo;
    }

    /**
     * Extract CBC parameters from OBX segments
     */
    protected function extractCBCParameters(Message $msg): array
    {
        $cbcResults = [];
        
        try {
            // Define CBC parameter mappings
            $cbcMappings = [
                '6690-2' => 'WBC', // White Blood Cell Count
                '731-0' => 'LYM#', // Lymphocyte Count
                '8005' => 'MXD#', // Mixed Cell Count
                '8006' => 'NEUT#', // Neutrophil Count
                '736-9' => 'LYM%', // Lymphocyte Percentage
                '8007' => 'MXD%', // Mixed Cell Percentage
                '8008' => 'NEUT%', // Neutrophil Percentage
                '789-8' => 'RBC', // Red Blood Cell Count
                '718-7' => 'HGB', // Hemoglobin
                '4544-3' => 'HCT', // Hematocrit
                '787-2' => 'MCV', // Mean Corpuscular Volume
                '785-6' => 'MCH', // Mean Corpuscular Hemoglobin
                '786-4' => 'MCHC', // Mean Corpuscular Hemoglobin Concentration
                '788-0' => 'RDW-CV', // Red Cell Distribution Width CV
                '21000-5' => 'RDW-SD', // Red Cell Distribution Width SD
                '777-3' => 'PLT', // Platelet Count
                '32623-1' => 'MPV', // Mean Platelet Volume
                '32207-3' => 'PDW', // Platelet Distribution Width
                '8002' => 'PCT', // Plateletcrit
                '8003' => 'PLCC', // Platelet Large Cell Count
                '8004' => 'PLCR', // Platelet Large Cell Ratio
            ];
            
            // Process OBX segments
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'OBX') {
                    $obxData = $this->parseOBXSegment($segment);
                    
                    if ($obxData && isset($cbcMappings[$obxData['test_code']])) {
                        $parameterName = $cbcMappings[$obxData['test_code']];
                        
                        $cbcResults[$parameterName] = [
                            'test_code' => $obxData['test_code'],
                            'test_name' => $obxData['test_name'],
                            'value' => $obxData['value'],
                            'unit' => $obxData['unit'],
                            'reference_range' => $obxData['reference_range'],
                            'abnormal_flag' => $obxData['abnormal_flag'],
                            'status' => $obxData['status'],
                        ];
                    }
                }
            }
            
        } catch (\Exception $e) {
            // Error handling without logging
        }
        
        return $cbcResults;
    }

    /**
     * Parse OBX segment to extract test data
     */
    protected function parseOBXSegment($segment): ?array
    {
        try {
            $fields = $segment->getFields();
            
            // OBX field structure (corrected for ACON device):
            // 1: Set ID, 2: Value Type, 3: Observation Identifier, 5: Observation Value,
            // 6: Units, 7: References Range, 8: Abnormal Flags, 11: Observation Result Status
            
            // Handle array fields properly
            $observationIdentifier = is_array($fields[3] ?? '') ? implode('^', $fields[3]) : ($fields[3] ?? '');
            $observationValue = is_array($fields[5] ?? '') ? implode('^', $fields[5]) : ($fields[5] ?? '');
            $units = is_array($fields[6] ?? '') ? implode('^', $fields[6]) : ($fields[6] ?? '');
            $referenceRange = is_array($fields[7] ?? '') ? implode('^', $fields[7]) : ($fields[7] ?? '');
            $abnormalFlag = is_array($fields[8] ?? '') ? implode('^', $fields[8]) : ($fields[8] ?? '');
            $status = is_array($fields[11] ?? '') ? implode('^', $fields[11]) : ($fields[11] ?? '');
            
            // Extract test code and name from observation identifier
            $testParts = explode('^', $observationIdentifier);
            $testCode = $testParts[0] ?? '';
            $testName = $testParts[1] ?? $testCode;
            
            // Debug logging removed
            
            return [
                'test_code' => $testCode,
                'test_name' => $testName,
                'value' => $observationValue,
                'unit' => $units,
                'reference_range' => $referenceRange,
                'abnormal_flag' => $abnormalFlag,
                'status' => $status,
            ];
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Log CBC results to database
     */
    protected function logCBCResults(array $patientInfo, array $cbcResults): void
    {
        try {
            // Insert into CBC results table
            $resultData = [
                'patient_id' => $patientInfo['patient_id'] ?? null,
                'patient_name' => $patientInfo['name'] ?? null,
                'patient_dob' => $patientInfo['dob'] ?? null,
                'patient_gender' => $patientInfo['gender'] ?? null,
                'device_type' => 'ACON',
                'test_date' => now(),
                'results' => json_encode($cbcResults),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            
            DB::table('acon_cbc_results')->insert($resultData);
            
            // Also insert individual results for easier querying
            foreach ($cbcResults as $parameter => $result) {
                DB::table('acon_cbc_parameters')->insert([
                    'patient_id' => $patientInfo['patient_id'] ?? null,
                    'parameter_name' => $parameter,
                    'test_code' => $result['test_code'],
                    'test_name' => $result['test_name'],
                    'value' => $result['value'],
                    'unit' => $result['unit'],
                    'reference_range' => $result['reference_range'],
                    'abnormal_flag' => $result['abnormal_flag'],
                    'status' => $result['status'],
                    'test_date' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            
        } catch (\Exception $e) {
            // Error handling without logging
        }
    }

    /**
     * Send acknowledgment back to device
     */
    protected function sendAcknowledgment($connection, MSH $msh): void
    {
        try {
            // Create ACK message
            $ackMessage = $this->createACKMessage($msh);
            
            // Send acknowledgment
            $connection->write($ackMessage);
            
            // ACK message sent
            
        } catch (\Exception $e) {
            // Error handling without logging
        }
    }

    /**
     * Create ACK message
     */
    protected function createACKMessage(MSH $msh): string
    {
        try {
            // Extract original message control ID
            $originalControlId = $msh->getField(9);
            $sendingApp = $msh->getField(4); // Receiving application becomes sending
            $sendingFacility = $msh->getField(5); // Receiving facility becomes sending
            $receivingApp = $msh->getField(2); // Sending application becomes receiving
            $receivingFacility = $msh->getField(3); // Sending facility becomes receiving
            
            // Create new control ID for ACK
            $ackControlId = 'ACK' . time();
            
            // Create timestamp
            $timestamp = date('YmdHis');
            
            // Build ACK message
            $ackMessage = "MSH|^~\\&|{$sendingApp}|{$sendingFacility}|{$receivingApp}|{$receivingFacility}|{$timestamp}||ACK^R01^ACK|{$ackControlId}|P|2.3.1\r";
            $ackMessage .= "MSA|AA|{$originalControlId}|Message accepted\r";
            
            return $ackMessage;
            
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get CBC parameter reference ranges
     */
    public function getCBCReferenceRanges(): array
    {
        return [
            'WBC' => ['min' => 4.0, 'max' => 10.0, 'unit' => '10*9/L'],
            'LYM#' => ['min' => 0.8, 'max' => 5.5, 'unit' => '10*9/L'],
            'MXD#' => ['min' => 0.1, 'max' => 1.5, 'unit' => '10*9/L'],
            'NEUT#' => ['min' => 2.0, 'max' => 7.0, 'unit' => '10*9/L'],
            'LYM%' => ['min' => 20.0, 'max' => 55.0, 'unit' => '%'],
            'MXD%' => ['min' => 3.0, 'max' => 15.0, 'unit' => '%'],
            'NEUT%' => ['min' => 50.0, 'max' => 70.0, 'unit' => '%'],
            'RBC' => ['min' => 3.50, 'max' => 5.50, 'unit' => '10*12/L'],
            'HGB' => ['min' => 11.0, 'max' => 16.0, 'unit' => 'g/dL'],
            'HCT' => ['min' => 35.0, 'max' => 54.0, 'unit' => '%'],
            'MCV' => ['min' => 80.0, 'max' => 100.0, 'unit' => 'fL'],
            'MCH' => ['min' => 27.0, 'max' => 34.0, 'unit' => 'pg'],
            'MCHC' => ['min' => 32.0, 'max' => 36.0, 'unit' => 'g/dL'],
            'RDW-CV' => ['min' => 11.0, 'max' => 16.0, 'unit' => '%'],
            'RDW-SD' => ['min' => 35.0, 'max' => 56.0, 'unit' => 'fL'],
            'PLT' => ['min' => 100, 'max' => 300, 'unit' => '10*9/L'],
            'MPV' => ['min' => 6.5, 'max' => 12.0, 'unit' => 'fL'],
            'PDW' => ['min' => 9.0, 'max' => 18.0, 'unit' => 'fL'],
            'PCT' => ['min' => 0.150, 'max' => 0.350, 'unit' => '%'],
            'PLCC' => ['min' => 30, 'max' => 90, 'unit' => '10*9/L'],
            'PLCR' => ['min' => 11.0, 'max' => 45.0, 'unit' => '%'],
        ];
    }

    /**
     * Format CBC results with proper categorization and interpretation
     */
    public function formatCBCResults(array $cbcResults): string
    {
        $referenceRanges = $this->getCBCReferenceRanges();
        $formatted = '';
        
        // White Blood Cell Parameters
        $wbcParams = ['WBC', 'LYM#', 'MXD#', 'NEUT#', 'LYM%', 'MXD%', 'NEUT%'];
        $formatted .= "White Blood Cell Parameters:\n";
        foreach ($wbcParams as $param) {
            if (isset($cbcResults[$param])) {
                $result = $cbcResults[$param];
                $interpretation = $this->interpretResult($param, $result['value'], $referenceRanges);
                $formatted .= "{$param}: {$result['value']} {$result['unit']} {$interpretation}\n";
            }
        }
        
        // Red Blood Cell Parameters
        $rbcParams = ['RBC', 'HGB', 'HCT', 'MCV', 'MCH', 'MCHC', 'RDW-CV', 'RDW-SD'];
        $formatted .= "\nRed Blood Cell Parameters:\n";
        foreach ($rbcParams as $param) {
            if (isset($cbcResults[$param])) {
                $result = $cbcResults[$param];
                $interpretation = $this->interpretResult($param, $result['value'], $referenceRanges);
                $formatted .= "{$param}: {$result['value']} {$result['unit']} {$interpretation}\n";
            }
        }
        
        // Platelet Parameters
        $pltParams = ['PLT', 'MPV', 'PDW', 'PCT', 'PLCC', 'PLCR'];
        $formatted .= "\nPlatelet Parameters:\n";
        foreach ($pltParams as $param) {
            if (isset($cbcResults[$param])) {
                $result = $cbcResults[$param];
                $interpretation = $this->interpretResult($param, $result['value'], $referenceRanges);
                $formatted .= "{$param}: {$result['value']} {$result['unit']} {$interpretation}\n";
            }
        }
        
        return $formatted;
    }

    /**
     * Interpret CBC result with clinical significance
     */
    protected function interpretResult(string $parameter, string $value, array $referenceRanges): string
    {
        if (!isset($referenceRanges[$parameter])) {
            return '(normal)';
        }
        
        $numericValue = (float) $value;
        $range = $referenceRanges[$parameter];
        $min = $range['min'];
        $max = $range['max'];
        
        if ($numericValue < $min) {
            $flag = '⬇️';
            $interpretation = $this->getLowInterpretation($parameter);
            return "{$flag} ({$interpretation})";
        } elseif ($numericValue > $max) {
            $flag = '⬆️';
            $interpretation = $this->getHighInterpretation($parameter);
            return "{$flag} ({$interpretation})";
        } else {
            return '(normal)';
        }
    }

    /**
     * Get clinical interpretation for low values
     */
    protected function getLowInterpretation(string $parameter): string
    {
        $interpretations = [
            'WBC' => 'leukopenia',
            'LYM#' => 'lymphocytopenia',
            'MXD#' => 'mixed cell deficiency',
            'NEUT#' => 'neutropenia',
            'LYM%' => 'low lymphocytes',
            'MXD%' => 'low mixed cells',
            'NEUT%' => 'low neutrophils',
            'RBC' => 'anemia',
            'HGB' => 'low - anemia',
            'HCT' => 'low hematocrit',
            'MCV' => 'low - microcytic',
            'MCH' => 'low - hypochromic',
            'MCHC' => 'low - hypochromic',
            'RDW-CV' => 'low RDW',
            'RDW-SD' => 'low RDW',
            'PLT' => 'thrombocytopenia',
            'MPV' => 'low MPV',
            'PDW' => 'low',
            'PCT' => 'low plateletcrit',
            'PLCC' => 'low large platelets',
            'PLCR' => 'low platelet ratio',
        ];
        
        return $interpretations[$parameter] ?? 'low';
    }

    /**
     * Get clinical interpretation for high values
     */
    protected function getHighInterpretation(string $parameter): string
    {
        $interpretations = [
            'WBC' => 'leukocytosis',
            'LYM#' => 'lymphocytosis',
            'MXD#' => 'mixed cell increase',
            'NEUT#' => 'neutrophilia',
            'LYM%' => 'high lymphocytes',
            'MXD%' => 'high mixed cells',
            'NEUT%' => 'high neutrophils',
            'RBC' => 'polycythemia',
            'HGB' => 'high hemoglobin',
            'HCT' => 'high hematocrit',
            'MCV' => 'high - macrocytic',
            'MCH' => 'high - hyperchromic',
            'MCHC' => 'high - hyperchromic',
            'RDW-CV' => 'high RDW',
            'RDW-SD' => 'high RDW',
            'PLT' => 'high - thrombocytosis',
            'MPV' => 'high MPV',
            'PDW' => 'high',
            'PCT' => 'high',
            'PLCC' => 'high large platelets',
            'PLCR' => 'high platelet ratio',
        ];
        
        return $interpretations[$parameter] ?? 'high';
    }
}
