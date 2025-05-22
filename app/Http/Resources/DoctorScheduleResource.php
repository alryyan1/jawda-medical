<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class DoctorScheduleResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->whenLoaded('doctor', optional($this->doctor)->name),
            'day_of_week' => (int) $this->day_of_week,
            'time_slot' => $this->time_slot,
            // 'start_time' => $this->start_time, // If using specific times
            // 'end_time' => $this->end_time,
        ];
    }
}