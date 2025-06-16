<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildTest;
use App\Models\Device;
use App\Models\DeviceChildTestNormalRange;
use Illuminate\Http\Request;
use App\Http\Resources\DeviceChildTestNormalRangeResource; // Create this

class DeviceChildTestNormalRangeController extends Controller
{
    public function getNormalRange(Request $request, ChildTest $childTest, Device $device)
    {
        // Add permission check if needed, e.g., can('view device_normal_ranges')
        $normalRangeRecord = DeviceChildTestNormalRange::firstOrCreate(
            ['child_test_id' => $childTest->id, 'device_id' => $device->id],
            ['normal_range' => ''] // Default to empty string if creating new
        );
        return new DeviceChildTestNormalRangeResource($normalRangeRecord);
    }

    public function storeOrUpdateNormalRange(Request $request, ChildTest $childTest, Device $device)
    {
        // Add permission check, e.g., can('manage device_normal_ranges')
        $validated = $request->validate([
            'normal_range' => 'required|string|max:255', // Max length from your table
        ]);

        $normalRangeRecord = DeviceChildTestNormalRange::updateOrCreate(
            ['child_test_id' => $childTest->id, 'device_id' => $device->id],
            ['normal_range' => $validated['normal_range']]
        );
        return new DeviceChildTestNormalRangeResource($normalRangeRecord);
    }
}