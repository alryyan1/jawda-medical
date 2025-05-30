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
            'sub_service_cost_id' => $this->sub_service_cost_id,
            'service_cost_id' => $this->service_cost_id,
            'amount' => (float) $this->amount,
            'created_at' => $this->created_at?->toIso8601String(),
            'sub_service_cost_name' => $this->whenLoaded('subServiceCost', $this->subServiceCost->name),
            'service_cost_definition_name' => $this->whenLoaded('serviceCostDefinition', $this->serviceCostDefinition->name),
            // Include full resources if needed
            'sub_service_cost' => new SubServiceCostResource($this->whenLoaded('subServiceCost')),
            'service_cost_definition' => new ServiceCostResource($this->whenLoaded('serviceCostDefinition')),
        ];
    }
}