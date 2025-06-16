<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', $this->user?->name),
            'shift_definition_id' => $this->shift_definition_id,
            'shift_label' => $this->whenLoaded('shiftDefinition', $this->shiftDefinition?->shift_label),
            'shift_name' => $this->whenLoaded('shiftDefinition', $this->shiftDefinition?->name),
            'attendance_date' => $this->attendance_date->format('Y-m-d'),
            'status' => $this->status,
            'check_in_time' => $this->check_in_time?->toIso8601String(),
            'check_out_time' => $this->check_out_time?->toIso8601String(),
            'supervisor_id' => $this->supervisor_id,
            'supervisor_name' => $this->whenLoaded('supervisor', $this->supervisor?->name),
            'notes' => $this->notes,
            'recorded_by_user_id' => $this->recorded_by_user_id,
            'recorded_by_user_name' => $this->whenLoaded('recorder', $this->recorder?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}