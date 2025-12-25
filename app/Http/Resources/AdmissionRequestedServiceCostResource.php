<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionRequestedServiceCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_requested_service_id' => $this->admission_requested_service_id,
            'service_cost_id' => $this->service_cost_id,
            'service_cost' => new ServiceCostResource($this->whenLoaded('serviceCost')),
            'sub_service_cost_id' => $this->sub_service_cost_id,
            'sub_service_cost' => new SubServiceCostResource($this->whenLoaded('subServiceCost')),
            'amount' => (float) $this->amount,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
