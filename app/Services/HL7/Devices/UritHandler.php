<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Illuminate\Support\Facades\Log;

class UritHandler
{
    protected SysmexCbcInserter $sysmexInserter;

    public function __construct(SysmexCbcInserter $sysmexInserter)
    {
        $this->sysmexInserter = $sysmexInserter;
    }

    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            Log::info('URIT: Processing CBC message');

            $cbcData = $this->parseCbcMessage($msg, $msh);
            if (!$cbcData) {
                return;
            }

            if (!$cbcData['doctor_visit_id']) {
                Log::warning('URIT: No doctor visit ID found');
                return;
            }

            $cbcResults = $this->formatCbcResultsForInserter($cbcData['results']);

            // Insert into Sysmex table
            $result = $this->sysmexInserter->insertCbcData(
                $cbcResults,
                (int)$cbcData['doctor_visit_id'],
                ['patient_id' => $cbcData['patient_id']]
            );

            if ($result['success']) {
                Log::info('URIT: CBC results saved', [
                    'doctor_visit_id' => $cbcData['doctor_visit_id'],
                    'sysmex_id' => $result['sysmex_id'],
                ]);
            } else {
                Log::error('URIT: Failed to save CBC', ['error' => $result['message']]);
            }
        } catch (\Exception $e) {
            Log::error('URIT processing error: ' . $e->getMessage());
        }
    }

    protected function parseCbcMessage(Message $msg, MSH $msh = null): ?array
    {
        try {
            $data = [
                'patient_id' => null,
                'doctor_visit_id' => null,
                'results' => [],
            ];

            // Doctor visit id: prefer OBR-3 (matches user sample)
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'OBR') {
                    $data['doctor_visit_id'] = $segment->getField(3);
                    break;
                }
            }

            // Patient id from PID-3 if available
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() === 'PID') {
                    $data['patient_id'] = $segment->getField(3) ?: null;
                    break;
                }
            }

            // OBX collection
            foreach ($msg->getSegments() as $segment) {
                if ($segment->getName() !== 'OBX') {
                    continue;
                }
                $testCodeRaw = $segment->getField(3);
                $value = $segment->getField(5);
                $unit = $segment->getField(6);
                $ref = $segment->getField(7);

                // Observation identifier may be like "WBCHistogram^LeftLine"; take first token as code when composite
                if (is_array($testCodeRaw)) {
                    $testName = $testCodeRaw[0] ?? '';
                } else {
                    $parts = explode('^', (string)$testCodeRaw);
                    $testName = $parts[0] ?? '';
                }

                $data['results'][] = [
                    'name' => $testName,
                    'value' => $value,
                    'unit' => $unit,
                    'reference_range' => $ref,
                ];
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('URIT: Error parsing CBC: ' . $e->getMessage());
            return null;
        }
    }

    protected function formatCbcResultsForInserter(array $results): array
    {
        $formatted = [];

        foreach ($results as $r) {
            $name = (string)($r['name'] ?? '');

            // Skip histograms and non-numeric/graphical entries for Sysmex table
            if ($name === '' || stripos($name, 'Histogram') !== false) {
                continue;
            }

            // Normalize parameter names to match SysmexCbcInserter mapping
            $normalized = $this->normalizeParameterName($name);

            // Ensure numeric where possible
            $value = $r['value'];
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            if (is_numeric($value)) {
                $value = (float)$value;
            }

            $formatted[$normalized] = [
                'value' => $value,
                'unit' => is_array($r['unit']) ? ($r['unit'][0] ?? null) : $r['unit'],
                'reference_range' => is_array($r['reference_range']) ? ($r['reference_range'][0] ?? null) : $r['reference_range'],
            ];
        }

        return $formatted;
    }

    protected function normalizeParameterName(string $name): string
    {
        // Map URIT-specific aliases to Sysmex fields
        $map = [
            'RDW_CV' => 'RDW-CV',
            'RDW_SD' => 'RDW-SD',
            'P_LCR' => 'P-LCR',
            'P_LCC' => 'P-LCC',
            'GRAN%' => 'NEUT%',
            'GRAN#' => 'NEUT#',
            'NEU%' => 'NEUT%', // safety
            'NEU#' => 'NEUT#', // safety
        ];

        return $map[$name] ?? $name;
    }
}


