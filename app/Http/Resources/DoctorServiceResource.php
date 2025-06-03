<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // When this resource is used for a Service model fetched via doctor->specificServices()
        // $this->resource refers to the Service model instance.
        // The pivot data is accessible via $this->pivot.
        return [
            // Service Details
            'service_id' => $this->id,
            'service_name' => $this->name,
            'service_group_name' => $this->whenLoaded('serviceGroup', $this->serviceGroup?->name),
            'standard_price' => (float) $this->price, // Standard price of the service

            // DoctorService (Pivot) Details
            'doctor_service_id' => $this->pivot->id, // The ID of the doctor_services table row
            'doctor_id' => $this->pivot->doctor_id,
            'percentage' => $this->pivot->percentage !== null ? (float) $this->pivot->percentage : null,
            'fixed' => $this->pivot->fixed !== null ? (float) $this->pivot->fixed : null,
            'created_at' => $this->pivot->created_at?->toIso8601String(), // If pivot has timestamps
            'updated_at' => $this->pivot->updated_at?->toIso8601String(), // If pivot has timestamps
        ];
    }
}