<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'service_id' => $this->service_id,
            'percentage' => (float) $this->percentage,
            'fixed' => (float) $this->fixed,
            'cost_type' => $this->cost_type,
            'sub_service_cost_id' => $this->sub_service_cost_id,
            'sub_service_cost_name' => $this->whenLoaded('subServiceCost', $this->subServiceCost->name),
            'service_name' => $this->whenLoaded('service', $this->service->name), // If service is loaded
            // Include SubServiceCostResource if eager loaded
            'sub_service_cost' => new SubServiceCostResource($this->whenLoaded('subServiceCost')),
        ];
    }
}