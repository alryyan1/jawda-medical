<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestedServiceCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requested_service_id' => $this->requested_service_id,
            'party_id' => $this->party_id,
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'user_id' => $this->user_id,
            'party' => new PartyResource($this->whenLoaded('party')),
            'user' => new UserStrippedResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
