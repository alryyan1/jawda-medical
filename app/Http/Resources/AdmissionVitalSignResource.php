<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionVitalSignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_id' => $this->admission_id,
            'user_id' => $this->user_id,
            'user' => new UserStrippedResource($this->whenLoaded('user')),
            'reading_date' => $this->reading_date?->toDateString(),
            'reading_time' => $this->reading_time?->format('H:i:s'),
            'temperature' => $this->temperature ? (float) $this->temperature : null,
            'blood_pressure_systolic' => $this->blood_pressure_systolic,
            'blood_pressure_diastolic' => $this->blood_pressure_diastolic,
            'oxygen_saturation' => $this->oxygen_saturation ? (float) $this->oxygen_saturation : null,
            'oxygen_flow' => $this->oxygen_flow ? (float) $this->oxygen_flow : null,
            'pulse_rate' => $this->pulse_rate,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
