<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdmissionSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $formatTime = static function ($value): ?string {
            if ($value === null) {
                return null;
            }

            // Expecting values like "07:00:00" or "07:00"
            $string = (string) $value;
            // Always return HH:MM
            return substr($string, 0, 5);
        };

        return [
            'id' => $this->id,
            'morning_start' => $formatTime($this->morning_start),
            'morning_end' => $formatTime($this->morning_end),
            'evening_start' => $formatTime($this->evening_start),
            'evening_end' => $formatTime($this->evening_end),
            'full_day_boundary' => $formatTime($this->full_day_boundary),
            'default_period_start' => $formatTime($this->default_period_start),
            'default_period_end' => $formatTime($this->default_period_end),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

