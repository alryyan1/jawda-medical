<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WardResource extends JsonResource
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
            'status' => (bool) $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'rooms' => RoomResource::collection($this->whenLoaded('rooms')),
            'rooms_count' => isset($this->rooms_count)
                ? (int) $this->rooms_count
                : ($this->relationLoaded('rooms') ? $this->rooms->count() : 0),
            'beds_count' => isset($this->beds_count) ? (int) $this->beds_count : 0,
            'current_admissions_count' => isset($this->current_admissions_count)
                ? (int) $this->current_admissions_count
                : 0,
        ];
    }
}
