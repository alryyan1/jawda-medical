<?php

namespace App\Jobs;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmitPatientRegisteredJob
{
    use Dispatchable;

    public function __construct(public int $patientId) {}

    public function handle(): void
    {
        Log::info('EmitPatientRegisteredJob: started', ['patient_id' => $this->patientId]);
        $patient = Patient::with(['company', 'primaryDoctor', 'doctorVisit.doctor', 'doctorVisit.file', 'sampleCollectedBy'])
            ->find($this->patientId);

        if (! $patient) {
            Log::warning('EmitPatientRegisteredJob: patient not found', ['patient_id' => $this->patientId]);

            return;
        }

        $payload = [
            'patient' => (new PatientResource($patient))->resolve(),
        ];

        $baseUrl = rtrim((string) config('services.realtime.url'), '/');
        if ($baseUrl === '') {
            return;
        }

        try {
            $response = Http::withHeaders([
                'x-internal-token' => (string) config('services.realtime.token'),
            ])->timeout(3)->connectTimeout(2)->post($baseUrl.'/emit/patient-registered', $payload);

            Log::info('EmitPatientRegisteredJob: emit response', [
                'status' => $response->status(),
                'ok' => $response->successful(),
            ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('EmitPatientRegisteredJob: realtime server unreachable', [
                'patient_id' => $this->patientId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
