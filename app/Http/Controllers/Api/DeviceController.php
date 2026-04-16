<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Http\Resources\DeviceResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use function React\Promise\all;

class DeviceController extends Controller
{
    public function indexList(Request $request)
    {
        return DeviceResource::collection(Device::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('devices', 'name')]
        ]);
        $device = Device::create($validated);
        return new DeviceResource($device);
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('devices', 'name')->ignore($device->id)]
        ]);
        $device->update($validated);
        return new DeviceResource($device);
    }

    public function destroy(Device $device)
    {
        $device->delete();
        return response()->noContent();
    }
}
