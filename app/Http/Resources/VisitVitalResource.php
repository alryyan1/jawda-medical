<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitVitalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_visit_id' => $this->doctor_visit_id,
            'patient_id' => $this->patient_id,
            'blood_pressure_systolic' => $this->blood_pressure_systolic,
            'blood_pressure_diastolic' => $this->blood_pressure_diastolic,
            'temperature' => $this->temperature,
            'heart_rate' => $this->heart_rate,
            'respiratory_rate' => $this->respiratory_rate,
            'pain_scale' => $this->pain_scale,
            'spo2' => $this->spo2,
            'weight' => $this->weight,
            'height' => $this->height,
            'rbs' => $this->rbs,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
        ];
    }
}
