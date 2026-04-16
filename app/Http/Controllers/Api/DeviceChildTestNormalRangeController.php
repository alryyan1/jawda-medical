<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildTest;
use App\Models\Device;
use App\Models\DeviceChildTestNormalRange;
use Illuminate\Http\Request;
use App\Http\Resources\DeviceChildTestNormalRangeResource;

class DeviceChildTestNormalRangeController extends Controller
{
    public function listForChildTest(ChildTest $childTest)
    {
        $ranges = DeviceChildTestNormalRange::where('child_test_id', $childTest->id)
            ->with('device')
            ->get();
        return DeviceChildTestNormalRangeResource::collection($ranges);
    }

    public function getNormalRange(Request $request, ChildTest $childTest, Device $device)
    {
        $normalRangeRecord = DeviceChildTestNormalRange::firstOrCreate(
            ['child_test_id' => $childTest->id,
             'device_id' => $device->id],
            ['normal_range' => '',
             'user_id' => auth()->id()]
        );
        $normalRangeRecord->load('device');
        return new DeviceChildTestNormalRangeResource($normalRangeRecord);
    }

    public function storeOrUpdateNormalRange(Request $request, ChildTest $childTest, Device $device)
    {
        $validated = $request->validate([
            'normal_range' => 'required|string|max:255',
            'is_default'   => 'sometimes|boolean',
        ]);

        // If marking this device as default, clear the flag from all other devices for this child test
        if (!empty($validated['is_default'])) {
            DeviceChildTestNormalRange::where('child_test_id', $childTest->id)
                ->where('device_id', '!=', $device->id)
                ->update(['is_default' => false]);
        }

        $normalRangeRecord = DeviceChildTestNormalRange::updateOrCreate(
            ['child_test_id' => $childTest->id, 'device_id' => $device->id],
            [
                'normal_range' => $validated['normal_range'],
                'is_default'   => $validated['is_default'] ?? false,
                'user_id'      => auth()->id(),
            ]
        );
        $normalRangeRecord->load('device');
        return new DeviceChildTestNormalRangeResource($normalRangeRecord);
    }
}
