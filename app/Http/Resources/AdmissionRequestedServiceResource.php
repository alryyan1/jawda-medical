<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionRequestedServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_id' => $this->admission_id,
            'admission' => new AdmissionResource($this->whenLoaded('admission')),
            'service_id' => $this->service_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'user_id' => $this->user_id,
            'requesting_user' => new UserStrippedResource($this->whenLoaded('requestingUser')),
            'user_deposited' => $this->user_deposited,
            'deposit_user' => new UserStrippedResource($this->whenLoaded('depositUser')),
            'doctor_id' => $this->doctor_id,
            'performing_doctor' => new DoctorStrippedResource($this->whenLoaded('performingDoctor')),
            'price' => (float) $this->price,
            'amount_paid' => (float) $this->amount_paid,
            'endurance' => (float) $this->endurance,
            'is_paid' => (bool) $this->is_paid,
            'discount' => (float) $this->discount,
            'discount_per' => (int) $this->discount_per,
            'bank' => (bool) $this->bank,
            'count' => (int) $this->count,
            'doctor_note' => $this->doctor_note,
            'nurse_note' => $this->nurse_note,
            'done' => (bool) $this->done,
            'approval' => (bool) $this->approval,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Calculated fields
            'total_price' => $this->total_price,
            'net_payable_by_patient' => $this->net_payable_by_patient,
            'balance' => $this->balance,
            'costs' => AdmissionRequestedServiceCostResource::collection($this->whenLoaded('requestedServiceCosts')),
            'deposits' => AdmissionRequestedServiceDepositResource::collection($this->whenLoaded('deposits')),
        ];
    }
}
