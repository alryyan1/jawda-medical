<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceGroupWithServicesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // 'services' will be loaded based on the controller logic
            'services' => ServiceResource::collection($this->whenLoaded('services')),
        ];
    }
}