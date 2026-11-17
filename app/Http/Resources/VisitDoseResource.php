<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitDoseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_visit_id' => $this->doctor_visit_id,
            'patient_id' => $this->patient_id,
            'medication_name' => $this->medication_name,
            'dosage' => $this->dosage,
            'status' => $this->status,
            'administered_at' => $this->administered_at?->toIso8601String(),
            'notes' => $this->notes,
            'administered_by' => $this->whenLoaded('administeredByUser', fn () => $this->administeredByUser ? [
                'id' => $this->administeredByUser->id,
                'name' => $this->administeredByUser->name,
            ] : null),
        ];
    }
}
