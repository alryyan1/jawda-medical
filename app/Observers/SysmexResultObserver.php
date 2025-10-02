<?php

namespace App\Observers;

use App\Models\SysmexResult;
use App\Models\Doctorvisit;
use App\Models\Patient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SysmexResultObserver
{
    /**
     * Handle the SysmexResult "created" event.
     */
    public function created(SysmexResult $sysmexResult): void
    {
        Log::info('SysmexResultObserver: New sysmex result created', [
            'sysmex_id' => $sysmexResult->id,
            'doctorvisit_id' => $sysmexResult->doctorvisit_id
        ]);

        try {
            // Get the doctor visit and patient data
            $doctorVisit = Doctorvisit::with(['patient', 'labRequests'])->find($sysmexResult->doctorvisit_id);
            
            if (!$doctorVisit) {
                Log::warning('SysmexResultObserver: Doctor visit not found', [
                    'sysmex_id' => $sysmexResult->id,
                    'doctorvisit_id' => $sysmexResult->doctorvisit_id
                ]);
                return;
            }

            $patient = $doctorVisit->patient;
            if (!$patient) {
                Log::warning('SysmexResultObserver: Patient not found for doctor visit', [
                    'sysmex_id' => $sysmexResult->id,
                    'doctorvisit_id' => $sysmexResult->doctorvisit_id
                ]);
                return;
            }

            // Prepare the payload for the realtime event
            $payload = [
                'sysmexResult' => [
                    'id' => $sysmexResult->id,
                    'doctorvisit_id' => $sysmexResult->doctorvisit_id,
                    'wbc' => $sysmexResult->wbc,
                    'rbc' => $sysmexResult->rbc,
                    'hgb' => $sysmexResult->hgb,
                    'hct' => $sysmexResult->hct,
                    'mcv' => $sysmexResult->mcv,
                    'mch' => $sysmexResult->mch,
                    'mchc' => $sysmexResult->mchc,
                    'plt' => $sysmexResult->plt,
                    'lym_p' => $sysmexResult->lym_p,
                    'mxd_p' => $sysmexResult->mxd_p,
                    'neut_p' => $sysmexResult->neut_p,
                    'lym_c' => $sysmexResult->lym_c,
                    'mxd_c' => $sysmexResult->mxd_c,
                    'neut_c' => $sysmexResult->neut_c,
                    'rdw_sd' => $sysmexResult->rdw_sd,
                    'rdw_cv' => $sysmexResult->rdw_cv,
                    'pdw' => $sysmexResult->pdw,
                    'mpv' => $sysmexResult->mpv,
                    'plcr' => $sysmexResult->plcr,
                ],
                'doctorVisit' => [
                    'id' => $doctorVisit->id,
                    'patient_id' => $doctorVisit->patient_id,
                    'shift_id' => $doctorVisit->shift_id,
                    'created_at' => $doctorVisit->created_at,
                ],
                'patient' => [
                    'id' => $patient->id,
                    'name' => $patient->name,
                    'phone' => $patient->phone,
                    'result_is_locked' => $patient->result_is_locked,
                ]
            ];

            // Send the realtime event
            $this->emitSysmexResultInserted($payload);

        } catch (\Exception $e) {
            Log::error('SysmexResultObserver: Error processing sysmex result created event', [
                'sysmex_id' => $sysmexResult->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Handle the SysmexResult "updated" event.
     */
    public function updated(SysmexResult $sysmexResult): void
    {
        // You can add logic here if you want to notify on updates as well
        Log::info('SysmexResultObserver: Sysmex result updated', [
            'sysmex_id' => $sysmexResult->id,
            'doctorvisit_id' => $sysmexResult->doctorvisit_id
        ]);
    }

    /**
     * Handle the SysmexResult "deleted" event.
     */
    public function deleted(SysmexResult $sysmexResult): void
    {
        Log::info('SysmexResultObserver: Sysmex result deleted', [
            'sysmex_id' => $sysmexResult->id,
            'doctorvisit_id' => $sysmexResult->doctorvisit_id
        ]);
    }

    /**
     * Emit sysmex result inserted event to realtime server
     */
    private function emitSysmexResultInserted(array $payload): void
    {
        $baseUrl = rtrim((string) config('services.realtime.url'), '/');
        if ($baseUrl === '') {
            Log::warning('SysmexResultObserver: Realtime server URL not configured');
            return;
        }

        try {
            $response = Http::withHeaders([
                'x-internal-token' => (string) config('services.realtime.token'),
            ])->timeout(5)->post($baseUrl . '/emit/sysmex-result-inserted', $payload);

            if ($response->successful()) {
                Log::info('SysmexResultObserver: Successfully emitted sysmex-result-inserted event', [
                    'sysmex_id' => $payload['sysmexResult']['id'],
                    'doctorvisit_id' => $payload['sysmexResult']['doctorvisit_id'],
                    'response_status' => $response->status()
                ]);
            } else {
                Log::error('SysmexResultObserver: Failed to emit sysmex-result-inserted event', [
                    'sysmex_id' => $payload['sysmexResult']['id'],
                    'response_status' => $response->status(),
                    'response_body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SysmexResultObserver: Exception while emitting sysmex-result-inserted event', [
                'sysmex_id' => $payload['sysmexResult']['id'],
                'error' => $e->getMessage()
            ]);
        }
    }
}
