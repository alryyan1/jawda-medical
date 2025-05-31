<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditedRequestedServiceResource extends JsonResource
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
            'audited_patient_record_id' => $this->audited_patient_record_id,
            'original_requested_service_id' => $this->original_requested_service_id,
            'service_id' => $this->service_id,
            
            'audited_price' => (float) $this->audited_price,
            'audited_count' => (int) $this->audited_count,
            'audited_discount_per' => $this->audited_discount_per !== null ? (float) $this->audited_discount_per : null,
            'audited_discount_fixed' => $this->audited_discount_fixed !== null ? (float) $this->audited_discount_fixed : null,
            'audited_endurance' => (float) $this->audited_endurance,
            'audited_status' => $this->audited_status,
            'auditor_notes_for_service' => $this->auditor_notes_for_service,
            
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'service' => new ServiceResource($this->whenLoaded('service')), // Or full ServiceResource
            // You might want to load the original requested service details for comparison
            'original_requested_service' => new RequestedServiceResource($this->whenLoaded('originalRequestedService')),
        ];
    }
}