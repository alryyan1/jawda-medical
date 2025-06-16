<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftDefinitionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'shift_label' => $this->shift_label,
            'start_time' => $this->start_time, // Consider formatting if needed (e.g., H:i)
            'end_time' => $this->end_time,   // Or Carbon::parse($this->start_time)->format('H:i')
            'duration_hours' => $this->duration_hours, // Accessor output
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}