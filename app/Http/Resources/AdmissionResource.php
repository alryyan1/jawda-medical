<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionResource extends JsonResource
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
            'patient' => new PatientStrippedResource($this->whenLoaded('patient')),
            'ward_id' => $this->ward_id,
            'ward' => new WardResource($this->whenLoaded('ward')),
            'room_id' => $this->bed?->room_id,
            'room' => $this->when(
                $this->relationLoaded('bed') && $this->bed && $this->bed->relationLoaded('room'),
                fn () => new RoomResource($this->bed->room)
            ),
            'bed_id' => $this->bed_id,
            'bed' => new BedResource($this->whenLoaded('bed')),
            'admission_date' => $this->admission_date?->toIso8601String(),
            'admission_days' => $this->admission_days,
            'admission_purpose' => $this->admission_purpose,
            'discharge_date' => $this->discharge_date?->toIso8601String(),
            'admission_reason' => $this->admission_reason,
            'diagnosis' => $this->diagnosis,
            'provisional_diagnosis' => $this->provisional_diagnosis,
            'operations' => $this->operations,
            'status' => $this->status,
            'doctor_id' => $this->doctor_id,
            'doctor' => new DoctorStrippedResource($this->whenLoaded('doctor')),
            'specialist_doctor_id' => $this->specialist_doctor_id,
            'specialist_doctor' => new DoctorStrippedResource($this->whenLoaded('specialistDoctor')),
            'user_id' => $this->user_id,
            'user' => new UserStrippedResource($this->whenLoaded('user')),
            'notes' => $this->notes,
            'medical_history' => $this->medical_history,
            'current_medications' => $this->current_medications,
            'referral_source' => $this->referral_source,
            'expected_discharge_date' => $this->expected_discharge_date?->toDateString(),
            'next_of_kin_name' => $this->next_of_kin_name,
            'next_of_kin_relation' => $this->next_of_kin_relation,
            'next_of_kin_phone' => $this->next_of_kin_phone,
            'balance' => (float) $this->balance,
            'days_admitted' => $this->days_admitted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
