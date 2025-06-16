<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSetting;
use Illuminate\Http\Request;
use App\Http\Resources\AttendanceSettingResource;
use Illuminate\Support\Facades\Auth;

class AttendanceSettingController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:view attendance_settings')->only('show');
        // $this->middleware('can:update attendance_settings')->only('update');
    }

    public function show()
    {
        // if (!Auth::user()->can('view attendance_settings')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        $settings = AttendanceSetting::current(); // Uses the static helper
        return new AttendanceSettingResource($settings);
    }

    public function update(Request $request)
    {
        // if (!Auth::user()->can('update attendance_settings')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        $settings = AttendanceSetting::current();

        $validated = $request->validate([
            'number_of_shifts_per_day' => 'required|integer|min:1|max:3',
            // Add validation for other settings if they exist
        ]);

        $settings->update($validated);
        return new AttendanceSettingResource($settings->fresh());
    }
}