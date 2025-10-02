<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Illuminate\Support\Facades\Log;
use App\Services\HL7\Devices\SysmexCbcInserter;

class ZybioHandler
{
    protected SysmexCbcInserter $sysmexInserter;

    public function __construct(SysmexCbcInserter $sysmexInserter)
    {
        $this->sysmexInserter = $sysmexInserter;
    }

    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            Log::info('Zybio: Processing CBC message');
            
            // Process CBC results from Zybio Z3 device
            $this->processCbcResults($msg, $msh);
            
            Log::info('Zybio: CBC results processed successfully');

        } catch (\Exception $e) {
            Log::error('Zybio processing error: ' . $e->getMessage(), [
                'exception' => $e,
                'message' => $msg->toString()
            ]);
        }
    }

    /**
     * Correct HL7 message format by fixing field separator issues
     * Specifically fixes the MSH segment field separator from ^~& to ^~\&
     */
    public static function correctHl7MessageFormat(string $rawMessage): string
    {
        try {
            // Remove control characters from the beginning and end
            $correctedMessage = trim($rawMessage, "\x00-\x1F");
            
            // Fix the field separator in MSH segment
            // Replace ^~& with ^~\& (add missing backslash)
            $correctedMessage = preg_replace('/MSH\|\^~&/', 'MSH|^~\&', $correctedMessage);
            
            // Normalize line endings to \r (HL7 standard) but be more careful
            // First, normalize \r\n to \n, then \r to \n, then \n to \r
            $correctedMessage = str_replace("\r\n", "\n", $correctedMessage);
            $correctedMessage = str_replace("\r", "\n", $correctedMessage);
            $correctedMessage = str_replace("\n", "\r", $correctedMessage);
            
            // Remove extra whitespace but preserve line breaks
            $correctedMessage = preg_replace('/[ \t]+/', ' ', $correctedMessage);
            
            // Remove any remaining control characters except \r (carriage return = \x0D)
            $correctedMessage = preg_replace('/[\x00-\x0C\x0E-\x1F\x7F]/', '', $correctedMessage);
            
            // Trim leading/trailing whitespace
            $correctedMessage = trim($correctedMessage);
            
            Log::info('Zybio: HL7 message format corrected', [
                'original_length' => strlen($rawMessage),
                'corrected_length' => strlen($correctedMessage),
                'field_separator_fixed' => $rawMessage !== $correctedMessage,
                'control_chars_removed' => strlen($rawMessage) !== strlen($correctedMessage)
            ]);
            
            return $correctedMessage;
            
        } catch (\Exception $e) {
            Log::error('Zybio: Error correcting HL7 message format: ' . $e->getMessage());
            return $rawMessage; // Return original message if correction fails
        }
    }

    /**
     * Process raw HL7 message string with automatic format correction
     */
    public function processRawMessage(string $rawMessage, $connection): void
    {
        try {
            Log::info('Zybio: Processing raw HL7 message');
            
            // Correct the message format first
            $correctedMessage = self::correctHl7MessageFormat($rawMessage);
            
            // Parse the corrected message
            $msg = new Message($correctedMessage);
            $msh = $msg->getSegmentByIndex(0);
            
            // Process the message
            $this->processMessage($msg, $msh, $connection);
            
        } catch (\Exception $e) {
            Log::error('Zybio: Error processing raw message: ' . $e->getMessage(), [
                'exception' => $e,
                'raw_message' => $rawMessage
            ]);
        }
    }

    protected function processCbcResults(Message $msg, MSH $msh = null): void
    {
        try {
            // Parse CBC segments and extract results
            $cbcData = $this->parseCbcMessage($msg, $msh);
            
            if ($cbcData) {
                $this->saveCbcResults($cbcData);
            }

        } catch (\Exception $e) {
            Log::error('Zybio: Error processing CBC results: ' . $e->getMessage());
        }
    }

    protected function parseCbcMessage(Message $msg, MSH $msh = null): ?array
    {
        try {
            // Parse OBR and OBX segments to extract CBC parameters
            $cbcData = [
                'patient_id' => null,
                'doctor_visit_id' => null,
                'results' => []
            ];

            // Extract doctor visit ID from MSH field 49 (Zybio specific)
            if ($msh) {
                $cbcData['doctor_visit_id'] = $msh->getField(49);
            }
            
            // Fallback: Extract doctor visit ID from OBR field 3 if MSH field 49 is empty
            if (!$cbcData['doctor_visit_id']) {
                foreach ($msg->getSegments() as $segment) {
                    if ($segment->getName() === 'OBR') {
                        $cbcData['doctor_visit_id'] = $segment->getField(3);
                        break;
                    }
                }
            }

            // Extract patient ID from PID segment
            $pidSegment = null;
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'PID') {
                    $pidSegment = $segment;
                    break;
                }
            }
            if ($pidSegment) {
                $cbcData['patient_id'] = $pidSegment->getField(3); // Patient ID
            }

            // Extract results from OBX segments
            $obxSegments = [];
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'OBX') {
                    $obxSegments[] = $segment;
                }
            }
            foreach ($obxSegments as $obx) {
                $testCode = $obx->getField(3); // Observation identifier
                $value = $obx->getField(5); // Observation value
                $unit = $obx->getField(6); // Units
                $referenceRange = $obx->getField(7); // Reference range

                $cbcData['results'][] = [
                    'test_code' => $testCode,
                    'value' => $value,
                    'unit' => $unit,
                    'reference_range' => $referenceRange
                ];
            }

            return $cbcData;

        } catch (\Exception $e) {
            Log::error('Zybio: Error parsing CBC message: ' . $e->getMessage());
            return null;
        }
    }

    protected function saveCbcResults(array $cbcData): void
    {
        try {
            if (!$cbcData['patient_id']) {
                Log::warning('Zybio: No patient ID found in CBC data');
                return;
            }

            if (!$cbcData['doctor_visit_id']) {
                Log::warning('Zybio: No doctor visit ID found in CBC data');
                return;
            }

            // Convert CBC results to the format expected by SysmexCbcInserter
            $cbcResults = $this->formatCbcResultsForInserter($cbcData['results']);

            // Validate CBC data before insertion
            $validation = $this->sysmexInserter->validateCbcData($cbcResults);
            if (!$validation['valid']) {
                Log::error('Zybio: CBC data validation failed', [
                    'errors' => $validation['errors']
                ]);
                return;
            }

            // Insert CBC data into Sysmex table
            $result = $this->sysmexInserter->insertCbcData(
                $cbcResults,
                (int)$cbcData['doctor_visit_id'],
                ['patient_id' => $cbcData['patient_id']]
            );

            if ($result['success']) {
                Log::info('Zybio: CBC results saved successfully', [
                    'patient_id' => $cbcData['patient_id'],
                    'doctor_visit_id' => $cbcData['doctor_visit_id'],
                    'sysmex_id' => $result['sysmex_id'],
                    'results_count' => count($cbcResults)
                ]);
            } else {
                Log::error('Zybio: Failed to save CBC results', [
                    'error' => $result['message'],
                    'patient_id' => $cbcData['patient_id'],
                    'doctor_visit_id' => $cbcData['doctor_visit_id']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Zybio: Error saving CBC results: ' . $e->getMessage());
        }
    }

    /**
     * Format CBC results for SysmexCbcInserter
     */
    protected function formatCbcResultsForInserter(array $results): array
    {
        $formattedResults = [];
        
        foreach ($results as $result) {
            $testCode = $result['test_code'];
            if (is_array($testCode)) {
                $testName = $testCode[1] ?? $testCode[0] ?? 'Unknown';
            } else {
                $testName = $testCode;
            }
            
            // Skip non-clinical parameters
            if (in_array($testName, ['Take Mode', 'Blood Mode', 'Test Mode', 'Ref Group', 'Age', 'Leucopenia', 'Remark'])) {
                continue;
            }
            
            // Convert value to numeric if possible
            $value = $result['value'];
            if (is_numeric($value)) {
                $value = (float)$value;
            }
            
            $formattedResults[$testName] = [
                'value' => $value,
                'unit' => $result['unit'],
                'reference_range' => $result['reference_range']
            ];
        }
        
        return $formattedResults;
    }

    /**
     * Map Zybio CBC test codes to internal test identifiers
     */
    protected function mapZybioTestCodes(): array
    {
        return [
            'WBC' => 'white_blood_cells',
            'RBC' => 'red_blood_cells', 
            'HGB' => 'hemoglobin',
            'HCT' => 'hematocrit',
            'MCV' => 'mean_cell_volume',
            'MCH' => 'mean_cell_hemoglobin',
            'MCHC' => 'mean_cell_hemoglobin_concentration',
            'PLT' => 'platelets',
            'LYM#' => 'lymphocytes_absolute',
            'MID#' => 'mid_cells_absolute',
            'NEU#' => 'neutrophils_absolute',
            'LYM%' => 'lymphocytes_percent',
            'MID%' => 'mid_cells_percent',
            'NEU%' => 'neutrophils_percent',
            'MPV' => 'mean_platelet_volume',
            'PDW' => 'platelet_distribution_width',
            'PCT' => 'plateletcrit',
            'P-LCC' => 'platelet_large_cell_count',
            'P-LCR' => 'platelet_large_cell_ratio',
            'RDW-CV' => 'red_cell_distribution_width_cv',
            'RDW-SD' => 'red_cell_distribution_width_sd',
        ];
    }
}
