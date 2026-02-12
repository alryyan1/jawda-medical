<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionSetting;
use Illuminate\Http\Request;
use App\Http\Resources\AdmissionSettingResource;

class AdmissionSettingController extends Controller
{
    public function show()
    {
        $settings = AdmissionSetting::current();
        return new AdmissionSettingResource($settings);
    }

    public function update(Request $request)
    {
        $settings = AdmissionSetting::current();

        $validated = $request->validate([
            'morning_start' => 'required|date_format:H:i',
            'morning_end' => 'required|date_format:H:i',
            'evening_start' => 'required|date_format:H:i',
            'evening_end' => 'required|date_format:H:i',
            'full_day_boundary' => 'required|date_format:H:i',
            'default_period_start' => 'required|date_format:H:i',
            'default_period_end' => 'required|date_format:H:i',
        ]);

        // Ensure times are stored with seconds for MySQL TIME columns
        foreach ($validated as $key => $value) {
            if (strlen($value) === 5) {
                $validated[$key] = $value . ':00';
            }
        }

        $settings->update($validated);
        return new AdmissionSettingResource($settings->fresh());
    }
}
