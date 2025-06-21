<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ServiceGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'services_count' => $this->whenCounted('services'), // Include if loaded with withCount
            // 'created_at' => $this->created_at?->toIso8601String(), // if model has timestamps
            // 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}