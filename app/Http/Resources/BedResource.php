<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BedResource extends JsonResource
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
            'room_id' => $this->room_id,
            'room' => new RoomResource($this->whenLoaded('room')),
            'bed_number' => $this->bed_number,
            'status' => $this->status,
            'is_available' => $this->isAvailable(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'current_admission' => new AdmissionResource($this->whenLoaded('currentAdmission')),
        ];
    }
}
