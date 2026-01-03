<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionRequestedLabTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_id' => $this->admission_id,
            'admission' => new AdmissionResource($this->whenLoaded('admission')),
            'main_test_id' => $this->main_test_id,
            'main_test' => $this->when($this->relationLoaded('mainTest'), function () {
                return [
                    'id' => $this->mainTest->id,
                    'main_test_name' => $this->mainTest->main_test_name,
                    'price' => (float) $this->mainTest->price,
                ];
            }),
            'user_id' => $this->user_id,
            'requesting_user' => new UserStrippedResource($this->whenLoaded('requestingUser')),
            'doctor_id' => $this->doctor_id,
            'performing_doctor' => new DoctorStrippedResource($this->whenLoaded('performingDoctor')),
            'price' => (float) $this->price,
            'discount' => (float) $this->discount,
            'discount_per' => (int) $this->discount_per,
            'done' => (bool) $this->done,
            'approval' => (bool) $this->approval,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Calculated fields
            'net_payable_by_patient' => $this->net_payable_by_patient,
            'balance' => $this->balance,
        ];
    }
}

