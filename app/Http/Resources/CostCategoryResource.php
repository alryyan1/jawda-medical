<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Check if the resource instance is null
        if (is_null($this->resource)) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'service_group_id' => $this->service_group_id,
            'service_group' => $this->whenLoaded('serviceGroup', fn () => $this->serviceGroup ? [
                'id' => $this->serviceGroup->id,
                'name' => $this->serviceGroup->name,
            ] : null),
        ];
    }
}
