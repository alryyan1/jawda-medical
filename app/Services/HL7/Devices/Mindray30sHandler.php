<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Aranyasen\HL7\Segments\PID;
use Aranyasen\HL7\Segments\OBR;
use Aranyasen\HL7\Segments\OBX;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\HL7\Devices\SysmexCbcInserter;

class Mindray30sHandler
{
    protected SysmexCbcInserter $sysmexInserter;

    public function __construct()
    {
        $this->sysmexInserter = new SysmexCbcInserter();
    }

    /**
     * Process HL7 message from Mindray 30s CBC analyzer
     */
    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            Log::info('Mindray 30s: Processing CBC message', [
                'message_segments_count' => count($msg->getSegments()),
                'message_type' => $msh->getField(9) ?? 'N/A',
                'sending_facility' => $msh->getField(4) ?? 'N/A'
            ]);
            
            // Log all segment types in the message
            $segmentTypes = [];
            foreach ($msg->getSegments() as $segment) {
                $segmentTypes[] = $segment->getName();
            }
            Log::info('Mindray 30s: Message segment types', ['segments' => $segmentTypes]);
            
            // Extract patient information
            $patientInfo = $this->extractPatientInfo($msg);
            
            // Extract CBC parameters
            $cbcResults = $this->extractCBCParameters($msg);
            if (empty($cbcResults)) {
                $obxCount = count(array_filter($segmentTypes, fn($type) => $type === 'OBX'));
                if ($obxCount === 0) {
                    Log::info('Mindray 30s: Non-CBC message received (no OBX segments)', [
                        'total_segments' => count($msg->getSegments()),
                        'segment_types' => $segmentTypes,
                        'message_type' => $msh->getField(9) ?? 'N/A'
                    ]);
                } else {
                    Log::warning('Mindray 30s: CBC message with OBX segments but no recognized parameters', [
                        'total_segments' => count($msg->getSegments()),
                        'segment_types' => $segmentTypes,
                        'obx_count' => $obxCount
                    ]);
                }
                return;
            }
            
            Log::info('Mindray 30s: CBC results extracted', ['results_count' => count($cbcResults)]);

            // Extract doctor visit ID from OBR segment field 3 (Filler Order Number)
            $doctorVisitId = $this->extractDoctorVisitId($msg);
            Log::info('Mindray 30s: Doctor visit ID extracted', ['doctorVisitId' => $doctorVisitId]);
            
            // Insert into Sysmex table (if doctor visit ID is available)
            if ($doctorVisitId && is_numeric($doctorVisitId)) {
                $insertResult = $this->sysmexInserter->insertCbcData(
                    $cbcResults, 
                    (int)$doctorVisitId, 
                    $patientInfo
                );

                if (!$insertResult['success']) {
                    Log::error('Mindray 30s: Insertion error', $insertResult);
                } else {
                    Log::info('Mindray 30s: Successfully inserted CBC data into sysmex table', ['sysmex_id' => $insertResult['sysmex_id']]);
                }
            } else {
                Log::warning('Mindray 30s: No valid doctor visit ID found', ['doctorVisitId' => $doctorVisitId]);
            }
            
        } catch (\Exception $e) {
            Log::error('Mindray 30s: Error processing message: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    /**
     * Extract patient information from PID segment
     */
    private function extractPatientInfo(Message $msg): array
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
            Log::error('Mindray 30s: Error extracting patient info: ' . $e->getMessage());
        }
        
        return $patientInfo;
    }

    /**
     * Extract doctor visit ID from OBR segment
     */
    private function extractDoctorVisitId(Message $msg): ?string
    {
        try {
            // Find OBR segment
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'OBR') {
                    // Get field 3 (Filler Order Number) which contains the doctor visit ID
                    $fields = $segment->getFields();
                    $doctorVisitId = $fields[3] ?? null;
                    return $doctorVisitId ?: null;
                }
            }
        } catch (\Exception $e) {
            Log::error('Mindray 30s: Error extracting doctor visit ID: ' . $e->getMessage());
        }
        
        return null;
    }
    
    /**
     * Extract patient ID from PID segment
     */
    private function extractPatientId(Message $msg): ?string
    {
        try {
            // First try to get patient ID from PID segment
            $pidSegment = null;
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'PID') {
                    $pidSegment = $segment;
                    break;
                }
            }
            
            if ($pidSegment) {
                $pid = new PID($pidSegment->getFields());
                $patientId = $pid->getField(3); // Patient ID is in field 3 (Patient Identifier List)
                
                if (is_array($patientId)) {
                    // Extract patient ID from the array format
                    $patientIdParts = explode('^', $patientId[0] ?? '');
                    $patientId = $patientIdParts[0] ?? null;
                }
                
                // If we have a valid patient ID, return it
                if (!empty($patientId) && $patientId !== 'MR') {
                    return $patientId;
                }
            }
            
            // If PID doesn't have a valid patient ID, try to get it from OBR segment
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'OBR') {
                    $fields = $segment->getFields();
                    // Get field 3 directly from the segment (OBR constructor shifts indices)
                    $fillerOrderNumber = $fields[3] ?? '';
                    
                    if (!empty($fillerOrderNumber)) {
                        Log::info('Mindray 30s: Using OBR filler order number as patient ID', ['patient_id' => $fillerOrderNumber]);
                        return $fillerOrderNumber;
                    }
                }
            }
            
            Log::warning('Mindray 30s: No valid patient ID found in PID or OBR segments');
            return null;
        } catch (\Exception $e) {
            Log::error('Mindray 30s: Error extracting patient ID: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract CBC parameters from OBX segments
     */
    private function extractCBCParameters(Message $msg): array
    {
        $cbcResults = [];
        
        try {
            // Define CBC parameter mappings for Mindray 30s
            $cbcMappings = [
                // LOINC codes
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
                
                // 99MRC codes (ACON device specific)
                '10027' => 'MID#', // Mid cells count
                '10029' => 'MID%', // Mid cells percentage
                '10028' => 'GRAN#', // Granulocytes count
                '10030' => 'GRAN%', // Granulocytes percentage
                '10013' => 'PLCC', // Platelet Large Cell Count
                '10014' => 'PLCR', // Platelet Large Cell Ratio
                '10002' => 'PCT', // Plateletcrit
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
            Log::error('Mindray 30s: Error extracting CBC parameters: ' . $e->getMessage());
        }
        
        return $cbcResults;
    }

    /**
     * Parse OBX segment to extract test data
     */
    private function parseOBXSegment($segment): ?array
    {
        try {
            $fields = $segment->getFields();
            
            // OBX field structure:
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
            Log::error('Mindray 30s: Error parsing OBX segment: ' . $e->getMessage());
            return null;
        }
    }
}

