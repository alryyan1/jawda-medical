<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class RequestedServiceResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'doctorvisits_id' => $this->doctorvisits_id, // Or your FK name
            'service_id' => $this->service_id,
            'service' => new ServiceResource($this->whenLoaded('service')), // Eager load service.serviceGroup in controller
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
            'discount' => (float) $this->discount, // Fixed discount amount
            'discount_per' => (int) $this->discount_per, // Percentage discount
            'bank' => (bool) $this->bank,
            'count' => (int) $this->count,
            'doctor_note' => $this->doctor_note,
            'nurse_note' => $this->nurse_note,
            'done' => (bool) $this->done,
            'approval' => (bool) $this->approval,
            'created_at' => $this->created_at?->toIso8601String(),
            // Calculated fields for convenience (can also be done on frontend)
            'sub_total' => $this->total_price, // From accessor: price * count
            'net_payable' => $this->net_amount_due, // From accessor: sub_total - discount - endurance
            'balance_due' => $this->balance, // From accessor: net_payable - amount_paid
        ];
    }
}