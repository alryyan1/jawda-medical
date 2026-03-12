<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnedLabRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_request_id' => $this->lab_request_id,
            'amount' => (float) $this->amount,
            'returned_payment_method' => $this->returned_payment_method,
            'user_id' => $this->user_id,
            'user' => new UserStrippedResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
