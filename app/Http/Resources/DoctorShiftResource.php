<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, // The DoctorShift record ID
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->whenLoaded('doctor', optional($this->doctor)->name),
            'doctor' => new DoctorResource($this->whenLoaded('doctor')), // Full doctor object if needed
            
            'shift_id' => $this->shift_id, // ID of the general clinic Shift model
            'general_shift_details' => new ShiftResource($this->whenLoaded('generalShift')), // If you have a ShiftResource

            'user_id' => $this->user_id, // User who created/managed this entry
            'user_name' => $this->whenLoaded('user', optional($this->user)->name),

            'status' => (bool) $this->status,
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            
            'is_cash_revenue_prooved' => (bool) $this->is_cash_revenue_prooved,
            'is_cash_reclaim_prooved' => (bool) $this->is_cash_reclaim_prooved,
            'is_company_revenue_prooved' => (bool) $this->is_company_revenue_prooved,
            'is_company_reclaim_prooved' => (bool) $this->is_company_reclaim_prooved,
            
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}