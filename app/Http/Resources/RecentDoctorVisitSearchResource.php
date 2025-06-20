<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class RecentDoctorVisitSearchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'visit_id' => $this->id,
            'patient_id' => $this->patient_id,
            'patient_name' => $this->whenLoaded('patient', $this->patient?->name),
            'patient_phone' => $this->whenLoaded('patient', $this->patient?->phone),
            'doctor_name' => $this->whenLoaded('doctor', $this->doctor?->name ?? 'N/A'),
            'visit_date' => $this->visit_date ? Carbon::parse($this->visit_date)->format('Y-m-d') : null,
            'visit_time' => $this->visit_time, // Already formatted as H:i:s string potentially
            // You can add a label for the autocomplete here if needed
            'autocomplete_label' => ($this->whenLoaded('patient', $this->patient?->name) ?? 'Unknown Patient') . 
                                    ' (Visit #' . $this->id . 
                                    ($this->visit_date ? ' - ' . Carbon::parse($this->visit_date)->format('d M Y') : '') .
                                    ($this->whenLoaded('doctor', $this->doctor?->name) ? ' / Dr. ' . $this->doctor->name : '') .
                                    ')',
        ];
    }
}