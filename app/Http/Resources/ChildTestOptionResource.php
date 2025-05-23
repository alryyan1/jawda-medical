<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildTestOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Check if the resource instance is null (e.g., when 'whenLoaded' results in no data)
        if (is_null($this->resource)) {
            return [];
        }

        return [
            'id' => $this->id,
            'child_test_id' => $this->child_test_id,
            'name' => $this->name,
            // Include other fields from your ChildTestOption model if needed
            // 'value_code' => $this->value_code,
            // 'is_default' => (bool) $this->is_default,
            // 'order' => $this->order,
            // 'created_at' => $this->created_at?->toIso8601String(), // If you have timestamps
            // 'updated_at' => $this->updated_at?->toIso8601String(), // If you have timestamps
        ];
    }
}