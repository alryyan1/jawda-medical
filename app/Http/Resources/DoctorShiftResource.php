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
        $duration = null;
        if ($this->start_time && $this->end_time) {
            $duration = $this->start_time->diff($this->end_time)->format('%Hh %Im'); // Format as Hh Mm
        } elseif ($this->start_time && $this->status) { // If active and started
            $duration = $this->start_time->diff(now())->format('%Hh %Im') . ' (مستمرة)';
        }

        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->whenLoaded('doctor', optional($this->doctor)->name),
            'shift_id' => $this->shift_id,
            'general_shift_name' => $this->whenLoaded('generalShift', optional($this->generalShift)->name), // Assuming Shift model has 'name'
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', optional($this->user)->name),
            'status' => (bool) $this->status,
            'status_text' => $this->status ? 'مفتوحة' : 'مغلقة',
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'doctor_specialist_name' => $this->whenLoaded('doctor.specialist', optional($this->doctor->specialist)->name), // Get specialist name
            'formatted_start_time' => $this->start_time?->format('Y-m-d H:i A'),
            'formatted_end_time' => $this->end_time?->format('Y-m-d H:i A'),
            'duration' => $duration, // Calculated duration
            'is_cash_revenue_prooved' => (bool) $this->is_cash_revenue_prooved,
            'doctor_avatar_url' => $this->whenLoaded('doctor', optional($this->doctor)->image_url), // If doctor model has image_url accessor
            // ... other proof flags ...
            'created_at' => $this->created_at?->toIso8601String(),
            // New fields computed in controller
            'is_examining' => (bool) ($this->is_examining ?? false), // Default to false if not set
            'patients_count' => (int) ($this->patients_waiting_or_with_doctor_count ?? 0), // Default 
            // 'visits_count' => $this->whenCounted('doctorVisits'), // If you add withCount
        ];
    }
}
