<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class RequestedServiceResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id, // ID of the requested_services record
            'visit_id' => $this->doctorvisits_id, // Check FK name
            'service_id' => $this->service_id,
            'service' => new ServiceResource($this->whenLoaded('service')),
            'user_id' => $this->user_id, // User who requested
            'user_name' => $this->whenLoaded('user', optional($this->user)->name),
            'user_deposited_id' => $this->user_deposited,
            'user_deposited_name' => $this->whenLoaded('userDeposited', optional($this->userDeposited)->name),
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->whenLoaded('doctor', optional($this->doctor)->name),
            'price' => (float) $this->price,
            'amount_paid' => (float) $this->amount_paid,
            'endurance' => (float) $this->endurance,
            'is_paid' => (bool) $this->is_paid,
            'discount' => (int) $this->discount,
            'discount_per' => (int) $this->discount_per,
            'bank' => (bool) $this->bank,
            'count' => (int) $this->count,
            'doctor_note' => $this->doctor_note,
            'nurse_note' => $this->nurse_note,
            'done' => (bool) $this->done,
            'approval' => (bool) $this->approval,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}