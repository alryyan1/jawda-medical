<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorStrippedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            // Optional: Include specialist name if commonly needed with stripped doctor info
            'specialist_name' => $this->whenLoaded('specialist', optional($this->specialist)->name),
            // 'image_thumbnail_url' => $this->thumbnail_url, // If doctors have images
        ];
    }
}