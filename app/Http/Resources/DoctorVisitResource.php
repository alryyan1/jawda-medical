<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorVisitResource extends JsonResource
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
            'patient_id' => $this->patient_id,
            'patient' => new PatientStrippedResource($this->whenLoaded('patient')), // A minimal patient resource
            'doctor_id' => $this->doctor_id,
            'doctor' => new DoctorStrippedResource($this->whenLoaded('doctor')), // A minimal doctor resource
            'user_id' => $this->user_id,
            'created_by_user' => new UserStrippedResource($this->whenLoaded('createdByUser')),
            'shift_id' => $this->shift_id,
            'general_shift' => new ShiftResource($this->whenLoaded('generalShift')),
            'doctor_shift_id' => $this->doctor_shift_id,
            'doctor_shift_details' => new DoctorShiftResource($this->whenLoaded('doctorShift')),
            // 'appointment_id' => $this->appointment_id,
            'file_id' => $this->file_id,
            'visit_date' => $this->visit_date?->toDateString(), // Format as YYYY-MM-DD
            'visit_time' => $this->visit_time, // Or $this->visit_time?->format('H:i:s'),
            'status' => $this->status,
            'visit_type' => $this->visit_type,
            'queue_number' => $this->queue_number,
            'reason_for_visit' => $this->reason_for_visit,
            'visit_notes' => $this->visit_notes,
            'is_new' => (bool) $this->is_new,
            'number' => (int) $this->number,
            'only_lab' => (bool) $this->only_lab,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'requested_services' => RequestedServiceResource::collection($this->whenLoaded('requestedServices')),
        ];
    }
}