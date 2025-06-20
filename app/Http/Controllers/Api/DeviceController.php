<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Http\Resources\DeviceResource; // We'll create this
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function indexList(Request $request) // Changed from index to indexList for clarity
    {
        // Add permission: can('list devices')
        return DeviceResource::collection(Device::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        // Add permission: can('create devices')
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('devices', 'name')]
        ]);
        $device = Device::create($validated);
        return new DeviceResource($device);
    }
}