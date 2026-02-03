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
            'room_id' => $this->room_id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'bed_id' => $this->bed_id,
            'bed' => new BedResource($this->whenLoaded('bed')),
            'booking_type' => $this->booking_type ?? 'bed',
            'admission_date' => $this->admission_date?->toDateString(),
            'admission_time' => $this->admission_time?->format('H:i:s'),
            'discharge_date' => $this->discharge_date?->toDateString(),
            'discharge_time' => $this->discharge_time?->format('H:i:s'),
            'admission_type' => $this->admission_type,
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
