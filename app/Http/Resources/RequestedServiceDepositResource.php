<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class RequestedServiceDepositResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'requested_service_id' => $this->requested_service_id,
            'amount' => (float) $this->amount,
            'is_bank' => (bool) $this->is_bank,
            'is_claimed' => (bool) $this->is_claimed,
            'shift_id' => $this->shift_id,
            'user_id' => $this->user_id,
            'user' => new UserStrippedResource($this->whenLoaded('user')),
            'requested_service' => new RequestedServiceResource($this->whenLoaded('requestedService')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}