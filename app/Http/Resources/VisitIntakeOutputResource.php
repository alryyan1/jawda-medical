<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitIntakeOutputResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_visit_id' => $this->doctor_visit_id,
            'patient_id' => $this->patient_id,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'feeding' => (float) $this->feeding,
            'iv_fluid' => (float) $this->iv_fluid,
            'maintenance' => (float) $this->maintenance,
            'urine_output' => (float) $this->urine_output,
            'insensible_loss' => (float) $this->insensible_loss,
            'total_intake' => $this->totalIntake(),
            'total_output' => $this->totalOutput(),
            'balance' => $this->balance(),
            'notes' => $this->notes,
            'recorded_by' => $this->whenLoaded('recordedByUser', fn () => $this->recordedByUser ? [
                'id' => $this->recordedByUser->id,
                'name' => $this->recordedByUser->name,
            ] : null),
        ];
    }
}
