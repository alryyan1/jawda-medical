<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'service_group_id' => $this->service_group_id,
            'service_group_name' => $this->whenLoaded('serviceGroup', $this->serviceGroup->name),
            'service_group' => new ServiceGroupResource($this->whenLoaded('serviceGroup')),
            'price' => (float) $this->price, // Ensure it's a number
            'activate' => (bool) $this->activate,
            'variable' => (bool) $this->variable,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}