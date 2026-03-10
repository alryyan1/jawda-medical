<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight admission resource for patient list items (e.g. clinic-active-patients).
 * Includes ward, bed, room and requested surgeries summary.
 */
class AdmissionForPatientListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bed_id' => $this->bed_id,
            'ward' => new WardResource($this->whenLoaded('ward')),
            'room' => $this->when(
                $this->relationLoaded('bed') && $this->bed?->relationLoaded('room'),
                fn () => new RoomResource($this->bed->room)
            ),
            'bed' => new BedResource($this->whenLoaded('bed')),
            'requested_surgeries_summary' => $this->when(
                $this->getAttribute('requested_surgeries_summary') !== null,
                fn () => $this->getAttribute('requested_surgeries_summary')
            ),
        ];
    }
}
