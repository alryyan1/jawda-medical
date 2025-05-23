<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (is_null($this->resource)) {
            return [];
        }

        return [
            'package_id' => $this->package_id,
            'package_name' => $this->package_name,
            'container' => $this->container, // Or 'container_name' from relation if it's a FK
            // 'container_details' => new ContainerResource($this->whenLoaded('sampleContainer')), // If relation exists
            'exp_time' => $this->exp_time,
            'main_tests_count' => $this->whenCounted('mainTests'),
            'main_tests' => MainTestStrippedResource::collection($this->whenLoaded('mainTests')), // Use a stripped resource for tests
            // Add timestamps if your model has them
            // 'created_at' => $this->created_at?->toIso8601String(),
            // 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}