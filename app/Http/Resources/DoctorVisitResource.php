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
  // app/Http/Resources/DoctorVisitResource.php
// ...
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'visit_date' => $this->visit_date?->toDateString(),
        'visit_time' => $this->visit_time, // Or ->format('H:i')
        'status' => $this->status,
        'visit_type' => $this->visit_type,
        'queue_number' => $this->queue_number,
        'reason_for_visit' => $this->reason_for_visit,
        'visit_notes' => $this->visit_notes,
        'is_new' => (bool) $this->is_new,
        'number' => (int) $this->number, // Original 'number' column
        'only_lab' => (bool) $this->only_lab,
        
        'patient_id' => $this->patient_id,
        'patient' => new PatientResource($this->whenLoaded('patient')), // Use full PatientResource here

        'doctor_id' => $this->doctor_id,
        'doctor' => new DoctorStrippedResource($this->whenLoaded('doctor')),

        'user_id' => $this->user_id, // User who created the visit
        'created_by_user' => new UserStrippedResource($this->whenLoaded('createdByUser')),
        
        'shift_id' => $this->shift_id,
        'general_shift_details' => new ShiftResource($this->whenLoaded('generalShift')),

        'doctor_shift_id' => $this->doctor_shift_id,
        'doctor_shift_details' => new DoctorShiftResource($this->whenLoaded('doctorShift')),

        'requested_services' => RequestedServiceResource::collection($this->whenLoaded('requestedServices')),
        // Add other loaded relationships here:
        // 'vitals' => VitalResource::collection($this->whenLoaded('vitals')),
        // 'clinical_notes' => ClinicalNoteResource::collection($this->whenLoaded('clinicalNotes')),
        
        'created_at' => $this->created_at?->toIso8601String(),
        'updated_at' => $this->updated_at?->toIso8601String(),
    ];
}
}