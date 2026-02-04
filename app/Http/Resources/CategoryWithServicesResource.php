<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryWithServicesResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name' => $service->name,
                        'price' => $service->price,
                        'service_group_id' => $service->service_group_id,
                        'service_group' => $service->relationLoaded('serviceGroup') && $service->serviceGroup ? [
                            'id' => $service->serviceGroup->id,
                            'name' => $service->serviceGroup->name,
                        ] : null,
                        'percentage' => $service->pivot ? ($service->pivot->percentage !== null ? (float)$service->pivot->percentage : null) : null,
                        'fixed' => $service->pivot ? ($service->pivot->fixed !== null ? (float)$service->pivot->fixed : null) : null,
                    ];
                });
            }),
            'doctors' => $this->whenLoaded('doctors', function () {
                return $this->doctors->map(function ($doctor) {
                    return [
                        'id' => $doctor->id,
                        'name' => $doctor->name,
                        'specialist' => $doctor->relationLoaded('specialist') && $doctor->specialist ? [
                            'id' => $doctor->specialist->id,
                            'name' => $doctor->specialist->name,
                        ] : null,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
