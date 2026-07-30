<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientAppointmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_id' => $this->doctor_id,
            'doctor' => $this->whenLoaded('doctor', fn () => $this->doctor ? [
                'id' => $this->doctor->id,
                'name' => $this->doctor->name,
            ] : null),
            'created_by_user_id' => $this->created_by_user_id,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'notes' => $this->notes,
            'status' => $this->status,
            'whatsapp_sent_at' => $this->whatsapp_sent_at?->toIso8601String(),
            'whatsapp_send_error' => $this->whatsapp_send_error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
