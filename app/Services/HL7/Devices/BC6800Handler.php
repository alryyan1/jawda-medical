<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Illuminate\Support\Facades\Log;

class BC6800Handler
{
    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            Log::info('BC6800: Processing CBC message');
            
            // Process CBC results from BC-6800
            $this->processCbcResults($msg);
            
            Log::info('BC6800: CBC results processed successfully');

        } catch (\Exception $e) {
            Log::error('BC6800 processing error: ' . $e->getMessage(), [
                'exception' => $e,
                'message' => $msg->toString()
            ]);
        }
    }

    protected function processCbcResults(Message $msg): void
    {
        try {
            // Parse CBC segments and extract results
            $cbcData = $this->parseCbcMessage($msg);
            
            if ($cbcData) {
                $this->saveCbcResults($cbcData);
            }

        } catch (\Exception $e) {
            Log::error('BC6800: Error processing CBC results: ' . $e->getMessage());
        }
    }

    protected function parseCbcMessage(Message $msg): ?array
    {
        try {
            // Parse OBR and OBX segments to extract CBC parameters
            $cbcData = [
                'patient_id' => null,
                'results' => []
            ];

            // Extract patient ID from PID segment
            $pidSegment = $msg->getFirstSegmentInstance('PID');
            if ($pidSegment) {
                $cbcData['patient_id'] = $pidSegment->getField(3); // Patient ID
            }

            // Extract results from OBX segments
            $obxSegments = $msg->getSegmentsByName('OBX');
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
            Log::error('BC6800: Error parsing CBC message: ' . $e->getMessage());
            return null;
        }
    }

    protected function saveCbcResults(array $cbcData): void
    {
        try {
            if (!$cbcData['patient_id']) {
                Log::warning('BC6800: No patient ID found in CBC data');
                return;
            }

            // Save CBC results to database
            // This would typically involve:
            // 1. Finding the patient's lab request
            // 2. Updating the CBC test results
            // 3. Marking tests as completed

            Log::info('BC6800: Saving CBC results for patient ' . $cbcData['patient_id'], [
                'results_count' => count($cbcData['results'])
            ]);

            // Placeholder for actual database save logic
            // You would implement the specific logic to save CBC results
            // to your database tables based on your schema

        } catch (\Exception $e) {
            Log::error('BC6800: Error saving CBC results: ' . $e->getMessage());
        }
    }

    /**
     * Map CBC test codes to internal test identifiers
     */
    protected function mapCbcTestCodes(): array
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
            'LYM%' => 'lymphocytes_percent',
            'MON%' => 'monocytes_percent',
            'GRA%' => 'granulocytes_percent',
            'LYM#' => 'lymphocytes_absolute',
            'MON#' => 'monocytes_absolute',
            'GRA#' => 'granulocytes_absolute',
        ];
    }
}
