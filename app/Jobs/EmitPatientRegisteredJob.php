<?php

namespace App\Jobs;

use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmitPatientRegisteredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $patientId)
    {
    }

    public function handle(): void
    {
        Log::info('EmitPatientRegisteredJob: started', ['patient_id' => $this->patientId]);
        $patient = Patient::with(['company', 'primaryDoctor', 'doctorVisit.doctor', 'doctorVisit.file'])
            ->find($this->patientId);

        if (!$patient) {
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

        $response = Http::withHeaders([
            'x-internal-token' => (string) config('services.realtime.token'),
        ])->post($baseUrl . '/emit/patient-registered', $payload);

        Log::info('EmitPatientRegisteredJob: emit response', [
            'status' => $response->status(),
            'ok' => $response->successful(),
        ]);
    }
}


