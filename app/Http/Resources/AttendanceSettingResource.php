<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number_of_shifts_per_day' => $this->number_of_shifts_per_day,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}