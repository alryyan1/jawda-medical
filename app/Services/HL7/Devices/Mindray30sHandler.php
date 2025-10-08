<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Aranyasen\HL7\Segments\PID;
use Aranyasen\HL7\Segments\OBR;
use Aranyasen\HL7\Segments\OBX;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Mindray30sHandler
{
    /**
     * Process HL7 message from Mindray 30s CBC analyzer
     */
    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            Log::info('Mindray 30s: Processing CBC message');
            
            // Extract patient information
            $patientId = $this->extractPatientId($msg);
            if (!$patientId) {
                Log::warning('Mindray 30s: No patient ID found in message');
                return;
            }
            
            // Extract test results
            $testResults = $this->extractTestResults($msg);
            if (empty($testResults)) {
                Log::warning('Mindray 30s: No test results found in message');
                return;
            }
            
            // Store results in database
            $this->storeResults($patientId, $testResults);
            
            Log::info('Mindray 30s: Successfully processed CBC results', [
                'patient_id' => $patientId,
                'test_count' => count($testResults)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Mindray 30s: Error processing message: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }
    
    /**
     * Extract patient ID from PID segment
     */
    private function extractPatientId(Message $msg): ?string
    {
        try {
            $pid = new PID($msg->getSegmentByIndex(1)->getFields());
            $patientId = $pid->getField(4); // Patient ID is in field 4, not field 3
            
            if (is_array($patientId)) {
                return $patientId[0] ?? null;
            }
            
            return $patientId;
        } catch (\Exception $e) {
            Log::error('Mindray 30s: Error extracting patient ID: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Extract test results from OBX segments
     */
    private function extractTestResults(Message $msg): array
    {
        $results = [];
        
        try {
            $segments = $msg->getSegments();
            foreach ($segments as $segment) {
                if ($segment->getName() === 'OBX') {
                    $obx = new OBX($segment->getFields());
                    
                    $testCode = $obx->getField(3);
                    $testValue = $obx->getField(5);
                    $testUnit = $obx->getField(6);
                    $referenceRange = $obx->getField(7);
                    $abnormalFlag = $obx->getField(8);
                    
                    // Extract test name and code
                    $testName = '';
                    $testCodeValue = '';
                    
                    if (is_array($testCode)) {
                        $testCodeValue = $testCode[0] ?? '';
                        $testName = $testCode[1] ?? $testCodeValue;
                    } else {
                        $testCodeValue = $testCode;
                        $testName = $testCode;
                    }
                    
                    // Skip non-numeric results and control information
                    if ($obx->getField(2) !== 'NM' || empty($testValue) || is_array($testValue)) {
                        continue;
                    }
                    
                    $results[$testName] = [
                        'code' => $testCodeValue,
                        'name' => $testName,
                        'value' => floatval($testValue),
                        'unit' => is_array($testUnit) ? implode('^', $testUnit) : $testUnit,
                        'reference_range' => is_array($referenceRange) ? implode('^', $referenceRange) : $referenceRange,
                        'abnormal_flag' => is_array($abnormalFlag) ? implode('^', $abnormalFlag) : $abnormalFlag,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error('Mindray 30s: Error extracting test results: ' . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Store CBC results in database
     */
    private function storeResults(string $patientId, array $testResults): void
    {
        try {
            DB::beginTransaction();
            
            // Check if patient exists in sysmex550 table
            $existingRecord = DB::table('sysmex550')
                ->where('patient_id', $patientId)
                ->first();
            
            if ($existingRecord) {
                // Update existing record
                $updateData = [];
                foreach ($testResults as $testName => $result) {
                    $columnName = $this->getColumnName($testName);
                    if ($columnName) {
                        $updateData[$columnName] = $result['value'];
                    }
                }
                
                if (!empty($updateData)) {
                    $updateData['updated_at'] = now();
                    DB::table('sysmex550')
                        ->where('patient_id', $patientId)
                        ->update($updateData);
                    
                    Log::info('Mindray 30s: Updated existing CBC record', [
                        'patient_id' => $patientId,
                        'updated_fields' => array_keys($updateData)
                    ]);
                }
            } else {
                // Insert new record
                $insertData = ['patient_id' => $patientId];
                foreach ($testResults as $testName => $result) {
                    $columnName = $this->getColumnName($testName);
                    if ($columnName) {
                        $insertData[$columnName] = $result['value'];
                    }
                }
                
                // Set default values for required fields that might be missing
                $defaults = [
                    'WBC' => 0, 'RBC' => 0, 'HGB' => 0, 'HCT' => 0, 'MCV' => 0,
                    'MCH' => 0, 'MCHC' => 0, 'PLT' => 0, 'NEUTP' => 0, 'LYMPHP' => 0,
                    'MONOP' => 0, 'EOP' => 0, 'BASOP' => 0, 'NEUTC' => 0, 'LYMPHC' => 0,
                    'MONOC' => 0, 'EOC' => 0, 'BASOC' => 0, 'IGP' => 0, 'IGC' => 0,
                    'RDWSD' => 0, 'RDWCV' => 0, 'MICROR' => 0, 'MACROR' => 0,
                    'PDW' => 0, 'MPV' => 0, 'PLCR' => 0, 'PCT' => 0
                ];
                
                foreach ($defaults as $field => $defaultValue) {
                    if (!isset($insertData[$field])) {
                        $insertData[$field] = $defaultValue;
                    }
                }
                
                DB::table('sysmex550')->insert($insertData);
                
                Log::info('Mindray 30s: Created new CBC record', [
                    'patient_id' => $patientId,
                    'inserted_fields' => array_keys($insertData)
                ]);
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mindray 30s: Error storing results: ' . $e->getMessage(), [
                'patient_id' => $patientId,
                'exception' => $e
            ]);
            throw $e;
        }
    }
    
    /**
     * Map test names to database column names
     */
    private function getColumnName(string $testName): ?string
    {
        // Map to sysmex550 table columns
        $mapping = [
            'WBC' => 'WBC',
            'RBC' => 'RBC',
            'HGB' => 'HGB',
            'HCT' => 'HCT',
            'MCV' => 'MCV',
            'MCH' => 'MCH',
            'MCHC' => 'MCHC',
            'PLT' => 'PLT',
            'LYM%' => 'LYMPHP',
            'LYM#' => 'LYMPHC',
            'GRAN%' => 'NEUTP',
            'GRAN#' => 'NEUTC',
            'MID%' => 'MONOP',
            'MID#' => 'MONOC',
            'RDW-CV' => 'RDWCV',
            'RDW-SD' => 'RDWSD',
            'MPV' => 'MPV',
            'PDW' => 'PDW',
            'PCT' => 'PCT',
            'PLCR' => 'PLCR',
            // Note: PLCC column doesn't exist in sysmex550 table, so we skip it
        ];
        
        return $mapping[$testName] ?? null;
    }
}

