<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
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
            'name' => $this->name ?? "Shift #".$this->id, // Use a 'name' field if you add one, or generate
            'total' => (float) $this->total,
            'bank' => (float) $this->bank,
            'expenses' => (float) $this->expenses,
            'net_cash' => $this->net_cash, // Accessor defined in model
            'touched' => (bool) $this->touched,
            'is_closed' => (bool) $this->is_closed,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'pharmacy_entry' => $this->whenNotNull($this->pharmacy_entry), // Only include if not null
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Optional: Counts or brief related data
            // 'patients_count' => $this->whenCounted('patients'),
            // 'doctor_shifts_count' => $this->whenCounted('doctorShifts'),
            // 'opened_by_user' => new UserStrippedResource($this->whenLoaded('openedBy')), // UserStrippedResource for minimal user data
            // 'closed_by_user' => new UserStrippedResource($this->whenLoaded('closedBy')),
        ];
    }
}