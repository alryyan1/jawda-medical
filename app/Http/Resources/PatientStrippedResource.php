<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientStrippedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Only return this resource if the model instance is actually loaded
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'gender' => $this->gender,
            // Optional: A computed age string for quick display
            // 'age_string' => $this->full_age, // Assuming you have the getFullAgeAttribute accessor
            // 'age_year' => $this->age_year,
            // 'avatar_url' => $this->avatar_url, // If patients have avatars
        ];
    }
}