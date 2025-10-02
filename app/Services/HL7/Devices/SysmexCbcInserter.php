<?php

namespace App\Services\HL7\Devices;

use App\Models\SysmexResult;
use App\Models\Doctorvisit;
use Illuminate\Support\Facades\DB;

class SysmexCbcInserter
{
    /**
     * Insert CBC data into Sysmex table
     */
    public function insertCbcData(array $cbcResults, int $doctorVisitId, array $patientInfo = []): array
    {
        try {
            // Validate doctor visit exists
            $doctorVisit = Doctorvisit::find($doctorVisitId);
            if (!$doctorVisit) {
                return [
                    'success' => false,
                    'message' => 'Doctor visit not found',
                    'data' => null
                ];
            }

            // Map ACON CBC parameters to Sysmex table fields
            $sysmexData = $this->mapCbcToSysmexFields($cbcResults, $doctorVisitId, $patientInfo);

            // Insert into Sysmex table
            $sysmexResult = SysmexResult::create($sysmexData);

            return [
                'success' => true,
                'message' => 'CBC data inserted successfully',
                'data' => $sysmexResult,
                'sysmex_id' => $sysmexResult->id
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error inserting CBC data: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Map CBC parameters to Sysmex table fields
     */
    public function mapCbcToSysmexFields(array $cbcResults, int $doctorVisitId, array $patientInfo): array
    {
        $sysmexData = [
            'doctorvisit_id' => $doctorVisitId,
        ];

        // Map CBC parameters to Sysmex fields
        $fieldMapping = $this->getCbcToSysmexFieldMapping();

        foreach ($cbcResults as $parameter => $result) {
            if (isset($fieldMapping[$parameter])) {
                $sysmexField = $fieldMapping[$parameter];
                $sysmexData[$sysmexField] = $result['value'];
            }
        }

        return $sysmexData;
    }

    /**
     * Get mapping between CBC parameters and Sysmex table fields
     */
    public function getCbcToSysmexFieldMapping(): array
    {
        return [
            // White Blood Cell Parameters
            'WBC' => 'wbc',
            'LYM#' => 'lym_c',      // lym_c (count)
            'MXD#' => 'mxd_c',      // mxd_c (count)
            'NEUT#' => 'neut_c',    // neut_c (count)
            'NEU#' => 'neut_c',     // neut_c (count) - Zybio uses NEU#
            'LYM%' => 'lym_p',      // lym_p (percentage)
            'MXD%' => 'mxd_p',      // mxd_p (percentage)
            'NEUT%' => 'neut_p',    // neut_p (percentage)
            'NEU%' => 'neut_p',     // neut_p (percentage) - Zybio uses NEU%

            // BC6800 specific WBC differential parameters
            'BAS#' => 'bas_c',      // basophils count
            'BAS%' => 'bas_p',      // basophils percentage
            'EOS#' => 'eos_c',      // eosinophils count
            'EOS%' => 'eos_p',      // eosinophils percentage
            'MON#' => 'mon_c',      // monocytes count
            'MON%' => 'mon_p',      // monocytes percentage

            // Zybio specific WBC differential parameters
            'MID#' => 'mxd_c',      // mid cells count (maps to mxd_c)
            'MID%' => 'mxd_p',      // mid cells percentage (maps to mxd_p)

            // Red Blood Cell Parameters
            'RBC' => 'rbc',
            'HGB' => 'hgb',
            'HCT' => 'hct',
            'MCV' => 'mcv',
            'MCH' => 'mch',
            'MCHC' => 'mchc',
            'RDW-CV' => 'rdw_cv',
            'RDW-SD' => 'rdw_sd',

            // Platelet Parameters
            'PLT' => 'plt',
            'MPV' => 'mpv',
            'PDW' => 'pdw',
            'PCT' => 'pct',
            'PLCC' => 'plcc',
            'PLCR' => 'plcr',
            
            // Zybio specific platelet parameters
            'P-LCC' => 'plcc',      // Platelet Large Cell Count
            'P-LCR' => 'plcr',      // Platelet Large Cell Ratio
            
            // Additional BC6800 specific parameters
            'HFC#' => 'hfc_c',      // High Fluorescence Cell Count
            'HFC%' => 'hfc_p',      // High Fluorescence Cell Percentage
            'PLT-I' => 'plt_i',     // Platelet Immature
            'WBC-D' => 'wbc_d',     // WBC Differential
            'WBC-B' => 'wbc_b',     // WBC Basophil
            'PDW-SD' => 'pdw_sd',   // Platelet Distribution Width SD
            'InR#' => 'inr_c',      // Immature Reticulocyte Count
            'InR%' => 'inr_p',      // Immature Reticulocyte Percentage
        ];
    }

    /**
     * Update existing Sysmex record with new CBC data
     */
    public function updateCbcData(int $sysmexId, array $cbcResults): array
    {
        try {
            $sysmexResult = SysmexResult::find($sysmexId);
            if (!$sysmexResult) {
                return [
                    'success' => false,
                    'message' => 'Sysmex record not found',
                    'data' => null
                ];
            }

            // Map CBC parameters to Sysmex fields
            $fieldMapping = $this->getCbcToSysmexFieldMapping();
            $updateData = [];

            foreach ($cbcResults as $parameter => $result) {
                if (isset($fieldMapping[$parameter])) {
                    $sysmexField = $fieldMapping[$parameter];
                    $updateData[$sysmexField] = $result['value'];
                }
            }

            $updateData['updated_at'] = now();

            // Update the record
            $sysmexResult->update($updateData);

            return [
                'success' => true,
                'message' => 'CBC data updated successfully',
                'data' => $sysmexResult
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error updating CBC data: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Get latest Sysmex result for a doctor visit
     */
    public function getLatestSysmexResult(int $doctorVisitId): ?SysmexResult
    {
        return SysmexResult::where('doctorvisit_id', $doctorVisitId)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Get all Sysmex results for a doctor visit
     */
    public function getSysmexResults(int $doctorVisitId): \Illuminate\Database\Eloquent\Collection
    {
        return SysmexResult::where('doctorvisit_id', $doctorVisitId)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Delete Sysmex result
     */
    public function deleteSysmexResult(int $sysmexId): array
    {
        try {
            $sysmexResult = SysmexResult::find($sysmexId);
            if (!$sysmexResult) {
                return [
                    'success' => false,
                    'message' => 'Sysmex record not found'
                ];
            }

            $sysmexResult->delete();

            return [
                'success' => true,
                'message' => 'Sysmex record deleted successfully'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting Sysmex record: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get CBC data from Sysmex result
     */
    public function getCbcDataFromSysmex(SysmexResult $sysmexResult): array
    {
        $fieldMapping = $this->getCbcToSysmexFieldMapping();
        $cbcData = [];

        // Reverse mapping: Sysmex field -> CBC parameter
        $reverseMapping = array_flip($fieldMapping);

        foreach ($reverseMapping as $sysmexField => $cbcParameter) {
            if (isset($sysmexResult->$sysmexField) && $sysmexResult->$sysmexField !== null) {
                $cbcData[$cbcParameter] = [
                    'value' => $sysmexResult->$sysmexField,
                    'source' => 'sysmex',
                    'sysmex_id' => $sysmexResult->id
                ];
            }
        }

        return $cbcData;
    }

    /**
     * Validate CBC data before insertion
     */
    public function validateCbcData(array $cbcResults): array
    {
        $errors = [];
        $requiredFields = ['WBC', 'RBC', 'HGB', 'HCT', 'PLT'];

        // Check required fields
        foreach ($requiredFields as $field) {
            if (!isset($cbcResults[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate numeric values
        foreach ($cbcResults as $parameter => $result) {
            if (!is_numeric($result['value'])) {
                $errors[] = "Invalid numeric value for {$parameter}: {$result['value']}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get Sysmex table field names
     */
    public function getSysmexFieldNames(): array
    {
        return array_values($this->getCbcToSysmexFieldMapping());
    }

    /**
     * Get CBC parameter names
     */
    public function getCbcParameterNames(): array
    {
        return array_keys($this->getCbcToSysmexFieldMapping());
    }
}
