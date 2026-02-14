<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
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
            'ward_id' => $this->ward_id,
            'ward' => new WardResource($this->whenLoaded('ward')),
            'room_number' => $this->room_number,
            'room_type' => $this->room_type,
            'capacity' => (int) $this->capacity,
            'status' => (bool) $this->status,
            'price_per_day' => $this->price_per_day ? (float) $this->price_per_day : 0.00,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'beds' => BedResource::collection($this->whenLoaded('beds')),
            'beds_count' => isset($this->beds_count) ? (int) $this->beds_count : ($this->relationLoaded('beds') ? $this->beds->count() : 0),
            'current_admissions_count' => isset($this->current_admissions_count) ? (int) $this->current_admissions_count : 0,
            'current_admission' => new AdmissionResource($this->whenLoaded('currentAdmission')),
            'is_fully_occupied' => $this->whenLoaded('currentAdmission', function() {
                return $this->currentAdmission !== null;
            }),
        ];
    }
}
